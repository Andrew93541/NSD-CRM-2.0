<?php

namespace Tests\Feature;

use App\Models\AttendanceCorrection;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\GoodsReceipt;
use App\Models\InventoryStock;
use App\Models\Material;
use App\Models\MeasurementEntry;
use App\Models\PayrollCycle;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\RaBill;
use App\Models\Role;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\Task;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderItem;
use App\Services\Operations\RaBillingService;
use App\Services\Procurement\InventoryService;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase5RealWorldAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private array $roles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Infrastructure Acceptance Ltd',
            'code' => 'NSD',
            'is_active' => true,
        ]);

        TenantContext::setCompanyId($this->company->id);

        foreach (['company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff'] as $slug) {
            $this->roles[$slug] = Role::create([
                'company_id' => $this->company->id,
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'slug' => $slug,
                'is_system' => true,
            ]);
        }
    }

    private function createUserWithRole(string $roleSlug, array $siteIds = [], ?bool $restrictSites = null): User
    {
        $restricted = $restrictSites ?? in_array($roleSlug, ['site_manager', 'site_supervisor'], true);

        $user = User::create([
            'name' => ucwords(str_replace('_', ' ', $roleSlug)) . ' User',
            'email' => $roleSlug . '.' . uniqid() . '@nsd.local',
            'password' => bcrypt('password123'),
            'company_id' => $this->company->id,
            'is_active' => true,
            'restrict_to_assigned_sites' => $restricted,
        ]);

        $user->companies()->attach($this->company->id, [
            'role_id' => $this->roles[$roleSlug]->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        if (!empty($siteIds)) {
            $user->sites()->sync($siteIds);
        }

        return $user;
    }

    public function test_concurrent_operations_and_true_parallel_child_processes_prevent_collisions(): void
    {
        $admin = $this->createUserWithRole('company_admin');

        // 1. Two simultaneous Project creations submitting the same pre-filled suggested code (PRJ-001)
        $res1 = $this->actingAs($admin)->post(route('projects.store'), [
            'code' => 'PRJ-001',
            'name' => 'Metro Line 4 Package A',
            'status' => 'active',
        ]);
        $res1->assertRedirect();

        $res2 = $this->actingAs($admin)->post(route('projects.store'), [
            'code' => 'PRJ-001',
            'name' => 'Metro Line 4 Package B',
            'status' => 'active',
        ]);
        $res2->assertRedirect();

        $projects = Project::where('company_id', $this->company->id)->orderBy('code')->get();
        $this->assertCount(2, $projects);
        $this->assertSame(['PRJ-001', 'PRJ-002'], $projects->pluck('code')->all());

        // Custom non-auto project code collision is still rejected by validation
        $dupCustomProject = $this->actingAs($admin)->post(route('projects.store'), [
            'code' => 'PRJ-CUSTOM-99',
            'name' => 'Custom Project 1',
            'status' => 'active',
        ]);
        $dupCustomProject->assertRedirect();

        $dupCustomProject2 = $this->actingAs($admin)->from(route('projects.create'))->post(route('projects.store'), [
            'code' => 'PRJ-CUSTOM-99',
            'name' => 'Custom Project 2',
            'status' => 'active',
        ]);
        $dupCustomProject2->assertSessionHasErrors('code');

        // 2. Two simultaneous Site creations submitting the same pre-filled suggested code (SITE-001)
        $project = $projects->first();
        $siteRes1 = $this->actingAs($admin)->post(route('sites.store'), [
            'project_id' => $project->id,
            'code' => 'SITE-001',
            'name' => 'Pier 12 North Yard',
            'status' => 'active',
        ]);
        $siteRes1->assertRedirect();

        $siteRes2 = $this->actingAs($admin)->post(route('sites.store'), [
            'project_id' => $project->id,
            'code' => 'SITE-001',
            'name' => 'Pier 14 South Yard',
            'status' => 'active',
        ]);
        $siteRes2->assertRedirect();

        $sites = Site::where('company_id', $this->company->id)->orderBy('code')->get();
        $this->assertCount(2, $sites);
        $this->assertSame(['SITE-001', 'SITE-002'], $sites->pluck('code')->all());

        // 3. True Multi-Process Parallel Concurrency Test using proc_open on an isolated SQLite WAL database
        $tmpDb = sys_get_temp_dir() . '/nsd_phase5_concurrency_' . uniqid() . '.sqlite';
        $workerScript = sys_get_temp_dir() . '/nsd_phase5_worker_' . uniqid() . '.php';
        touch($tmpDb);

        try {
            $pdo = new \PDO('sqlite:' . $tmpDb);
            $pdo->exec('PRAGMA journal_mode = WAL;');
            $pdo->exec('PRAGMA busy_timeout = 5000;');
            $pdo->exec('CREATE TABLE worker_sequences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_id INTEGER NOT NULL,
                prefix VARCHAR(30) NOT NULL,
                year INTEGER NOT NULL DEFAULT 0,
                last_number INTEGER NOT NULL DEFAULT 0,
                UNIQUE(company_id, prefix, year)
            );');
            $pdo->exec('CREATE TABLE generated_docs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                doc_type VARCHAR(20) NOT NULL,
                code VARCHAR(50) NOT NULL UNIQUE
            );');
            $pdo->exec('CREATE TABLE stock_counters (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                material_id INTEGER NOT NULL UNIQUE,
                quantity NUMERIC NOT NULL DEFAULT 0
            );');
            $pdo->exec('INSERT INTO stock_counters (material_id, quantity) VALUES (1, 0);');
            unset($pdo);

            $scriptCode = <<<'PHP'
<?php
$dbFile = $argv[1];
$opType = $argv[2];
$pdo = new PDO('sqlite:' . $dbFile, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_TIMEOUT => 5,
]);
$pdo->exec('PRAGMA journal_mode = WAL;');
$pdo->exec('PRAGMA busy_timeout = 5000;');

for ($attempt = 0; $attempt < 20; $attempt++) {
    try {
        $pdo->exec('BEGIN IMMEDIATE TRANSACTION');
        $stmt = $pdo->prepare('SELECT id, last_number FROM worker_sequences WHERE company_id = 1 AND prefix = :p AND year = 2026');
        $stmt->execute([':p' => $opType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $pdo->prepare('INSERT INTO worker_sequences (company_id, prefix, year, last_number) VALUES (1, :p, 2026, 1)')->execute([':p' => $opType]);
            $next = 1;
        } else {
            $next = ((int) $row['last_number']) + 1;
            $pdo->prepare('UPDATE worker_sequences SET last_number = :n WHERE id = :id')->execute([':n' => $next, ':id' => $row['id']]);
        }

        $code = sprintf('%s-NSD-2026-%03d', $opType, $next);
        $pdo->prepare('INSERT INTO generated_docs (doc_type, code) VALUES (:t, :c)')->execute([':t' => $opType, ':c' => $code]);

        if ($opType === 'GRN') {
            $pdo->exec('UPDATE stock_counters SET quantity = quantity + 50 WHERE material_id = 1');
        }

        $pdo->exec('COMMIT');
        echo $code;
        exit(0);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK');
        }
        usleep(20000);
    }
}
fwrite(STDERR, "Failed after retries\n");
exit(1);
PHP;
            file_put_contents($workerScript, $scriptCode);

            // Launch 4 parallel processes (2 PR + 2 GRN) simultaneously
            $processes = [];
            $pipesList = [];
            foreach (['PR', 'PR', 'GRN', 'GRN'] as $idx => $opType) {
                $cmd = [PHP_BINARY, $workerScript, $tmpDb, $opType];
                $processes[$idx] = proc_open($cmd, [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w'],
                ], $pipesList[$idx]);
            }

            $outputs = [];
            foreach ($processes as $idx => $proc) {
                fclose($pipesList[$idx][0]);
                $stdout = stream_get_contents($pipesList[$idx][1]);
                $stderr = stream_get_contents($pipesList[$idx][2]);
                fclose($pipesList[$idx][1]);
                fclose($pipesList[$idx][2]);
                $exitCode = proc_close($proc);
                $this->assertSame(0, $exitCode, "Parallel worker {$idx} failed: {$stderr}");
                $outputs[] = trim($stdout);
            }

            $this->assertContains('PR-NSD-2026-001', $outputs);
            $this->assertContains('PR-NSD-2026-002', $outputs);
            $this->assertContains('GRN-NSD-2026-001', $outputs);
            $this->assertContains('GRN-NSD-2026-002', $outputs);

            $verifyPdo = new \PDO('sqlite:' . $tmpDb);
            $finalStock = (float) $verifyPdo->query('SELECT quantity FROM stock_counters WHERE material_id = 1')->fetchColumn();
            $this->assertSame(100.0, $finalStock);
            unset($verifyPdo);
        } finally {
            @unlink($workerScript);
            @unlink($tmpDb);
            @unlink($tmpDb . '-wal');
            @unlink($tmpDb . '-shm');
        }
    }

    public function test_worker_registration_handles_similar_names_shared_aadhaar_digits_and_duplicate_submissions(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-001',
            'name' => 'Bridge Construction Project',
            'status' => 'active',
        ]);

        $site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-001',
            'name' => 'Main Bridge Site',
            'status' => 'active',
        ]);

        $siteManager = $this->createUserWithRole('site_manager', [$site->id]);

        // Case 1: Register first worker (Ramesh Pawar, Aadhaar 4821)
        $res1 = $this->actingAs($siteManager)->post(route('workforce.store'), [
            'worker_type' => 'direct_labour',
            'first_name' => 'Ramesh',
            'last_name' => 'Pawar',
            'phone' => '9820011101',
            'aadhaar_last_four' => '4821',
            'trade_designation' => 'Mason',
            'assigned_site_id' => $site->id,
            'pay_type' => 'daily',
            'daily_rate' => 750,
        ]);
        $res1->assertRedirect(route('workforce.index'));

        // Case 2: Similar name (Rameshwar Pawar) with same Aadhaar last 4 digits (4821) -> Must Succeed
        $res2 = $this->actingAs($siteManager)->post(route('workforce.store'), [
            'worker_type' => 'direct_labour',
            'first_name' => 'Rameshwar',
            'last_name' => 'Pawar',
            'phone' => '9820011102',
            'aadhaar_last_four' => '4821',
            'trade_designation' => 'Mason',
            'assigned_site_id' => $site->id,
            'pay_type' => 'daily',
            'daily_rate' => 750,
        ]);
        $res2->assertRedirect(route('workforce.index'));

        // Case 3: Legitimate worker sharing the exact same name AND same last 4 Aadhaar digits (4821),
        // but with a distinct phone number -> Must Succeed (not falsely blocked!)
        $res3 = $this->actingAs($siteManager)->post(route('workforce.store'), [
            'worker_type' => 'direct_labour',
            'first_name' => 'Ramesh',
            'last_name' => 'Pawar',
            'phone' => '9820099999',
            'aadhaar_last_four' => '4821',
            'trade_designation' => 'Helper',
            'assigned_site_id' => $site->id,
            'pay_type' => 'daily',
            'daily_rate' => 600,
        ]);
        $res3->assertRedirect(route('workforce.index'));

        $this->assertSame(3, Worker::where('company_id', $this->company->id)->count());

        // Case 4: Accidental duplicate submission (same name, same Aadhaar 4821, same phone) -> Must Block
        $resDupAadhaar = $this->actingAs($siteManager)
            ->from(route('workforce.create'))
            ->post(route('workforce.store'), [
                'worker_type' => 'direct_labour',
                'first_name' => ' ramesh ',
                'last_name' => ' PAWAR ',
                'phone' => '9820011101',
                'aadhaar_last_four' => '4821',
                'trade_designation' => 'Mason',
                'assigned_site_id' => $site->id,
                'pay_type' => 'daily',
                'daily_rate' => 750,
            ]);
        $resDupAadhaar->assertRedirect(route('workforce.create'));
        $resDupAadhaar->assertSessionHasErrors('aadhaar_last_four');

        // Case 5: Accidental duplicate submission without Aadhaar (same name + same phone) -> Must Block
        $resNoAadhaar1 = $this->actingAs($siteManager)->post(route('workforce.store'), [
            'worker_type' => 'direct_labour',
            'first_name' => 'Santosh',
            'last_name' => 'Kale',
            'phone' => '9765432109',
            'trade_designation' => 'Carpenter',
            'assigned_site_id' => $site->id,
            'pay_type' => 'daily',
            'daily_rate' => 800,
        ]);
        $resNoAadhaar1->assertRedirect(route('workforce.index'));

        $resNoAadhaarDup = $this->actingAs($siteManager)
            ->from(route('workforce.create'))
            ->post(route('workforce.store'), [
                'worker_type' => 'direct_labour',
                'first_name' => 'Santosh',
                'last_name' => 'Kale',
                'phone' => '9765432109',
                'trade_designation' => 'Carpenter',
                'assigned_site_id' => $site->id,
                'pay_type' => 'daily',
                'daily_rate' => 800,
            ]);
        $resNoAadhaarDup->assertRedirect(route('workforce.create'));
        $resNoAadhaarDup->assertSessionHasErrors('phone');

        $this->assertSame(4, Worker::where('company_id', $this->company->id)->count());
    }

    public function test_transaction_and_file_recovery_cleans_up_orphaned_files_on_failure(): void
    {
        Storage::fake('local');

        $admin = $this->createUserWithRole('company_admin');
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-001',
            'name' => 'Recovery Test Project',
            'status' => 'active',
        ]);
        $site = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-001',
            'name' => 'Recovery Test Site',
            'status' => 'active',
        ]);

        // 1. Simulate failure during Work Order attachment persistence
        $woCountBefore = WorkOrder::count();
        WorkOrderAttachment::creating(function () {
            throw new \RuntimeException('Simulated database failure during work order attachment save');
        });

        try {
            $this->actingAs($admin)->post(route('work_orders.store'), [
                'project_id' => $project->id,
                'site_id' => $site->id,
                'order_type' => 'internal',
                'title' => 'Failing Work Order With Attachment',
                'estimated_amount' => 50000,
                'attachment' => UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf'),
            ]);
        } catch (\Throwable $ignored) {
        }

        WorkOrderAttachment::flushEventListeners();
        $this->assertSame($woCountBefore, WorkOrder::count(), 'WorkOrder creation must roll back when attachment DB save fails.');
        $this->assertEmpty(Storage::disk('local')->allFiles(), 'Orphaned work order attachment file must be deleted on transaction failure.');

        // 2. Simulate failure during Measurement Photo upload in RaBillingService
        $wo = WorkOrder::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'site_id' => $site->id,
            'order_number' => 'WO-NSD-2026-001',
            'order_type' => 'client_issued',
            'title' => 'Valid Work Order for Measurement',
            'estimated_amount' => 100000,
            'status' => 'senior_approved',
            'lifecycle_status' => WorkOrder::LIFECYCLE_CONFIRMED,
            'created_by' => $admin->id,
        ]);
        $woItem = WorkOrderItem::create([
            'company_id' => $this->company->id,
            'work_order_id' => $wo->id,
            'sr_no' => '1.1',
            'description' => 'Excavation in hard rock',
            'unit' => 'CUM',
            'ordered_quantity' => 100,
            'rate' => 1000,
            'amount' => 100000,
        ]);

        MeasurementEntry::creating(function () {
            throw new \RuntimeException('Simulated failure after measurement photo stored');
        });

        $raService = app(RaBillingService::class);
        $exceptionThrown = false;
        try {
            $raService->recordMeasurement(
                $wo,
                [
                    'work_order_item_id' => $woItem->id,
                    'site_id' => $site->id,
                    'direct_quantity' => 25,
                ],
                $admin,
                UploadedFile::fake()->image('site_measurement.jpg')
            );
        } catch (\RuntimeException $e) {
            $exceptionThrown = true;
        }

        MeasurementEntry::flushEventListeners();
        $this->assertTrue($exceptionThrown);
        $this->assertSame(0, MeasurementEntry::count());
        $this->assertEmpty(Storage::disk('local')->allFiles(), 'Orphaned measurement photo must be cleaned up on failure.');

        // 3. Simulate failure during Inventory Goods Receipt (second line item has negative quantity)
        $warehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'site_id' => $site->id,
            'name' => 'Site 1 Store',
            'code' => 'WH-001',
            'is_active' => true,
        ]);
        $matCategory = \App\Models\MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Cement',
            'code' => 'CAT-CEM',
        ]);
        $material = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $matCategory->id,
            'name' => 'OPC 53 Cement',
            'code' => 'MAT-CEM-01',
            'unit_of_measure' => 'Bags',
            'minimum_stock_level' => 20,
            'is_active' => true,
        ]);
        $po = PurchaseOrder::create([
            'company_id' => $this->company->id,
            'site_id' => $site->id,
            'warehouse_id' => $warehouse->id,
            'po_number' => 'PO-NSD-2026-001',
            'supplier_name' => 'UltraTech Suppliers',
            'order_date' => now()->toDateString(),
            'status' => 'ordered',
            'total_amount' => 40000,
            'created_by' => $admin->id,
        ]);
        $poItem1 = PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'material_id' => $material->id,
            'quantity_ordered' => 100,
            'quantity_received' => 0,
            'unit_price' => 400,
        ]);

        $invService = app(InventoryService::class);
        $grnFailed = false;
        try {
            $invService->recordGoodsReceipt($po, $warehouse->id, [
                $poItem1->id => 50,
                999999 => -10, // Negative quantity triggers InvalidArgumentException mid-transaction
            ], $admin, 'CHALLAN-99');
        } catch (\Throwable $e) {
            $grnFailed = true;
        }

        $this->assertTrue($grnFailed);
        $this->assertSame(0, \App\Models\InventoryTransaction::count(), 'No partial InventoryTransaction may remain on failure.');
        $this->assertSame(0.0, (float) $poItem1->fresh()->quantity_received, 'PO item quantity_received must roll back.');
        $this->assertSame(0, InventoryStock::count(), 'InventoryStock must roll back.');

        // 4. Simulate failure during RA Bill creation when a DB failure occurs mid-transaction
        \App\Models\RaBillItem::creating(function () {
            throw new \RuntimeException('Simulated failure during RA Bill line item creation');
        });
        $raFailed = false;
        try {
            $raService->createOrRecalculateRaBill($wo, [
                'invoice_date' => now()->toDateString(),
                'period_from' => '2026-04-01',
                'period_to' => '2026-04-30',
                'items' => [
                    $woItem->id => ['claimed_quantity' => 10, 'hold_quantity' => 0],
                ],
            ], $admin);
        } catch (\Throwable $e) {
            $raFailed = true;
        }
        \App\Models\RaBillItem::flushEventListeners();
        $this->assertTrue($raFailed);
        $this->assertSame(0, RaBill::count(), 'No partial RA Bill header may remain when line item creation fails.');
    }

    public function test_role_based_acceptance_testing_across_all_five_roles(): void
    {
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-001',
            'name' => 'Highway Expansion Project',
            'status' => 'active',
        ]);
        $site1 = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-001',
            'name' => 'Section A — Thane',
            'status' => 'active',
        ]);
        $site2 = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-002',
            'name' => 'Section B — Nashik',
            'status' => 'active',
        ]);

        $companyAdmin = $this->createUserWithRole('company_admin', [], false);
        $seniorManager = $this->createUserWithRole('senior_manager', [], false);
        $siteManager = $this->createUserWithRole('site_manager', [$site1->id], true);
        $supervisor = $this->createUserWithRole('site_supervisor', [$site1->id], true);
        $officeStaff = $this->createUserWithRole('office_staff', [], false);

        // Verify all 5 roles can load /dashboard without error (specifically verifying the site_id -> assigned_site_id fix for Site Manager & Supervisor!)
        foreach ([$companyAdmin, $seniorManager, $siteManager, $supervisor, $officeStaff] as $roleUser) {
            $this->actingAs($roleUser)
                ->get(route('dashboard'))
                ->assertOk();
        }

        // Verify Company Admin access: can manage employees, view attendance, export attendance, but CANNOT mark/edit/approve attendance by default
        $this->actingAs($companyAdmin)->get(route('admin.users.index'))->assertOk();
        $this->actingAs($companyAdmin)->get(route('attendance.register'))->assertOk();
        $this->actingAs($companyAdmin)->get(route('attendance.export_payroll'))->assertOk();
        $this->actingAs($companyAdmin)->post(route('attendance.store_muster'), [
            'site_id' => $site1->id,
            'attendance_date' => now()->toDateString(),
            'entries' => [],
        ])->assertForbidden();

        // Verify Supervisor access: can view assigned site, mark attendance on assigned site, create tasks, CANNOT access /admin/users or unassigned site2
        $this->actingAs($supervisor)->get(route('sites.show', $site1))->assertOk();
        $this->actingAs($supervisor)->get(route('sites.show', $site2))->assertForbidden();
        $this->actingAs($supervisor)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($supervisor)->get(route('tasks.index', ['site_id' => $site1->id]))->assertOk();
        $this->actingAs($supervisor)->get(route('tasks.index', ['site_id' => $site2->id]))->assertForbidden();

        // Verify Site Manager access: can view & manage Site 1, blocked from Site 2
        $this->actingAs($siteManager)->get(route('sites.show', $site1))->assertOk();
        $this->actingAs($siteManager)->get(route('sites.show', $site2))->assertForbidden();
        $this->actingAs($siteManager)->get(route('workforce.index', ['site_id' => $site1->id]))->assertOk();

        // Verify Office Staff access: can view procurement & inventory, CANNOT export attendance by default, CANNOT access /admin/users
        $this->actingAs($officeStaff)->get(route('procurement.requests'))->assertOk();
        $this->actingAs($officeStaff)->get(route('inventory.stocks'))->assertOk();
        $this->actingAs($officeStaff)->get(route('attendance.export_payroll'))->assertForbidden();
        $this->actingAs($officeStaff)->get(route('admin.users.index'))->assertForbidden();

        // Verify Senior Manager access: can view all sites & multi-site report, CANNOT approve attendance corrections without explicit grant
        $this->actingAs($seniorManager)->get(route('sites.show', $site1))->assertOk();
        $this->actingAs($seniorManager)->get(route('sites.show', $site2))->assertOk();
        $this->actingAs($seniorManager)->get(route('reports.multi_site'))->assertOk();
    }

    public function test_realistic_full_month_business_scenario_and_query_performance(): void
    {
        $companyAdmin = $this->createUserWithRole('company_admin', [], false);
        $seniorManager = $this->createUserWithRole('senior_manager', [], false);

        // 1. Create a Project with 2 Sites
        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-001',
            'name' => 'Pune Ring Road Package 2',
            'client_name' => 'MSRDC',
            'status' => 'active',
        ]);

        $site1 = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-001',
            'name' => 'Khed Viaduct Site',
            'status' => 'active',
        ]);

        $site2 = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-002',
            'name' => 'Chakan Interchange Site',
            'status' => 'active',
        ]);

        $siteManager1 = $this->createUserWithRole('site_manager', [$site1->id], true);
        $supervisor1 = $this->createUserWithRole('site_supervisor', [$site1->id], true);
        $officeStaff = $this->createUserWithRole('office_staff', [], false);

        $subcontractor = Subcontractor::create([
            'company_id' => $this->company->id,
            'code' => 'SUB-001',
            'name' => 'Sahyadri Civil Works',
            'is_active' => true,
        ]);

        // 2. Register 12 Workers (6 on Site 1, 6 on Site 2)
        $workersSite1 = [];
        $workersSite2 = [];
        for ($i = 1; $i <= 6; $i++) {
            $workersSite1[] = Worker::create([
                'company_id' => $this->company->id,
                'code' => sprintf('DL-NSD-2026-%03d', $i),
                'worker_type' => 'direct_labour',
                'first_name' => "WorkerA{$i}",
                'last_name' => 'Patil',
                'trade_designation' => 'Mason',
                'assigned_site_id' => $site1->id,
                'pay_type' => 'daily',
                'daily_rate' => 800,
                'overtime_rate' => 100,
                'status' => 'active',
                'joining_date' => '2026-03-01',
            ]);

            $workersSite2[] = Worker::create([
                'company_id' => $this->company->id,
                'code' => sprintf('SCW-NSD-2026-%03d', $i),
                'worker_type' => 'subcontractor_worker',
                'subcontractor_id' => $subcontractor->id,
                'first_name' => "WorkerB{$i}",
                'last_name' => 'Shinde',
                'trade_designation' => 'Fitter',
                'assigned_site_id' => $site2->id,
                'pay_type' => 'daily',
                'daily_rate' => 850,
                'overtime_rate' => 110,
                'status' => 'active',
                'joining_date' => '2026-03-01',
            ]);
        }

        // 3. Record a complete 30-day month of attendance (April 2026: 30 days * 12 workers = 360 records)
        $attendanceRows = [];
        $now = now();
        for ($day = 1; $day <= 30; $day++) {
            $dateStr = sprintf('2026-04-%02d', $day);
            foreach ($workersSite1 as $w) {
                $isSunday = ($day % 7 === 0);
                $attendanceRows[] = [
                    'company_id' => $this->company->id,
                    'project_id' => $project->id,
                    'worker_id' => $w->id,
                    'site_id' => $site1->id,
                    'attendance_date' => $dateStr,
                    'status' => $isSunday ? 'paid_leave' : 'present',
                    'worked_hours' => $isSunday ? 0 : 8.0,
                    'overtime_hours' => ($day % 5 === 0) ? 2.0 : 0.0,
                    'submitted_by' => $supervisor1->id,
                    'has_conflict' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            foreach ($workersSite2 as $w) {
                $isSunday = ($day % 7 === 0);
                $attendanceRows[] = [
                    'company_id' => $this->company->id,
                    'project_id' => $project->id,
                    'worker_id' => $w->id,
                    'site_id' => $site2->id,
                    'attendance_date' => $dateStr,
                    'status' => $isSunday ? 'absent' : 'present',
                    'worked_hours' => $isSunday ? 0 : 8.0,
                    'overtime_hours' => 0.0,
                    'submitted_by' => $seniorManager->id,
                    'has_conflict' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }
        DailyAttendance::insert($attendanceRows);
        $this->assertSame(360, DailyAttendance::where('company_id', $this->company->id)->count());

        // 4. Work Order Creation and 2-Tier Approval
        $woRes = $this->actingAs($siteManager1)->post(route('work_orders.store'), [
            'project_id' => $project->id,
            'site_id' => $site1->id,
            'client_name' => 'MSRDC Infrastructure',
            'order_type' => 'client_issued',
            'po_number' => 'MSRDC/2026/WO-77',
            'title' => 'RCC Pier Cap Casting Works',
            'cgst_rate' => 9,
            'sgst_rate' => 9,
            'retention_percent' => 5,
            'items' => [
                [
                    'sr_no' => '10.1',
                    'description' => 'M40 Grade Concrete Supply & Pouring',
                    'unit' => 'CUM',
                    'ordered_quantity' => 200,
                    'rate' => 6500,
                ],
            ],
        ]);
        $woRes->assertRedirect();
        $wo = WorkOrder::where('company_id', $this->company->id)->firstOrFail();
        $this->assertSame('submitted', $wo->status);

        // Tier 1 approval by Site Manager
        $this->actingAs($siteManager1)->post(route('work_orders.approve_site', $wo), [
            'notes' => 'Verified BOQ quantities on site.',
        ])->assertRedirect();
        $this->assertSame('site_manager_approved', $wo->fresh()->status);

        // Tier 2 approval by Senior Manager
        $this->actingAs($seniorManager)->post(route('work_orders.approve_senior', $wo), [
            'notes' => 'Approved for execution.',
        ])->assertRedirect();
        $this->assertSame('senior_manager_approved', $wo->fresh()->status);

        // 5. Purchase Request -> Purchase Order -> Goods Receipt -> Inventory Stock Issue
        $warehouse = Warehouse::create([
            'company_id' => $this->company->id,
            'site_id' => $site1->id,
            'name' => 'Khed Central Store',
            'code' => 'WH-KHED-01',
            'is_active' => true,
        ]);
        $steelCategory = \App\Models\MaterialCategory::create([
            'company_id' => $this->company->id,
            'name' => 'Steel',
            'code' => 'CAT-STL',
        ]);
        $material = Material::create([
            'company_id' => $this->company->id,
            'category_id' => $steelCategory->id,
            'name' => 'TMT Fe500D 16mm Rebar',
            'code' => 'MAT-TMT-16',
            'unit_of_measure' => 'MT',
            'minimum_stock_level' => 5,
            'is_active' => true,
        ]);

        $prRes = $this->actingAs($siteManager1)->post(route('procurement.store_request'), [
            'site_id' => $site1->id,
            'warehouse_id' => $warehouse->id,
            'is_emergency' => false,
            'notes' => 'Required for Pier Cap reinforcement',
            'items' => [
                [
                    'material_id' => $material->id,
                    'quantity' => 25,
                    'purpose_notes' => 'Pier P-12 reinforcement',
                ],
            ],
        ]);
        $prRes->assertRedirect();
        $pr = PurchaseRequest::where('company_id', $this->company->id)->firstOrFail();

        $this->actingAs($seniorManager)->post(route('procurement.approve_request', $pr), [
            'comments' => 'Approved for PO issuance.',
        ])->assertRedirect();

        $poRes = $this->actingAs($seniorManager)->post(route('procurement.store_po', $pr), [
            'supplier_name' => 'JSW Steel Distributors',
            'supplier_phone' => '9822001122',
            'expected_delivery_date' => '2026-04-15',
            'unit_prices' => [
                $material->id => 58000,
            ],
        ]);
        $poRes->assertRedirect();
        $po = PurchaseOrder::where('purchase_request_id', $pr->id)->firstOrFail();

        $grnRes = $this->actingAs($siteManager1)->post(route('procurement.confirm_receipt', $po), [
            'destination_warehouse_id' => $warehouse->id,
            'challan_or_invoice_no' => 'JSW-DC-2026-881',
            'received' => [
                $material->id => 25,
            ],
        ]);
        $grnRes->assertRedirect();

        $stock = InventoryStock::where('warehouse_id', $warehouse->id)->where('material_id', $material->id)->firstOrFail();
        $this->assertSame(25.0, (float) $stock->quantity);
        $this->assertSame(1, \App\Models\InventoryTransaction::where('purchase_order_id', $po->id)->count());

        // 6. Measurement Entry & RA Bill Creation + 2-Tier Approval
        $woItem = $wo->items()->firstOrFail();
        $measRes = $this->actingAs($siteManager1)->post(route('work_orders.measurements.store', $wo), [
            'work_order_item_id' => $woItem->id,
            'site_id' => $site1->id,
            'measured_on' => '2026-04-25',
            'location_chainage' => 'Pier P-12 Cap',
            'nos' => 1,
            'length' => 10,
            'breadth' => 5,
            'depth_height' => 2,
            'hold_quantity' => 0,
            'status' => 'verified',
        ]);
        $measRes->assertRedirect();

        $raRes = $this->actingAs($officeStaff)->post(route('work_orders.ra_bills.store', $wo), [
            'invoice_date' => '2026-04-30',
            'period_from' => '2026-04-01',
            'period_to' => '2026-04-30',
            'retention_percent' => 5,
            'other_deductions' => 0,
            'items' => [
                $woItem->id => [
                    'claimed_quantity' => 100,
                    'hold_quantity' => 0,
                ],
            ],
        ]);
        $raRes->assertRedirect();
        $raBill = RaBill::where('work_order_id', $wo->id)->firstOrFail();
        $this->assertSame(650000.0, (float) $raBill->current_bill_amount); // 100 CUM * 6500

        if ($raBill->status === 'draft') {
            $this->actingAs($officeStaff)->post(route('work_orders.ra_bills.submit', [$wo, $raBill]))->assertRedirect();
            $this->assertSame('submitted', $raBill->fresh()->status);
        }

        $this->actingAs($siteManager1)->post(route('work_orders.ra_bills.approve_site', [$wo, $raBill]), [
            'notes' => 'Quantities checked against MB.',
        ])->assertRedirect();
        $this->assertSame('site_manager_approved', $raBill->fresh()->status);

        $this->actingAs($seniorManager)->post(route('work_orders.ra_bills.approve_senior', [$wo, $raBill]), [
            'notes' => 'Approved for payment release.',
        ])->assertRedirect();
        $this->assertSame('approved', $raBill->fresh()->status);

        // 7. Payroll Generation & Report Exports
        $payrollRes = $this->actingAs($companyAdmin)->post(route('payroll.store'), [
            'title' => 'April 2026 Site 1 Payroll',
            'site_id' => $site1->id,
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]);
        $payrollRes->assertRedirect();
        $this->assertSame(1, \App\Models\PayrollPeriod::where('company_id', $this->company->id)->count());

        $this->actingAs($companyAdmin)->get(route('reports.workforce', ['export' => 'csv']))->assertOk();
        $this->actingAs($companyAdmin)->get(route('attendance.export_payroll', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]))->assertOk();
        $this->actingAs($companyAdmin)->get(route('reports.audit_logs.export'))->assertOk();

        // 8. Performance & N+1 Query Ceiling Verification
        DB::enableQueryLog();

        DB::flushQueryLog();
        $this->actingAs($companyAdmin)->get(route('dashboard'))->assertOk();
        $dashboardQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(50, $dashboardQueries, "Dashboard executed too many queries ({$dashboardQueries}).");

        DB::flushQueryLog();
        $this->actingAs($siteManager1)->get(route('workforce.index'))->assertOk();
        $workforceQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(35, $workforceQueries, "Workforce directory executed too many queries ({$workforceQueries}).");

        DB::flushQueryLog();
        $this->actingAs($companyAdmin)->get(route('attendance.register', [
            'month' => '2026-04',
            'site_id' => $site1->id,
        ]))->assertOk();
        $registerQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(35, $registerQueries, "Monthly attendance register (360 rows) executed too many queries ({$registerQueries}).");

        DB::flushQueryLog();
        $this->actingAs($companyAdmin)->get(route('reports.multi_site', [
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]))->assertOk();
        $multiSiteQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(30, $multiSiteQueries, "Multi-site comparison report executed too many queries ({$multiSiteQueries}).");

        DB::disableQueryLog();
    }
}

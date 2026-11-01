<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseAJswWorkOrderTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Site $siteA;
    private Site $siteB;
    private Project $project;
    private User $companyAdmin;
    private User $seniorManager;
    private User $siteManagerA;
    private User $siteManagerB;
    private User $officeStaffA;
    private User $warehouseManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Earth Movers & Construction',
            'code' => 'NSD',
            'gstin' => '27AABCN1234F1Z5',
            'pan' => 'AABCN1234F',
            'vendor_code' => '100984',
            'invoice_prefix' => 'CIVIL',
            'invoice_starting_number' => 21,
            'invoice_rounding_mode' => 'paisa',
            'is_active' => true,
        ]);

        TenantContext::setCompanyId($this->company->id);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-JSW-01',
            'name' => 'JSW Civil & Structural Works',
            'status' => 'active',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'JSW-DOLVI-A',
            'name' => 'JSW Dolvi SMS-2 Site A',
            'city' => 'Dolvi',
            'state' => 'Maharashtra',
            'status' => 'active',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $this->project->id,
            'code' => 'JSW-DOLVI-B',
            'name' => 'JSW Dolvi Pellet Plant Site B',
            'city' => 'Dolvi',
            'state' => 'Maharashtra',
            'status' => 'active',
        ]);

        $this->companyAdmin = $this->createUserWithRole('company_admin', 'Company Admin', ['*']);
        $this->seniorManager = $this->createUserWithRole('senior_manager', 'Senior VP', [
            'work_orders.view', 'work_orders.create', 'work_orders.approve_senior',
        ]);
        $this->siteManagerA = $this->createUserWithRole('site_manager', 'Site Manager A', [
            'work_orders.view', 'work_orders.create', 'work_orders.approve_site',
        ], [$this->siteA->id]);
        $this->siteManagerB = $this->createUserWithRole('site_manager', 'Site Manager B', [
            'work_orders.view', 'work_orders.create', 'work_orders.approve_site',
        ], [$this->siteB->id]);
        $this->officeStaffA = $this->createUserWithRole('office_staff', 'Office Staff A', [
            'work_orders.view', 'work_orders.create',
        ], [$this->siteA->id]);
        $this->warehouseManager = $this->createUserWithRole('warehouse_manager', 'Warehouse Manager', [
            'inventory.view',
        ], [$this->siteA->id]);
    }

    private function createUserWithRole(string $slug, string $name, array $permissions, array $siteIds = []): User
    {
        $role = Role::firstOrCreate(
            ['company_id' => $this->company->id, 'slug' => $slug],
            ['name' => $name, 'permissions' => $permissions]
        );

        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);

        $user->companies()->attach($this->company->id, [
            'role_id' => $role->id,
        ]);

        foreach ($siteIds as $siteId) {
            Site::find($siteId)?->assignedUsers()->attach($user->id);
        }

        return $user;
    }

    public function test_jsw_service_order_creation_preserves_sr_no_string_1_10_and_creates_rev_0(): void
    {
        Storage::fake('local');

        $client = Client::create([
            'company_id' => $this->company->id,
            'code' => 'JSW-DOLVI',
            'name' => 'JSW STEEL LIMITED',
            'address' => 'Geetapuram, Dolvi, Taluka Pen, Dist. Raigad - 402107',
            'gstin' => '27AAACJ4323N1Z5',
            'pan' => 'AAACJ4323N',
            'vendor_code' => '100984',
        ]);

        $response = $this->actingAs($this->officeStaffA)->post(route('work_orders.store'), [
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'client_id' => $client->id,
            'order_type' => 'client_issued',
            'po_number' => '4700226845',
            'po_date' => '2026-10-15',
            'client_reference' => '4700226845 / Rev 0',
            'job_location' => 'JSW Steel Ltd, Dolvi Works - SMS-2',
            'user_department_person' => 'Civil Projects / Mr. S. Kulkarni',
            'effective_date' => '2026-10-15',
            'completion_date' => '2027-03-31',
            'cgst_rate' => 9.00,
            'sgst_rate' => 9.00,
            'retention_percent' => 5.00,
            'title' => 'Civil & Structural Foundations at SMS-2 Dolvi Plant',
            'description' => 'JSW Service Order for SMS-2 civil foundation works',
            'items' => [
                [
                    'sr_no' => '1.10',
                    'service_code' => '6003235',
                    'sac_code' => '995419',
                    'description' => 'Earthwork in Excavation in All Types of Soil',
                    'specification' => 'Including dewatering, shoring and disposal up to 2 km lead',
                    'unit' => 'M3',
                    'ordered_quantity' => 250.500,
                    'rate' => 320.00,
                ],
                [
                    'sr_no' => '1.20',
                    'service_code' => '6003238',
                    'sac_code' => '995419',
                    'description' => 'Providing & Laying PCC M-15 Grade',
                    'specification' => 'Using 20mm graded crushed stone aggregate',
                    'unit' => 'M3',
                    'ordered_quantity' => 100.000,
                    'rate' => 5200.00,
                ],
            ],
            'attachment' => UploadedFile::fake()->create('jsw-po-4700226845.pdf', 180, 'application/pdf'),
        ]);

        $response->assertRedirect();

        $workOrder = WorkOrder::with(['items', 'revisions', 'client', 'attachments'])->firstOrFail();

        $this->assertSame($client->id, $workOrder->client_id);
        $this->assertSame('4700226845', $workOrder->po_number);
        $this->assertSame(0, (int) $workOrder->current_revision);
        // 250.5 * 320 = 80,160.00; 100 * 5200 = 520,000.00; Total = 600,160.00
        $this->assertSame('600160.00', (string) $workOrder->base_order_value);
        $this->assertSame(54014.40, $workOrder->cgstAmount());
        $this->assertSame(54014.40, $workOrder->sgstAmount());
        $this->assertSame(708188.80, $workOrder->grossOrderValue());

        // Verify sr_no is preserved as exact string '1.10' and '1.20' (not truncated to float 1.1)
        $this->assertCount(2, $workOrder->items);
        $this->assertSame('1.10', $workOrder->items[0]->sr_no);
        $this->assertSame('6003235', $workOrder->items[0]->service_code);
        $this->assertSame('80160.00', (string) $workOrder->items[0]->line_amount);
        $this->assertSame('1.20', $workOrder->items[1]->sr_no);

        // Verify initial Revision 0 snapshot was created
        $this->assertCount(1, $workOrder->revisions);
        $this->assertSame(0, (int) $workOrder->revisions->first()->revision_number);
        $this->assertSame('approved', $workOrder->revisions->first()->status);

        // Verify private attachment stored on 'local' disk
        $this->assertCount(1, $workOrder->attachments);
        Storage::disk('local')->assertExists($workOrder->attachments->first()->file_path);
    }

    public function test_work_order_revision_workflow_prevents_self_approval_and_updates_items_on_senior_approval(): void
    {
        $this->actingAs($this->siteManagerA)->post(route('work_orders.store'), [
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'order_type' => 'client_issued',
            'po_number' => '4700226900',
            'title' => 'Retaining Wall Extension',
            'items' => [
                [
                    'sr_no' => '1.10',
                    'service_code' => '6003235',
                    'sac_code' => '995419',
                    'description' => 'Excavation in Hard Rock',
                    'unit' => 'M3',
                    'ordered_quantity' => 100.000,
                    'rate' => 500.00,
                ],
            ],
        ])->assertRedirect();

        $workOrder = WorkOrder::firstOrFail();
        $this->assertSame('50000.00', (string) $workOrder->base_order_value);

        // Propose Revision #1 increasing ordered_quantity from 100 to 160
        $this->actingAs($this->officeStaffA)->post(route('work_orders.revisions.store', $workOrder), [
            'reason' => 'JSW Amendment #1 — Additional 60 M3 hard rock excavation approved',
            'items' => [
                [
                    'sr_no' => '1.10',
                    'service_code' => '6003235',
                    'sac_code' => '995419',
                    'description' => 'Excavation in Hard Rock',
                    'unit' => 'M3',
                    'ordered_quantity' => 160.000,
                    'rate' => 500.00,
                ],
            ],
        ])->assertRedirect();

        $workOrder->refresh();
        // Still Rev 0 and 50,000 until approved
        $this->assertSame(0, (int) $workOrder->current_revision);
        $this->assertSame('50000.00', (string) $workOrder->base_order_value);

        $pendingRevision = $workOrder->revisions()->where('revision_number', 1)->firstOrFail();
        $this->assertSame('pending_approval', $pendingRevision->status);
        $this->assertSame('50000.00', (string) $pendingRevision->previous_base_value);
        $this->assertSame('80000.00', (string) $pendingRevision->new_base_value);

        // Office staff / Site Manager cannot approve revision
        $this->actingAs($this->officeStaffA)
            ->post(route('work_orders.revisions.approve', [$workOrder, $pendingRevision->id]))
            ->assertStatus(403);

        $this->actingAs($this->siteManagerA)
            ->post(route('work_orders.revisions.approve', [$workOrder, $pendingRevision->id]))
            ->assertStatus(403);

        // If a Senior Manager creates a revision themselves, they cannot self-approve it
        $this->actingAs($this->seniorManager)->post(route('work_orders.revisions.store', $workOrder), [
            'reason' => 'Senior Manager self-proposed revision test',
            'items' => [
                [
                    'sr_no' => '1.10',
                    'description' => 'Excavation in Hard Rock',
                    'unit' => 'M3',
                    'ordered_quantity' => 200.000,
                    'rate' => 500.00,
                ],
            ],
        ])->assertRedirect();

        $selfRevision = $workOrder->revisions()->where('revision_number', 2)->firstOrFail();
        $this->actingAs($this->seniorManager)
            ->post(route('work_orders.revisions.approve', [$workOrder, $selfRevision->id]))
            ->assertStatus(403);

        // Senior Manager can approve Revision #1 proposed by Office Staff A
        $this->actingAs($this->seniorManager)
            ->post(route('work_orders.revisions.approve', [$workOrder, $pendingRevision->id]))
            ->assertRedirect();

        $workOrder->refresh();
        $pendingRevision->refresh();

        $this->assertSame('approved', $pendingRevision->status);
        $this->assertSame(1, (int) $workOrder->current_revision);
        $this->assertSame('80000.00', (string) $workOrder->base_order_value);
        $this->assertSame('160.000', (string) $workOrder->items()->first()->ordered_quantity);
        // Snapshot still preserves original 100.000 quantity
        $this->assertSame(100.0, (float) $pendingRevision->items_snapshot[0]['ordered_quantity']);
    }

    public function test_site_scoping_and_policy_authorization_on_work_orders_and_attachments(): void
    {
        Storage::fake('local');

        $this->actingAs($this->siteManagerA)->post(route('work_orders.store'), [
            'project_id' => $this->project->id,
            'site_id' => $this->siteA->id,
            'order_type' => 'client_issued',
            'title' => 'Site A Culvert Work Order',
            'items' => [
                ['sr_no' => '1.10', 'description' => 'Earthwork', 'unit' => 'M3', 'ordered_quantity' => 10, 'rate' => 250],
            ],
            'attachment' => UploadedFile::fake()->create('site-a-secret.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $workOrderA = WorkOrder::with('attachments')->firstOrFail();
        $attachmentA = $workOrderA->attachments->firstOrFail();

        // Site Manager B (assigned only to Site B) cannot view, approveSite, reject, or download attachment of Site A work order
        $this->actingAs($this->siteManagerB)
            ->get(route('work_orders.show', $workOrderA))
            ->assertStatus(403);

        $this->actingAs($this->siteManagerB)
            ->post(route('work_orders.approve_site', $workOrderA), ['notes' => 'Unauthorized cross-site approval'])
            ->assertStatus(403);

        $this->actingAs($this->siteManagerB)
            ->post(route('work_orders.reject', $workOrderA), ['rejection_reason' => 'Unauthorized cross-site rejection'])
            ->assertStatus(403);

        $this->actingAs($this->siteManagerB)
            ->get(route('work_orders.attachments.download', [$workOrderA, $attachmentA->id]))
            ->assertStatus(403);

        // Warehouse Manager cannot access Work Orders index or download attachment
        $this->actingAs($this->warehouseManager)
            ->get(route('work_orders.index'))
            ->assertStatus(403);

        // Site Manager A can download attachment and approve Tier 1
        $this->actingAs($this->siteManagerA)
            ->get(route('work_orders.attachments.download', [$workOrderA, $attachmentA->id]))
            ->assertStatus(200);

        $this->actingAs($this->siteManagerA)
            ->post(route('work_orders.approve_site', $workOrderA), ['notes' => 'Verified on Site A'])
            ->assertRedirect();

        $this->assertSame('site_manager_approved', $workOrderA->fresh()->status);
    }
}

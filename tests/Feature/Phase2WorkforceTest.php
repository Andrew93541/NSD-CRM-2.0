<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteAssignment;
use App\Models\Subcontractor;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerDocument;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase2WorkforceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected User $adminA;
    protected User $siteManager1;
    protected Site $site1;
    protected Site $site2;
    protected Subcontractor $subcontractorA;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->companyA = Company::create([
            'name' => 'Apex Infra Pvt Ltd',
            'code' => 'APEX',
            'status' => 'active',
        ]);

        TenantContext::setCompanyId($this->companyA->id);

        $roleAdmin = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);

        $roleSiteMgr = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => ['workforce.view', 'workforce.create', 'workforce.update'],
        ]);

        $this->adminA = $this->makeUser('admin@apex.test', $roleAdmin);
        $this->siteManager1 = $this->makeUser('sm1@apex.test', $roleSiteMgr);

        $projectA = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Highway Package 4',
            'code' => 'HWY-04',
            'status' => 'active',
        ]);

        $this->site1 = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $projectA->id,
            'name' => 'Sector 1 Viaduct',
            'code' => 'SITE-01',
            'status' => 'active',
        ]);

        $this->site2 = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $projectA->id,
            'name' => 'Sector 2 Bridge',
            'code' => 'SITE-02',
            'status' => 'active',
        ]);

        $this->siteManager1->sites()->attach($this->site1->id);

        $this->subcontractorA = Subcontractor::create([
            'company_id' => $this->companyA->id,
            'name' => 'Shree Ganesh Earthmovers',
            'code' => 'SUB-01',
            'is_active' => true,
        ]);
    }

    protected function makeUser(string $email, Role $role): User
    {
        $user = User::create([
            'name' => ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $user->companies()->attach($this->companyA->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        return $user;
    }

    public function test_dynamic_worker_ids_generated_sequentially_for_permanent_and_subcontractor_workers(): void
    {
        // 1. Create two permanent employees in 2026 -> EMP-2026-0001 and EMP-2026-0002
        $this->actingAs($this->adminA)->post('/workforce', [
            'worker_type' => 'employee',
            'first_name' => 'Anil',
            'last_name' => 'Kulkarni',
            'trade_designation' => 'Site Engineer',
            'skill_level' => 'highly_skilled',
            'joining_date' => '2026-02-01',
            'daily_rate' => 1500,
            'overtime_rate' => 220,
            'assigned_site_id' => $this->site1->id,
        ])->assertRedirect('/workforce');

        $this->actingAs($this->adminA)->post('/workforce', [
            'worker_type' => 'employee',
            'first_name' => 'Sunil',
            'last_name' => 'Joshi',
            'trade_designation' => 'Surveyor',
            'skill_level' => 'skilled',
            'joining_date' => '2026-02-10',
            'daily_rate' => 1250,
            'assigned_site_id' => $this->site1->id,
        ])->assertRedirect('/workforce');

        $emp1 = Worker::where('first_name', 'Anil')->firstOrFail();
        $emp2 = Worker::where('first_name', 'Sunil')->firstOrFail();

        $this->assertEquals('EMP-2026-0001', $emp1->code);
        $this->assertEquals('EMP-2026-0002', $emp2->code);
        $this->assertEquals('highly_skilled', $emp1->skill_level);
        $this->assertEquals(220.00, (float) $emp1->overtime_rate);

        // 2. Create two subcontractor / temporary workers in 2026 -> SUB-2026-0001 and SUB-2026-0002
        $this->actingAs($this->adminA)->post('/workforce', [
            'worker_type' => 'subcontractor_worker',
            'subcontractor_id' => $this->subcontractorA->id,
            'first_name' => 'Mahesh',
            'last_name' => 'More',
            'trade_designation' => 'Excavator Helper',
            'joining_date' => '2026-03-01',
            'daily_rate' => 650,
            'assigned_site_id' => $this->site1->id,
        ])->assertRedirect('/workforce');

        $this->actingAs($this->adminA)->post('/workforce', [
            'worker_type' => 'direct_labour',
            'first_name' => 'Dinesh',
            'last_name' => 'Kamble',
            'trade_designation' => 'General Helper',
            'joining_date' => '2026-03-05',
            'daily_rate' => 600,
            'assigned_site_id' => $this->site1->id,
        ])->assertRedirect('/workforce');

        $sub1 = Worker::where('first_name', 'Mahesh')->firstOrFail();
        $sub2 = Worker::where('first_name', 'Dinesh')->firstOrFail();

        $this->assertEquals('SUB-2026-0001', $sub1->code);
        $this->assertEquals('SUB-2026-0002', $sub2->code);

        $this->assertDatabaseHas('worker_sequences', [
            'company_id' => $this->companyA->id,
            'prefix' => 'EMP',
            'year' => 2026,
            'last_number' => 2,
        ]);

        $this->assertDatabaseHas('worker_sequences', [
            'company_id' => $this->companyA->id,
            'prefix' => 'SUB',
            'year' => 2026,
            'last_number' => 2,
        ]);
    }

    public function test_document_expiry_tracking_and_compliance_filtering(): void
    {
        $expiredLicence = UploadedFile::fake()->create('expired_licence.pdf', 120, 'application/pdf');
        $expiringMedical = UploadedFile::fake()->create('expiring_medical.pdf', 120, 'application/pdf');

        $this->actingAs($this->adminA)->post('/workforce', [
            'worker_type' => 'employee',
            'first_name' => 'Prakash',
            'last_name' => 'Shinde',
            'trade_designation' => 'Crane Operator',
            'joining_date' => '2026-01-10',
            'daily_rate' => 1300,
            'assigned_site_id' => $this->site1->id,
            'documents' => [$expiredLicence],
            'document_types' => ['licence'],
            'document_titles' => ['Old RTO Licence'],
            'document_expiry_dates' => [now()->subDays(5)->toDateString()],
        ])->assertRedirect('/workforce');

        $this->actingAs($this->adminA)->post('/workforce', [
            'worker_type' => 'employee',
            'first_name' => 'Santosh',
            'last_name' => 'Bhosale',
            'trade_designation' => 'Welder',
            'joining_date' => '2026-01-12',
            'daily_rate' => 1100,
            'assigned_site_id' => $this->site1->id,
            'documents' => [$expiringMedical],
            'document_types' => ['medical_fitness'],
            'document_titles' => ['Annual Fitness'],
            'document_expiry_dates' => [now()->addDays(12)->toDateString()],
        ])->assertRedirect('/workforce');

        $docExpired = WorkerDocument::where('document_type', 'licence')->firstOrFail();
        $docExpiring = WorkerDocument::where('document_type', 'medical_fitness')->firstOrFail();

        $this->assertTrue($docExpired->isExpired());
        $this->assertEquals('expired', $docExpired->expiryStatus());

        $this->assertTrue($docExpiring->isExpiringSoon());
        $this->assertEquals('expiring_soon', $docExpiring->expiryStatus());

        // Filter workforce directory by expired documents
        $this->flushSession();
        $this->actingAs($this->adminA)
            ->get('/workforce?compliance_filter=expired')
            ->assertOk()
            ->assertSee('Prakash Shinde')
            ->assertDontSee('Santosh Bhosale');

        // Filter workforce directory by expiring_soon documents
        $this->actingAs($this->adminA)
            ->get('/workforce?compliance_filter=expiring_soon')
            ->assertOk()
            ->assertSee('Santosh Bhosale')
            ->assertDontSee('Prakash Shinde');
    }

    public function test_worker_profile_site_transfer_preserves_history_and_enforces_authorization(): void
    {
        $this->actingAs($this->adminA)->post('/workforce', [
            'worker_type' => 'employee',
            'first_name' => 'Kiran',
            'last_name' => 'Gaikwad',
            'trade_designation' => 'Bar Bender',
            'joining_date' => '2026-01-05',
            'daily_rate' => 850,
            'assigned_site_id' => $this->site1->id,
        ])->assertRedirect('/workforce');

        $worker = Worker::where('first_name', 'Kiran')->firstOrFail();

        // Site Manager 1 is NOT assigned to Site 2 -> gets 403 when attempting to transfer worker to Site 2
        $this->actingAs($this->siteManager1)
            ->post(route('workforce.transfer', $worker), [
                'site_id' => $this->site2->id,
                'role_on_site' => 'Senior Bar Bender',
                'start_date' => '2026-04-10',
                'notes' => 'Unauthorized transfer attempt',
            ])
            ->assertForbidden();

        // Admin transfers worker to Site 2 -> closes Site 1 assignment and opens Site 2 assignment
        $this->actingAs($this->adminA)
            ->from(route('workforce.show', $worker))
            ->post(route('workforce.transfer', $worker), [
                'site_id' => $this->site2->id,
                'role_on_site' => 'Lead Bar Bender',
                'start_date' => '2026-04-10',
                'notes' => 'Reallocated to Sector 2 Bridge',
            ])
            ->assertRedirect(route('workforce.show', $worker));

        $this->assertEquals($this->site2->id, $worker->fresh()->assigned_site_id);

        $site1Record = SiteAssignment::where('worker_id', $worker->id)->where('site_id', $this->site1->id)->firstOrFail();
        $site2Record = SiteAssignment::where('worker_id', $worker->id)->where('site_id', $this->site2->id)->firstOrFail();

        $this->assertFalse($site1Record->is_active);
        $this->assertEquals('2026-04-10', $site1Record->end_date?->format('Y-m-d'));

        $this->assertTrue($site2Record->is_active);
        $this->assertNull($site2Record->end_date);
        $this->assertEquals('Lead Bar Bender', $site2Record->role_on_site);
    }
}

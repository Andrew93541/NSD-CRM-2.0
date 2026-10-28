<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkerFullProfileTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected User $adminA;
    protected User $siteManager1;
    protected Site $site1;
    protected Site $site2;
    protected Subcontractor $subcontractorA;
    protected Worker $existingWorker;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->companyA = Company::create([
            'name' => 'Apex Infra Pvt Ltd',
            'code' => 'APEX',
            'status' => 'active',
        ]);

        $this->companyB = Company::create([
            'name' => 'Other Builders Ltd',
            'code' => 'OTHR',
            'status' => 'active',
        ]);

        TenantContext::setCompanyId($this->companyA->id);

        $roleAdminA = Role::create([
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

        $this->adminA = $this->makeUser('admin@apex.test', $this->companyA, $roleAdminA);
        $this->siteManager1 = $this->makeUser('sm1@apex.test', $this->companyA, $roleSiteMgr);

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

        $this->existingWorker = Worker::create([
            'company_id' => $this->companyA->id,
            'worker_type' => 'employee',
            'code' => 'W-EMP-001',
            'first_name' => 'Rajesh',
            'last_name' => 'Patil',
            'phone' => '+91 9822112233',
            'aadhaar_last_four' => '4589',
            'trade_designation' => 'Site Foreman',
            'assigned_site_id' => $this->site1->id,
            'daily_rate' => 1100.00,
            'status' => 'active',
            'joining_date' => '2025-05-10',
        ]);
    }

    protected function makeUser(string $email, Company $company, Role $role): User
    {
        $user = User::create([
            'name' => ucfirst(explode('@', $email)[0]),
            'email' => $email,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->companies()->attach($company->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        return $user;
    }

    public function test_create_full_worker_profile_across_all_six_sections(): void
    {
        $createPage = $this->actingAs($this->adminA)->get('/workforce/create');
        $createPage->assertStatus(200)
            ->assertSee('1. Personal Information')
            ->assertSee('2. Contact Information')
            ->assertSee('3. Employment Information')
            ->assertSee('4. Site Assignment')
            ->assertSee('5. Emergency Contact')
            ->assertSee('6. Worker Documents (Optional)');

        $photo = UploadedFile::fake()->image('passport_photo.jpg', 300, 300);

        $response = $this->actingAs($this->adminA)->post('/workforce', [
            // Section 1: Personal Info
            'first_name' => 'Vikram',
            'last_name' => 'Deshmukh',
            'date_of_birth' => '1991-08-14',
            'gender' => 'male',
            'aadhaar_last_four' => '7812',

            // Section 2: Contact Info
            'phone' => '+91 9823456789',
            'alternate_phone' => '+91 9765432109',
            'email' => 'vikram.deshmukh@example.com',
            'address' => 'Plot 42, Sector 7 Labour Camp, Panvel, Maharashtra 410206',

            // Section 3: Employment Info
            'worker_type' => 'employee',
            'trade_designation' => 'Senior Tower Crane Operator',
            'department' => 'Plant & Heavy Machinery',
            'joining_date' => '2026-01-15',
            'status' => 'active',
            'daily_rate' => 1450.00,

            // Section 4: Site Assignment
            'assigned_site_id' => $this->site1->id,

            // Section 5: Emergency Contact
            'emergency_contact_name' => 'Sunita Deshmukh',
            'emergency_contact_relation' => 'Spouse',
            'emergency_contact_phone' => '+91 9823001122',

            // Section 6: Worker Documents
            'documents' => [$photo],
            'document_types' => ['photograph'],
            'document_titles' => ['Passport Size Photo'],
        ]);

        $response->assertRedirect('/workforce');

        $worker = Worker::where('first_name', 'Vikram')->firstOrFail();

        // Existing worker ID behavior preserved (W-EMP-002 since W-EMP-001 exists)
        $this->assertEquals('W-EMP-002', $worker->code);
        $this->assertEquals('Deshmukh', $worker->last_name);
        $this->assertEquals('1991-08-14', $worker->date_of_birth?->format('Y-m-d'));
        $this->assertEquals('male', $worker->gender);
        $this->assertEquals('7812', $worker->aadhaar_last_four);
        $this->assertEquals('+91 9823456789', $worker->phone);
        $this->assertEquals('+91 9765432109', $worker->alternate_phone);
        $this->assertEquals('vikram.deshmukh@example.com', $worker->email);
        $this->assertStringContainsString('Plot 42, Sector 7 Labour Camp', $worker->address);
        $this->assertEquals('Senior Tower Crane Operator', $worker->trade_designation);
        $this->assertEquals('Plant & Heavy Machinery', $worker->department);
        $this->assertEquals('2026-01-15', $worker->joining_date?->format('Y-m-d'));
        $this->assertEquals('active', $worker->status);
        $this->assertEquals(1450.00, (float) $worker->daily_rate);
        $this->assertEquals($this->site1->id, $worker->assigned_site_id);
        $this->assertEquals('Sunita Deshmukh', $worker->emergency_contact_name);
        $this->assertEquals('Spouse', $worker->emergency_contact_relation);
        $this->assertEquals('+91 9823001122', $worker->emergency_contact_phone);
        $this->assertCount(1, $worker->documents);

        // Verify detail page displays all 6 sections and values
        $showPage = $this->actingAs($this->adminA)->get("/workforce/{$worker->id}");
        $showPage->assertStatus(200)
            ->assertSee('Vikram Deshmukh')
            ->assertSee('1991-08-14')
            ->assertSee('Male')
            ->assertSee('XXXX-XXXX-7812')
            ->assertSee('+91 9823456789')
            ->assertSee('+91 9765432109')
            ->assertSee('vikram.deshmukh@example.com')
            ->assertSee('Plot 42, Sector 7 Labour Camp')
            ->assertSee('Plant &amp; Heavy Machinery', false)
            ->assertSee('2026-01-15')
            ->assertSee('Sunita Deshmukh')
            ->assertSee('Spouse')
            ->assertSee('+91 9823001122')
            ->assertSee('Download Photo');
    }

    public function test_edit_and_update_full_worker_profile_preserves_existing_records(): void
    {
        $editPage = $this->actingAs($this->adminA)->get("/workforce/{$this->existingWorker->id}/edit");
        $editPage->assertStatus(200)
            ->assertSee('1. Personal Information')
            ->assertSee('2. Contact Information')
            ->assertSee('3. Employment Information')
            ->assertSee('4. Site Assignment')
            ->assertSee('5. Emergency Contact')
            ->assertSee('Rajesh')
            ->assertSee('4589');

        $updateRes = $this->actingAs($this->adminA)->put("/workforce/{$this->existingWorker->id}", [
            'first_name' => 'Rajesh',
            'last_name' => 'Patil',
            'date_of_birth' => '1986-03-22',
            'gender' => 'male',
            'aadhaar_last_four' => '4589',
            'phone' => '+91 9822112233',
            'alternate_phone' => '+91 9822998877',
            'email' => 'rajesh.patil@apex.test',
            'address' => 'Chakan MIDC Phase 2, Pune',
            'worker_type' => 'subcontractor_worker',
            'subcontractor_id' => $this->subcontractorA->id,
            'trade_designation' => 'Lead Structural Foreman',
            'department' => 'Structural & Masonry',
            'joining_date' => '2025-05-10',
            'status' => 'active',
            'daily_rate' => 1350.00,
            'assigned_site_id' => $this->site2->id,
            'emergency_contact_name' => 'Mahesh Patil',
            'emergency_contact_relation' => 'Brother',
            'emergency_contact_phone' => '+91 9822004455',
        ]);

        $updateRes->assertRedirect('/workforce');

        $this->existingWorker->refresh();
        $this->assertEquals('W-EMP-001', $this->existingWorker->code); // Worker ID unchanged
        $this->assertEquals('1986-03-22', $this->existingWorker->date_of_birth?->format('Y-m-d'));
        $this->assertEquals('male', $this->existingWorker->gender);
        $this->assertEquals('rajesh.patil@apex.test', $this->existingWorker->email);
        $this->assertEquals('Structural & Masonry', $this->existingWorker->department);
        $this->assertEquals('Mahesh Patil', $this->existingWorker->emergency_contact_name);
        $this->assertEquals('Brother', $this->existingWorker->emergency_contact_relation);
        $this->assertEquals('+91 9822004455', $this->existingWorker->emergency_contact_phone);
    }

    public function test_validation_rejects_invalid_profile_inputs_and_full_aadhaar_numbers(): void
    {
        $res = $this->actingAs($this->adminA)->post('/workforce', [
            'worker_type' => 'employee',
            'first_name' => 'Invalid',
            'date_of_birth' => now()->addDays(5)->format('Y-m-d'), // future DOB invalid
            'gender' => 'invalid_gender',
            'aadhaar_last_four' => '123456789012', // full 12-digit Aadhaar rejected
            'phone' => 'invalid-phone-!@#',
            'email' => 'not-an-email',
            'trade_designation' => 'Helper',
            'daily_rate' => -100,
            'emergency_contact_phone' => 'bad-phone',
        ]);

        $res->assertSessionHasErrors([
            'date_of_birth',
            'gender',
            'aadhaar_last_four',
            'phone',
            'email',
            'daily_rate',
            'emergency_contact_phone',
        ]);
    }

    public function test_site_authorization_enforced_when_creating_or_updating_worker_profile(): void
    {
        // Site Manager 1 is assigned ONLY to Site 1, so assigning a worker to Site 2 is forbidden (403)
        $this->actingAs($this->siteManager1)->post('/workforce', [
            'worker_type' => 'direct_labour',
            'first_name' => 'Unauthorized',
            'trade_designation' => 'Mason',
            'assigned_site_id' => $this->site2->id,
            'daily_rate' => 700,
        ])->assertStatus(403);

        $this->actingAs($this->siteManager1)->put("/workforce/{$this->existingWorker->id}", [
            'worker_type' => 'employee',
            'first_name' => 'Rajesh',
            'trade_designation' => 'Site Foreman',
            'assigned_site_id' => $this->site2->id,
            'daily_rate' => 1100,
            'status' => 'active',
        ])->assertStatus(403);
    }

    public function test_worker_profile_sections_localized_in_en_hi_mr(): void
    {
        $this->actingAs($this->adminA)
            ->withSession(['locale' => 'hi'])
            ->get('/workforce/create')
            ->assertStatus(200)
            ->assertSee('1. व्यक्तिगत जानकारी')
            ->assertSee('2. संपर्क जानकारी')
            ->assertSee('3. रोजगार जानकारी')
            ->assertSee('4. साइट आवंटन')
            ->assertSee('5. आपातकालीन संपर्क');

        $this->actingAs($this->adminA)
            ->withSession(['locale' => 'mr'])
            ->get("/workforce/{$this->existingWorker->id}")
            ->assertStatus(200)
            ->assertSee('1. वैयक्तिक माहिती')
            ->assertSee('2. संपर्क माहिती')
            ->assertSee('3. रोजगार माहिती')
            ->assertSee('4. साईट नियुक्ती')
            ->assertSee('5. आपत्कालीन संपर्क');
    }
}

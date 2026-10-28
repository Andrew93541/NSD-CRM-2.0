<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerDocument;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class WorkerDocumentManagementTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected Project $projectA;
    protected Site $site1;
    protected Site $site2;
    protected User $adminA;
    protected User $siteManager1;
    protected User $supervisor1;
    protected User $officeStaff;
    protected User $warehouseMgr;
    protected User $adminB;
    protected Worker $workerSite1;
    protected Worker $workerSite2;
    protected Worker $workerCompanyB;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->companyA = Company::create(['name' => 'NSD Infrastructure Ltd', 'code' => 'NSD', 'is_active' => true]);
        $this->companyB = Company::create(['name' => 'External Builders Ltd', 'code' => 'EXT', 'is_active' => true]);

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
            'permissions' => ['work_orders.view', 'attendance.record', 'attendance.view'],
        ]);

        $roleSupervisor = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['attendance.record', 'attendance.view'],
        ]);

        $roleOffice = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Office Compliance Staff',
            'slug' => 'office_staff',
            'permissions' => ['reports.view', 'attendance.view'],
        ]);

        $roleWarehouse = Role::create([
            'company_id' => $this->companyA->id,
            'name' => 'Warehouse Storekeeper',
            'slug' => 'warehouse_manager',
            'permissions' => ['inventory.manage', 'procurement.view'],
        ]);

        $roleAdminB = Role::create([
            'company_id' => $this->companyB->id,
            'name' => 'Company B Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);

        $this->adminA = $this->makeUser('admin@nsd.local', $this->companyA, $roleAdminA);
        $this->siteManager1 = $this->makeUser('sm1@nsd.local', $this->companyA, $roleSiteMgr);
        $this->supervisor1 = $this->makeUser('sup1@nsd.local', $this->companyA, $roleSupervisor);
        $this->officeStaff = $this->makeUser('office@nsd.local', $this->companyA, $roleOffice);
        $this->warehouseMgr = $this->makeUser('warehouse@nsd.local', $this->companyA, $roleWarehouse);
        $this->adminB = $this->makeUser('admin@ext.local', $this->companyB, $roleAdminB);

        $this->projectA = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'NH-48 Corridor',
            'code' => 'NH48',
            'status' => 'active',
        ]);

        $this->site1 = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Sector 1 Earthworks',
            'code' => 'SITE-S1',
            'status' => 'active',
        ]);
        $this->site1->assignedUsers()->attach([$this->siteManager1->id, $this->supervisor1->id]);

        $this->site2 = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->projectA->id,
            'name' => 'Sector 2 Culverts',
            'code' => 'SITE-S2',
            'status' => 'active',
        ]);

        $this->workerSite1 = Worker::create([
            'company_id' => $this->companyA->id,
            'worker_type' => 'employee',
            'code' => 'W-EMP-001',
            'first_name' => 'Rajesh',
            'last_name' => 'More',
            'trade_designation' => 'Excavator Operator',
            'assigned_site_id' => $this->site1->id,
            'daily_rate' => 950,
            'status' => 'active',
        ]);

        $this->workerSite2 = Worker::create([
            'company_id' => $this->companyA->id,
            'worker_type' => 'direct_labour',
            'code' => 'W-LAB-001',
            'first_name' => 'Mahesh',
            'last_name' => 'Jadhav',
            'trade_designation' => 'Bar Bender',
            'assigned_site_id' => $this->site2->id,
            'daily_rate' => 700,
            'status' => 'active',
        ]);

        TenantContext::setCompanyId($this->companyB->id);
        $this->workerCompanyB = Worker::create([
            'company_id' => $this->companyB->id,
            'worker_type' => 'employee',
            'code' => 'W-EMP-001',
            'first_name' => 'External',
            'last_name' => 'Worker',
            'trade_designation' => 'Surveyor',
            'daily_rate' => 1000,
            'status' => 'active',
        ]);

        TenantContext::setCompanyId($this->companyA->id);
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

    public function test_worker_creation_with_multiple_documents_stores_in_private_storage(): void
    {
        $pdf1 = UploadedFile::fake()->create('aadhaar_card.pdf', 180, 'application/pdf');
        $img2 = UploadedFile::fake()->image('trade_cert.jpg', 400, 400);

        $response = $this->actingAs($this->adminA)->post('/workforce', [
            'worker_type' => 'employee',
            'first_name' => 'Ganesh',
            'last_name' => 'Sawant',
            'phone' => '+91 9822011223',
            'trade_designation' => 'Crane Operator',
            'assigned_site_id' => $this->site1->id,
            'daily_rate' => 1150,
            'documents' => [$pdf1, $img2],
            'document_types' => ['aadhaar_identity', 'qualification_certificate'],
            'document_titles' => ['Verified UIDAI Copy', 'Heavy Crane Cert'],
        ]);

        $response->assertRedirect('/workforce');

        $worker = Worker::where('first_name', 'Ganesh')->firstOrFail();
        $docs = $worker->documents()->orderBy('id')->get();
        $this->assertCount(2, $docs);

        foreach ($docs as $doc) {
            $this->assertEquals($this->companyA->id, $doc->company_id);
            $this->assertEquals($this->adminA->id, $doc->uploaded_by);
            $this->assertEquals('local', $doc->storage_disk);
            $this->assertStringStartsWith("worker-documents/{$this->companyA->id}/{$worker->id}/", $doc->file_path);
            $this->assertStringNotContainsString($doc->original_name, $doc->file_path);
            Storage::disk('local')->assertExists($doc->file_path);
        }
    }

    public function test_direct_upload_download_and_delete_lifecycle_with_storage_cleanup(): void
    {
        $licencePdf = UploadedFile::fake()->create('heavy_vehicle_licence.pdf', 240, 'application/pdf');
        $fitnessDoc = UploadedFile::fake()->create('medical_fitness.pdf', 150, 'application/pdf');

        // 1. Upload multiple documents to existing worker
        $uploadRes = $this->actingAs($this->siteManager1)->post("/workforce/{$this->workerSite1->id}/documents", [
            'documents' => [$licencePdf, $fitnessDoc],
            'document_types' => ['licence', 'medical_fitness'],
            'document_titles' => ['RTO Heavy Licence', 'Annual Medical Check'],
        ]);

        $uploadRes->assertRedirect();
        $this->assertEquals(2, $this->workerSite1->documents()->count());

        $doc = $this->workerSite1->documents()->where('document_type', 'licence')->firstOrFail();
        Storage::disk('local')->assertExists($doc->file_path);

        // 2. Authorized download by Site Manager, Supervisor, and Office Compliance Staff
        foreach ([$this->siteManager1, $this->supervisor1, $this->officeStaff] as $authorizedUser) {
            $dl = $this->actingAs($authorizedUser)->get("/workforce/{$this->workerSite1->id}/documents/{$doc->id}/download");
            $dl->assertStatus(200);
            $dl->assertHeader('X-Content-Type-Options', 'nosniff');
            $dl->assertDownload('heavy_vehicle_licence.pdf');
        }

        // 3. Delete document and verify private disk cleanup
        $storedPath = $doc->file_path;
        $delRes = $this->actingAs($this->siteManager1)->delete("/workforce/{$this->workerSite1->id}/documents/{$doc->id}");
        $delRes->assertRedirect();

        $this->assertDatabaseMissing('worker_documents', ['id' => $doc->id]);
        Storage::disk('local')->assertMissing($storedPath);
    }

    public function test_missing_file_on_disk_returns_404_gracefully(): void
    {
        $pdf = UploadedFile::fake()->create('contract.pdf', 120, 'application/pdf');
        $this->actingAs($this->adminA)->post("/workforce/{$this->workerSite1->id}/documents", [
            'documents' => [$pdf],
            'document_types' => ['employment_contract'],
        ]);

        $doc = $this->workerSite1->documents()->firstOrFail();
        Storage::disk('local')->delete($doc->file_path);

        $this->actingAs($this->adminA)
            ->get("/workforce/{$this->workerSite1->id}/documents/{$doc->id}/download")
            ->assertStatus(404);
    }

    public function test_invalid_executable_double_extension_and_oversized_files_are_rejected(): void
    {
        // 1. Executable PHP script
        $phpFile = UploadedFile::fake()->create('shell.php', 10, 'application/x-php');
        $res1 = $this->actingAs($this->adminA)->post("/workforce/{$this->workerSite1->id}/documents", [
            'documents' => [$phpFile],
            'document_types' => ['other'],
        ]);
        $res1->assertSessionHasErrors();
        $this->assertEquals(0, $this->workerSite1->documents()->count());

        // 2. Double extension containing executable token (payload.php.pdf)
        $doubleExt = UploadedFile::fake()->create('payload.php.pdf', 50, 'application/pdf');
        $res2 = $this->actingAs($this->adminA)->post("/workforce/{$this->workerSite1->id}/documents", [
            'documents' => [$doubleExt],
            'document_types' => ['other'],
        ]);
        $res2->assertSessionHasErrors();
        $this->assertEquals(0, $this->workerSite1->documents()->count());

        // 3. Oversized file (> 5120 KB)
        $oversized = UploadedFile::fake()->create('huge_scan.pdf', 6000, 'application/pdf');
        $res3 = $this->actingAs($this->adminA)->post("/workforce/{$this->workerSite1->id}/documents", [
            'documents' => [$oversized],
            'document_types' => ['aadhaar_identity'],
        ]);
        $res3->assertSessionHasErrors();
        $this->assertEquals(0, $this->workerSite1->documents()->count());
    }

    public function test_authorization_and_site_scoping_for_worker_documents(): void
    {
        $pdf = UploadedFile::fake()->create('site2_worker_id.pdf', 100, 'application/pdf');
        $this->actingAs($this->adminA)->post("/workforce/{$this->workerSite2->id}/documents", [
            'documents' => [$pdf],
            'document_types' => ['aadhaar_identity'],
        ]);
        $docSite2 = $this->workerSite2->documents()->firstOrFail();

        // Site Manager 1 (assigned only to Site 1) cannot view, upload, download, or delete Site 2 worker's documents
        $this->actingAs($this->siteManager1)->get("/workforce/{$this->workerSite2->id}")->assertStatus(403);
        $this->actingAs($this->siteManager1)->get("/workforce/{$this->workerSite2->id}/edit")->assertStatus(403);
        $this->actingAs($this->siteManager1)->post("/workforce/{$this->workerSite2->id}/documents", [
            'documents' => [UploadedFile::fake()->create('unauth.pdf', 50, 'application/pdf')],
            'document_types' => ['other'],
        ])->assertStatus(403);
        $this->actingAs($this->siteManager1)->get("/workforce/{$this->workerSite2->id}/documents/{$docSite2->id}/download")->assertStatus(403);
        $this->actingAs($this->siteManager1)->delete("/workforce/{$this->workerSite2->id}/documents/{$docSite2->id}")->assertStatus(403);

        // Supervisor 1 (assigned to Site 1) can view Site 1 worker, but cannot upload or delete documents
        $this->actingAs($this->supervisor1)->get("/workforce/{$this->workerSite1->id}")->assertStatus(200);
        $this->actingAs($this->supervisor1)->post("/workforce/{$this->workerSite1->id}/documents", [
            'documents' => [UploadedFile::fake()->create('sup_upload.pdf', 50, 'application/pdf')],
            'document_types' => ['other'],
        ])->assertStatus(403);

        // Office Staff can view & download, but cannot upload or delete
        $this->actingAs($this->officeStaff)->get("/workforce/{$this->workerSite2->id}")->assertStatus(200);
        $this->actingAs($this->officeStaff)->get("/workforce/{$this->workerSite2->id}/documents/{$docSite2->id}/download")->assertStatus(200);
        $this->actingAs($this->officeStaff)->post("/workforce/{$this->workerSite2->id}/documents", [
            'documents' => [UploadedFile::fake()->create('office_upload.pdf', 50, 'application/pdf')],
            'document_types' => ['other'],
        ])->assertStatus(403);
        $this->actingAs($this->officeStaff)->delete("/workforce/{$this->workerSite2->id}/documents/{$docSite2->id}")->assertStatus(403);

        // Warehouse Manager cannot view, upload, download, or delete worker documents
        $this->actingAs($this->warehouseMgr)->get("/workforce/{$this->workerSite1->id}")->assertStatus(403);
        $this->actingAs($this->warehouseMgr)->get("/workforce/{$this->workerSite2->id}/documents/{$docSite2->id}/download")->assertStatus(403);

        // Cross-worker document mismatch returns 404
        $this->actingAs($this->adminA)
            ->get("/workforce/{$this->workerSite1->id}/documents/{$docSite2->id}/download")
            ->assertStatus(404);
        $this->actingAs($this->adminA)
            ->delete("/workforce/{$this->workerSite1->id}/documents/{$docSite2->id}")
            ->assertStatus(404);
    }

    public function test_cross_company_tenant_isolation_for_worker_documents(): void
    {
        TenantContext::setCompanyId($this->companyB->id);
        $docB = WorkerDocument::create([
            'company_id' => $this->companyB->id,
            'worker_id' => $this->workerCompanyB->id,
            'document_type' => 'pan_tax_id',
            'original_name' => 'pan_b.pdf',
            'storage_disk' => 'local',
            'file_path' => "worker-documents/{$this->companyB->id}/{$this->workerCompanyB->id}/test.pdf",
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
            'uploaded_by' => $this->adminB->id,
        ]);
        Storage::disk('local')->put($docB->file_path, 'secret-pdf');

        // Switch to Company A Admin
        TenantContext::setCompanyId($this->companyA->id);

        $this->actingAs($this->adminA)->get("/workforce/{$this->workerCompanyB->id}")->assertStatus(404);
        $this->actingAs($this->adminA)->post("/workforce/{$this->workerCompanyB->id}/documents", [
            'documents' => [UploadedFile::fake()->create('test.pdf', 50, 'application/pdf')],
            'document_types' => ['other'],
        ])->assertStatus(404);
        $this->actingAs($this->adminA)->get("/workforce/{$this->workerCompanyB->id}/documents/{$docB->id}/download")->assertStatus(404);
        $this->actingAs($this->adminA)->delete("/workforce/{$this->workerCompanyB->id}/documents/{$docB->id}")->assertStatus(404);
        $this->actingAs($this->adminA)->get("/workforce/{$this->workerSite1->id}/documents/{$docB->id}/download")->assertStatus(404);
    }

    public function test_worker_profile_and_documents_localization_in_en_hi_mr(): void
    {
        $this->actingAs($this->adminA)
            ->withSession(['locale' => 'en'])
            ->get("/workforce/{$this->workerSite1->id}")
            ->assertStatus(200)
            ->assertSee('Worker Documents & Identity Records');

        $this->actingAs($this->adminA)
            ->withSession(['locale' => 'hi'])
            ->get("/workforce/{$this->workerSite1->id}")
            ->assertStatus(200)
            ->assertSee('कामगार दस्तावेज़ और पहचान रिकॉर्ड');

        $this->actingAs($this->adminA)
            ->withSession(['locale' => 'mr'])
            ->get("/workforce/{$this->workerSite1->id}")
            ->assertStatus(200)
            ->assertSee('कामगार कागदपत्रे आणि ओळख नोंदी');
    }

    public function test_crafted_keys_spoofed_mime_and_tampered_storage_paths_are_blocked(): void
    {
        // 1. Crafted associative array keys where document_types key does not match documents key
        $validPdf = UploadedFile::fake()->create('valid.pdf', 50, 'application/pdf');
        $resKeyMismatch = $this->actingAs($this->adminA)->post("/workforce/{$this->workerSite1->id}/documents", [
            'documents' => ['custom_key' => $validPdf],
            'document_types' => [0 => 'aadhaar_identity'],
        ]);
        $resKeyMismatch->assertSessionHasErrors('document_types.custom_key');
        $this->assertEquals(0, $this->workerSite1->documents()->count());

        // 2. Spoofed MIME content (PHP script content disguised with a .pdf filename)
        $spoofedPdf = UploadedFile::fake()->createWithContent('disguised.pdf', "<?php echo 'pwned'; ?>");
        $resSpoofed = $this->actingAs($this->adminA)->post("/workforce/{$this->workerSite1->id}/documents", [
            'documents' => [$spoofedPdf],
            'document_types' => ['aadhaar_identity'],
        ]);
        $resSpoofed->assertSessionHasErrors();
        $this->assertEquals(0, $this->workerSite1->documents()->count());

        // 3. Failed upload during worker creation rolls back the worker creation transaction
        $workerCountBefore = Worker::count();
        $resRollback = $this->actingAs($this->adminA)->post('/workforce', [
            'first_name' => 'Rollback',
            'last_name' => 'Candidate',
            'worker_type' => 'daily_labour',
            'assigned_site_id' => $this->site1->id,
            'daily_rate' => 600,
            'status' => 'active',
            'documents' => [$spoofedPdf],
            'document_types' => ['aadhaar_identity'],
        ]);
        $resRollback->assertSessionHasErrors();
        $this->assertEquals($workerCountBefore, Worker::count());

        // 4. Tampered storage path outside strict worker-documents/{company_id}/{worker_id}/{uuid}.{ext} is blocked with 403
        Storage::disk('local')->put('outside-secret.pdf', 'top-secret');
        $tamperedDoc = WorkerDocument::create([
            'company_id' => $this->companyA->id,
            'worker_id' => $this->workerSite1->id,
            'document_type' => 'other',
            'original_name' => 'outside-secret.pdf',
            'storage_disk' => 'local',
            'file_path' => 'outside-secret.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 10,
            'uploaded_by' => $this->adminA->id,
        ]);

        $this->actingAs($this->adminA)
            ->get("/workforce/{$this->workerSite1->id}/documents/{$tamperedDoc->id}/download")
            ->assertStatus(403);

        $this->actingAs($this->adminA)
            ->delete("/workforce/{$this->workerSite1->id}/documents/{$tamperedDoc->id}")
            ->assertStatus(403);

        // Ensure the arbitrary file outside the confined directory was NOT deleted
        Storage::disk('local')->assertExists('outside-secret.pdf');
    }
}


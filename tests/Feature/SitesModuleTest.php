<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\Project;
use App\Models\PurchaseRequest;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SitesModuleTest extends TestCase
{
    use RefreshDatabase;

    protected Company $companyA;
    protected Company $companyB;
    protected Project $project1;
    protected Project $project2;
    protected Site $site1;
    protected Site $site2;
    protected User $adminA;
    protected User $seniorManagerA;
    protected User $siteManagerA;
    protected User $supervisor1A;
    protected User $officeStaffA;

    protected function setUp(): void
    {
        parent::setUp();

        $this->companyA = Company::create(['name' => 'NSD Infrastructure Ltd', 'code' => 'NSD', 'is_active' => true]);
        $this->companyB = Company::create(['name' => 'Other Infra Corp', 'code' => 'OTH', 'is_active' => true]);

        TenantContext::setCompanyId($this->companyA->id);

        $roles = [
            'company_admin' => Role::create(['company_id' => $this->companyA->id, 'name' => 'Admin', 'slug' => 'company_admin', 'permissions' => ['*']]),
            'senior_manager' => Role::create(['company_id' => $this->companyA->id, 'name' => 'Senior VP', 'slug' => 'senior_manager', 'permissions' => ['work_orders.view', 'reports.view']]),
            'site_manager' => Role::create(['company_id' => $this->companyA->id, 'name' => 'Site Manager', 'slug' => 'site_manager', 'permissions' => ['work_orders.view', 'attendance.record']]),
            'site_supervisor' => Role::create(['company_id' => $this->companyA->id, 'name' => 'Supervisor', 'slug' => 'site_supervisor', 'permissions' => ['attendance.record', 'attendance.view']]),
            'office_staff' => Role::create(['company_id' => $this->companyA->id, 'name' => 'Office Staff', 'slug' => 'office_staff', 'permissions' => ['reports.view']]),
        ];

        $this->adminA = $this->createUser('admin@nsd.local', 'Admin User', $roles['company_admin']);
        $this->seniorManagerA = $this->createUser('vp@nsd.local', 'Vikram VP', $roles['senior_manager']);
        $this->siteManagerA = $this->createUser('sm@nsd.local', 'Arun Site Manager', $roles['site_manager']);
        $this->supervisor1A = $this->createUser('sup1@nsd.local', 'Ramesh Supervisor', $roles['site_supervisor']);
        $this->officeStaffA = $this->createUser('office@nsd.local', 'Pooja Office', $roles['office_staff']);

        $this->project1 = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'NH-48 Highway Expansion',
            'code' => 'NH-48',
            'client_name' => 'NHAI',
            'manager_id' => $this->siteManagerA->id,
            'status' => 'active',
        ]);

        $this->project2 = Project::create([
            'company_id' => $this->companyA->id,
            'name' => 'Metro Package 3 Viaduct',
            'code' => 'MTR-P3',
            'client_name' => 'MRDC',
            'manager_id' => $this->siteManagerA->id,
            'status' => 'active',
        ]);

        $this->site1 = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project1->id,
            'code' => 'SITE-NH48-1',
            'name' => 'NH-48 Sector 1 Earthworks',
            'location' => 'Sector 1 km 24-32 Corridor',
            'address' => 'Plot 12, Highway Camp',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'postal_code' => '411045',
            'latitude' => 18.5204300,
            'longitude' => 73.8567430,
            'description' => 'Primary earthmoving and culvert package.',
            'status' => 'active',
            'supervisor_id' => $this->supervisor1A->id,
        ]);

        $this->site2 = Site::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project2->id,
            'code' => 'SITE-MTR-04',
            'name' => 'Metro Station 4 Viaduct Yard',
            'location' => 'Urban Terminal Station 4 Hub',
            'address' => 'Station 4 East Enclosure',
            'city' => 'Mumbai',
            'state' => 'Maharashtra',
            'postal_code' => '400001',
            'status' => 'paused',
        ]);

        // Assign supervisor1A and siteManagerA only to site1
        $this->site1->assignedUsers()->attach([$this->supervisor1A->id, $this->siteManagerA->id]);
    }

    protected function createUser(string $email, string $name, Role $role): User
    {
        $user = User::create([
            'name' => $name,
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

    public function test_guest_is_redirected_from_all_site_routes(): void
    {
        $this->get('/sites')->assertRedirect('/login');
        $this->get('/sites/create')->assertRedirect('/login');
        $this->post('/sites', [])->assertRedirect('/login');
        $this->get("/sites/{$this->site1->id}")->assertRedirect('/login');
        $this->get("/sites/{$this->site1->id}/edit")->assertRedirect('/login');
        $this->put("/sites/{$this->site1->id}", [])->assertRedirect('/login');
    }

    public function test_site_directory_lists_searches_filters_and_paginates_sites(): void
    {
        // 1. Admin sees both sites
        $response = $this->actingAs($this->adminA)->get('/sites');
        $response->assertOk();
        $response->assertSee('SITE-NH48-1');
        $response->assertSee('NH-48 Sector 1 Earthworks');
        $response->assertSee('SITE-MTR-04');
        $response->assertSee('Metro Station 4 Viaduct Yard');
        $response->assertSee('hidden sm:block', false);
        $response->assertSee('sm:hidden', false);

        // 2. Search by code
        $searchCode = $this->actingAs($this->adminA)->get('/sites?search=SITE-NH48-1');
        $searchCode->assertOk();
        $searchCode->assertSee('NH-48 Sector 1 Earthworks');
        $searchCode->assertDontSee('Metro Station 4 Viaduct Yard');

        // 3. Search by city / address
        $searchAddress = $this->actingAs($this->adminA)->get('/sites?search=Mumbai');
        $searchAddress->assertOk();
        $searchAddress->assertSee('Metro Station 4 Viaduct Yard');
        $searchAddress->assertDontSee('NH-48 Sector 1 Earthworks');

        // 4. Filter by status
        $filterPaused = $this->actingAs($this->adminA)->get('/sites?status=paused');
        $filterPaused->assertOk();
        $filterPaused->assertSee('SITE-MTR-04');
        $filterPaused->assertDontSee('SITE-NH48-1');

        // 5. Filter by project_id
        $filterProject = $this->actingAs($this->adminA)->get('/sites?project_id=' . $this->project1->id);
        $filterProject->assertOk();
        $filterProject->assertSee('SITE-NH48-1');
        $filterProject->assertDontSee('SITE-MTR-04');

        // 6. Search with no results shows search-no-results state
        $noMatch = $this->actingAs($this->adminA)->get('/sites?search=NONEXISTENT-XYZ-999');
        $noMatch->assertOk();
        $noMatch->assertSee('No sites match your search or filter criteria.');

        // 7. Pagination works when more than 12 sites exist
        for ($i = 1; $i <= 14; $i++) {
            Site::create([
                'company_id' => $this->companyA->id,
                'project_id' => $this->project1->id,
                'code' => sprintf('SITE-PG-%02d', $i),
                'name' => "Paginated Highway Site {$i}",
                'status' => 'active',
            ]);
        }

        $page1 = $this->actingAs($this->adminA)->get('/sites');
        $page1->assertOk();
        $page2 = $this->actingAs($this->adminA)->get('/sites?page=2');
        $page2->assertOk();
        $page2->assertSee('SITE-PG-');
    }

    public function test_site_detail_page_displays_real_related_entities_and_map_link_when_coordinates_exist(): void
    {
        $worker = Worker::create([
            'company_id' => $this->companyA->id,
            'worker_type' => 'employee',
            'code' => 'W-EMP-101',
            'first_name' => 'Ganesh',
            'last_name' => 'Jadhav',
            'trade_designation' => 'Excavator Operator',
            'assigned_site_id' => $this->site1->id,
            'daily_rate' => 1100,
        ]);

        DailyAttendance::create([
            'company_id' => $this->companyA->id,
            'site_id' => $this->site1->id,
            'project_id' => $this->project1->id,
            'worker_id' => $worker->id,
            'attendance_date' => Carbon::today()->format('Y-m-d'),
            'clock_in' => '08:00:00',
            'clock_out' => '17:00:00',
            'worked_hours' => 9.00,
            'status' => 'present',
        ]);

        $wo = WorkOrder::create([
            'company_id' => $this->companyA->id,
            'project_id' => $this->project1->id,
            'site_id' => $this->site1->id,
            'order_number' => 'WO-NSD-501',
            'title' => 'Box Culvert Excavation',
            'estimated_amount' => 320000,
            'status' => 'submitted',
            'created_by' => $this->siteManagerA->id,
        ]);

        $wh = Warehouse::create([
            'company_id' => $this->companyA->id,
            'site_id' => $this->site1->id,
            'name' => 'Sector 1 Yard Store',
            'code' => 'WH-S1',
            'location_type' => 'site_store',
            'is_active' => true,
        ]);

        $pr = PurchaseRequest::create([
            'company_id' => $this->companyA->id,
            'site_id' => $this->site1->id,
            'warehouse_id' => $wh->id,
            'request_number' => 'PR-NSD-701',
            'requested_by' => $this->siteManagerA->id,
            'urgency' => 'urgent',
            'status' => 'pending',
        ]);

        // Site 1 has valid coordinates
        $res1 = $this->actingAs($this->adminA)->get("/sites/{$this->site1->id}");
        $res1->assertOk();
        $res1->assertSee('SITE-NH48-1');
        $res1->assertSee('NH-48 Sector 1 Earthworks');
        $res1->assertSee('Plot 12, Highway Camp');
        $res1->assertSee('Pune');
        $res1->assertSee('411045');
        $res1->assertSee('18.520430, 73.856743');
        $res1->assertSee('https://www.openstreetmap.org/?mlat=18.520430&mlon=73.856743');
        $res1->assertSee('Ganesh Jadhav');
        $res1->assertSee('WO-NSD-501');
        $res1->assertSee('Box Culvert Excavation');
        $res1->assertSee('Sector 1 Yard Store');
        $res1->assertSee('PR-NSD-701');

        // Site 2 has no coordinates -> must show "Coordinates not recorded" and no map link
        $res2 = $this->actingAs($this->adminA)->get("/sites/{$this->site2->id}");
        $res2->assertOk();
        $res2->assertSee('Coordinates not recorded');
        $res2->assertDontSee('https://www.openstreetmap.org/?mlat=');
    }

    public function test_site_create_and_update_with_valid_and_invalid_inputs(): void
    {
        // 1. Valid site creation by Admin
        $createRes = $this->actingAs($this->adminA)->post('/sites', [
            'project_id' => $this->project1->id,
            'code' => 'SITE-NH48-3',
            'name' => 'NH-48 Sector 3 Flyover',
            'status' => 'active',
            'supervisor_id' => $this->supervisor1A->id,
            'location' => 'Sector 3 km 45-60',
            'address' => 'Camp 3, Highway Toll Plaza',
            'city' => 'Satara',
            'state' => 'Maharashtra',
            'postal_code' => '415001',
            'latitude' => '17.680460',
            'longitude' => '74.018260',
            'description' => 'Six-lane elevated flyover works.',
        ]);

        $newSite = Site::where('code', 'SITE-NH48-3')->first();
        $this->assertNotNull($newSite);
        $createRes->assertRedirect(route('sites.show', $newSite));
        $this->assertEquals('Satara', $newSite->city);
        $this->assertTrue($newSite->hasValidCoordinates());
        $this->assertTrue($this->supervisor1A->isAssignedToSite($newSite));

        // 2. Duplicate code within same company is rejected
        $dupRes = $this->actingAs($this->adminA)->from('/sites/create')->post('/sites', [
            'project_id' => $this->project1->id,
            'code' => 'SITE-NH48-3',
            'name' => 'Duplicate Code Site',
            'status' => 'active',
        ]);
        $dupRes->assertRedirect('/sites/create');
        $dupRes->assertSessionHasErrors(['code']);

        // 3. Invalid coordinates (out of range or partial) are rejected
        $invalidCoordRes = $this->actingAs($this->adminA)->from('/sites/create')->post('/sites', [
            'project_id' => $this->project1->id,
            'code' => 'SITE-BAD-COORD',
            'name' => 'Invalid Coordinate Site',
            'status' => 'active',
            'latitude' => '95.12345', // > 90
            'longitude' => '195.0000', // > 180
        ]);
        $invalidCoordRes->assertSessionHasErrors(['latitude', 'longitude']);

        $partialCoordRes = $this->actingAs($this->adminA)->from('/sites/create')->post('/sites', [
            'project_id' => $this->project1->id,
            'code' => 'SITE-PARTIAL-COORD',
            'name' => 'Partial Coordinate Site',
            'status' => 'active',
            'latitude' => '18.5000',
            'longitude' => '', // missing longitude when latitude is provided
        ]);
        $partialCoordRes->assertSessionHasErrors(['longitude']);

        // 4. Valid update by assigned Site Manager on site1
        $updateRes = $this->actingAs($this->siteManagerA)->put("/sites/{$this->site1->id}", [
            'project_id' => $this->project1->id,
            'code' => 'SITE-NH48-1',
            'name' => 'NH-48 Sector 1 Earthworks (Updated)',
            'status' => 'active',
            'supervisor_id' => $this->supervisor1A->id,
            'location' => 'Sector 1 km 24-32 Corridor',
            'address' => 'Updated Plot 14 Camp',
            'city' => 'Pune',
            'state' => 'Maharashtra',
            'postal_code' => '411045',
            'latitude' => '18.530000',
            'longitude' => '73.860000',
            'description' => 'Updated operational scope.',
        ]);

        $updateRes->assertRedirect(route('sites.show', $this->site1));
        $this->site1->refresh();
        $this->assertEquals('NH-48 Sector 1 Earthworks (Updated)', $this->site1->name);
        $this->assertEquals('Updated Plot 14 Camp', $this->site1->address);
    }

    public function test_server_side_site_visibility_and_role_authorization_boundaries(): void
    {
        // 1. Supervisor 1 is assigned to site1 ONLY, not site2
        $dirRes = $this->actingAs($this->supervisor1A)->get('/sites');
        $dirRes->assertOk();
        $dirRes->assertSee('SITE-NH48-1');
        $dirRes->assertDontSee('SITE-MTR-04'); // Unassigned site must not leak in directory

        // 2. Supervisor 1 can view site1 detail page, but gets 403 Forbidden on direct URL to site2
        $this->actingAs($this->supervisor1A)->get("/sites/{$this->site1->id}")->assertOk();
        $this->actingAs($this->supervisor1A)->get("/sites/{$this->site2->id}")->assertForbidden();

        // 3. Supervisor 1 and Office Staff cannot create or edit sites
        $this->actingAs($this->supervisor1A)->get('/sites/create')->assertForbidden();
        $this->actingAs($this->supervisor1A)->post('/sites', [])->assertForbidden();
        $this->actingAs($this->supervisor1A)->get("/sites/{$this->site1->id}/edit")->assertForbidden();
        $this->actingAs($this->supervisor1A)->put("/sites/{$this->site1->id}", [])->assertForbidden();

        $this->actingAs($this->officeStaffA)->get('/sites/create')->assertForbidden();
        $this->actingAs($this->officeStaffA)->get("/sites/{$this->site1->id}")->assertForbidden();

        // 4. Site Manager cannot edit unassigned site2 or create a new site
        $this->actingAs($this->siteManagerA)->get('/sites/create')->assertForbidden();
        $this->actingAs($this->siteManagerA)->get("/sites/{$this->site2->id}/edit")->assertForbidden();
        $this->actingAs($this->siteManagerA)->put("/sites/{$this->site2->id}", [])->assertForbidden();

        // 5. Cross-company isolation: Company B site returns 404 Not Found for Company A Admin
        TenantContext::setCompanyId($this->companyB->id);
        $projB = Project::create([
            'company_id' => $this->companyB->id,
            'name' => 'Secret Project B',
            'code' => 'SP-B',
            'status' => 'active',
        ]);
        $siteB = Site::create([
            'company_id' => $this->companyB->id,
            'project_id' => $projB->id,
            'code' => 'SITE-B-99',
            'name' => 'Secret Site B',
            'status' => 'active',
        ]);

        TenantContext::setCompanyId($this->companyA->id);
        $this->actingAs($this->adminA)->get("/sites/{$siteB->id}")->assertNotFound();
        $this->actingAs($this->adminA)->get("/sites/{$siteB->id}/edit")->assertNotFound();
        $this->actingAs($this->adminA)->put("/sites/{$siteB->id}", [])->assertNotFound();
    }

    public function test_sites_module_supports_light_dark_theme_and_hindi_marathi_localization(): void
    {
        // Light theme + Hindi
        $hiRes = $this->actingAs($this->adminA)
            ->withSession(['theme' => 'light', 'locale' => 'hi'])
            ->get('/sites');

        $hiRes->assertOk();
        $hiRes->assertViewHas('currentTheme', 'light');
        $hiRes->assertSee('निर्माण साइट्स');
        $hiRes->assertSee('नई साइट जोड़ें');

        // Dark theme + Marathi on detail view
        $mrRes = $this->actingAs($this->adminA)
            ->withSession(['theme' => 'dark', 'locale' => 'mr'])
            ->get("/sites/{$this->site1->id}");

        $mrRes->assertOk();
        $mrRes->assertViewHas('currentTheme', 'dark');
        $mrRes->assertSee('साईट ठिकाण व पत्ता');
        $mrRes->assertSee('नेमलेले कामगार');
    }
}

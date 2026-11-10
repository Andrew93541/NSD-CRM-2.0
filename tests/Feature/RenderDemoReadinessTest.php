<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Project;
use App\Models\RaBill;
use App\Models\Site;
use App\Models\SiteNote;
use App\Models\Task;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkOrder;
use Database\Seeders\RenderDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RenderDemoReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_healthz_endpoint_returns_minimal_ok_payload_without_sensitive_metadata(): void
    {
        $response = $this->getJson('/healthz');

        $response->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_render_demo_seeder_is_idempotent_and_populates_synthetic_records_with_env_credentials(): void
    {
        putenv('DEMO_COMPANY_CODE=DEMO-NSD');
        putenv('DEMO_ADMIN_EMAIL=demo.admin@nsd-demo.example');
        putenv('DEMO_ADMIN_PASSWORD=Str0ng!DemoAdminPass2026');
        putenv('DEMO_MANAGER_PASSWORD=Str0ng!DemoMgrPass2026');
        putenv('DEMO_STAFF_PASSWORD=Str0ng!DemoStaffPass2026');

        try {
            $this->artisan('nsd:seed-demo')->assertExitCode(0);

            $company = Company::where('code', 'DEMO-NSD')->firstOrFail();
            $this->assertSame('NSD Demo Infrastructure Pvt Ltd', $company->name);

            $admin = User::where('email', 'demo.admin@nsd-demo.example')->firstOrFail();
            $this->assertTrue(Hash::check('Str0ng!DemoAdminPass2026', $admin->password));
            $this->assertFalse((bool) $admin->two_factor_enabled);

            $this->assertSame(5, User::count());
            $this->assertSame(2, Project::where('company_id', $company->id)->count());
            $this->assertSame(2, Site::where('company_id', $company->id)->count());
            $this->assertSame(6, Worker::where('company_id', $company->id)->count());
            $this->assertSame(1, WorkOrder::where('company_id', $company->id)->count());
            $this->assertSame(1, RaBill::where('company_id', $company->id)->where('invoice_number', 'CIVIL/01/26-27')->count());
            $this->assertSame(5, Task::where('company_id', $company->id)->count());
            $this->assertSame(2, SiteNote::where('company_id', $company->id)->count());

            // Simulate user changing their password after initial seed
            $admin->update(['password' => Hash::make('UserChangedPassword!999')]);

            // Run a second time (even with DEMO_ADMIN_PASSWORD still set in env) to verify idempotency and password preservation
            (new RenderDemoSeeder())->run();

            $admin->refresh();
            $this->assertTrue(Hash::check('UserChangedPassword!999', $admin->password));
            $this->assertFalse(Hash::check('Str0ng!DemoAdminPass2026', $admin->password));

            $this->assertSame(1, Company::where('code', 'DEMO-NSD')->count());
            $this->assertSame(5, User::count());
            $this->assertSame(2, Project::where('company_id', $company->id)->count());
            $this->assertSame(2, Site::where('company_id', $company->id)->count());
            $this->assertSame(6, Worker::where('company_id', $company->id)->count());
            $this->assertSame(1, WorkOrder::where('company_id', $company->id)->count());
            $this->assertSame(1, RaBill::where('company_id', $company->id)->count());
            $this->assertSame(5, Task::where('company_id', $company->id)->count());
            $this->assertSame(2, SiteNote::where('company_id', $company->id)->count());
        } finally {
            putenv('DEMO_COMPANY_CODE');
            putenv('DEMO_ADMIN_EMAIL');
            putenv('DEMO_ADMIN_PASSWORD');
            putenv('DEMO_MANAGER_PASSWORD');
            putenv('DEMO_STAFF_PASSWORD');
        }
    }

    public function test_render_deployment_artifacts_exist_and_enforce_safety_guards(): void
    {
        $dockerfile = file_get_contents(base_path('Dockerfile'));
        $entrypoint = file_get_contents(base_path('docker/render-entrypoint.sh'));
        $renderYaml = file_get_contents(base_path('render.yaml'));
        $envExample = file_get_contents(base_path('.env.render.example'));

        $this->assertNotFalse($dockerfile);
        $this->assertStringContainsString('APACHE_DOCUMENT_ROOT=/var/www/html/public', $dockerfile);
        $this->assertStringContainsString('--no-dev', $dockerfile);
        $this->assertStringContainsString('pdo_mysql', $dockerfile);

        $this->assertNotFalse($entrypoint);
        $this->assertStringContainsString('LISTEN_PORT="${PORT:-10000}"', $entrypoint);
        $this->assertStringContainsString('if [[ "${DB_DATABASE:-}" == "nsd_crm_2" ]]', $entrypoint);
        $this->assertStringContainsString('php artisan config:cache', $entrypoint);

        $this->assertNotFalse($renderYaml);
        $this->assertStringContainsString('healthCheckPath: /healthz', $renderYaml);
        $this->assertStringContainsString('value: nsd_crm_demo', $renderYaml);
        $this->assertStringContainsString('MYSQL_ATTR_SSL_CA', $renderYaml);

        $this->assertNotFalse($envExample);
        $this->assertStringContainsString('DB_DATABASE=nsd_crm_demo', $envExample);
        $this->assertStringContainsString('DEMO_ADMIN_PASSWORD=', $envExample);
    }
}


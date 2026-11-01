<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class CpanelXamppCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_direct_requests_to_sensitive_files_and_directories_are_blocked_with_403(): void
    {
        $protectedPaths = [
            '/.env',
            '/.env.example',
            '/.git/config',
            '/.htaccess',
            '/composer.json',
            '/composer.lock',
            '/package.json',
            '/artisan',
            '/phpunit.xml',
            '/vite.config.js',
            '/app/Models/User.php',
            '/bootstrap/app.php',
            '/config/database.php',
            '/database/sql/nsd_crm_2_cpanel_xampp.sql',
            '/database/database.sqlite',
            '/storage/logs/laravel.log',
            '/storage/app/private/worker-documents/aadhaar.pdf',
            '/storage/app/private/work-order-attachments/contract.pdf',
            '/tests/TestCase.php',
            '/vendor/autoload.php',
        ];

        foreach ($protectedPaths as $path) {
            $response = $this->get($path);
            $this->assertEquals(
                403,
                $response->getStatusCode(),
                "Expected 403 Forbidden for protected path [{$path}], got [{$response->getStatusCode()}]"
            );
        }
    }

    public function test_apache_htaccess_and_root_front_controller_files_exist_and_contain_hardening_rules(): void
    {
        $rootHtaccess = base_path('.htaccess');
        $publicHtaccess = public_path('.htaccess');
        $storageHtaccess = storage_path('.htaccess');
        $databaseHtaccess = database_path('.htaccess');
        $rootIndex = base_path('index.php');

        $this->assertFileExists($rootHtaccess);
        $this->assertFileExists($publicHtaccess);
        $this->assertFileExists($storageHtaccess);
        $this->assertFileExists($databaseHtaccess);
        $this->assertFileExists($rootIndex);

        $rootHtaccessContent = file_get_contents($rootHtaccess);
        $this->assertStringContainsString('Options -MultiViews -Indexes', $rootHtaccessContent);
        $this->assertStringContainsString('Require all denied', $rootHtaccessContent);
        $this->assertStringContainsString('app|bootstrap|config|database|docs|lang|node_modules|resources|routes|storage|tests|vendor', $rootHtaccessContent);

        $privateDirs = [
            storage_path('app/private/worker-documents'),
            storage_path('app/private/work-order-attachments'),
            storage_path('app/private/project-documents'),
        ];

        foreach ($privateDirs as $dir) {
            $this->assertDirectoryExists($dir);
        }
    }

    public function test_prebuilt_vite_assets_exist_and_use_subdirectory_safe_relative_font_urls(): void
    {
        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath);

        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);
        $this->assertArrayHasKey('resources/css/app.css', $manifest);
        $this->assertArrayHasKey('resources/js/app.js', $manifest);

        foreach ($manifest as $entry) {
            if (!empty($entry['file'])) {
                $this->assertFileExists(public_path('build/' . $entry['file']));
            }
        }

        foreach (glob(public_path('build/assets/*.css')) as $cssFile) {
            $cssContent = file_get_contents($cssFile);
            $this->assertStringNotContainsString(
                'url("/build/assets/',
                $cssContent,
                "CSS file [" . basename($cssFile) . "] must not contain root-hardcoded /build/assets/ font URLs."
            );
        }
    }

    public function test_subdirectory_deployment_routing_and_authentication_work_under_xampp_path(): void
    {
        $company = Company::create(['name' => 'NSD Test Co', 'code' => 'NSD', 'is_active' => true]);
        $role = Role::create(['company_id' => $company->id, 'name' => 'Admin', 'slug' => 'company_admin', 'permissions' => ['*']]);
        $user = User::create([
            'name' => 'Subdir Admin',
            'email' => 'subdir@nsd.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->companies()->attach($company->id, ['role_id' => $role->id, 'status' => 'active', 'is_primary' => true]);

        $serverVars = [
            'SCRIPT_NAME' => '/NSD-CRM-2.0/index.php',
            'SCRIPT_FILENAME' => base_path('index.php'),
            'PHP_SELF' => '/NSD-CRM-2.0/index.php',
        ];

        // Unauthenticated request to /NSD-CRM-2.0/dashboard redirects to subdirectory /NSD-CRM-2.0/login
        $guestResp = $this->withServerVariables($serverVars)
            ->get('http://localhost/NSD-CRM-2.0/dashboard');
        $guestResp->assertRedirect('http://localhost/NSD-CRM-2.0/login');

        // Login page renders subdirectory form action URLs
        $loginPage = $this->withServerVariables($serverVars)
            ->get('http://localhost/NSD-CRM-2.0/login');
        $loginPage->assertStatus(200);
        $loginPage->assertSee('http://localhost/NSD-CRM-2.0/login', false);

        // Submitting login redirects to subdirectory /NSD-CRM-2.0/dashboard
        $loginPost = $this->withServerVariables($serverVars)
            ->post('http://localhost/NSD-CRM-2.0/login', [
                'email' => 'subdir@nsd.local',
                'password' => 'password123',
            ]);
        $loginPost->assertRedirect('http://localhost/NSD-CRM-2.0/dashboard');
    }

    public function test_sql_deployment_package_has_all_61_tables_and_mariadb_compatible_collation(): void
    {
        $sqlPath = database_path('sql/nsd_crm_2_cpanel_xampp.sql');
        $this->assertFileExists($sqlPath);

        $sql = file_get_contents($sqlPath);
        $this->assertStringContainsString('utf8mb4_unicode_ci', $sql);
        $this->assertStringNotContainsString('utf8mb4_0900_ai_ci', $sql);
        $this->assertStringNotContainsString('@@GLOBAL.GTID_PURGED', $sql);

        preg_match_all('/CREATE TABLE `[a-z0-9_]+`/i', $sql, $matches);
        $this->assertCount(61, $matches[0], 'Expected exactly 61 CREATE TABLE definitions in SQL deployment package.');
    }
}

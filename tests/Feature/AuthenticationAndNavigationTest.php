<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationAndNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard');
        $response->assertRedirect('/login');
    }

    public function test_user_can_view_login_page(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertSee('Earth Movers');
    }

    public function test_user_can_authenticate_with_valid_credentials(): void
    {
        $company = Company::create(['name' => 'NSD Test Co', 'code' => 'NSD', 'is_active' => true]);
        $role = Role::create(['company_id' => $company->id, 'name' => 'Admin', 'slug' => 'company_admin', 'permissions' => ['*']]);

        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@nsd.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->companies()->attach($company->id, ['role_id' => $role->id]);

        $response = $this->post('/login', [
            'email' => 'john@nsd.local',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_password_fails_authentication(): void
    {
        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@nsd.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);

        $response = $this->from('/login')->post('/login', [
            'email' => 'jane@nsd.local',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        $company = Company::create(['name' => 'NSD Test Co', 'code' => 'NSD', 'is_active' => true]);
        $user = User::create([
            'name' => 'Inactive User',
            'email' => 'inactive@nsd.local',
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $response = $this->post('/login', [
            'email' => 'inactive@nsd.local',
            'password' => 'password123',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_demo_login_shortcuts_are_hidden_in_production(): void
    {
        // Simulate production environment
        config(['app.env' => 'production']);

        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertDontSee('Quick Demo Credentials');
        $response->assertDontSee('admin@nsd.local');
        $response->assertDontSee('senior.manager@nsd.local');
    }

    public function test_database_seeder_does_not_seed_demo_records_in_production(): void
    {
        // Simulate production environment
        config(['app.env' => 'production']);

        $this->assertEquals(0, User::count());
        $this->assertEquals(0, Company::count());

        $seeder = new \Database\Seeders\DatabaseSeeder();
        $seeder->run();

        // Must remain 0 because production guard aborts seeding demo data
        $this->assertEquals(0, User::count());
        $this->assertEquals(0, Company::count());
    }
}

<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PhaseFManagerTwoFactorAndLockoutTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private Role $adminRole;
    private Role $siteManagerRole;
    private Role $supervisorRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Construction Ltd',
            'code' => 'NSD',
            'is_active' => true,
        ]);

        $this->adminRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Company Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);

        $this->siteManagerRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'permissions' => ['work_orders.view', 'work_orders.create'],
        ]);

        $this->supervisorRole = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Site Supervisor',
            'slug' => 'site_supervisor',
            'permissions' => ['attendance.view'],
        ]);
    }

    public function test_manager_with_2fa_enabled_must_complete_otp_challenge_before_accessing_dashboard(): void
    {
        $manager = User::create([
            'name' => 'Rajesh Site Manager',
            'email' => 'rajesh.manager@nsd.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'two_factor_enabled' => true,
        ]);
        $manager->companies()->attach($this->company->id, [
            'role_id' => $this->siteManagerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Step 1: Login with valid credentials redirects to /two-factor-challenge
        $loginResponse = $this->post('/login', [
            'email' => 'rajesh.manager@nsd.local',
            'password' => 'password123',
        ]);

        $loginResponse->assertRedirect(route('two-factor.challenge'));
        $this->assertAuthenticatedAs($manager);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auth.2fa_challenge_issued',
            'user_id' => $manager->id,
        ]);

        // Step 2: Attempting to bypass /two-factor-challenge and visit /dashboard redirects back to challenge
        $bypassResponse = $this->get('/dashboard');
        $bypassResponse->assertRedirect(route('two-factor.challenge'));

        // Step 3: Challenge screen renders properly
        $challengeScreen = $this->get('/two-factor-challenge');
        $challengeScreen->assertStatus(200);
        $challengeScreen->assertSee('Manager Security Verification');

        // Step 4: Submitting an invalid 6-digit code fails and logs auth.2fa_failed
        $invalidResponse = $this->post('/two-factor-challenge', [
            'code' => '000000',
        ]);
        $invalidResponse->assertSessionHasErrors('code');
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auth.2fa_failed',
            'user_id' => $manager->id,
        ]);

        // Step 5: Issue a known fresh code and verify it succeeds
        $freshCode = $manager->fresh()->issueTwoFactorCode();
        $verifyResponse = $this->post('/two-factor-challenge', [
            'code' => $freshCode,
        ]);

        $verifyResponse->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auth.2fa_verified',
            'user_id' => $manager->id,
        ]);

        // Step 6: Now /dashboard is accessible
        $dashboardResponse = $this->get('/dashboard');
        $dashboardResponse->assertStatus(200);
    }

    public function test_enforced_manager_2fa_config_challenges_manager_roles_but_not_supervisors(): void
    {
        config(['auth.enforce_manager_2fa' => true]);

        $manager = User::create([
            'name' => 'Senior Manager',
            'email' => 'senior@nsd.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'two_factor_enabled' => false,
        ]);
        $manager->companies()->attach($this->company->id, [
            'role_id' => $this->siteManagerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $supervisor = User::create([
            'name' => 'Field Supervisor',
            'email' => 'supervisor@nsd.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'two_factor_enabled' => false,
        ]);
        $supervisor->companies()->attach($this->company->id, [
            'role_id' => $this->supervisorRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Supervisor logs in directly to /dashboard without 2FA challenge
        $supLogin = $this->post('/login', [
            'email' => 'supervisor@nsd.local',
            'password' => 'password123',
        ]);
        $supLogin->assertRedirect(route('dashboard'));
        $this->post('/logout');

        // Manager is challenged for 2FA when enforce_manager_2fa is enabled
        $mgrLogin = $this->post('/login', [
            'email' => 'senior@nsd.local',
            'password' => 'password123',
        ]);
        $mgrLogin->assertRedirect(route('two-factor.challenge'));
    }

    public function test_deactivated_user_with_active_session_is_immediately_locked_out_on_next_request(): void
    {
        $user = User::create([
            'name' => 'Active Staff',
            'email' => 'staff@nsd.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->companies()->attach($this->company->id, [
            'role_id' => $this->siteManagerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // User is logged in and can view dashboard
        $this->actingAs($user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/dashboard')
            ->assertStatus(200);

        // Admin deactivates the user in the database while session is still active
        $user->update(['is_active' => false]);

        // Next request immediately logs out the user, invalidates session, and redirects to /login
        $response = $this->get('/dashboard');
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auth.deactivated_lockout',
        ]);
    }

    public function test_revoked_company_membership_immediately_locks_out_active_session(): void
    {
        $user = User::create([
            'name' => 'Contractor Staff',
            'email' => 'contractor@nsd.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $user->companies()->attach($this->company->id, [
            'role_id' => $this->siteManagerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['current_company_id' => $this->company->id])
            ->get('/dashboard')
            ->assertStatus(200);

        // Company membership is marked inactive
        DB::table('company_user')
            ->where('user_id', $user->id)
            ->where('company_id', $this->company->id)
            ->update(['status' => 'inactive']);

        $response = $this->get('/dashboard');
        $response->assertStatus(403);
        $this->assertGuest();

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'auth.deactivated_lockout',
        ]);
    }

    public function test_company_admin_can_toggle_2fa_and_deactivate_users_within_own_company_only(): void
    {
        $admin = User::create([
            'name' => 'Company Admin',
            'email' => 'admin@nsd.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $admin->companies()->attach($this->company->id, [
            'role_id' => $this->adminRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $targetUser = User::create([
            'name' => 'Target Manager',
            'email' => 'target@nsd.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
            'two_factor_enabled' => false,
        ]);
        $targetUser->companies()->attach($this->company->id, [
            'role_id' => $this->siteManagerRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        $otherCompany = Company::create(['name' => 'Other Co', 'code' => 'OTH', 'is_active' => true]);
        $otherRole = Role::create(['company_id' => $otherCompany->id, 'name' => 'Admin', 'slug' => 'company_admin', 'permissions' => ['*']]);
        $externalUser = User::create([
            'name' => 'External User',
            'email' => 'ext@other.local',
            'password' => Hash::make('password123'),
            'is_active' => true,
        ]);
        $externalUser->companies()->attach($otherCompany->id, [
            'role_id' => $otherRole->id,
            'status' => 'active',
            'is_primary' => true,
        ]);

        // Admin enables 2FA for targetUser
        $this->actingAs($admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('users.toggle_2fa', $targetUser), ['enabled' => true])
            ->assertRedirect();

        $this->assertTrue($targetUser->fresh()->two_factor_enabled);

        // Admin deactivates targetUser
        $this->actingAs($admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('users.toggle_active', $targetUser), ['is_active' => false])
            ->assertRedirect();

        $this->assertFalse($targetUser->fresh()->is_active);
        $this->assertDatabaseHas('activity_logs', [
            'action' => 'user.deactivated',
            'subject_id' => $targetUser->id,
        ]);

        // Cross-tenant attempt is forbidden (403)
        $this->actingAs($admin)
            ->withSession(['current_company_id' => $this->company->id])
            ->post(route('users.toggle_active', $externalUser), ['is_active' => false])
            ->assertStatus(403);
    }
}

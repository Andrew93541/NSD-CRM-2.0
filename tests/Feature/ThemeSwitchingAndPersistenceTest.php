<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ThemeSwitchingAndPersistenceTest extends TestCase
{
    use RefreshDatabase;

    protected Company $company;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'NSD Infrastructure Ltd',
            'code' => 'NSD',
            'is_active' => true,
        ]);
        TenantContext::setCompanyId($this->company->id);

        $role = Role::create([
            'company_id' => $this->company->id,
            'name' => 'Admin',
            'slug' => 'company_admin',
            'permissions' => ['*'],
        ]);

        $this->user = User::create([
            'name' => 'Theme Auditor',
            'email' => 'auditor@nsdinfra.com',
            'password' => Hash::make('Secret123!'),
            'is_active' => true,
        ]);
        $this->user->companies()->attach($this->company->id, [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ]);
    }

    public function test_theme_switch_via_json_post_sets_session_and_cookie(): void
    {
        $response = $this->postJson(route('switch_theme'), [
            'theme' => 'light',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'status' => 'success',
                'theme' => 'light',
            ]);

        $this->assertEquals('light', session('theme'));
        $response->assertCookie('theme', 'light');
    }

    public function test_theme_switch_via_form_post_redirects_and_persists_dark(): void
    {
        $response = $this->from('/dashboard')->post(route('switch_theme'), [
            'theme' => 'dark',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertEquals('dark', session('theme'));
        $response->assertCookie('theme', 'dark');
    }

    public function test_theme_switch_rejects_invalid_themes(): void
    {
        $response = $this->postJson(route('switch_theme'), [
            'theme' => 'neon-cyberpunk',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['theme']);
    }

    public function test_middleware_restores_theme_from_cookie_when_session_is_blank(): void
    {
        $response = $this->actingAs($this->user)
            ->withCookies(['theme' => 'light'])
            ->get('/dashboard');

        $response->assertOk();
        $this->assertEquals('light', session('theme'));
        $response->assertViewHas('currentTheme', 'light');
        $response->assertDontSee('class="h-full antialiased dark"', false);
    }

    public function test_layout_renders_dark_class_when_dark_theme_is_active(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['theme' => 'dark'])
            ->get('/dashboard');

        $response->assertOk();
        $response->assertViewHas('currentTheme', 'dark');
        $response->assertSee('class="h-full antialiased dark"', false);
    }

    public function test_layout_includes_theme_switch_toggle_and_anti_fouc_script(): void
    {
        $response = $this->actingAs($this->user)->get('/dashboard');

        $response->assertOk();
        // Check for theme switcher UI elements
        $response->assertSee('toggleTheme()', false);
        $response->assertSee('id="theme-toggle-btn"', false);
        $response->assertSee('Switch to light theme', false);
        // Check for anti-FOUC script
        $response->assertSee('localStorage.getItem(\'theme\')', false);
    }

    public function test_login_screen_renders_theme_toggle_for_guests(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('toggleTheme()', false);
        $response->assertSee('Switch to light theme', false);
        $response->assertSee('localStorage.getItem(\'theme\')', false);
    }

    public function test_theme_persists_across_logout(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['theme' => 'light', 'locale' => 'hi'])
            ->post(route('logout'));

        $response->assertRedirect(route('login'));
        $this->assertEquals('light', session('theme'));
        $this->assertEquals('hi', session('locale'));
    }
}

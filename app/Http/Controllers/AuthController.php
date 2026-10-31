<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Services\Security\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $throttleKey = 'staff-login:' . strtolower(trim($credentials['email'])) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ])->onlyInput('email');
        }

        $credentials['is_active'] = true;

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();
            $user = Auth::user();

            // Set primary or first active company membership
            $company = $user->companies()
                ->wherePivot('status', 'active')
                ->where('companies.is_active', true)
                ->orderByDesc('company_user.is_primary')
                ->first();

            if ($company) {
                $request->session()->put('current_company_id', $company->id);
                TenantContext::setCompanyId($company->id);
            } else {
                TenantContext::setCompanyId(null);
            }

            AuditLog::record(
                'auth.login_succeeded',
                $user,
                null,
                ['email' => $user->email],
                null,
                $user->id,
                $company?->id
            );

            return redirect()->intended(route('dashboard'));
        }

        RateLimiter::hit($throttleKey, 60);
        AuditLog::record(
            'auth.login_failed',
            null,
            null,
            ['email' => $credentials['email'], 'ip' => $request->ip()]
        );

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    public function logout(Request $request): RedirectResponse
    {
        $theme = $request->session()->get('theme');
        $locale = $request->session()->get('locale');

        TenantContext::clear();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($theme) {
            $request->session()->put('theme', $theme);
        }
        if ($locale) {
            $request->session()->put('locale', $locale);
        }

        return redirect()->route('login');
    }

    public function switchCompany(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'company_id' => ['required', 'integer', 'exists:companies,id'],
        ]);

        $user = Auth::user();
        $company = $user->companies()
            ->where('companies.id', $validated['company_id'])
            ->wherePivot('status', 'active')
            ->where('companies.is_active', true)
            ->first();

        if (!$company) {
            abort(403, 'Unauthorized company selection or inactive membership.');
        }

        $request->session()->put('current_company_id', $company->id);
        $request->session()->forget('active_site_id');
        TenantContext::setCompanyId($company->id);

        return back()->with('success', 'Switched organization to ' . $company->name);
    }

    public function switchSite(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => ['nullable', 'integer'],
        ]);

        $siteId = $validated['site_id'] ?? null;
        if (empty($siteId)) {
            $request->session()->forget('active_site_id');
            return back()->with('success', __('Viewing all accessible sites.'));
        }

        $user = Auth::user();
        if (!$user || !$user->isAssignedToSite((int) $siteId)) {
            abort(403, 'Unauthorized site selection.');
        }

        $request->session()->put('active_site_id', (int) $siteId);

        return back()->with('success', __('Active site filter updated.'));
    }

    public function switchLocale(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'locale' => ['required', 'in:en,hi,mr'],
        ]);

        $request->session()->put('locale', $validated['locale']);

        return back()->with('success', __('Language changed successfully.'));
    }
    public function switchTheme(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'in:light,dark,system'],
        ]);

        $theme = $validated['theme'];
        $request->session()->put('theme', $theme);

        // Create persistent cookie for 1 year (525600 minutes)
        $cookie = cookie('theme', $theme, 525600);
        cookie()->queue($cookie);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'status' => 'success',
                'success' => true,
                'theme' => $theme,
            ])->withCookie($cookie);
        }

        return back()->with('success', __('Theme updated successfully.'))->withCookie($cookie);
    }
}

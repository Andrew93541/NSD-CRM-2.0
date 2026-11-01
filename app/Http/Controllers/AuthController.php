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

            if ($user->requiresTwoFactorChallenge($company?->id)) {
                $code = $user->issueTwoFactorCode();
                $request->session()->put('two_factor_pending', true);

                if (!app()->environment('production') && config('app.env') !== 'production') {
                    $request->session()->flash('two_factor_demo_code', $code);
                }

                AuditLog::record(
                    'auth.2fa_challenge_issued',
                    $user,
                    null,
                    ['email' => $user->email, 'role' => $user->currentRole($company?->id)?->slug],
                    null,
                    $user->id,
                    $company?->id
                );

                return redirect()->route('two-factor.challenge');
            }

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

    public function showTwoFactorChallenge(Request $request): View|RedirectResponse
    {
        $user = Auth::user()?->fresh();
        if (!$user) {
            return redirect()->route('login');
        }

        $pending = (bool) $request->session()->get('two_factor_pending', false)
            || ((bool) config('auth.enforce_manager_2fa', false) && $user->isManagerOrAbove() && !$user->two_factor_verified_at);

        if (!$pending) {
            return redirect()->route('dashboard');
        }

        if (!$user->two_factor_code || !$user->two_factor_expires_at || $user->two_factor_expires_at->isPast()) {
            $code = $user->issueTwoFactorCode();
            if (!app()->environment('production') && config('app.env') !== 'production') {
                $request->session()->flash('two_factor_demo_code', $code);
            }
        }

        return view('auth.two_factor', [
            'user' => $user,
            'expiresAt' => $user->two_factor_expires_at,
        ]);
    }

    public function verifyTwoFactorChallenge(Request $request): RedirectResponse
    {
        $user = Auth::user()?->fresh();
        if (!$user) {
            return redirect()->route('login');
        }

        $throttleKey = 'two-factor:' . $user->id . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            return back()->withErrors([
                'code' => "Too many verification attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $validated = $request->validate([
            'code' => ['required', 'digits:6'],
        ]);

        if ($user->verifyTwoFactorCode($validated['code'])) {
            RateLimiter::clear($throttleKey);
            $request->session()->forget('two_factor_pending');
            $request->session()->put('two_factor_verified', true);

            AuditLog::record(
                'auth.2fa_verified',
                $user,
                null,
                ['email' => $user->email]
            );

            return redirect()->intended(route('dashboard'))->with('success', __('Two-factor authentication verified.'));
        }

        RateLimiter::hit($throttleKey, 60);
        AuditLog::record(
            'auth.2fa_failed',
            $user,
            null,
            ['email' => $user->email, 'ip' => $request->ip()]
        );

        return back()->withErrors([
            'code' => __('The verification code is invalid or has expired.'),
        ]);
    }

    public function resendTwoFactorChallenge(Request $request): RedirectResponse
    {
        $user = Auth::user()?->fresh();
        if (!$user) {
            return redirect()->route('login');
        }

        $code = $user->issueTwoFactorCode();
        $request->session()->put('two_factor_pending', true);

        if (!app()->environment('production') && config('app.env') !== 'production') {
            $request->session()->flash('two_factor_demo_code', $code);
        }

        AuditLog::record(
            'auth.2fa_resent',
            $user,
            null,
            ['email' => $user->email]
        );

        return back()->with('success', __('A fresh 6-digit verification code has been issued.'));
    }

    public function toggleTwoFactor(Request $request, \App\Models\User $user): RedirectResponse
    {
        $actor = Auth::user();
        $companyId = TenantContext::getCompanyId();

        $isAdmin = $actor->hasRole(['super_admin', 'company_admin']);
        $isSelf = (int) $actor->id === (int) $user->id;

        if (!$isAdmin && !$isSelf) {
            abort(403, 'Unauthorized to manage Two-Factor Authentication for this user.');
        }

        if (!$isSelf && $companyId) {
            $belongsToCompany = $user->companies()->where('companies.id', $companyId)->exists();
            if (!$belongsToCompany && !$actor->hasRole('super_admin')) {
                abort(403, 'Target user does not belong to your company.');
            }
        }

        $enabled = $request->has('enabled') ? $request->boolean('enabled') : !$user->two_factor_enabled;
        $user->forceFill([
            'two_factor_enabled' => $enabled,
        ])->save();

        AuditLog::record(
            'auth.2fa_toggled',
            $user,
            ['two_factor_enabled' => !$enabled],
            ['two_factor_enabled' => $enabled, 'toggled_by' => $actor->id]
        );

        return back()->with('success', $enabled
            ? __('Two-Factor Authentication enabled for :name.', ['name' => $user->name])
            : __('Two-Factor Authentication disabled for :name.', ['name' => $user->name]));
    }

    public function toggleUserActive(Request $request, \App\Models\User $user): RedirectResponse
    {
        $actor = Auth::user();
        $companyId = TenantContext::getCompanyId();

        if (!$actor->hasRole(['super_admin', 'company_admin'])) {
            abort(403, 'Only Company Administrators can activate or deactivate user accounts.');
        }

        if ((int) $actor->id === (int) $user->id) {
            return back()->with('error', __('You cannot deactivate your own account.'));
        }

        if ($companyId && !$actor->hasRole('super_admin')) {
            $belongsToCompany = $user->companies()->where('companies.id', $companyId)->exists();
            if (!$belongsToCompany) {
                abort(403, 'Target user does not belong to your company.');
            }
        }

        $newStatus = $request->has('is_active') ? $request->boolean('is_active') : !$user->is_active;
        $oldStatus = (bool) $user->is_active;

        $user->forceFill([
            'is_active' => $newStatus,
        ])->save();

        if (!$newStatus && \Illuminate\Support\Facades\Schema::hasTable('sessions')) {
            \Illuminate\Support\Facades\DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();
        }

        AuditLog::record(
            $newStatus ? 'user.activated' : 'user.deactivated',
            $user,
            ['is_active' => $oldStatus],
            ['is_active' => $newStatus, 'acted_by' => $actor->id]
        );

        return back()->with('success', $newStatus
            ? __('User account :name has been reactivated.', ['name' => $user->name])
            : __('User account :name has been deactivated and all active sessions revoked.', ['name' => $user->name]));
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Services\Security\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        // Reset static tenant state for each request to avoid leakage across worker cycles
        TenantContext::reset();

        // Defense-in-depth: block direct HTTP requests to sensitive files, dotfiles, and internal directories
        $relPath = '/' . ltrim($request->getPathInfo(), '/');
        $blockedPattern = '#^/(?:\.(?!well-known(?:/|$))|artisan$|composer\.(?:json|lock)$|package(?:-lock)?\.json$|phpunit\.xml|vite\.config\.js$|(?:app|bootstrap|config|database|docs|lang|node_modules|resources|routes|storage|tests|vendor)(?:/|$)|.*\.(?:sql|sqlite|sqlite3|log|bak|env)$)#i';
        if (preg_match($blockedPattern, $relPath)) {
            abort(403, 'Access to protected system path is forbidden.');
        }

        if (Auth::check()) {
            $user = Auth::user()->fresh() ?? Auth::user();

            if (!$user->is_active) {
                \App\Models\AuditLog::record('auth.deactivated_lockout', $user, null, [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'reason' => 'user_deactivated',
                ]);
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated.']);
            }

            $sessionCompanyId = $request->session()->get('current_company_id');
            $company = null;

            if ($sessionCompanyId) {
                $company = $user->companies()
                    ->where('companies.id', $sessionCompanyId)
                    ->wherePivot('status', 'active')
                    ->where('companies.is_active', true)
                    ->first();

                if (!$company) {
                    // Forged or revoked company ID in session: purge it
                    $request->session()->forget('current_company_id');
                }
            }

            if (!$company) {
                $company = $user->companies()
                    ->wherePivot('status', 'active')
                    ->where('companies.is_active', true)
                    ->orderByDesc('company_user.is_primary')
                    ->first();

                if ($company) {
                    $request->session()->put('current_company_id', $company->id);
                }
            }

            if (!$company) {
                TenantContext::setCompanyId(null);

                \App\Models\AuditLog::record('auth.deactivated_lockout', $user, null, [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'reason' => 'company_membership_deactivated',
                ]);
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                // Fail closed when company context cannot be safely resolved
                if (!$request->routeIs('logout')) {
                    abort(403, 'No active company membership found. Access denied.');
                }
            } else {
                TenantContext::setCompanyId($company->id);

                $currentRole = $user->currentRole($company->id);

                $accessibleSites = $user->hasRole(['company_admin', 'super_admin', 'senior_manager'])
                    ? $company->sites()->where('status', 'active')->orderBy('code')->get()
                    : \App\Models\Site::whereIn('id', $user->accessibleSiteIds())->where('status', 'active')->orderBy('code')->get();

                $activeSiteId = $request->session()->get('active_site_id');
                if ($activeSiteId && !$user->isAssignedToSite((int) $activeSiteId)) {
                    $request->session()->forget('active_site_id');
                    $activeSiteId = null;
                }

                View::share([
                    'currentCompany' => $company,
                    'currentUser' => $user,
                    'currentRole' => $currentRole,
                    'accessibleSites' => $accessibleSites,
                    'activeSiteId' => $activeSiteId,
                ]);

                $twoFactorPending = (bool) $request->session()->get('two_factor_pending', false)
                    || ((bool) config('auth.enforce_manager_2fa', false) && $user->isManagerOrAbove($company->id) && !$user->two_factor_verified_at);

                if ($twoFactorPending && !$request->routeIs('two-factor.*', 'logout', 'switch_locale', 'switch_theme')) {
                    return redirect()->route('two-factor.challenge');
                }
            }
        }

        return $next($request);
    }
}

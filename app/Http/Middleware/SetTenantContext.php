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

        if (Auth::check()) {
            $user = Auth::user();

            if (!$user->is_active) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                return redirect()->route('login')->withErrors(['email' => 'Your account is deactivated.']);
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

                // Fail closed when company context cannot be safely resolved
                if (!$request->routeIs('logout')) {
                    abort(403, 'No active company membership found. Access denied.');
                }
            } else {
                TenantContext::setCompanyId($company->id);

                $currentRole = $user->currentRole($company->id);

                $accessibleSites = $user->hasRole(['company_admin', 'super_admin', 'senior_manager'])
                    ? $company->sites()->where('status', 'active')->get()
                    : $user->sites()->where('sites.company_id', $company->id)->where('sites.status', 'active')->get();

                View::share([
                    'currentCompany' => $company,
                    'currentUser' => $user,
                    'currentRole' => $currentRole,
                    'accessibleSites' => $accessibleSites,
                ]);
            }
        }

        return $next($request);
    }
}

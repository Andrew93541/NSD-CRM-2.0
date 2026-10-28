<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->currentCompany() !== null;
    }

    public function view(User $user, Site $site): bool
    {
        return $user->isAssignedToSite($site);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('sites.create')
            || $user->hasPermission('sites.manage')
            || $user->hasRole(['super_admin', 'company_admin', 'senior_manager']);
    }

    public function update(User $user, Site $site): bool
    {
        if (!$user->isAssignedToSite($site)) {
            return false;
        }

        return $user->hasPermission('sites.update')
            || $user->hasPermission('sites.manage')
            || $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']);
    }

    public function recordAttendance(User $user, Site $site): bool
    {
        return ($user->hasPermission('attendance.record') || $user->hasRole(['site_supervisor', 'site_manager', 'company_admin', 'super_admin']))
            && $user->isAssignedToSite($site);
    }
}

<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class SitePolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isExplicitlyDenied('sites.view')) {
            return false;
        }

        return $user->currentCompany() !== null;
    }

    public function view(User $user, Site $site): bool
    {
        if ($user->isExplicitlyDenied('sites.view')) {
            return false;
        }

        return $user->isAssignedToSite($site);
    }

    public function create(User $user): bool
    {
        if ($user->isExplicitlyDenied('sites.create') || $user->isExplicitlyDenied('sites.manage')) {
            return false;
        }

        return $user->hasPermission('sites.create')
            || $user->hasPermission('sites.manage')
            || $user->hasRole(['super_admin', 'company_admin', 'senior_manager']);
    }

    public function update(User $user, Site $site): bool
    {
        if (!$user->isAssignedToSite($site)) {
            return false;
        }

        if ($user->isExplicitlyDenied('sites.update') || $user->isExplicitlyDenied('sites.manage')) {
            return false;
        }

        return $user->hasPermission('sites.update')
            || $user->hasPermission('sites.manage')
            || $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']);
    }

    public function recordAttendance(User $user, Site $site): bool
    {
        if ($user->isExplicitlyDenied('attendance.mark') || $user->isExplicitlyDenied('attendance.record')) {
            return false;
        }

        return $user->hasPermission('attendance.mark')
            && $user->isAssignedToSite($site);
    }

    public function delete(User $user, Site $site): bool
    {
        if (!$user->isAssignedToSite($site)) {
            return false;
        }

        if (!$site->canBeDeleted()) {
            return false;
        }

        if ($user->isExplicitlyDenied('sites.delete') || $user->isExplicitlyDenied('sites.manage')) {
            return false;
        }

        return $user->hasPermission('sites.delete')
            || $user->hasPermission('sites.manage')
            || $user->hasRole(['super_admin', 'company_admin', 'senior_manager']);
    }
}

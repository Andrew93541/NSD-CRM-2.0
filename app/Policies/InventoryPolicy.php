<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isExplicitlyDenied('inventory.view')) {
            return false;
        }

        return $user->hasPermission('inventory.view')
            || $user->hasPermission('inventory.manage')
            || $user->hasPermission('inventory.receive')
            || $user->hasPermission('inventory.issue')
            || $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'warehouse_manager', 'office_staff']);
    }

    public function receive(User $user, ?Site $site = null): bool
    {
        if ($user->isExplicitlyDenied('inventory.receive')) {
            return false;
        }

        if ($user->hasPermission('inventory.receive')) {
            return $site ? $user->isAssignedToSite($site) : true;
        }

        if ($user->hasRole('office_staff')) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'company_admin']) || (!$user->isRestrictedToAssignedSites() && $user->hasRole(['senior_manager', 'warehouse_manager']))) {
            return true;
        }

        if ($site) {
            return $user->isAssignedToSite($site)
                && $user->hasRole(['site_supervisor', 'site_manager', 'warehouse_manager', 'senior_manager']);
        }

        return $user->hasRole(['site_supervisor', 'site_manager']);
    }

    public function issue(User $user): bool
    {
        return $user->canPerform('inventory.issue', ['super_admin', 'company_admin', 'warehouse_manager']);
    }
}

<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

class InventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function receive(User $user, ?Site $site = null): bool
    {
        if ($user->hasRole(['super_admin', 'company_admin', 'warehouse_manager'])) {
            return true;
        }

        if ($site && $user->isAssignedToSite($site)) {
            return $user->hasPermission('inventory.receive') || $user->hasRole(['site_supervisor', 'site_manager']);
        }

        return false;
    }

    public function issue(User $user): bool
    {
        return $user->hasPermission('inventory.issue') || $user->hasRole(['super_admin', 'company_admin', 'warehouse_manager']);
    }
}

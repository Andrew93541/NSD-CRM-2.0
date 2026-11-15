<?php

namespace App\Policies;

use App\Models\InventoryTransaction;
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

        if ($user->hasRole(['super_admin', 'company_admin']) || (! $user->isRestrictedToAssignedSites() && $user->hasRole(['senior_manager', 'warehouse_manager']))) {
            return true;
        }

        if ($user->hasPermission('inventory.receive')) {
            return $site ? $user->isAssignedToSite($site) : true;
        }

        if ($user->hasRole('office_staff')) {
            return false;
        }

        if ($site) {
            return $user->isAssignedToSite($site)
                && $user->hasRole(['site_supervisor', 'site_manager', 'warehouse_manager', 'senior_manager']);
        }

        return $user->hasRole(['site_supervisor', 'site_manager']);
    }

    public function issue(User $user, ?Site $site = null): bool
    {
        if ($user->isExplicitlyDenied('inventory.issue')) {
            return false;
        }

        if ($user->hasRole('office_staff') && ! $user->hasPermission('inventory.issue')) {
            return false;
        }

        $allowed = $user->canPerform('inventory.issue', [
            'super_admin',
            'company_admin',
            'senior_manager',
            'warehouse_manager',
            'site_manager',
        ]);

        if (! $allowed) {
            return false;
        }

        if ($site && ($user->isRestrictedToAssignedSites() || $user->hasRole(['site_manager', 'site_supervisor']))) {
            return $user->isAssignedToSite($site);
        }

        return true;
    }

    public function manage(User $user): bool
    {
        if ($user->isExplicitlyDenied('inventory.manage')) {
            return false;
        }

        return $user->canPerform('inventory.manage', ['super_admin', 'company_admin', 'senior_manager', 'warehouse_manager']);
    }

    public function approveAdjustment(User $user, InventoryTransaction $transaction): bool
    {
        if (! $this->manage($user)) {
            return false;
        }

        if ($transaction->transaction_type !== 'stock_adjustment' || $transaction->status !== 'pending_confirmation') {
            return false;
        }

        // Maker-checker separation of duties: requester cannot approve/reject their own stock adjustment
        if ((int) $transaction->performed_by === (int) $user->id) {
            return false;
        }

        if ($user->isRestrictedToAssignedSites()) {
            $siteId = $transaction->site_id ?: $transaction->sourceWarehouse?->site_id ?: $transaction->destinationWarehouse?->site_id;
            if (! $siteId || ! $user->isAssignedToSite((int) $siteId)) {
                return false;
            }
        }

        return true;
    }
}

<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;
use App\Services\Security\TenantContext;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isExplicitlyDenied('procurement.view')) {
            return false;
        }

        return $user->hasPermission('procurement.view')
            || $user->hasPermission('procurement.request')
            || $user->hasPermission('procurement.approve')
            || $user->hasPermission('inventory.receive')
            || $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'warehouse_manager', 'office_staff']);
    }

    public function create(User $user): bool
    {
        if ($user->isExplicitlyDenied('procurement.request')) {
            return false;
        }

        if ($user->hasPermission('procurement.request')) {
            return true;
        }

        if ($user->hasRole('office_staff')) {
            return false;
        }

        return $user->hasRole(['site_manager', 'warehouse_manager', 'company_admin', 'super_admin']);
    }

    public function approve(User $user, PurchaseRequest $pr): bool
    {
        if ((int) $pr->company_id !== (int) TenantContext::getCompanyId()) {
            return false;
        }

        if ($user->isRestrictedToAssignedSites() && $pr->site_id !== null && !$user->isAssignedToSite((int) $pr->site_id)) {
            return false;
        }

        return $pr->status === 'pending'
            && $user->canPerform('procurement.approve', ['senior_manager', 'company_admin', 'super_admin']);
    }

    public function createOrder(User $user, PurchaseRequest $pr): bool
    {
        if ((int) $pr->company_id !== (int) TenantContext::getCompanyId()) {
            return false;
        }

        if ($user->isRestrictedToAssignedSites() && $pr->site_id !== null && !$user->isAssignedToSite((int) $pr->site_id)) {
            return false;
        }

        return $pr->status === 'approved'
            && $user->canPerform('procurement.approve', ['super_admin', 'company_admin', 'senior_manager', 'warehouse_manager']);
    }
}

<?php

namespace App\Policies;

use App\Models\PurchaseRequest;
use App\Models\User;

class PurchaseRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('procurement.view') || $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'warehouse_manager']);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('procurement.request') || $user->hasRole(['site_manager', 'warehouse_manager', 'company_admin', 'super_admin']);
    }

    public function approve(User $user, PurchaseRequest $pr): bool
    {
        return $pr->status === 'pending'
            && ($user->hasPermission('procurement.approve') || $user->hasRole(['senior_manager', 'company_admin', 'super_admin']));
    }

    public function createOrder(User $user, PurchaseRequest $pr): bool
    {
        return $pr->status === 'approved'
            && $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'warehouse_manager']);
    }
}

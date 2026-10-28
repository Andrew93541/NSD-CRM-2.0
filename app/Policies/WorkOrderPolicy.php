<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('work_orders.view') || $user->hasRole(['company_admin', 'super_admin', 'senior_manager', 'site_manager', 'office_staff']);
    }

    public function view(User $user, WorkOrder $wo): bool
    {
        if ($user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])) {
            return true;
        }

        return $wo->site_id ? $user->isAssignedToSite($wo->site_id) : true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('work_orders.create') || $user->hasRole(['site_manager', 'company_admin', 'super_admin']);
    }

    public function approveSite(User $user, WorkOrder $wo): bool
    {
        if ($wo->status !== 'submitted') {
            return false;
        }

        return $user->hasPermission('work_orders.approve_site') || $user->hasRole(['site_manager', 'company_admin', 'super_admin']);
    }

    public function approveSenior(User $user, WorkOrder $wo): bool
    {
        if ($wo->status !== 'site_manager_approved') {
            return false;
        }

        return $user->hasPermission('work_orders.approve_senior') || $user->hasRole(['senior_manager', 'company_admin', 'super_admin']);
    }

    public function reject(User $user, WorkOrder $wo): bool
    {
        if (!in_array($wo->status, ['submitted', 'site_manager_approved'], true)) {
            return false;
        }

        return $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']);
    }
}

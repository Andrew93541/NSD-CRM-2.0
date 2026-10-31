<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Services\Security\TenantContext;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole([
            'super_admin',
            'company_admin',
            'senior_manager',
            'site_manager',
            'site_supervisor',
            'supervisor',
            'office_staff',
        ]);
    }

    public function view(User $user, Task $task): bool
    {
        if ((int) $task->company_id !== (int) TenantContext::getCompanyId()) {
            return false;
        }

        if ((int) $task->assigned_to === (int) $user->id) {
            return true;
        }

        return $task->site_id ? $user->isAssignedToSite((int) $task->site_id) : false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole([
            'super_admin',
            'company_admin',
            'senior_manager',
            'site_manager',
            'site_supervisor',
            'supervisor',
        ]);
    }

    public function update(User $user, Task $task): bool
    {
        if ((int) $task->company_id !== (int) TenantContext::getCompanyId()) {
            return false;
        }

        if ((int) $task->assigned_to === (int) $user->id) {
            return true;
        }

        return $task->site_id ? $user->isAssignedToSite((int) $task->site_id) : false;
    }
}


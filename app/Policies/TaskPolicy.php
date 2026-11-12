<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use App\Services\Security\TenantContext;

class TaskPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canPerform('tasks.view', [
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

        if ($user->isExplicitlyDenied('tasks.view')) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'company_admin'])) {
            return true;
        }

        if (!$user->isRestrictedToAssignedSites() && $user->hasRole(['senior_manager', 'office_staff'])) {
            return true;
        }

        if ((int) $task->assigned_to === (int) $user->id) {
            return true;
        }

        return $task->site_id ? $user->isAssignedToSite((int) $task->site_id) : false;
    }

    public function create(User $user): bool
    {
        return $user->canPerform('tasks.create', [
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

        if ($user->isExplicitlyDenied('tasks.update')) {
            return false;
        }

        // Office staff and warehouse managers have read-only or no access to task mutations unless explicitly granted
        if (!$user->hasPermission('tasks.update') && !$this->create($user)) {
            return false;
        }

        if ((int) $task->assigned_to === (int) $user->id) {
            return true;
        }

        return $task->site_id ? $user->isAssignedToSite((int) $task->site_id) : false;
    }

    public function delete(User $user, Task $task): bool
    {
        if ((int) $task->company_id !== (int) TenantContext::getCompanyId()) {
            return false;
        }

        if ($user->isExplicitlyDenied('tasks.delete')) {
            return false;
        }

        if (!$user->hasPermission('tasks.delete') && !$this->create($user)) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'company_admin']) || ($user->hasRole('senior_manager') && !$user->isRestrictedToAssignedSites())) {
            return true;
        }

        if (!$task->site_id || !$user->isAssignedToSite((int) $task->site_id)) {
            return false;
        }

        if ($user->hasRole(['site_supervisor', 'supervisor'])) {
            return (int) $task->created_by === (int) $user->id;
        }

        return $user->hasRole('site_manager')
            || (int) $task->created_by === (int) $user->id
            || $user->hasPermission('tasks.delete');
    }
}

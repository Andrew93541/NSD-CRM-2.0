<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerDocument;
use App\Services\Security\TenantContext;

class WorkerPolicy
{
    protected function belongsToActiveTenant(User $user, Worker $worker): bool
    {
        $companyId = TenantContext::getCompanyId();
        if (!$companyId || !$user->currentCompany()) {
            return false;
        }

        return (int) $worker->company_id === (int) $companyId;
    }

    protected function canAccessWorkerSite(User $user, Worker $worker): bool
    {
        if (!$this->belongsToActiveTenant($user, $worker)) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])
            || $user->hasPermission('workforce.manage')
            || $user->hasPermission('workforce.view_all')
        ) {
            return true;
        }

        if ($worker->assigned_site_id === null) {
            return true;
        }

        return $user->isAssignedToSite((int) $worker->assigned_site_id);
    }

    public function viewAny(User $user): bool
    {
        if (!$user->currentCompany()) {
            return false;
        }

        return $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff'])
            || $user->hasPermission('workforce.view')
            || $user->hasPermission('workforce.manage')
            || $user->hasPermission('attendance.view')
            || $user->hasPermission('attendance.record')
            || $user->hasPermission('attendance.muster');
    }

    public function view(User $user, Worker $worker): bool
    {
        if (!$this->canAccessWorkerSite($user, $worker)) {
            return false;
        }

        return $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff'])
            || $user->hasPermission('workforce.view')
            || $user->hasPermission('workforce.manage')
            || $user->hasPermission('attendance.view')
            || $user->hasPermission('attendance.record');
    }

    public function create(User $user): bool
    {
        if (!$user->currentCompany()) {
            return false;
        }

        return $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])
            || $user->hasPermission('workforce.create')
            || $user->hasPermission('workforce.manage');
    }

    public function update(User $user, Worker $worker): bool
    {
        if (!$this->canAccessWorkerSite($user, $worker)) {
            return false;
        }

        return $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])
            || $user->hasPermission('workforce.update')
            || $user->hasPermission('workforce.manage');
    }

    public function uploadDocument(User $user, Worker $worker): bool
    {
        if (!$this->canAccessWorkerSite($user, $worker)) {
            return false;
        }

        return $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])
            || $user->hasPermission('workforce.update')
            || $user->hasPermission('workforce.create')
            || $user->hasPermission('workforce.manage');
    }

    public function downloadDocument(User $user, Worker $worker, WorkerDocument $document): bool
    {
        if ((int) $document->worker_id !== (int) $worker->id
            || (int) $document->company_id !== (int) $worker->company_id
        ) {
            return false;
        }

        return $this->view($user, $worker);
    }

    public function deleteDocument(User $user, Worker $worker, WorkerDocument $document): bool
    {
        if ((int) $document->worker_id !== (int) $worker->id
            || (int) $document->company_id !== (int) $worker->company_id
        ) {
            return false;
        }

        return $this->update($user, $worker);
    }
}


<?php

namespace App\Policies;

use App\Models\RaBill;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderRevision;
use App\Services\Security\TenantContext;

class WorkOrderPolicy
{
    protected function belongsToActiveTenant(User $user, WorkOrder $wo): bool
    {
        $companyId = TenantContext::getCompanyId();
        if (!$companyId || !$user->currentCompany()) {
            return false;
        }

        return (int) $wo->company_id === (int) $companyId;
    }

    protected function canAccessWorkOrderSite(User $user, WorkOrder $wo): bool
    {
        if (!$this->belongsToActiveTenant($user, $wo)) {
            return false;
        }

        if ($user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            return true;
        }

        if ($wo->site_id === null) {
            return true;
        }

        if ($user->hasRole('office_staff')) {
            $hasExplicitSites = $user->sites()->where('sites.company_id', $wo->company_id)->exists();
            if (!$hasExplicitSites) {
                return true;
            }
        }

        return $user->isAssignedToSite((int) $wo->site_id);
    }

    public function viewAny(User $user): bool
    {
        if (!$user->currentCompany()) {
            return false;
        }

        return $user->hasPermission('work_orders.view')
            || $user->hasPermission('work_orders.create')
            || $user->hasPermission('work_orders.approve_site')
            || $user->hasPermission('work_orders.approve_senior')
            || $user->hasRole(['company_admin', 'super_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff']);
    }

    public function view(User $user, WorkOrder $wo): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        if (!$user->currentCompany()) {
            return false;
        }

        return $user->hasPermission('work_orders.create')
            || $user->hasRole(['site_manager', 'company_admin', 'super_admin']);
    }

    public function approveSite(User $user, WorkOrder $wo): bool
    {
        if ($wo->status !== 'submitted' || !$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        return $user->hasPermission('work_orders.approve_site')
            || $user->hasRole(['site_manager', 'company_admin', 'super_admin']);
    }

    public function approveSenior(User $user, WorkOrder $wo): bool
    {
        if ($wo->status !== 'site_manager_approved' || !$this->belongsToActiveTenant($user, $wo)) {
            return false;
        }

        return $user->hasPermission('work_orders.approve_senior')
            || $user->hasRole(['senior_manager', 'company_admin', 'super_admin']);
    }

    public function reject(User $user, WorkOrder $wo): bool
    {
        if (!in_array($wo->status, ['submitted', 'site_manager_approved'], true)) {
            return false;
        }

        if (!$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        return $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']);
    }

    public function uploadAttachment(User $user, WorkOrder $wo): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        return $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])
            || $user->hasPermission('work_orders.create');
    }

    public function downloadAttachment(User $user, WorkOrder $wo, WorkOrderAttachment $attachment): bool
    {
        if ((int) $attachment->work_order_id !== (int) $wo->id) {
            return false;
        }

        return $this->view($user, $wo);
    }

    public function deleteAttachment(User $user, WorkOrder $wo, WorkOrderAttachment $attachment): bool
    {
        if ((int) $attachment->work_order_id !== (int) $wo->id) {
            return false;
        }

        return $this->uploadAttachment($user, $wo);
    }

    public function createRevision(User $user, WorkOrder $wo): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        return $user->hasPermission('work_orders.create')
            || $user->hasRole(['office_staff', 'site_manager', 'senior_manager', 'company_admin', 'super_admin']);
    }

    public function approveRevision(User $user, WorkOrder $wo, WorkOrderRevision $revision): bool
    {
        if (!$this->belongsToActiveTenant($user, $wo) || (int) $revision->work_order_id !== (int) $wo->id) {
            return false;
        }

        if ($revision->status !== 'pending_approval') {
            return false;
        }

        // Non-self-approval rule: a user can never approve their own revision request
        if ((int) $revision->changed_by === (int) $user->id) {
            return false;
        }

        return $user->hasPermission('work_orders.approve_senior')
            || $user->hasRole(['senior_manager', 'company_admin', 'super_admin']);
    }

    public function recordMeasurement(User $user, WorkOrder $wo): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        return $user->hasRole(['site_supervisor', 'site_manager', 'office_staff', 'senior_manager', 'company_admin', 'super_admin'])
            || $user->hasPermission('work_orders.create');
    }

    public function createRaBill(User $user, WorkOrder $wo): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        return $user->hasRole(['office_staff', 'site_manager', 'senior_manager', 'company_admin', 'super_admin'])
            || $user->hasPermission('work_orders.create');
    }

    public function approveRaBillSite(User $user, WorkOrder $wo, RaBill $raBill): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo) || (int) $raBill->work_order_id !== (int) $wo->id) {
            return false;
        }

        if (!in_array($raBill->status, ['draft', 'submitted'], true)) {
            return false;
        }

        return $user->hasPermission('work_orders.approve_site')
            || $user->hasRole(['site_manager', 'company_admin', 'super_admin']);
    }

    public function approveRaBillSenior(User $user, WorkOrder $wo, RaBill $raBill): bool
    {
        if (!$this->belongsToActiveTenant($user, $wo) || (int) $raBill->work_order_id !== (int) $wo->id) {
            return false;
        }

        if ($raBill->status !== 'site_manager_approved') {
            return false;
        }

        // Non-self-approval rule: user who verified Tier 1 cannot perform Tier 2 final approval
        if ((int) $raBill->site_manager_id === (int) $user->id) {
            return false;
        }

        return $user->hasPermission('work_orders.approve_senior')
            || $user->hasRole(['senior_manager', 'company_admin', 'super_admin']);
    }

    public function rejectRaBill(User $user, WorkOrder $wo, RaBill $raBill): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo) || (int) $raBill->work_order_id !== (int) $wo->id) {
            return false;
        }

        if ($raBill->isLocked() || $raBill->status === 'rejected') {
            return false;
        }

        return $user->hasRole(['site_manager', 'senior_manager', 'company_admin', 'super_admin']);
    }
}

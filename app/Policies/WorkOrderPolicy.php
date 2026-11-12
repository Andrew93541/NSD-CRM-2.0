<?php

namespace App\Policies;

use App\Models\RaBill;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderRevision;
use App\Models\WorkOrderUpload;
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

        if ($user->hasRole(['super_admin', 'company_admin'])) {
            return true;
        }

        if ($wo->site_id === null) {
            return true;
        }

        if ($user->hasRole('senior_manager') && !$user->isRestrictedToAssignedSites()) {
            return true;
        }

        if ($user->hasRole('office_staff') && !$user->isRestrictedToAssignedSites()) {
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

        if ($user->isExplicitlyDenied('work_orders.view')) {
            return false;
        }

        return $user->hasPermission('work_orders.view')
            || $user->hasPermission('work_orders.create')
            || $user->hasPermission('work_orders.approve_site')
            || $user->hasPermission('work_orders.approve_senior')
            || $user->hasPermission('ra_bills.view')
            || $user->hasPermission('measurements.view')
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

        return $user->canPerform('work_orders.create', ['site_manager', 'company_admin', 'super_admin']);
    }

    public function approveSite(User $user, WorkOrder $wo): bool
    {
        if ($wo->status !== 'submitted' || !$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        return $user->canPerform('work_orders.approve_site', ['site_manager', 'company_admin', 'super_admin']);
    }

    public function approveSenior(User $user, WorkOrder $wo): bool
    {
        if ($wo->status !== 'site_manager_approved' || !$this->belongsToActiveTenant($user, $wo)) {
            return false;
        }

        if ($user->isRestrictedToAssignedSites() && $wo->site_id !== null && !$user->isAssignedToSite((int) $wo->site_id)) {
            return false;
        }

        return $user->canPerform('work_orders.approve_senior', ['senior_manager', 'company_admin', 'super_admin']);
    }

    public function reject(User $user, WorkOrder $wo): bool
    {
        if (!in_array($wo->status, ['submitted', 'site_manager_approved'], true)) {
            return false;
        }

        if (!$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        return $user->canPerform('work_orders.approve_site', ['super_admin', 'company_admin', 'senior_manager', 'site_manager'])
            || $user->canPerform('work_orders.approve_senior', ['super_admin', 'company_admin', 'senior_manager']);
    }

    public function uploadAttachment(User $user, WorkOrder $wo): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        return $user->canPerform('work_orders.create', ['super_admin', 'company_admin', 'senior_manager', 'site_manager']);
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

        return $user->canPerform('work_orders.create', ['office_staff', 'site_manager', 'senior_manager', 'company_admin', 'super_admin']);
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

        return $user->canPerform('work_orders.approve_senior', ['senior_manager', 'company_admin', 'super_admin']);
    }

    public function recordMeasurement(User $user, WorkOrder $wo): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        if ($user->isExplicitlyDenied('measurements.create') || $user->isExplicitlyDenied('measurements.update')) {
            return false;
        }

        return $user->hasPermission('measurements.create')
            || $user->hasPermission('measurements.update')
            || $user->canPerform('work_orders.create', ['site_supervisor', 'site_manager', 'office_staff', 'senior_manager', 'company_admin', 'super_admin']);
    }

    public function createRaBill(User $user, WorkOrder $wo): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        if ($user->isExplicitlyDenied('ra_bills.create')) {
            return false;
        }

        return $user->hasPermission('ra_bills.create')
            || $user->canPerform('work_orders.create', ['office_staff', 'site_manager', 'senior_manager', 'company_admin', 'super_admin']);
    }

    public function approveRaBillSite(User $user, WorkOrder $wo, RaBill $raBill): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo) || (int) $raBill->work_order_id !== (int) $wo->id) {
            return false;
        }

        if (!in_array($raBill->status, ['draft', 'submitted'], true)) {
            return false;
        }

        if ($user->isExplicitlyDenied('ra_bills.approve')) {
            return false;
        }

        return $user->hasPermission('ra_bills.approve')
            || $user->canPerform('work_orders.approve_site', ['site_manager', 'company_admin', 'super_admin']);
    }

    public function approveRaBillSenior(User $user, WorkOrder $wo, RaBill $raBill): bool
    {
        if (!$this->belongsToActiveTenant($user, $wo) || (int) $raBill->work_order_id !== (int) $wo->id) {
            return false;
        }

        if ($user->isRestrictedToAssignedSites() && $wo->site_id !== null && !$user->isAssignedToSite((int) $wo->site_id)) {
            return false;
        }

        if ($raBill->status !== 'site_manager_approved') {
            return false;
        }

        // Non-self-approval rule: user who verified Tier 1 cannot perform Tier 2 final approval
        if ((int) $raBill->site_manager_id === (int) $user->id) {
            return false;
        }

        if ($user->isExplicitlyDenied('ra_bills.approve')) {
            return false;
        }

        return $user->hasPermission('ra_bills.approve')
            || $user->canPerform('work_orders.approve_senior', ['senior_manager', 'company_admin', 'super_admin']);
    }

    public function rejectRaBill(User $user, WorkOrder $wo, RaBill $raBill): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo) || (int) $raBill->work_order_id !== (int) $wo->id) {
            return false;
        }

        if ($raBill->isLocked() || $raBill->status === 'rejected') {
            return false;
        }

        if ($user->isExplicitlyDenied('ra_bills.approve')) {
            return false;
        }

        return $user->hasPermission('ra_bills.approve')
            || $user->hasRole(['site_manager', 'senior_manager', 'company_admin', 'super_admin']);
    }

    public function uploadPo(User $user): bool
    {
        if (!$user->currentCompany()) {
            return false;
        }

        if ($user->isExplicitlyDenied('work_orders.create')) {
            return false;
        }

        if ($user->hasPermission('work_orders.create')) {
            return true;
        }

        if ($user->hasRole(['supervisor', 'site_supervisor', 'warehouse_manager'])) {
            return false;
        }

        return $user->hasRole(['office_staff', 'site_manager', 'senior_manager', 'company_admin', 'super_admin']);
    }

    public function manageUpload(User $user, WorkOrderUpload $upload): bool
    {
        $companyId = TenantContext::getCompanyId();
        if (!$companyId || !$user->currentCompany() || (int) $upload->company_id !== (int) $companyId) {
            return false;
        }

        return $this->uploadPo($user);
    }

    public function transitionLifecycle(User $user, WorkOrder $wo): bool
    {
        if (!$this->canAccessWorkOrderSite($user, $wo)) {
            return false;
        }

        return $user->canPerform('work_orders.update', ['office_staff', 'site_manager', 'senior_manager', 'company_admin', 'super_admin']);
    }
}

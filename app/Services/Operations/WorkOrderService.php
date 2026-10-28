<?php

namespace App\Services\Operations;

use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderApproval;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkOrderService
{
    public function createWorkOrder(array $data, User $creator): WorkOrder
    {
        $companyId = TenantContext::getCompanyId();
        if (!$companyId) {
            throw new InvalidArgumentException('Tenant context not established.');
        }

        $year = Carbon::now()->format('Y');
        $count = WorkOrder::withoutGlobalScopes()->where('company_id', $companyId)->count() + 1;
        $orderNumber = sprintf('WO-NSD-%s-%03d', $year, $count);

        return DB::transaction(function () use ($companyId, $data, $creator, $orderNumber) {
            $wo = new WorkOrder();
            $wo->company_id = $companyId;
            $wo->project_id = $data['project_id'];
            $wo->site_id = $data['site_id'] ?? null;
            $wo->order_type = $data['order_type'] ?? 'client_issued';
            $wo->order_number = $orderNumber;
            $wo->client_reference = $data['client_reference'] ?? null;
            $wo->title = $data['title'];
            $wo->description = $data['description'] ?? null;
            $wo->scope_of_work = $data['scope_of_work'] ?? null;
            $wo->estimated_amount = $data['estimated_amount'] ?? 0.00;
            $wo->target_start_date = $data['target_start_date'] ?? null;
            $wo->target_completion_date = $data['target_completion_date'] ?? null;
            $wo->status = 'submitted';
            $wo->created_by = $creator->id;
            $wo->save();

            return $wo;
        });
    }

    public function approveBySiteManager(WorkOrder $wo, User $manager, ?string $notes = null): WorkOrder
    {
        if ($wo->status !== 'submitted') {
            throw new InvalidArgumentException('Work order must be in submitted status for site manager approval.');
        }

        return DB::transaction(function () use ($wo, $manager, $notes) {
            $wo->status = 'site_manager_approved';
            $wo->site_manager_id = $manager->id;
            $wo->site_manager_approved_at = Carbon::now();
            $wo->site_manager_notes = $notes;
            $wo->save();

            WorkOrderApproval::create([
                'work_order_id' => $wo->id,
                'stage' => 'site_manager',
                'action' => 'approved',
                'actor_id' => $manager->id,
                'comments' => $notes,
            ]);

            return $wo;
        });
    }

    public function approveBySeniorManager(WorkOrder $wo, User $seniorManager, ?string $notes = null): WorkOrder
    {
        if ($wo->status !== 'site_manager_approved') {
            throw new InvalidArgumentException('Work order must be approved by site manager before senior manager approval.');
        }

        return DB::transaction(function () use ($wo, $seniorManager, $notes) {
            $wo->status = 'senior_manager_approved';
            $wo->senior_manager_id = $seniorManager->id;
            $wo->senior_manager_approved_at = Carbon::now();
            $wo->senior_manager_notes = $notes;
            $wo->save();

            WorkOrderApproval::create([
                'work_order_id' => $wo->id,
                'stage' => 'senior_manager',
                'action' => 'approved',
                'actor_id' => $seniorManager->id,
                'comments' => $notes,
            ]);

            return $wo;
        });
    }

    public function rejectWorkOrder(WorkOrder $wo, User $actor, string $reason): WorkOrder
    {
        if (!in_array($wo->status, ['submitted', 'site_manager_approved'], true)) {
            throw new InvalidArgumentException('Work order cannot be rejected in its current status.');
        }

        return DB::transaction(function () use ($wo, $actor, $reason) {
            $stage = $wo->status === 'submitted' ? 'site_manager' : 'senior_manager';
            $wo->status = 'rejected';
            $wo->rejected_by = $actor->id;
            $wo->rejected_at = Carbon::now();
            $wo->rejection_reason = $reason;
            $wo->save();

            WorkOrderApproval::create([
                'work_order_id' => $wo->id,
                'stage' => $stage,
                'action' => 'rejected',
                'actor_id' => $actor->id,
                'comments' => $reason,
            ]);

            return $wo;
        });
    }
}

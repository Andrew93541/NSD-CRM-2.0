<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkOrder extends Model
{
    use HasFactory, BelongsToCompany;

    public const DEFAULT_PAYMENT_TERMS = 'Running Account (RA) Bill every 15 days; 90% within 7 days of Site Manager certification, 5% retention released post defect liability.';

    public const DEFAULT_TERMS = "1. SCOPE & MEASUREMENTS: All earthwork, excavation, backfilling, or civil quantities shall be jointly measured with the NSD Site Engineer and certified by the Site Manager.\n2. HSE & SAFETY COMPLIANCE: Contractor/execution crew must strictly enforce PPE (helmets, reflective jackets, safety shoes) and machinery safety fitness certificates on site.\n3. QUALITY & MACHINERY: All plant, JCB/Excavators, and dumpers deployed must maintain valid PUC, insurance, and operator licences.\n4. STATUTORY DEDUCTIONS: Applicable TDS and GST compliance shall be governed by prevailing Indian tax norms.\n5. TWO-TIER AUTHORIZATION: This Work Order is valid for execution and billing only upon Tier-1 (Site Manager) and Tier-2 (Senior Management / VP) approval.";

    public const LIFECYCLE_DRAFT_PO = 'draft_po';
    public const LIFECYCLE_CONFIRMED = 'confirmed';
    public const LIFECYCLE_IN_PROGRESS = 'in_progress';
    public const LIFECYCLE_WORK_COMPLETE = 'work_complete';
    public const LIFECYCLE_FINAL_BILL_ISSUED = 'final_bill_issued';
    public const LIFECYCLE_DEFECT_LIABILITY = 'defect_liability';
    public const LIFECYCLE_CLOSED = 'closed';

    public const LIFECYCLE_STATUSES = [
        self::LIFECYCLE_DRAFT_PO => 'Draft PO',
        self::LIFECYCLE_CONFIRMED => 'Confirmed',
        self::LIFECYCLE_IN_PROGRESS => 'In progress',
        self::LIFECYCLE_WORK_COMPLETE => 'Work complete',
        self::LIFECYCLE_FINAL_BILL_ISSUED => 'Final bill issued',
        self::LIFECYCLE_DEFECT_LIABILITY => 'Defect liability',
        self::LIFECYCLE_CLOSED => 'Closed',
    ];

    protected $fillable = [
        'company_id',
        'project_id',
        'site_id',
        'client_id',
        'order_type',
        'service_type',
        'order_number',
        'po_number',
        'po_date',
        'job_location',
        'user_department_person',
        'buyer_name',
        'effective_date',
        'completion_date',
        'contract_tenure',
        'client_reference',
        'contractor_name',
        'contractor_gst',
        'contractor_contact',
        'payment_terms',
        'price_basis',
        'title',
        'subject',
        'description',
        'scope_of_work',
        'boq_items',
        'terms_and_conditions',
        'general_terms_text',
        'estimated_amount',
        'cgst_rate',
        'sgst_rate',
        'retention_percent',
        'defect_liability_months',
        'defect_liability_ends_on',
        'retention_release_due_on',
        'retention_released_at',
        'liquidated_damages',
        'current_revision',
        'base_order_value',
        'target_start_date',
        'target_completion_date',
        'status',
        'lifecycle_status',
        'is_draft_po',
        'created_by',
        'site_manager_id',
        'site_manager_approved_at',
        'site_manager_notes',
        'senior_manager_id',
        'senior_manager_approved_at',
        'senior_manager_notes',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'boq_items' => 'array',
            'po_date' => 'date',
            'effective_date' => 'date',
            'completion_date' => 'date',
            'defect_liability_months' => 'integer',
            'defect_liability_ends_on' => 'date',
            'retention_release_due_on' => 'date',
            'retention_released_at' => 'datetime',
            'is_draft_po' => 'boolean',
            'estimated_amount' => 'decimal:2',
            'cgst_rate' => 'decimal:2',
            'sgst_rate' => 'decimal:2',
            'retention_percent' => 'decimal:2',
            'current_revision' => 'integer',
            'base_order_value' => 'decimal:2',
            'target_start_date' => 'date',
            'target_completion_date' => 'date',
            'site_manager_approved_at' => 'datetime',
            'senior_manager_approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(WorkOrderItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(WorkOrderRevision::class)->orderByDesc('revision_number');
    }

    public function raBills(): HasMany
    {
        return $this->hasMany(RaBill::class)->orderBy('ra_number');
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(MeasurementEntry::class)->orderByDesc('measured_on')->orderByDesc('id');
    }

    public function exports(): HasMany
    {
        return $this->hasMany(BillExport::class)->latest('id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function siteManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'site_manager_id');
    }

    public function seniorManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'senior_manager_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(WorkOrderApproval::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(WorkOrderAttachment::class);
    }

    public function uploads(): HasMany
    {
        return $this->hasMany(WorkOrderUpload::class)->latest('id');
    }

    public function isDraftPo(): bool
    {
        return (bool) $this->is_draft_po
            || $this->lifecycle_status === self::LIFECYCLE_DRAFT_PO
            || $this->status === self::LIFECYCLE_DRAFT_PO;
    }

    public function canBeBilled(): bool
    {
        return !$this->isDraftPo();
    }

    public function effectiveLifecycleStatus(): string
    {
        if ($this->isDraftPo()) {
            return self::LIFECYCLE_DRAFT_PO;
        }

        if (!empty($this->lifecycle_status)) {
            return (string) $this->lifecycle_status;
        }

        return match ((string) $this->status) {
            'completed' => self::LIFECYCLE_WORK_COMPLETE,
            default => self::LIFECYCLE_CONFIRMED,
        };
    }

    public function measuredOrderValue(): float
    {
        $items = $this->relationLoaded('items') ? $this->items : $this->items()->get();
        $measurements = $this->relationLoaded('measurements')
            ? $this->measurements
            : $this->measurements()->get();

        $byItem = $measurements->groupBy('work_order_item_id');
        $total = 0.0;
        foreach ($items as $item) {
            $measuredQty = (float) ($byItem->get($item->id)?->sum('computed_quantity') ?? 0);
            $total += round($measuredQty * (float) $item->rate, 2);
        }

        return round($total, 2);
    }

    public function measuredConsumptionPercent(): float
    {
        $base = (float) ($this->base_order_value ?: $this->estimated_amount);
        if ($base <= 0) {
            return 0.0;
        }

        return min(100.0, round(($this->measuredOrderValue() / $base) * 100, 2));
    }

    public function completionAlertLevel(): string
    {
        $targetDate = $this->completion_date ?? $this->target_completion_date;
        if (!$targetDate) {
            return 'normal';
        }

        if (in_array($this->effectiveLifecycleStatus(), [
            self::LIFECYCLE_WORK_COMPLETE,
            self::LIFECYCLE_FINAL_BILL_ISSUED,
            self::LIFECYCLE_DEFECT_LIABILITY,
            self::LIFECYCLE_CLOSED,
        ], true)) {
            return 'normal';
        }

        $daysLeft = (int) now()->startOfDay()->diffInDays($targetDate->copy()->startOfDay(), false);

        if ($daysLeft < 0) {
            return 'overdue';
        }
        if ($daysLeft <= 7) {
            return 'due_7_days';
        }
        if ($daysLeft <= 30) {
            return 'due_30_days';
        }

        return 'normal';
    }

    public function completionAlertState(): string
    {
        return $this->completionAlertLevel();
    }

    public function retentionAndDlpSummary(): array
    {
        $retentionPct = (float) ($this->retention_percent ?? 5.0);
        $billedBase = $this->totalBilledAmount();
        $retentionHeld = round($billedBase * ($retentionPct / 100), 2);
        $dlpMonths = (int) ($this->defect_liability_months ?: 12);
        $dlpEndsOn = $this->defect_liability_ends_on;
        $releaseDueOn = $this->retention_release_due_on ?? $dlpEndsOn;

        $releaseStatus = 'not_started';
        if ($this->retention_released_at) {
            $releaseStatus = 'released';
        } elseif ($releaseDueOn) {
            $daysToRelease = (int) now()->startOfDay()->diffInDays($releaseDueOn->copy()->startOfDay(), false);
            if ($daysToRelease < 0) {
                $releaseStatus = 'overdue_release';
            } elseif ($daysToRelease <= 30) {
                $releaseStatus = 'due_within_30_days';
            } else {
                $releaseStatus = 'in_defect_liability';
            }
        }

        return [
            'retention_percent' => $retentionPct,
            'retention_held_amount' => $retentionHeld,
            'defect_liability_months' => $dlpMonths,
            'defect_liability_ends_on' => $dlpEndsOn?->toDateString(),
            'retention_release_due_on' => $releaseDueOn?->toDateString(),
            'retention_released_at' => $this->retention_released_at?->toDateTimeString(),
            'release_status' => $releaseStatus,
        ];
    }

    // Compatibility aliases for notes
    public function getSiteNotesAttribute(): ?string
    {
        return $this->site_manager_notes;
    }

    public function getSeniorNotesAttribute(): ?string
    {
        return $this->senior_manager_notes;
    }

    public function cgstAmount(): float
    {
        return round((float) $this->base_order_value * ((float) $this->cgst_rate / 100), 2);
    }

    public function sgstAmount(): float
    {
        return round((float) $this->base_order_value * ((float) $this->sgst_rate / 100), 2);
    }

    public function grossOrderValue(): float
    {
        return round((float) $this->base_order_value + $this->cgstAmount() + $this->sgstAmount(), 2);
    }

    public function totalBilledAmount(): float
    {
        if ($this->relationLoaded('raBills')) {
            return round((float) $this->raBills
                ->whereIn('status', RaBill::APPROVED_STATUSES)
                ->sum('current_bill_amount'), 2);
        }

        return round((float) $this->raBills()
            ->whereIn('status', RaBill::APPROVED_STATUSES)
            ->sum('current_bill_amount'), 2);
    }

    public function consumptionPercent(): float
    {
        $base = (float) ($this->base_order_value ?: $this->estimated_amount);
        if ($base <= 0) {
            return 0.0;
        }

        return min(100.0, round(($this->totalBilledAmount() / $base) * 100, 2));
    }

    public function consumptionWarningLevel(): string
    {
        $pct = $this->consumptionPercent();
        if ($pct >= 100.0) {
            return 'cap_100';
        }
        if ($pct >= 90.0) {
            return 'warning_90';
        }
        if ($pct >= 80.0) {
            return 'warning_80';
        }

        return 'normal';
    }
}

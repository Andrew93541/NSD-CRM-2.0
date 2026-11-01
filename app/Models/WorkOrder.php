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

    protected $fillable = [
        'company_id',
        'project_id',
        'site_id',
        'client_id',
        'order_type',
        'order_number',
        'po_number',
        'po_date',
        'job_location',
        'user_department_person',
        'effective_date',
        'completion_date',
        'client_reference',
        'contractor_name',
        'contractor_gst',
        'contractor_contact',
        'payment_terms',
        'title',
        'description',
        'scope_of_work',
        'boq_items',
        'terms_and_conditions',
        'estimated_amount',
        'cgst_rate',
        'sgst_rate',
        'retention_percent',
        'current_revision',
        'base_order_value',
        'target_start_date',
        'target_completion_date',
        'status',
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

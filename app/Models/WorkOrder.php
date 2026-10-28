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

    protected $fillable = [
        'company_id',
        'project_id',
        'site_id',
        'order_type',
        'order_number',
        'client_reference',
        'title',
        'description',
        'scope_of_work',
        'estimated_amount',
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
            'estimated_amount' => 'decimal:2',
            'target_start_date' => 'date',
            'target_completion_date' => 'date',
            'site_manager_approved_at' => 'datetime',
            'senior_manager_approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
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
}

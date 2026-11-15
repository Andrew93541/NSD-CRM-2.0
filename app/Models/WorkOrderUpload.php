<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderUpload extends Model
{
    use BelongsToCompany, HasFactory;

    public const STATUS_UPLOADED = 'uploaded';

    public const STATUS_REVIEW_PENDING = 'review_pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_UNREADABLE = 'unreadable';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'company_id',
        'uploaded_by',
        'work_order_id',
        'site_id',
        'original_filename',
        'storage_disk',
        'file_path',
        'file_size',
        'mime_type',
        'status',
        'is_scanned_or_unreadable',
        'is_draft_po',
        'extracted_payload',
        'reviewed_payload',
        'validation_errors',
        'override_reason',
        'confirmed_by',
        'confirmed_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'is_scanned_or_unreadable' => 'boolean',
            'is_draft_po' => 'boolean',
            'extracted_payload' => 'array',
            'reviewed_payload' => 'array',
            'validation_errors' => 'array',
            'confirmed_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function activePayload(): array
    {
        return $this->reviewed_payload ?? $this->extracted_payload ?? [];
    }

    public function hasValidationMismatches(): bool
    {
        return ! empty($this->validation_errors);
    }
}

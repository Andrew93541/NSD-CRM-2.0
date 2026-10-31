<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectDocument extends Model
{
    use HasFactory, BelongsToCompany;

    public const DOCUMENT_TYPES = [
        'drawing' => 'Engineering / CAD Drawing',
        'permit' => 'Regulatory Permit / NOC',
        'contract' => 'Client / Subcontractor Contract',
        'boq' => 'Bill of Quantities (BOQ)',
        'safety_plan' => 'HSE / Site Safety Plan',
        'other' => 'Other Project Document',
    ];

    public const ALLOWED_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'csv',
    ];

    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
    ];

    public const MAX_FILE_SIZE_KB = 10240; // 10 MB

    protected $fillable = [
        'company_id',
        'project_id',
        'site_id',
        'document_type',
        'title',
        'version',
        'original_name',
        'storage_disk',
        'file_path',
        'mime_type',
        'file_size',
        'visibility_roles',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'file_size' => 'integer',
            'visibility_roles' => 'array',
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

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        return __(self::DOCUMENT_TYPES[$this->document_type] ?? 'Other Project Document');
    }

    public function formattedSize(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return $bytes . ' B';
    }

    public function isVisibleTo(User $user): bool
    {
        if ($user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            return true;
        }

        if ($this->site_id && !$user->isAssignedToSite((int) $this->site_id)) {
            return false;
        }

        if (empty($this->visibility_roles)) {
            return true;
        }

        return $user->hasRole($this->visibility_roles);
    }
}


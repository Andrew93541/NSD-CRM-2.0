<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkerDocument extends Model
{
    use HasFactory, BelongsToCompany;

    public const DOCUMENT_TYPES = [
        'aadhaar_identity' => 'Aadhaar / Identity Proof',
        'pan_tax_id' => 'PAN / Tax ID',
        'address_proof' => 'Address Proof',
        'employment_contract' => 'Employment / Contract Document',
        'qualification_certificate' => 'Qualification / Trade Certificate',
        'licence' => 'Driving / Operator Licence',
        'medical_fitness' => 'Medical / Fitness Certificate',
        'photograph' => 'Worker Photograph',
        'other' => 'Other Document',
    ];

    public const ALLOWED_EXTENSIONS = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'doc',
        'docx',
    ];

    public const ALLOWED_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    public const MAX_FILE_SIZE_KB = 5120; // 5 MB

    protected $fillable = [
        'company_id',
        'worker_id',
        'document_type',
        'title',
        'document_number',
        'original_name',
        'storage_disk',
        'file_path',
        'mime_type',
        'file_size',
        'issue_date',
        'expiry_date',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'issue_date' => 'date',
            'expiry_date' => 'date',
        ];
    }

    public function isExpired(): bool
    {
        return $this->expiry_date !== null
            && $this->expiry_date->isPast()
            && !$this->expiry_date->isToday();
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        return $this->expiry_date !== null
            && !$this->isExpired()
            && $this->expiry_date->lte(now()->addDays($days)->endOfDay());
    }

    public function expiryStatus(int $days = 30): string
    {
        if ($this->expiry_date === null) {
            return 'no_expiry';
        }
        if ($this->isExpired()) {
            return 'expired';
        }
        if ($this->isExpiringSoon($days)) {
            return 'expiring_soon';
        }

        return 'valid';
    }

    public static function documentTypes(): array
    {
        return self::DOCUMENT_TYPES;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function typeLabel(): string
    {
        $label = self::DOCUMENT_TYPES[$this->document_type] ?? 'Other Document';

        return __($label);
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
}


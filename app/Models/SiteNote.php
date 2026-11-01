<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteNote extends Model
{
    use HasFactory, BelongsToCompany;

    public const CATEGORIES = [
        'general' => 'General Field Note',
        'safety_hse' => 'Safety / HSE Observation',
        'quality' => 'Quality Inspection',
        'client_instruction' => 'Client Instruction',
        'handover' => 'Shift Handover Note',
    ];

    protected $fillable = [
        'company_id',
        'project_id',
        'site_id',
        'user_id',
        'category',
        'title',
        'body',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    public function categoryLabel(): string
    {
        return __(self::CATEGORIES[$this->category] ?? ucfirst(str_replace('_', ' ', $this->category)));
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

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function convertedTaskInfo(): ?array
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('activity_logs')) {
            return null;
        }

        $log = AuditLog::where('company_id', $this->company_id)
            ->where('action', 'site_note.converted_to_task')
            ->where('subject_type', self::class)
            ->where('subject_id', $this->id)
            ->latest('id')
            ->first();

        if (!$log || empty($log->new_values['task_id'])) {
            return null;
        }

        return [
            'task_id' => (int) $log->new_values['task_id'],
            'task_code' => (string) ($log->new_values['task_code'] ?? ('#' . $log->new_values['task_id'])),
        ];
    }
}


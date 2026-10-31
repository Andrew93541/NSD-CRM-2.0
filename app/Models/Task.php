<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory, BelongsToCompany;

    public const PRIORITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    public const STATUSES = [
        'todo' => 'To Do',
        'in_progress' => 'In Progress',
        'review' => 'In Review',
        'completed' => 'Completed',
        'blocked' => 'Blocked',
    ];

    protected $fillable = [
        'company_id',
        'project_id',
        'site_id',
        'code',
        'title',
        'description',
        'priority',
        'status',
        'due_date',
        'completed_at',
        'assigned_to',
        'assigned_worker_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
        ];
    }

    public function isOverdue(): bool
    {
        if (!$this->due_date || $this->status === 'completed') {
            return false;
        }

        return $this->due_date->copy()->endOfDay()->isPast();
    }

    public function priorityLabel(): string
    {
        return __(self::PRIORITIES[$this->priority] ?? ucfirst($this->priority));
    }

    public function statusLabel(): string
    {
        return __(self::STATUSES[$this->status] ?? ucfirst(str_replace('_', ' ', $this->status)));
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

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignedWorker(): BelongsTo
    {
        return $this->belongsTo(Worker::class, 'assigned_worker_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->latest();
    }
}


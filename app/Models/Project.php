<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Project extends Model
{
    use HasFactory, BelongsToCompany;

    public const STATUSES = [
        'planning' => 'Planning',
        'active' => 'Active',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'archived' => 'Archived',
    ];

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'client_name',
        'manager_id',
        'description',
        'status',
        'start_date',
        'end_date',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function linkedSites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'project_sites')
            ->withPivot(['company_id', 'start_date', 'end_date', 'is_primary'])
            ->withTimestamps();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(ProjectMilestone::class)->orderBy('due_date');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class)->orderByDesc('version')->latest();
    }

    /**
     * All sites associated with this project (both direct sites.project_id and project_sites pivot).
     */
    public function allSites(): Collection
    {
        $direct = $this->sites()->get();
        if (!Schema::hasTable('project_sites')) {
            return $direct;
        }

        $linked = $this->linkedSites()->get();
        return $direct->merge($linked)->unique('id')->values();
    }

    public function overallProgressPercent(): int
    {
        if (!Schema::hasTable('project_milestones')) {
            return 0;
        }

        $milestones = $this->milestones()->get();
        if ($milestones->isEmpty()) {
            return 0;
        }

        return (int) round($milestones->avg('percent_complete'));
    }

    // Compatibility aliases for end_date / target_completion_date
    public function getTargetCompletionDateAttribute(): mixed
    {
        return $this->end_date;
    }

    public function setTargetCompletionDateAttribute(mixed $value): void
    {
        $this->attributes['end_date'] = $value;
    }
}

<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SiteAssignment extends Model
{
    use HasFactory, BelongsToCompany;

    protected $fillable = [
        'company_id',
        'site_id',
        'user_id',
        'worker_id',
        'role_on_site',
        'start_date',
        'end_date',
        'is_active',
        'assigned_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function worker(): BelongsTo
    {
        return $this->belongsTo(Worker::class);
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Record a dated worker site assignment and close any prior active assignment on a different site.
     */
    public static function recordWorkerAssignment(
        Worker $worker,
        ?int $newSiteId,
        ?string $startDate = null,
        ?int $assignedBy = null,
        ?string $notes = null
    ): ?self {
        if (!Schema::hasTable('site_assignments')) {
            return null;
        }

        $effectiveDate = $startDate ? Carbon::parse($startDate)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

        return DB::transaction(function () use ($worker, $newSiteId, $effectiveDate, $assignedBy, $notes) {
            // Close active assignments on other sites
            $activeQuery = static::withoutGlobalScopes()
                ->where('company_id', $worker->company_id)
                ->where('worker_id', $worker->id)
                ->where('is_active', true);

            if ($newSiteId !== null) {
                $existingSameSite = (clone $activeQuery)->where('site_id', $newSiteId)->first();
                if ($existingSameSite) {
                    return $existingSameSite;
                }
            }

            $activeQuery->update([
                'is_active' => false,
                'end_date' => $effectiveDate,
            ]);

            if ($newSiteId === null) {
                return null;
            }

            return static::create([
                'company_id' => $worker->company_id,
                'site_id' => $newSiteId,
                'worker_id' => $worker->id,
                'role_on_site' => $worker->trade_designation ?: 'worker',
                'start_date' => $effectiveDate,
                'end_date' => null,
                'is_active' => true,
                'assigned_by' => $assignedBy,
                'notes' => $notes,
            ]);
        });
    }

    /**
     * Record a dated staff (User) site assignment and sync with user_site pivot.
     */
    public static function recordUserAssignment(
        Site $site,
        User $user,
        string $roleOnSite = 'supervisor',
        ?string $startDate = null,
        ?int $assignedBy = null,
        ?string $notes = null,
        bool $closeOtherSites = false
    ): ?self {
        $site->assignedUsers()->syncWithoutDetaching([
            $user->id => ['role_in_site' => $roleOnSite],
        ]);

        if (!Schema::hasTable('site_assignments')) {
            return null;
        }

        $effectiveDate = $startDate ? Carbon::parse($startDate)->format('Y-m-d') : Carbon::today()->format('Y-m-d');

        return DB::transaction(function () use ($site, $user, $roleOnSite, $effectiveDate, $assignedBy, $notes, $closeOtherSites) {
            if ($closeOtherSites) {
                $otherSitesIds = static::withoutGlobalScopes()
                    ->where('company_id', $site->company_id)
                    ->where('user_id', $user->id)
                    ->where('site_id', '!=', $site->id)
                    ->where('is_active', true)
                    ->pluck('site_id')
                    ->all();

                static::withoutGlobalScopes()
                    ->where('company_id', $site->company_id)
                    ->where('user_id', $user->id)
                    ->where('site_id', '!=', $site->id)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'end_date' => $effectiveDate,
                    ]);

                if (!empty($otherSitesIds)) {
                    $user->sites()->detach($otherSitesIds);
                }
            }

            $existing = static::withoutGlobalScopes()
                ->where('company_id', $site->company_id)
                ->where('site_id', $site->id)
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();

            if ($existing) {
                $existing->update([
                    'role_on_site' => $roleOnSite,
                    'notes' => $notes ?? $existing->notes,
                ]);
                return $existing;
            }

            return static::create([
                'company_id' => $site->company_id,
                'site_id' => $site->id,
                'user_id' => $user->id,
                'role_on_site' => $roleOnSite,
                'start_date' => $effectiveDate,
                'end_date' => null,
                'is_active' => true,
                'assigned_by' => $assignedBy,
                'notes' => $notes,
            ]);
        });
    }
}


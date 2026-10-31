<?php

namespace App\Models;

use App\Services\Security\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LogicException;

class AuditLog extends Model
{
    protected $table = 'activity_logs';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(function () {
            throw new LogicException('Audit logs in activity_logs are append-only and cannot be modified.');
        });

        static::deleting(function () {
            throw new LogicException('Audit logs in activity_logs are append-only and cannot be deleted.');
        });
    }

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getEventAttribute(): string
    {
        return (string) ($this->attributes['action'] ?? '');
    }

    public function site()
    {
        return $this->belongsTo(Site::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public static function record(
        string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        mixed $descriptionOrSiteId = null,
        ?int $actorId = null,
        ?int $companyId = null
    ): ?self {
        try {
            if (Schema::hasTable('activity_logs')) {
                $siteId = is_int($descriptionOrSiteId)
                    ? $descriptionOrSiteId
                    : ($subject?->site_id ?? ($newValues['site_id'] ?? null));
                if (!$siteId && $subject instanceof Site) {
                    $siteId = $subject->id;
                }

                $projectId = $subject?->project_id ?? ($newValues['project_id'] ?? null);
                if (!$projectId && $subject instanceof Project) {
                    $projectId = $subject->id;
                }

                $description = is_string($descriptionOrSiteId) ? $descriptionOrSiteId : null;

                return self::create([
                    'company_id' => $companyId ?? TenantContext::getCompanyId() ?? ($subject?->company_id ?? null),
                    'project_id' => $projectId,
                    'site_id' => $siteId,
                    'user_id' => $actorId ?? Auth::id(),
                    'action' => $action,
                    'subject_type' => $subject ? get_class($subject) : null,
                    'subject_id' => $subject?->getKey(),
                    'old_values' => $oldValues,
                    'new_values' => $newValues,
                    'description' => $description,
                    'ip_address' => request()?->ip(),
                ]);
            }
        } catch (\Throwable $e) {
            // Fall through to application log
        }

        Log::info("AuditLog [{$action}]", [
            'user_id' => $actorId ?? Auth::id(),
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'old' => $oldValues,
            'new' => $newValues,
        ]);

        return null;
    }
}

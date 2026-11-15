<?php

namespace App\Models;

use App\Services\Security\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use LogicException;

class AuditLog extends Model
{
    public const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'pin',
        'pin_hash',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'remember_token',
        'kiosk_token',
        'qr_token',
        'bank_account_number',
        'bank_account_no',
        'api_key',
        'secret',
        'access_token',
        'refresh_token',
        'token',
    ];

    protected $table = 'activity_logs';

    protected $fillable = [
        'company_id',
        'project_id',
        'site_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'old_values',
        'new_values',
        'description',
        'ip_address',
    ];

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

    /**
     * Scope query strictly to a specific tenant (or the active TenantContext), failing closed if unresolved.
     */
    public function scopeForCompany(Builder $query, ?int $companyId = null): Builder
    {
        $targetCompanyId = $companyId ?? TenantContext::getCompanyId();
        if (! $targetCompanyId) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($this->getTable().'.company_id', (int) $targetCompanyId);
    }

    /**
     * Recursively redact sensitive credentials, tokens, PINs, and bank account numbers from audit payloads.
     */
    public static function sanitizePayload(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $sanitized = [];
        foreach ($values as $key => $value) {
            $normalizedKey = is_string($key) ? strtolower(trim($key)) : '';
            if ($normalizedKey !== '' && self::isSensitiveKey($normalizedKey)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = self::sanitizePayload($value);

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    protected static function isSensitiveKey(string $normalizedKey): bool
    {
        if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        foreach (['password', 'secret', 'pin_hash', 'kiosk_token', 'qr_token', 'bank_account'] as $fragment) {
            if (str_contains($normalizedKey, $fragment)) {
                return true;
            }
        }

        return false;
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
        $safeOldValues = self::sanitizePayload($oldValues);
        $safeNewValues = self::sanitizePayload($newValues);

        try {
            if (Schema::hasTable('activity_logs')) {
                $siteId = is_int($descriptionOrSiteId)
                    ? $descriptionOrSiteId
                    : ($subject?->site_id ?? ($newValues['site_id'] ?? null));
                if (! $siteId && $subject instanceof Site) {
                    $siteId = $subject->id;
                }

                $projectId = $subject?->project_id ?? ($newValues['project_id'] ?? null);
                if (! $projectId && $subject instanceof Project) {
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
                    'old_values' => $safeOldValues,
                    'new_values' => $safeNewValues,
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
            'old' => $safeOldValues,
            'new' => $safeNewValues,
        ]);

        return null;
    }
}

<?php

namespace App\Models;

use App\Models\Traits\BelongsToCompany;
use App\Services\Security\TenantContext;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class CrmNotification extends Model
{
    use HasFactory, BelongsToCompany;

    protected $table = 'crm_notifications';

    protected $fillable = [
        'company_id',
        'user_id',
        'site_id',
        'type',
        'title',
        'message',
        'action_url',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
        ];
    }

    public static function notifyUser(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?int $siteId = null,
        ?int $companyId = null
    ): ?self {
        if (!Schema::hasTable('crm_notifications')) {
            return null;
        }

        $resolvedCompanyId = $companyId ?? TenantContext::getCompanyId();
        if (!$resolvedCompanyId) {
            return null;
        }

        return static::create([
            'company_id' => $resolvedCompanyId,
            'user_id' => $userId,
            'site_id' => $siteId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
        ]);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}


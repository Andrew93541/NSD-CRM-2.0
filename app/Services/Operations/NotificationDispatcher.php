<?php

namespace App\Services\Operations;

use App\Models\CrmNotification;
use App\Models\User;
use App\Services\Security\TenantContext;
use Illuminate\Support\Facades\Schema;

class NotificationDispatcher
{
    /**
     * Send or refresh an unread in-app notification for a single user without duplicating unread alerts.
     */
    public static function notifyUserDeduplicated(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?int $siteId = null,
        ?int $companyId = null
    ): ?CrmNotification {
        if (! Schema::hasTable('crm_notifications') || $userId <= 0) {
            return null;
        }

        $resolvedCompanyId = $companyId ?? (int) TenantContext::getCompanyId();
        if ($resolvedCompanyId <= 0) {
            return null;
        }

        $existingUnread = CrmNotification::withoutGlobalScopes()
            ->where('company_id', $resolvedCompanyId)
            ->where('user_id', $userId)
            ->where('type', $type)
            ->when(
                $actionUrl !== null,
                fn ($q) => $q->where('action_url', $actionUrl),
                fn ($q) => $q->whereNull('action_url')
            )
            ->whereNull('read_at')
            ->first();

        if ($existingUnread) {
            $existingUnread->update([
                'title' => $title,
                'message' => $message,
                'site_id' => $siteId ?? $existingUnread->site_id,
            ]);

            return $existingUnread;
        }

        return CrmNotification::withoutGlobalScopes()->create([
            'company_id' => $resolvedCompanyId,
            'user_id' => $userId,
            'site_id' => $siteId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
        ]);
    }

    /**
     * Notify all active company users who hold the required approval permission and site access,
     * excluding the initiating actor / prior tier approver (for maker-checker / non-self-approval).
     */
    public static function notifyApprovers(
        int $companyId,
        string $permission,
        array $fallbackRoles,
        string $type,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?int $siteId = null,
        array $excludeUserIds = []
    ): int {
        if (! Schema::hasTable('crm_notifications') || $companyId <= 0) {
            return 0;
        }

        $excluded = array_values(array_unique(array_filter(array_map('intval', $excludeUserIds))));

        $candidates = User::where('is_active', true)
            ->when(! empty($excluded), fn ($q) => $q->whereNotIn('id', $excluded))
            ->whereHas('companies', function ($q) use ($companyId) {
                $q->where('companies.id', $companyId)
                    ->where('company_user.status', 'active');
            })
            ->get();

        $previousCompanyId = TenantContext::getCompanyId();
        TenantContext::setCompanyId($companyId);

        $count = 0;
        try {
            foreach ($candidates as $candidate) {
                if ($candidate->isExplicitlyDenied($permission, $companyId)) {
                    continue;
                }

                if (! $candidate->canPerform($permission, $fallbackRoles)) {
                    continue;
                }

                if ($siteId !== null && ! $candidate->isAssignedToSite($siteId)) {
                    continue;
                }

                $notif = self::notifyUserDeduplicated(
                    userId: (int) $candidate->id,
                    type: $type,
                    title: $title,
                    message: $message,
                    actionUrl: $actionUrl,
                    siteId: $siteId,
                    companyId: $companyId
                );

                if ($notif) {
                    $count++;
                }
            }
        } finally {
            TenantContext::setCompanyId($previousCompanyId);
        }

        return $count;
    }
}

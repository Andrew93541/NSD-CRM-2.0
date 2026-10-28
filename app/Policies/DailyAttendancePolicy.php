<?php

namespace App\Policies;

use App\Models\DailyAttendance;
use App\Models\Site;
use App\Models\User;

class DailyAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('attendance.view') || $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff']);
    }

    public function record(User $user, Site $site): bool
    {
        return ($user->hasPermission('attendance.record') || $user->hasRole(['site_supervisor', 'site_manager', 'company_admin', 'super_admin']))
            && $user->isAssignedToSite($site);
    }

    public function approve(User $user, DailyAttendance $att): bool
    {
        return $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])
            && $user->isAssignedToSite($att->site_id);
    }
}

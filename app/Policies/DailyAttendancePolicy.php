<?php

namespace App\Policies;

use App\Models\DailyAttendance;
use App\Models\Site;
use App\Models\User;

class DailyAttendancePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canPerform('attendance.view', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff']);
    }

    public function record(User $user, Site $site): bool
    {
        return $user->canPerform('attendance.record', ['site_supervisor', 'site_manager', 'company_admin', 'super_admin'])
            && $user->isAssignedToSite($site);
    }

    public function approve(User $user, DailyAttendance $att): bool
    {
        return $user->canPerform('attendance.approve', ['super_admin', 'company_admin', 'senior_manager', 'site_manager'])
            && $user->isAssignedToSite($att->site_id);
    }
}

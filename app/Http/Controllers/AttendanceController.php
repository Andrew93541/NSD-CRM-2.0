<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\AttendanceFlag;
use App\Models\AttendanceLock;
use App\Models\AuditLog;
use App\Models\DailyAttendance;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\Site;
use App\Models\Worker;
use App\Services\Operations\NotificationDispatcher;
use App\Services\Security\TenantContext;
use App\Services\Workforce\AttendanceService;
use App\Services\Workforce\PayrollCalculationService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService) {}

    public function muster(Request $request): View
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('attendance.mark')) {
            abort(403, 'Unauthorized to mark daily attendance. Explicit attendance marking permission is required.');
        }

        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin'])
            || ($user->hasRole('senior_manager') && ! $user->isRestrictedToAssignedSites());

        $accessibleSites = $hasAllSiteAccess
            ? Site::where('status', 'active')->get()
            : Site::whereIn('id', $user->accessibleSiteIds())->where('status', 'active')->get();

        $activeSiteSession = session('active_site_id');
        $selectedSiteId = $request->query('site_id') ?? $activeSiteSession ?? $accessibleSites->first()?->id;
        $date = $request->query('date') ?? Carbon::today()->format('Y-m-d');

        $site = $selectedSiteId ? Site::find($selectedSiteId) : null;
        if ($site && ! $user->isAssignedToSite($site)) {
            abort(403, 'Unauthorized to view or record muster roll for this site.');
        }

        $assignedWorkers = $site
            ? Worker::where('status', 'active')
                ->where(function ($q) use ($site, $date) {
                    $q->where('assigned_site_id', $site->id)
                        ->orWhereHas('attendances', function ($aq) use ($site, $date) {
                            $aq->where('site_id', $site->id)->whereDate('attendance_date', $date);
                        });
                })
                ->with(['subcontractor'])
                ->orderBy('first_name')
                ->get()
            : collect();

        $existingRecords = $site
            ? DailyAttendance::where('site_id', $site->id)
                ->whereDate('attendance_date', $date)
                ->get()
                ->groupBy('worker_id')
            : collect();

        $lock = ($site && Schema::hasTable('attendance_locks'))
            ? AttendanceLock::where('site_id', $site->id)->whereDate('attendance_date', $date)->first()
            : null;

        $holiday = ($site && Schema::hasTable('holidays'))
            ? Holiday::whereDate('holiday_date', $date)
                ->where(function ($q) use ($site) {
                    $q->whereNull('site_id')->orWhere('site_id', $site->id);
                })
                ->first()
            : null;

        $openFlagsCount = Schema::hasTable('attendance_flags')
            ? AttendanceFlag::where('status', 'open')
                ->when($site, fn ($q) => $q->where('site_id', $site->id))
                ->count()
            : 0;

        $pendingCorrectionsCount = AttendanceCorrection::where('status', 'pending')->count();
        $pendingLeavesCount = Schema::hasTable('leave_requests')
            ? LeaveRequest::where('status', 'pending')->count()
            : 0;

        return view('attendance.muster', compact(
            'accessibleSites',
            'selectedSiteId',
            'site',
            'date',
            'assignedWorkers',
            'existingRecords',
            'lock',
            'holiday',
            'openFlagsCount',
            'pendingCorrectionsCount',
            'pendingLeavesCount'
        ));
    }

    public function storeMuster(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('attendance.mark')) {
            abort(403, 'Unauthorized to mark daily attendance. Explicit attendance marking permission is required.');
        }

        $validated = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'attendance_date' => ['required', 'date'],
            'workers' => ['required', 'array'],
        ]);

        $site = Site::findOrFail($validated['site_id']);
        if (! $user->isAssignedToSite($site)) {
            abort(403, 'Unauthorized to record attendance for this site.');
        }

        // Supervisors (who are not managers/admins) can only mark the current day
        $isSupervisorOnly = $user->hasRole(['site_supervisor', 'supervisor'])
            && ! $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']);

        if ($isSupervisorOnly && Carbon::parse($validated['attendance_date'])->format('Y-m-d') !== Carbon::today()->format('Y-m-d')) {
            abort(403, 'Supervisors can only mark attendance for the current day. Submit a correction request for back-dated entries.');
        }

        if ($this->attendanceService->isSiteDateLocked($site->id, $validated['attendance_date'])) {
            abort(403, 'Attendance for this site and date is locked and cannot be edited directly.');
        }

        try {
            $this->attendanceService->recordMusterRoll(
                $site->id,
                $validated['attendance_date'],
                $validated['workers'],
                $user->id
            );

            if (Schema::hasTable('attendance_locks')) {
                $existingLock = AttendanceLock::where('site_id', $site->id)
                    ->whereDate('attendance_date', $validated['attendance_date'])
                    ->first();

                if (! $existingLock || $existingLock->stage === 'open') {
                    $this->attendanceService->transitionLockStage(
                        $site,
                        $validated['attendance_date'],
                        'submitted',
                        $user->id
                    );
                }
            }
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }

        return redirect()->route('attendance.muster', [
            'site_id' => $site->id,
            'date' => $validated['attendance_date'],
        ])->with('success', 'Muster roll recorded successfully.');
    }

    /**
     * Supervisor QR Badge Scan endpoint on Muster Roll.
     */
    public function qrScan(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('attendance.mark')) {
            abort(403, 'Unauthorized to mark attendance via QR scan.');
        }

        $qrThrottleKey = 'supervisor-qr-scan:'.$user->id.'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($qrThrottleKey, 60)) {
            return back()->withErrors(['badge_token' => 'Too many QR scan requests. Please wait a moment and try again.']);
        }
        RateLimiter::hit($qrThrottleKey, 60);

        $validated = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'badge_token' => ['required', 'string', 'max:100'],
            'punch_type' => ['nullable', 'in:toggle,in,out'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'device_id' => ['nullable', 'string', 'max:120'],
        ]);

        $site = Site::findOrFail($validated['site_id']);
        if (! $user->isAssignedToSite($site)) {
            abort(403, 'Unauthorized to scan QR badges for this site.');
        }

        $tokenOrCode = trim($validated['badge_token']);
        $worker = Worker::where('qr_token', $tokenOrCode)
            ->orWhere('code', $tokenOrCode)
            ->first();

        if (! $worker) {
            return back()->withErrors(['badge_token' => 'No active worker found matching this QR badge or worker code.']);
        }

        try {
            $punch = $this->attendanceService->recordPunch($worker, $site, [
                'punch_type' => $validated['punch_type'] ?? 'toggle',
                'source' => 'qr',
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'device_id' => $validated['device_id'] ?? ('SUP-QR-'.$user->id),
                'ip_address' => $request->ip(),
                'notes' => 'Supervisor QR Scan by '.$user->name,
            ], $user->id);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }

        AuditLog::record(
            'attendance.qr_scanned',
            $punch,
            null,
            [
                'worker_code' => $worker->code,
                'site_code' => $site->code,
                'punch_type' => $punch->punch_type,
                'punched_at' => $punch->punched_at->toIso8601String(),
            ],
            "QR badge scanned for {$worker->full_name} ({$worker->code}) at {$site->code}"
        );

        return redirect()->route('attendance.muster', [
            'site_id' => $site->id,
            'date' => Carbon::today()->format('Y-m-d'),
        ])->with('success', sprintf(
            'QR Punch %s recorded for %s (%s) at %s (Server Time).',
            strtoupper($punch->punch_type),
            $worker->full_name,
            $worker->code,
            $punch->punched_at->format('H:i:s')
        ));
    }

    /**
     * Three-stage lock transition:
     * - submitted (Supervisor / Site Manager / Senior Manager / Admin)
     * - site_locked (Site Manager / Senior Manager / Admin)
     * - payroll_locked (Senior Manager / Admin)
     * - open [Unlock] (Admin only, mandatory unlock_reason)
     */
    public function lockDay(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'attendance_date' => ['required', 'date'],
            'stage' => ['required', 'in:submitted,site_locked,payroll_locked,open'],
            'unlock_reason' => ['nullable', 'required_if:stage,open', 'string', 'min:5', 'max:500'],
        ]);

        $site = Site::findOrFail($validated['site_id']);
        if (! $user->isAssignedToSite($site)) {
            abort(403, 'Unauthorized to manage attendance locks for this site.');
        }

        $targetStage = $validated['stage'];

        if ($targetStage === 'site_locked' && ! $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'Only Site Managers or above can approve and lock daily site attendance.');
        }

        if ($targetStage === 'payroll_locked' && ! $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Only Senior Managers or Administrators can apply a Payroll Lock.');
        }

        if ($targetStage === 'open' && ! $user->hasRole(['super_admin', 'company_admin'])) {
            abort(403, 'Only Administrators can unlock a locked attendance period.');
        }

        $lock = $this->attendanceService->transitionLockStage(
            $site,
            $validated['attendance_date'],
            $targetStage,
            $user->id,
            $validated['unlock_reason'] ?? null
        );

        AuditLog::record(
            'attendance.lock_stage_changed',
            $lock,
            null,
            [
                'site_id' => $site->id,
                'attendance_date' => $validated['attendance_date'],
                'stage' => $targetStage,
                'unlock_reason' => $validated['unlock_reason'] ?? null,
            ],
            "Attendance lock stage changed to {$targetStage} for {$site->code} on {$validated['attendance_date']}"
        );

        return back()->with('success', 'Attendance lock stage updated to '.(AttendanceLock::STAGES[$targetStage] ?? $targetStage).'.');
    }

    public function conflicts(): View
    {
        $user = Auth::user();
        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin'])
            || ($user->hasRole('senior_manager') && ! $user->isRestrictedToAssignedSites());

        $accessibleSiteIds = $hasAllSiteAccess
            ? null
            : $user->accessibleSiteIds();

        $conflicts = DailyAttendance::where('has_conflict', true)
            ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
            ->with(['worker', 'site', 'project', 'submitter'])
            ->latest('attendance_date')
            ->paginate(25);

        return view('attendance.conflicts', compact('conflicts'));
    }

    public function requestCorrection(Request $request, DailyAttendance $attendance): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('attendance.edit')) {
            abort(403, 'Unauthorized to request or edit attendance corrections.');
        }

        if ($attendance->site && ! $user->isAssignedToSite($attendance->site)) {
            abort(403, 'Unauthorized to request corrections for this site.');
        }

        $validated = $request->validate([
            'proposed_clock_in' => ['nullable', 'date_format:H:i'],
            'proposed_clock_out' => ['nullable', 'date_format:H:i'],
            'proposed_status' => ['required', 'in:present,half_day,absent,paid_leave'],
            'reason' => ['required', 'string', 'min:5'],
        ]);

        $prevIn = $attendance->clock_in ? Carbon::parse($attendance->clock_in)->format('H:i:s') : null;
        $prevOut = $attendance->clock_out ? Carbon::parse($attendance->clock_out)->format('H:i:s') : null;
        $propIn = ! empty($validated['proposed_clock_in']) ? Carbon::parse($validated['proposed_clock_in'])->format('H:i:s') : null;
        $propOut = ! empty($validated['proposed_clock_out']) ? Carbon::parse($validated['proposed_clock_out'])->format('H:i:s') : null;

        $propHours = 0.00;
        if ($propIn && $propOut) {
            $in = Carbon::parse($propIn);
            $out = Carbon::parse($propOut);
            if ($out->gte($in)) {
                $propHours = round($in->diffInMinutes($out) / 60, 2);
            }
        }

        $shiftThreshold = (float) ($attendance->site?->shift_hours ?? 8.00);
        $propOvertime = round(max(0.0, $propHours - $shiftThreshold), 2);

        $correction = AttendanceCorrection::create([
            'company_id' => $attendance->company_id,
            'daily_attendance_id' => $attendance->id,
            'requested_by' => $user->id,
            'previous_status' => $attendance->status,
            'proposed_status' => $validated['proposed_status'],
            'previous_clock_in' => $prevIn,
            'proposed_clock_in' => $propIn,
            'previous_clock_out' => $prevOut,
            'proposed_clock_out' => $propOut,
            'previous_worked_hours' => $attendance->worked_hours,
            'proposed_worked_hours' => $propHours,
            'previous_overtime_hours' => $attendance->overtime_hours,
            'proposed_overtime_hours' => $propOvertime,
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        AuditLog::record(
            'attendance.correction_requested',
            $correction,
            [
                'status' => $attendance->status,
                'clock_in' => $prevIn,
                'clock_out' => $prevOut,
                'worked_hours' => (float) $attendance->worked_hours,
            ],
            [
                'proposed_status' => $validated['proposed_status'],
                'proposed_clock_in' => $propIn,
                'proposed_clock_out' => $propOut,
                'proposed_worked_hours' => $propHours,
                'reason' => $validated['reason'],
            ],
            "Attendance correction requested for worker #{$attendance->worker_id} on {$attendance->attendance_date->format('Y-m-d')}"
        );

        NotificationDispatcher::notifyApprovers(
            companyId: (int) $correction->company_id,
            permission: 'attendance.approve',
            fallbackRoles: ['super_admin', 'company_admin', 'senior_manager', 'site_manager'],
            type: 'attendance_correction_pending',
            title: 'Attendance Correction Awaiting Review',
            message: "{$user->name} requested an attendance correction for {$attendance->worker?->full_name} on {$attendance->attendance_date->format('d M Y')}.",
            actionUrl: route('attendance.approvals'),
            siteId: $attendance->site_id ? (int) $attendance->site_id : null,
            excludeUserIds: [(int) $user->id]
        );

        return back()->with('success', 'Correction request submitted for approval.');
    }

    public function corrections(): View
    {
        $user = Auth::user();
        if (! $user || (! $user->hasPermission('attendance.view') && ! $user->hasPermission('attendance.edit') && ! $user->hasPermission('attendance.approve'))) {
            abort(403, 'Unauthorized to view attendance corrections.');
        }

        $accessibleSiteIds = ($user->hasRole(['super_admin', 'company_admin']) || ($user->hasRole('senior_manager') && ! $user->isRestrictedToAssignedSites()))
            ? null
            : $user->accessibleSiteIds();

        $corrections = AttendanceCorrection::with(['attendance.worker', 'attendance.site', 'requester', 'reviewer'])
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->whereHas('attendance', fn ($aq) => $aq->whereIn('site_id', $accessibleSiteIds));
            })
            ->latest()
            ->paginate(20);

        return view('attendance.corrections', compact('corrections'));
    }

    public function reviewCorrection(Request $request, AttendanceCorrection $correction): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('attendance.approve')) {
            abort(403, 'Unauthorized to review attendance corrections. Explicit attendance approval permission is required.');
        }

        // Strict Non-Self-Approval Rule
        if ((int) $correction->requested_by === (int) $user->id) {
            abort(403, 'Nobody can approve or reject their own correction request.');
        }

        $att = $correction->attendance;
        if ($att && $att->site && ! $user->isAssignedToSite($att->site)) {
            abort(403, 'Unauthorized to review corrections for this site.');
        }

        if ($att && $att->lock_stage === 'payroll_locked' && ! $user->hasRole(['super_admin', 'company_admin'])) {
            abort(403, 'Payroll-locked attendance cannot be modified unless unlocked by an administrator.');
        }

        $validated = $request->validate([
            'action' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string'],
        ]);

        if ($correction->status !== 'pending') {
            return back()->withErrors([
                'status' => 'This attendance correction request has already been reviewed ('.$correction->status.').',
            ]);
        }

        $alreadyReviewed = false;

        DB::transaction(function () use ($correction, $validated, $user, &$att, &$alreadyReviewed) {
            $lockedCorrection = AttendanceCorrection::where('id', $correction->id)->lockForUpdate()->firstOrFail();
            if ($lockedCorrection->status !== 'pending') {
                $alreadyReviewed = true;

                return;
            }

            $lockedCorrection->status = $validated['action'];
            $lockedCorrection->reviewed_by = $user->id;
            $lockedCorrection->reviewed_at = Carbon::now();
            $lockedCorrection->review_notes = $validated['review_notes'] ?? null;
            $lockedCorrection->save();

            $correction->status = $lockedCorrection->status;
            $correction->reviewed_by = $lockedCorrection->reviewed_by;
            $correction->reviewed_at = $lockedCorrection->reviewed_at;
            $correction->review_notes = $lockedCorrection->review_notes;

            if ($validated['action'] === 'approved' && $att) {
                $lockedAtt = DailyAttendance::where('id', $att->id)->lockForUpdate()->firstOrFail();
                $lockedAtt->status = $lockedCorrection->proposed_status;
                $lockedAtt->clock_in = $lockedCorrection->proposed_clock_in;
                $lockedAtt->clock_out = $lockedCorrection->proposed_clock_out;
                $lockedAtt->worked_hours = $lockedCorrection->proposed_worked_hours;
                $lockedAtt->overtime_hours = $lockedCorrection->proposed_overtime_hours;
                $lockedAtt->approved_by = $user->id;
                $lockedAtt->approved_at = Carbon::now();
                $lockedAtt->approval_status = 'approved';
                $lockedAtt->save();
                $att = $lockedAtt;
            }

            AuditLog::record(
                'attendance.correction_reviewed',
                $lockedCorrection,
                [
                    'status' => 'pending',
                    'previous_attendance_status' => $lockedCorrection->previous_status,
                    'previous_clock_in' => $lockedCorrection->previous_clock_in,
                    'previous_clock_out' => $lockedCorrection->previous_clock_out,
                    'previous_worked_hours' => (float) $lockedCorrection->previous_worked_hours,
                ],
                [
                    'status' => $lockedCorrection->status,
                    'proposed_attendance_status' => $lockedCorrection->proposed_status,
                    'proposed_clock_in' => $lockedCorrection->proposed_clock_in,
                    'proposed_clock_out' => $lockedCorrection->proposed_clock_out,
                    'proposed_worked_hours' => (float) $lockedCorrection->proposed_worked_hours,
                    'reason' => $lockedCorrection->reason,
                    'review_notes' => $lockedCorrection->review_notes,
                    'reviewed_by' => $user->id,
                ],
                "Attendance correction #{$lockedCorrection->id} {$lockedCorrection->status} by {$user->name}"
            );
        });

        if ($alreadyReviewed) {
            return back()->withErrors([
                'status' => 'This attendance correction request has already been reviewed.',
            ]);
        }

        if ($correction->requested_by) {
            NotificationDispatcher::notifyUserDeduplicated(
                userId: (int) $correction->requested_by,
                type: 'correction_reviewed',
                title: 'Attendance Correction '.ucfirst($correction->status),
                message: "Your correction request for {$att?->worker?->full_name} was {$correction->status} by {$user->name}.",
                actionUrl: route('attendance.corrections'),
                siteId: $att?->site_id ? (int) $att->site_id : null,
                companyId: (int) $correction->company_id
            );
        }

        return back()->with('success', 'Attendance correction '.$validated['action'].' successfully.');
    }

    /**
     * Unified Approvals & Anomaly Inbox:
     * - Pending Attendance Corrections
     * - Open Attendance Anomaly Flags (outside geofence, shared device, impossible travel, overlaps)
     * - Pending Worker Leave Requests
     */
    public function approvals(Request $request): View
    {
        $user = Auth::user();
        if (! $user || (! $user->hasPermission('attendance.approve') && ! $user->hasPermission('attendance.view'))) {
            abort(403, 'Unauthorized to access attendance approvals.');
        }

        $accessibleSiteIds = ($user->hasRole(['super_admin', 'company_admin']) || ($user->hasRole('senior_manager') && ! $user->isRestrictedToAssignedSites()))
            ? null
            : $user->accessibleSiteIds();

        $pendingCorrections = AttendanceCorrection::with(['attendance.worker', 'attendance.site', 'requester'])
            ->where('status', 'pending')
            ->when($accessibleSiteIds !== null, function ($q) use ($accessibleSiteIds) {
                $q->whereHas('attendance', fn ($aq) => $aq->whereIn('site_id', $accessibleSiteIds));
            })
            ->latest()
            ->get();

        $openFlags = Schema::hasTable('attendance_flags')
            ? AttendanceFlag::with(['worker', 'site', 'attendance'])
                ->where('status', 'open')
                ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
                ->latest('attendance_date')
                ->get()
            : collect();

        $pendingLeaves = Schema::hasTable('leave_requests')
            ? LeaveRequest::with(['worker', 'site', 'requester'])
                ->where('status', 'pending')
                ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
                ->latest()
                ->get()
            : collect();

        return view('attendance.approvals', compact('pendingCorrections', 'openFlags', 'pendingLeaves'));
    }

    public function resolveFlag(Request $request, AttendanceFlag $flag): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('attendance.approve')) {
            abort(403, 'Unauthorized to resolve attendance anomaly flags.');
        }

        if ($flag->site && ! $user->isAssignedToSite($flag->site)) {
            abort(403, 'Unauthorized to resolve flags for this site.');
        }

        $validated = $request->validate([
            'status' => ['required', 'in:resolved,dismissed'],
            'resolution_notes' => ['required', 'string', 'min:3', 'max:500'],
        ]);

        $flag->update([
            'status' => $validated['status'],
            'resolved_by' => $user->id,
            'resolved_at' => Carbon::now(),
            'resolution_notes' => $validated['resolution_notes'],
        ]);

        AuditLog::record(
            'attendance.flag_resolved',
            $flag,
            ['status' => 'open'],
            ['status' => $validated['status'], 'resolution_notes' => $validated['resolution_notes']],
            "Attendance anomaly flag #{$flag->id} ({$flag->flag_type}) marked {$validated['status']}"
        );

        return back()->with('success', 'Attendance anomaly flag marked as '.$validated['status'].'.');
    }

    /**
     * Shifts, Leave Requests & Holiday Calendar Screen.
     */
    public function shiftsAndLeaves(Request $request): View
    {
        $user = Auth::user();
        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin'])
            || ($user->hasRole('senior_manager') && ! $user->isRestrictedToAssignedSites());

        $accessibleSites = $hasAllSiteAccess
            ? Site::where('status', 'active')->orderBy('name')->get()
            : Site::whereIn('id', $user->accessibleSiteIds())->where('status', 'active')->orderBy('name')->get();

        $accessibleSiteIds = $accessibleSites->pluck('id')->all();

        $shifts = Schema::hasTable('shifts')
            ? Shift::with('site')
                ->when(! $hasAllSiteAccess, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('site_id')->orWhereIn('site_id', $accessibleSiteIds)))
                ->orderBy('name')
                ->get()
            : collect();

        $holidays = Schema::hasTable('holidays')
            ? Holiday::with('site')
                ->when(! $hasAllSiteAccess, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('site_id')->orWhereIn('site_id', $accessibleSiteIds)))
                ->orderBy('holiday_date')
                ->get()
            : collect();

        $leaveTypes = Schema::hasTable('leave_types')
            ? LeaveType::orderBy('name')->get()
            : collect();

        $leaveRequests = Schema::hasTable('leave_requests')
            ? LeaveRequest::with(['worker', 'site', 'leaveType', 'requester', 'reviewer'])
                ->when(! $hasAllSiteAccess, fn ($q) => $q->where(fn ($sub) => $sub->whereNull('site_id')->orWhereIn('site_id', $accessibleSiteIds)))
                ->latest()
                ->paginate(20)
            : collect();

        $workers = Worker::where('status', 'active')
            ->when(! $hasAllSiteAccess, fn ($q) => $q->whereIn('assigned_site_id', $accessibleSiteIds))
            ->orderBy('first_name')
            ->limit(500)
            ->get();

        return view('attendance.shifts_leaves', compact('accessibleSites', 'shifts', 'holidays', 'leaveTypes', 'leaveRequests', 'workers'));
    }

    public function storeShift(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'Unauthorized to configure shifts.');
        }

        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'site_id' => [
                'nullable',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'half_day_hours' => ['required', 'numeric', 'min:1', 'max:12'],
            'full_day_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'overtime_after_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if (! empty($validated['site_id'])) {
            $site = Site::findOrFail($validated['site_id']);
            if (! $user->isAssignedToSite($site)) {
                abort(403, 'Unauthorized to configure shifts for this site.');
            }
        } elseif ($user->isRestrictedToAssignedSites() || ! $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Site-restricted managers must select an assigned site when configuring shifts.');
        }

        $isDefault = $request->boolean('is_default', false);
        $siteId = ! empty($validated['site_id']) ? (int) $validated['site_id'] : null;

        DB::transaction(function () use ($companyId, $siteId, $validated, $isDefault) {
            if ($isDefault) {
                Shift::where('company_id', $companyId)
                    ->when($siteId !== null, fn ($q) => $q->where('site_id', $siteId), fn ($q) => $q->whereNull('site_id'))
                    ->update(['is_default' => false]);
            }

            Shift::create([
                'company_id' => $companyId,
                'site_id' => $siteId,
                'name' => $validated['name'],
                'start_time' => $validated['start_time'].':00',
                'end_time' => $validated['end_time'].':00',
                'grace_minutes' => $validated['grace_minutes'],
                'half_day_hours' => $validated['half_day_hours'],
                'full_day_hours' => $validated['full_day_hours'],
                'overtime_after_hours' => $validated['overtime_after_hours'],
                'is_default' => $isDefault,
            ]);
        });

        return back()->with('success', 'Shift rule created successfully.');
    }

    public function updateShift(Request $request, Shift $shift): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'Unauthorized to configure shifts.');
        }

        if ($shift->site_id) {
            if (! $user->isAssignedToSite((int) $shift->site_id)) {
                abort(403, 'Unauthorized to edit shifts for this site.');
            }
        } elseif ($user->isRestrictedToAssignedSites() || ! $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Unauthorized to edit company-wide shifts.');
        }

        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'site_id' => [
                'nullable',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'half_day_hours' => ['required', 'numeric', 'min:1', 'max:12'],
            'full_day_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'overtime_after_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $siteId = ! empty($validated['site_id']) ? (int) $validated['site_id'] : null;
        if ($siteId && ! $user->isAssignedToSite($siteId)) {
            abort(403, 'Unauthorized to assign shift to this site.');
        }
        if (! $siteId && ($user->isRestrictedToAssignedSites() || ! $user->hasRole(['super_admin', 'company_admin', 'senior_manager']))) {
            abort(403, 'Site-restricted managers must select an assigned site.');
        }

        $isDefault = $request->boolean('is_default', false);

        DB::transaction(function () use ($shift, $companyId, $siteId, $validated, $isDefault) {
            if ($isDefault) {
                Shift::where('company_id', $companyId)
                    ->where('id', '!=', $shift->id)
                    ->when($siteId !== null, fn ($q) => $q->where('site_id', $siteId), fn ($q) => $q->whereNull('site_id'))
                    ->update(['is_default' => false]);
            }

            $shift->update([
                'site_id' => $siteId,
                'name' => $validated['name'],
                'start_time' => $validated['start_time'].':00',
                'end_time' => $validated['end_time'].':00',
                'grace_minutes' => $validated['grace_minutes'],
                'half_day_hours' => $validated['half_day_hours'],
                'full_day_hours' => $validated['full_day_hours'],
                'overtime_after_hours' => $validated['overtime_after_hours'],
                'is_default' => $isDefault,
            ]);
        });

        return back()->with('success', 'Shift rule updated successfully.');
    }

    public function destroyShift(Shift $shift): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'Unauthorized to delete shifts.');
        }

        if ($shift->site_id) {
            if (! $user->isAssignedToSite((int) $shift->site_id)) {
                abort(403, 'Unauthorized to delete shifts for this site.');
            }
        } elseif ($user->isRestrictedToAssignedSites() || ! $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Unauthorized to delete company-wide shifts.');
        }

        if (Schema::hasColumn('daily_attendances', 'shift_id') && DailyAttendance::where('shift_id', $shift->id)->exists()) {
            return back()->withErrors([
                'shift' => "Cannot delete shift {$shift->name} because historical attendance records reference it.",
            ]);
        }

        $shift->delete();

        return back()->with('success', 'Shift rule deleted.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Only Senior Managers or Administrators can manage the Holiday Calendar.');
        }

        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'site_id' => [
                'nullable',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:150'],
            'holiday_date' => ['required', 'date'],
            'is_paid' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if (! empty($validated['site_id'])) {
            $site = Site::findOrFail($validated['site_id']);
            if (! $user->isAssignedToSite($site)) {
                abort(403, 'Unauthorized to configure holidays for this site.');
            }
        } elseif ($user->isRestrictedToAssignedSites()) {
            abort(403, 'Site-restricted managers must select an assigned site when adding a holiday.');
        }

        Holiday::create([
            'company_id' => $companyId,
            'site_id' => $validated['site_id'] ?? null,
            'name' => $validated['name'],
            'holiday_date' => $validated['holiday_date'],
            'is_paid' => $request->boolean('is_paid', true),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Holiday added to calendar.');
    }

    public function updateHoliday(Request $request, Holiday $holiday): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Only Senior Managers or Administrators can manage the Holiday Calendar.');
        }

        if ($holiday->site_id && ! $user->isAssignedToSite((int) $holiday->site_id)) {
            abort(403, 'Unauthorized to edit holidays for this site.');
        }
        if (! $holiday->site_id && $user->isRestrictedToAssignedSites()) {
            abort(403, 'Unauthorized to edit company-wide holidays.');
        }

        if ($this->isHolidayDatePayrollLocked((int) $holiday->company_id, $holiday->site_id, $holiday->holiday_date->format('Y-m-d'))) {
            return back()->withErrors([
                'holiday' => 'Cannot modify a holiday falling in a payroll-locked attendance date.',
            ]);
        }

        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'site_id' => [
                'nullable',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'name' => ['required', 'string', 'max:150'],
            'holiday_date' => ['required', 'date'],
            'is_paid' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $holiday->update([
            'site_id' => $validated['site_id'] ?? null,
            'name' => $validated['name'],
            'holiday_date' => $validated['holiday_date'],
            'is_paid' => $request->boolean('is_paid', false),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Holiday updated.');
    }

    public function destroyHoliday(Holiday $holiday): RedirectResponse
    {
        $user = Auth::user();
        if (! $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Only Senior Managers or Administrators can manage the Holiday Calendar.');
        }

        if ($holiday->site_id && ! $user->isAssignedToSite((int) $holiday->site_id)) {
            abort(403, 'Unauthorized to delete holidays for this site.');
        }
        if (! $holiday->site_id && $user->isRestrictedToAssignedSites()) {
            abort(403, 'Unauthorized to delete company-wide holidays.');
        }

        if ($this->isHolidayDatePayrollLocked((int) $holiday->company_id, $holiday->site_id, $holiday->holiday_date->format('Y-m-d'))) {
            return back()->withErrors([
                'holiday' => 'Cannot delete a holiday falling in a payroll-locked attendance date.',
            ]);
        }

        $holiday->delete();

        return back()->with('success', 'Holiday removed from calendar.');
    }

    protected function isHolidayDatePayrollLocked(int $companyId, ?int $siteId, string $date): bool
    {
        if (! Schema::hasTable('attendance_locks')) {
            return false;
        }

        return AttendanceLock::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->whereDate('attendance_date', $date)
            ->where('stage', 'payroll_locked')
            ->when($siteId !== null, fn ($q) => $q->where('site_id', $siteId))
            ->exists();
    }

    public function storeLeaveType(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || $user->isExplicitlyDenied('attendance.manage') || ! $user->canPerform('attendance.manage', ['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Only Administrators or Senior Managers can configure leave types.');
        }

        $companyId = (int) TenantContext::getCompanyId();

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('leave_types', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'annual_quota' => ['required', 'integer', 'min:0', 'max:365'],
            'is_paid' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        LeaveType::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'code' => $validated['code'],
            'annual_quota' => (int) $validated['annual_quota'],
            'is_paid' => $request->boolean('is_paid', true),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', "Leave type {$validated['name']} created.");
    }

    public function updateLeaveType(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || $user->isExplicitlyDenied('attendance.manage') || ! $user->canPerform('attendance.manage', ['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Only Administrators or Senior Managers can configure leave types.');
        }

        $companyId = (int) TenantContext::getCompanyId();

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('leave_types', 'code')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($leaveType->id),
            ],
            'annual_quota' => ['required', 'integer', 'min:0', 'max:365'],
            'is_paid' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $leaveType->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'annual_quota' => (int) $validated['annual_quota'],
            'is_paid' => $request->boolean('is_paid', false),
            'is_active' => $request->boolean('is_active', false),
        ]);

        return back()->with('success', "Leave type {$leaveType->name} updated.");
    }

    public function destroyLeaveType(LeaveType $leaveType): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || $user->isExplicitlyDenied('attendance.manage') || ! $user->canPerform('attendance.manage', ['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Only Administrators or Senior Managers can configure leave types.');
        }

        if (LeaveRequest::where('leave_type_id', $leaveType->id)->exists()) {
            return back()->withErrors([
                'leave_type' => "Cannot delete leave type {$leaveType->name} because worker leave requests reference it. Mark it Inactive instead.",
            ]);
        }

        $name = $leaveType->name;
        $leaveType->delete();

        return back()->with('success', "Leave type {$name} deleted.");
    }

    public function storeLeaveRequest(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (! $user
            || $user->isExplicitlyDenied('attendance.mark')
            || $user->isExplicitlyDenied('attendance.record')
            || (! $user->canPerform('attendance.mark', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'supervisor'])
                && ! $user->hasPermission('attendance.record')
                && ! $user->hasPermission('attendance.approve'))
        ) {
            abort(403, 'Unauthorized to submit worker leave requests.');
        }

        $companyId = (int) TenantContext::getCompanyId();

        $validated = $request->validate([
            'worker_id' => [
                'required',
                Rule::exists('workers', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'leave_type_id' => ['nullable', 'integer'],
            'leave_type_name' => ['required_without:leave_type_id', 'nullable', 'string', 'max:60'],
            'is_paid' => ['nullable', 'boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $worker = Worker::findOrFail($validated['worker_id']);
        if ($worker->assignedSite && ! $user->isAssignedToSite($worker->assignedSite)) {
            abort(403, 'Unauthorized to submit leave requests for workers at an unassigned site.');
        }
        if (! $worker->assignedSite && $user->isRestrictedToAssignedSites()) {
            abort(403, 'Site-restricted users cannot submit leave requests for unassigned workers.');
        }

        $validated['is_paid'] = $request->boolean('is_paid', true);
        $payload = $this->attendanceService->validateAndBuildLeavePayload($worker, $validated, $user->id);

        $leaveRequest = LeaveRequest::create($payload);

        NotificationDispatcher::notifyApprovers(
            companyId: $companyId,
            permission: 'attendance.approve',
            fallbackRoles: ['super_admin', 'company_admin', 'senior_manager', 'site_manager'],
            type: 'leave_request_pending',
            title: 'Worker Leave Request Awaiting Approval',
            message: "{$user->name} submitted a leave request for {$worker->full_name} ({$leaveRequest->start_date->format('d M')} – {$leaveRequest->end_date->format('d M Y')}).",
            actionUrl: route('attendance.approvals'),
            siteId: $leaveRequest->site_id ? (int) $leaveRequest->site_id : null,
            excludeUserIds: [(int) $user->id]
        );

        return back()->with('success', 'Leave request submitted for approval.');
    }

    public function reviewLeaveRequest(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('attendance.approve')) {
            abort(403, 'Unauthorized to review leave requests.');
        }

        if ($leaveRequest->requested_by && (int) $leaveRequest->requested_by === (int) $user->id) {
            abort(403, 'Nobody can approve their own leave request.');
        }

        if ($leaveRequest->site && ! $user->isAssignedToSite($leaveRequest->site)) {
            abort(403, 'Unauthorized to review leave requests for this site.');
        }

        $validated = $request->validate([
            'action' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:500'],
        ]);

        if ($leaveRequest->status !== 'pending') {
            return back()->withErrors([
                'status' => 'This leave request has already been reviewed ('.$leaveRequest->status.').',
            ]);
        }

        $alreadyReviewed = false;

        DB::transaction(function () use ($leaveRequest, $validated, $user, &$alreadyReviewed) {
            $lockedLeave = LeaveRequest::where('id', $leaveRequest->id)->lockForUpdate()->firstOrFail();
            if ($lockedLeave->status !== 'pending') {
                $alreadyReviewed = true;

                return;
            }

            $lockedLeave->update([
                'status' => $validated['action'],
                'reviewed_by' => $user->id,
                'reviewed_at' => Carbon::now(),
                'review_notes' => $validated['review_notes'] ?? null,
            ]);

            if ($validated['action'] === 'approved') {
                $this->attendanceService->applyApprovedLeave($lockedLeave, $user->id);
            }
        });

        if ($alreadyReviewed) {
            return back()->withErrors([
                'status' => 'This leave request has already been reviewed.',
            ]);
        }

        if ($leaveRequest->requested_by && (int) $leaveRequest->requested_by !== (int) $user->id) {
            NotificationDispatcher::notifyUserDeduplicated(
                userId: (int) $leaveRequest->requested_by,
                type: 'leave_request_reviewed',
                title: 'Leave Request '.ucfirst($validated['action']),
                message: "Leave request for {$leaveRequest->worker?->full_name} was {$validated['action']} by {$user->name}.",
                actionUrl: route('attendance.shifts_leaves'),
                siteId: $leaveRequest->site_id ? (int) $leaveRequest->site_id : null,
                companyId: (int) $leaveRequest->company_id
            );
        }

        return back()->with('success', 'Leave request '.$validated['action'].' successfully.');
    }

    /**
     * Exports payroll-ready CSV for a date range and optional site filter.
     */
    public function exportPayrollCsv(Request $request): StreamedResponse
    {
        $user = Auth::user();
        if (! $user || ! $user->hasPermission('attendance.export')) {
            abort(403, 'Unauthorized to export payroll attendance data.');
        }

        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $siteId = $request->query('site_id');

        if ($siteId) {
            $site = Site::findOrFail($siteId);
            if (! $user->isAssignedToSite($site)) {
                abort(403, 'Unauthorized to export payroll for this site.');
            }
        }

        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin'])
            || ($user->hasRole(['senior_manager', 'office_staff']) && ! $user->isRestrictedToAssignedSites());

        $accessibleSiteIds = $hasAllSiteAccess
            ? null
            : $user->accessibleSiteIds();

        $records = DailyAttendance::with(['worker', 'site'])
            ->whereDate('attendance_date', '>=', $startDate)
            ->whereDate('attendance_date', '<=', $endDate)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
            ->get();

        $byWorker = $records->groupBy('worker_id');
        $filename = "nsd_payroll_export_{$startDate}_to_{$endDate}.csv";
        $payrollService = app(PayrollCalculationService::class);
        $periodStart = Carbon::parse($startDate);
        $periodEnd = Carbon::parse($endDate);

        return response()->streamDownload(function () use ($byWorker, $startDate, $endDate, $payrollService, $periodStart, $periodEnd) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Worker Code',
                'Worker Name',
                'Worker Type',
                'Pay Type',
                'Trade Designation',
                'Period Start',
                'Period End',
                'Present Days',
                'Half Days',
                'Paid Leave Days',
                'Total Worked Hours',
                'Overtime Hours',
                'Base Rate (INR)',
                'Overtime Rate / Hr (INR)',
                'Estimated Gross Pay (INR)',
                'Advance Deductions (INR)',
                'Estimated Net Pay (INR)',
            ]);

            foreach ($byWorker as $workerId => $attList) {
                $worker = $attList->first()?->worker;
                if (! $worker) {
                    continue;
                }

                // Deduplicate by date for present/half_day/paid_leave counts
                $dedupedAttList = $attList->unique(fn ($item) => $item->attendance_date->format('Y-m-d'));
                $metrics = $payrollService->calculateWorkerMetrics($worker, $dedupedAttList, $periodStart, $periodEnd);

                fputcsv($out, [
                    $this->sanitizePayrollCsvCell((string) $worker->code),
                    $this->sanitizePayrollCsvCell((string) $worker->full_name),
                    $this->sanitizePayrollCsvCell((string) $worker->worker_type),
                    $this->sanitizePayrollCsvCell((string) $metrics['pay_type']),
                    $this->sanitizePayrollCsvCell((string) $worker->trade_designation),
                    $this->sanitizePayrollCsvCell((string) $startDate),
                    $this->sanitizePayrollCsvCell((string) $endDate),
                    $metrics['present_days'],
                    $metrics['half_days'],
                    $metrics['paid_leave_days'],
                    number_format((float) $attList->sum('worked_hours'), 2, '.', ''),
                    number_format((float) $attList->sum('overtime_hours'), 2, '.', ''),
                    number_format((float) $metrics['base_rate'], 2, '.', ''),
                    number_format((float) $metrics['overtime_rate'], 2, '.', ''),
                    number_format((float) $metrics['gross_pay'], 2, '.', ''),
                    number_format((float) $metrics['advance_deduction'], 2, '.', ''),
                    number_format((float) $metrics['net_pay'], 2, '.', ''),
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function sanitizePayrollCsvCell(string $value): string
    {
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * Date-Wise & Month-Wise Attendance Present Register for Admins & Managers.
     */
    public function register(Request $request): View
    {
        $user = Auth::user();
        if (! $user || (! $user->hasPermission('attendance.view') && ! $user->hasPermission('attendance.mark'))) {
            abort(403, 'Unauthorized to view attendance register.');
        }

        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin'])
            || ($user->hasRole(['senior_manager', 'office_staff']) && ! $user->isRestrictedToAssignedSites());

        $accessibleSites = $hasAllSiteAccess
            ? Site::where('status', 'active')->orderBy('code')->get()
            : Site::whereIn('id', $user->accessibleSiteIds())->where('status', 'active')->orderBy('code')->get();

        $accessibleSiteIds = $hasAllSiteAccess
            ? null
            : $accessibleSites->pluck('id')->all();

        $mode = $request->query('mode', 'month');
        if (! in_array($mode, ['date', 'month'], true)) {
            $mode = 'month';
        }

        $date = $request->query('date', Carbon::today()->format('Y-m-d'));
        $month = $request->query('month', Carbon::parse($date)->format('Y-m'));
        $siteId = $request->filled('site_id') ? (int) $request->query('site_id') : null;
        $workerType = $request->query('worker_type');
        $presenceFilter = $request->query('presence_filter', 'all');
        $search = $request->query('search');

        if ($siteId) {
            $site = Site::findOrFail($siteId);
            if (! $user->isAssignedToSite($site)) {
                abort(403, 'Unauthorized to view attendance register for this site.');
            }
        }

        $dateRegister = null;
        $monthMatrix = null;

        $perPage = max(5, min(100, (int) $request->query('per_page', 25)));

        if ($mode === 'month') {
            $monthMatrix = $this->attendanceService->getMonthWiseAttendanceMatrix(
                $month,
                $siteId,
                $workerType,
                $search,
                $accessibleSiteIds,
                $perPage
            );
        } else {
            $dateRegister = $this->attendanceService->getDateWiseAttendanceRegister(
                $date,
                $siteId,
                $workerType,
                $presenceFilter,
                $search,
                $accessibleSiteIds
            );
        }

        return view('attendance.register', compact(
            'mode',
            'date',
            'month',
            'siteId',
            'workerType',
            'presenceFilter',
            'search',
            'accessibleSites',
            'dateRegister',
            'monthMatrix'
        ));
    }
}

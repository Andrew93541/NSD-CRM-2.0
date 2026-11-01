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
use App\Services\Security\TenantContext;
use App\Services\Workforce\AttendanceService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService)
    {
    }

    public function muster(Request $request): View
    {
        $user = Auth::user();
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff'])
            && !$user->hasPermission('attendance.view')
            && !$user->hasPermission('attendance.record')
            && !$user->hasPermission('attendance.muster')
        ) {
            abort(403, 'Unauthorized to access attendance muster roll.');
        }

        $accessibleSites = $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])
            ? Site::where('status', 'active')->get()
            : $user->sites()->where('status', 'active')->get();

        $activeSiteSession = session('active_site_id');
        $selectedSiteId = $request->query('site_id') ?? $activeSiteSession ?? $accessibleSites->first()?->id;
        $date = $request->query('date') ?? Carbon::today()->format('Y-m-d');

        $site = $selectedSiteId ? Site::find($selectedSiteId) : null;
        if ($site && !$user->isAssignedToSite($site)) {
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

        $validated = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'attendance_date' => ['required', 'date'],
            'workers' => ['required', 'array'],
        ]);

        $site = Site::findOrFail($validated['site_id']);
        if (!$user->isAssignedToSite($site)) {
            abort(403, 'Unauthorized to record attendance for this site.');
        }

        // Supervisors (who are not managers/admins) can only mark the current day
        $isSupervisorOnly = $user->hasRole(['site_supervisor', 'supervisor'])
            && !$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']);

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

                if (!$existingLock || $existingLock->stage === 'open') {
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

        $validated = $request->validate([
            'site_id' => ['required', 'exists:sites,id'],
            'badge_token' => ['required', 'string', 'max:100'],
            'punch_type' => ['nullable', 'in:toggle,in,out'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'device_id' => ['nullable', 'string', 'max:120'],
        ]);

        $site = Site::findOrFail($validated['site_id']);
        if (!$user->isAssignedToSite($site)) {
            abort(403, 'Unauthorized to scan QR badges for this site.');
        }

        $tokenOrCode = trim($validated['badge_token']);
        $worker = Worker::where('qr_token', $tokenOrCode)
            ->orWhere('code', $tokenOrCode)
            ->first();

        if (!$worker) {
            return back()->withErrors(['badge_token' => 'No active worker found matching this QR badge or worker code.']);
        }

        try {
            $punch = $this->attendanceService->recordPunch($worker, $site, [
                'punch_type' => $validated['punch_type'] ?? 'toggle',
                'source' => 'qr',
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'device_id' => $validated['device_id'] ?? ('SUP-QR-' . $user->id),
                'ip_address' => $request->ip(),
                'notes' => 'Supervisor QR Scan by ' . $user->name,
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
        if (!$user->isAssignedToSite($site)) {
            abort(403, 'Unauthorized to manage attendance locks for this site.');
        }

        $targetStage = $validated['stage'];

        if ($targetStage === 'site_locked' && !$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'Only Site Managers or above can approve and lock daily site attendance.');
        }

        if ($targetStage === 'payroll_locked' && !$user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Only Senior Managers or Administrators can apply a Payroll Lock.');
        }

        if ($targetStage === 'open' && !$user->hasRole(['super_admin', 'company_admin'])) {
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

        return back()->with('success', 'Attendance lock stage updated to ' . (AttendanceLock::STAGES[$targetStage] ?? $targetStage) . '.');
    }

    public function conflicts(): View
    {
        $user = Auth::user();
        $accessibleSiteIds = $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])
            ? null
            : $user->sites()->pluck('sites.id')->all();

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
        if ($attendance->site && !$user->isAssignedToSite($attendance->site)) {
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
        $propIn = !empty($validated['proposed_clock_in']) ? Carbon::parse($validated['proposed_clock_in'])->format('H:i:s') : null;
        $propOut = !empty($validated['proposed_clock_out']) ? Carbon::parse($validated['proposed_clock_out'])->format('H:i:s') : null;

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

        AttendanceCorrection::create([
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

        return back()->with('success', 'Correction request submitted for approval.');
    }

    public function corrections(): View
    {
        $user = Auth::user();
        $accessibleSiteIds = $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])
            ? null
            : $user->sites()->pluck('sites.id')->all();

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
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'Unauthorized to review attendance corrections.');
        }

        // Strict Non-Self-Approval Rule
        if ((int) $correction->requested_by === (int) $user->id) {
            abort(403, 'Nobody can approve or reject their own correction request.');
        }

        $att = $correction->attendance;
        if ($att && $att->site && !$user->isAssignedToSite($att->site)) {
            abort(403, 'Unauthorized to review corrections for this site.');
        }

        if ($att && $att->lock_stage === 'payroll_locked' && !$user->hasRole(['super_admin', 'company_admin'])) {
            abort(403, 'Payroll-locked attendance cannot be modified unless unlocked by an administrator.');
        }

        $validated = $request->validate([
            'action' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string'],
        ]);

        $correction->status = $validated['action'];
        $correction->reviewed_by = $user->id;
        $correction->reviewed_at = Carbon::now();
        $correction->review_notes = $validated['review_notes'] ?? null;
        $correction->save();

        if ($validated['action'] === 'approved' && $att) {
            $att->status = $correction->proposed_status;
            $att->clock_in = $correction->proposed_clock_in;
            $att->clock_out = $correction->proposed_clock_out;
            $att->worked_hours = $correction->proposed_worked_hours;
            $att->overtime_hours = $correction->proposed_overtime_hours;
            $att->approved_by = $user->id;
            $att->approved_at = Carbon::now();
            $att->approval_status = 'approved';
            $att->save();
        }

        AuditLog::record(
            'attendance.correction_reviewed',
            $correction,
            ['status' => 'pending'],
            ['status' => $correction->status, 'reviewed_by' => $user->id],
            "Attendance correction #{$correction->id} {$correction->status} by {$user->name}"
        );

        if ($correction->requested_by) {
            \App\Models\CrmNotification::notifyUser(
                (int) $correction->requested_by,
                'correction_reviewed',
                'Attendance Correction ' . ucfirst($correction->status),
                "Your correction request for {$att?->worker?->full_name} was {$correction->status} by {$user->name}.",
                route('attendance.corrections'),
                $att?->site_id,
                $correction->company_id
            );
        }

        return back()->with('success', 'Attendance correction ' . $validated['action'] . ' successfully.');
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
        $accessibleSiteIds = $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])
            ? null
            : $user->sites()->pluck('sites.id')->all();

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
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'Unauthorized to resolve attendance anomaly flags.');
        }

        if ($flag->site && !$user->isAssignedToSite($flag->site)) {
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

        return back()->with('success', 'Attendance anomaly flag marked as ' . $validated['status'] . '.');
    }

    /**
     * Shifts, Leave Requests & Holiday Calendar Screen.
     */
    public function shiftsAndLeaves(Request $request): View
    {
        $user = Auth::user();
        $accessibleSites = $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])
            ? Site::where('status', 'active')->orderBy('name')->get()
            : $user->sites()->where('status', 'active')->orderBy('name')->get();

        $accessibleSiteIds = $accessibleSites->pluck('id')->all();

        $shifts = Schema::hasTable('shifts')
            ? Shift::with('site')->orderBy('name')->get()
            : collect();

        $holidays = Schema::hasTable('holidays')
            ? Holiday::with('site')->orderBy('holiday_date')->get()
            : collect();

        $leaveRequests = Schema::hasTable('leave_requests')
            ? LeaveRequest::with(['worker', 'site', 'requester', 'reviewer'])
                ->when(!$user->hasRole(['super_admin', 'company_admin', 'senior_manager']), fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
                ->latest()
                ->paginate(20)
            : collect();

        $workers = Worker::where('status', 'active')
            ->when(!$user->hasRole(['super_admin', 'company_admin', 'senior_manager']), fn ($q) => $q->whereIn('assigned_site_id', $accessibleSiteIds))
            ->orderBy('first_name')
            ->get();

        return view('attendance.shifts_leaves', compact('accessibleSites', 'shifts', 'holidays', 'leaveRequests', 'workers'));
    }

    public function storeShift(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'Unauthorized to configure shifts.');
        }

        $validated = $request->validate([
            'site_id' => ['nullable', 'exists:sites,id'],
            'name' => ['required', 'string', 'max:100'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['required', 'integer', 'min:0', 'max:120'],
            'half_day_hours' => ['required', 'numeric', 'min:1', 'max:12'],
            'full_day_hours' => ['required', 'numeric', 'min:1', 'max:24'],
            'overtime_after_hours' => ['required', 'numeric', 'min:1', 'max:24'],
        ]);

        if (!empty($validated['site_id'])) {
            $site = Site::findOrFail($validated['site_id']);
            if (!$user->isAssignedToSite($site)) {
                abort(403, 'Unauthorized to configure shifts for this site.');
            }
        }

        Shift::create([
            'company_id' => TenantContext::getCompanyId(),
            'site_id' => $validated['site_id'] ?? null,
            'name' => $validated['name'],
            'start_time' => $validated['start_time'] . ':00',
            'end_time' => $validated['end_time'] . ':00',
            'grace_minutes' => $validated['grace_minutes'],
            'half_day_hours' => $validated['half_day_hours'],
            'full_day_hours' => $validated['full_day_hours'],
            'overtime_after_hours' => $validated['overtime_after_hours'],
            'is_default' => false,
        ]);

        return back()->with('success', 'Shift rule created successfully.');
    }

    public function storeHoliday(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager'])) {
            abort(403, 'Only Senior Managers or Administrators can manage the Holiday Calendar.');
        }

        $validated = $request->validate([
            'site_id' => ['nullable', 'exists:sites,id'],
            'name' => ['required', 'string', 'max:150'],
            'holiday_date' => ['required', 'date'],
            'is_paid' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        Holiday::create([
            'company_id' => TenantContext::getCompanyId(),
            'site_id' => $validated['site_id'] ?? null,
            'name' => $validated['name'],
            'holiday_date' => $validated['holiday_date'],
            'is_paid' => $request->boolean('is_paid', true),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Holiday added to calendar.');
    }

    public function storeLeaveRequest(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $validated = $request->validate([
            'worker_id' => ['required', 'exists:workers,id'],
            'leave_type_name' => ['required', 'string', 'max:60'],
            'is_paid' => ['nullable', 'boolean'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $worker = Worker::findOrFail($validated['worker_id']);
        if ($worker->assignedSite && !$user->isAssignedToSite($worker->assignedSite)) {
            abort(403, 'Unauthorized to submit leave requests for workers at an unassigned site.');
        }

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $daysCount = (int) $start->diffInDays($end) + 1;

        LeaveRequest::create([
            'company_id' => $worker->company_id,
            'worker_id' => $worker->id,
            'site_id' => $worker->assigned_site_id,
            'leave_type_name' => $validated['leave_type_name'],
            'is_paid' => $request->boolean('is_paid', true),
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days_count' => $daysCount,
            'reason' => $validated['reason'],
            'status' => 'pending',
            'requested_by' => $user->id,
        ]);

        return back()->with('success', 'Leave request submitted for approval.');
    }

    public function reviewLeaveRequest(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $user = Auth::user();
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'Unauthorized to review leave requests.');
        }

        if ($leaveRequest->requested_by && (int) $leaveRequest->requested_by === (int) $user->id) {
            abort(403, 'Nobody can approve their own leave request.');
        }

        if ($leaveRequest->site && !$user->isAssignedToSite($leaveRequest->site)) {
            abort(403, 'Unauthorized to review leave requests for this site.');
        }

        $validated = $request->validate([
            'action' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $leaveRequest->update([
            'status' => $validated['action'],
            'reviewed_by' => $user->id,
            'reviewed_at' => Carbon::now(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        if ($validated['action'] === 'approved') {
            $this->attendanceService->applyApprovedLeave($leaveRequest, $user->id);
        }

        return back()->with('success', 'Leave request ' . $validated['action'] . ' successfully.');
    }

    /**
     * Exports payroll-ready CSV for a date range and optional site filter.
     */
    public function exportPayrollCsv(Request $request): StreamedResponse
    {
        $user = Auth::user();
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff'])) {
            abort(403, 'Unauthorized to export payroll attendance data.');
        }

        $startDate = $request->query('start_date', Carbon::now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->query('end_date', Carbon::now()->endOfMonth()->format('Y-m-d'));
        $siteId = $request->query('site_id');

        if ($siteId) {
            $site = Site::findOrFail($siteId);
            if (!$user->isAssignedToSite($site)) {
                abort(403, 'Unauthorized to export payroll for this site.');
            }
        }

        $accessibleSiteIds = $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])
            ? null
            : $user->sites()->pluck('sites.id')->all();

        $records = DailyAttendance::with(['worker', 'site'])
            ->whereDate('attendance_date', '>=', $startDate)
            ->whereDate('attendance_date', '<=', $endDate)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->when($accessibleSiteIds !== null, fn ($q) => $q->whereIn('site_id', $accessibleSiteIds))
            ->get();

        $byWorker = $records->groupBy('worker_id');
        $filename = "nsd_payroll_export_{$startDate}_to_{$endDate}.csv";
        $payrollService = app(\App\Services\Workforce\PayrollCalculationService::class);
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
                if (!$worker) {
                    continue;
                }

                // Deduplicate by date for present/half_day/paid_leave counts
                $dedupedAttList = $attList->unique(fn ($item) => $item->attendance_date->format('Y-m-d'));
                $metrics = $payrollService->calculateWorkerMetrics($worker, $dedupedAttList, $periodStart, $periodEnd);

                fputcsv($out, [
                    $worker->code,
                    $worker->full_name,
                    $worker->worker_type,
                    $metrics['pay_type'],
                    $worker->trade_designation,
                    $startDate,
                    $endDate,
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

    /**
     * Date-Wise & Month-Wise Attendance Present Register for Admins & Managers.
     */
    public function register(Request $request): View
    {
        $user = Auth::user();
        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff'])
            && !$user->hasPermission('attendance.view')
            && !$user->hasPermission('attendance.record')
            && !$user->hasPermission('reports.view')
        ) {
            abort(403, 'Unauthorized to view attendance register.');
        }

        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']);

        $accessibleSites = $hasAllSiteAccess
            ? Site::where('status', 'active')->orderBy('code')->get()
            : $user->sites()->where('status', 'active')->orderBy('code')->get();

        $accessibleSiteIds = $hasAllSiteAccess
            ? null
            : $accessibleSites->pluck('id')->all();

        $mode = $request->query('mode', 'date');
        if (!in_array($mode, ['date', 'month'], true)) {
            $mode = 'date';
        }

        $date = $request->query('date', Carbon::today()->format('Y-m-d'));
        $month = $request->query('month', Carbon::parse($date)->format('Y-m'));
        $siteId = $request->filled('site_id') ? (int) $request->query('site_id') : null;
        $workerType = $request->query('worker_type');
        $presenceFilter = $request->query('presence_filter', 'all');
        $search = $request->query('search');

        if ($siteId) {
            $site = Site::findOrFail($siteId);
            if (!$user->isAssignedToSite($site)) {
                abort(403, 'Unauthorized to view attendance register for this site.');
            }
        }

        $dateRegister = null;
        $monthMatrix = null;

        if ($mode === 'month') {
            $monthMatrix = $this->attendanceService->getMonthWiseAttendanceMatrix(
                $month,
                $siteId,
                $workerType,
                $search,
                $accessibleSiteIds
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

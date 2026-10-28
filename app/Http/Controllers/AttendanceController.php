<?php

namespace App\Http\Controllers;

use App\Models\AttendanceCorrection;
use App\Models\DailyAttendance;
use App\Models\Site;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use App\Services\Workforce\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService)
    {
    }

    public function muster(Request $request): View
    {
        $user = Auth::user();
        $accessibleSites = $user->hasRole(['super_admin', 'company_admin', 'senior_manager'])
            ? Site::where('status', 'active')->get()
            : $user->sites()->where('status', 'active')->get();

        $selectedSiteId = $request->query('site_id') ?? $accessibleSites->first()?->id;
        $date = $request->query('date') ?? Carbon::today()->format('Y-m-d');

        $site = Site::find($selectedSiteId);
        if ($site && !$user->isAssignedToSite($site)) {
            abort(403, 'Unauthorized to view or record muster roll for this site.');
        }

        // Fetch all workers assigned to this site OR who already have attendance records at this site today
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

        // Load existing attendances for this site and date
        $existingRecords = $site
            ? DailyAttendance::where('site_id', $site->id)
                ->whereDate('attendance_date', $date)
                ->get()
                ->groupBy('worker_id')
            : collect();

        return view('attendance.muster', compact('accessibleSites', 'selectedSiteId', 'site', 'date', 'assignedWorkers', 'existingRecords'));
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

        $this->attendanceService->recordMusterRoll(
            $site->id,
            $validated['attendance_date'],
            $validated['workers'],
            $user->id
        );

        return redirect()->route('attendance.muster', [
            'site_id' => $site->id,
            'date' => $validated['attendance_date'],
        ])->with('success', 'Muster roll recorded successfully.');
    }

    public function conflicts(): View
    {
        $conflicts = DailyAttendance::where('has_conflict', true)
            ->with(['worker', 'site', 'project', 'submitter'])
            ->latest('attendance_date')
            ->paginate(25);

        return view('attendance.conflicts', compact('conflicts'));
    }

    public function requestCorrection(Request $request, DailyAttendance $attendance): RedirectResponse
    {
        $user = Auth::user();

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
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return back()->with('success', 'Correction request submitted for approval.');
    }

    public function corrections(): View
    {
        $corrections = AttendanceCorrection::with(['attendance.worker', 'attendance.site', 'requester'])
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

        $validated = $request->validate([
            'action' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string'],
        ]);

        $correction->status = $validated['action'];
        $correction->reviewed_by = $user->id;
        $correction->reviewed_at = Carbon::now();
        $correction->review_notes = $validated['review_notes'] ?? null;
        $correction->save();

        if ($validated['action'] === 'approved') {
            $att = $correction->attendance;
            $att->status = $correction->proposed_status;
            $att->clock_in = $correction->proposed_clock_in;
            $att->clock_out = $correction->proposed_clock_out;
            $att->worked_hours = $correction->proposed_worked_hours;
            $att->save(); // will recalculate conflicts
        }

        return back()->with('success', 'Attendance correction ' . $validated['action'] . ' successfully.');
    }
}

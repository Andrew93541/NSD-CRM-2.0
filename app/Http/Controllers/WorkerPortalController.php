<?php

namespace App\Http\Controllers;

use App\Models\DailyAttendance;
use App\Models\LeaveRequest;
use App\Models\Site;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use App\Services\Workforce\AttendanceService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class WorkerPortalController extends Controller
{
    public function __construct(protected AttendanceService $attendanceService)
    {
    }

    public function showLogin(): View
    {
        return view('worker_portal.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'worker_code' => ['required', 'string', 'max:40'],
            'pin' => ['required', 'string', 'min:4', 'max:12'],
        ]);

        $throttleKey = 'worker-login:' . strtolower(trim($validated['worker_code'])) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()->withErrors(['worker_code' => 'Too many login attempts. Please wait a minute and try again.']);
        }

        $worker = Worker::withoutGlobalScopes()
            ->where('code', trim($validated['worker_code']))
            ->where('status', 'active')
            ->first();

        if (!$worker || empty($worker->pin) || !Hash::check($validated['pin'], $worker->pin)) {
            RateLimiter::hit($throttleKey, 60);
            return back()->withErrors(['worker_code' => 'Invalid worker code or PIN, or account is not active.'])->withInput(['worker_code' => $validated['worker_code']]);
        }

        if ($worker->worker_type !== 'employee') {
            return back()->withErrors(['worker_code' => 'Direct Labour and Subcontractor workers must use QR Badge scanning at the site muster roll.']);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();
        session([
            'worker_auth_id' => $worker->id,
            'worker_company_id' => $worker->company_id,
        ]);

        if ($worker->must_change_pin) {
            return redirect()->route('worker.pin.show');
        }

        return redirect()->route('worker.punch');
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget(['worker_auth_id', 'worker_company_id']);
        return redirect()->route('worker.login');
    }

    protected function resolveAuthenticatedWorker(): Worker
    {
        $workerId = session('worker_auth_id');
        if (!$workerId) {
            abort(401, 'Unauthenticated worker session.');
        }

        $worker = Worker::withoutGlobalScopes()->where('id', $workerId)->where('status', 'active')->first();
        if (!$worker) {
            session()->forget(['worker_auth_id', 'worker_company_id']);
            abort(403, 'Worker account is deactivated or unavailable.');
        }

        TenantContext::setCompanyId($worker->company_id);

        return $worker;
    }

    public function showPinChange(): View|RedirectResponse
    {
        if (!session('worker_auth_id')) {
            return redirect()->route('worker.login');
        }

        $worker = $this->resolveAuthenticatedWorker();

        return view('worker_portal.change_pin', compact('worker'));
    }

    public function updatePin(Request $request): RedirectResponse
    {
        if (!session('worker_auth_id')) {
            return redirect()->route('worker.login');
        }

        $worker = $this->resolveAuthenticatedWorker();

        $validated = $request->validate([
            'new_pin' => ['required', 'digits:6', 'confirmed'],
        ]);

        $worker->update([
            'pin' => Hash::make($validated['new_pin']),
            'must_change_pin' => false,
        ]);

        return redirect()->route('worker.punch')->with('success', 'Your 6-digit PIN has been updated.');
    }

    public function punchScreen(): View|RedirectResponse
    {
        if (!session('worker_auth_id')) {
            return redirect()->route('worker.login');
        }

        $worker = $this->resolveAuthenticatedWorker();
        if ($worker->must_change_pin) {
            return redirect()->route('worker.pin.show');
        }

        $site = $worker->assigned_site_id ? Site::withoutGlobalScopes()->find($worker->assigned_site_id) : null;
        $today = Carbon::today()->format('Y-m-d');

        $todayRecords = DailyAttendance::withoutGlobalScopes()
            ->where('company_id', $worker->company_id)
            ->where('worker_id', $worker->id)
            ->whereDate('attendance_date', $today)
            ->with('site')
            ->get();

        $openAttendance = $todayRecords->whereNotNull('clock_in')->whereNull('clock_out')->last();
        $recentPunches = $worker->punches()->withoutGlobalScopes()->with('site')->limit(10)->get();
        $leaveRequests = $worker->leaveRequests()->withoutGlobalScopes()->limit(5)->get();

        return view('worker_portal.punch', compact('worker', 'site', 'todayRecords', 'openAttendance', 'recentPunches', 'leaveRequests'));
    }

    public function storePunch(Request $request): RedirectResponse
    {
        if (!session('worker_auth_id')) {
            return redirect()->route('worker.login');
        }

        $worker = $this->resolveAuthenticatedWorker();
        if ($worker->must_change_pin) {
            return redirect()->route('worker.pin.show');
        }

        $validated = $request->validate([
            'site_id' => ['nullable', 'exists:sites,id'],
            'punch_type' => ['nullable', 'in:toggle,in,out'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'min:0'],
            'device_id' => ['nullable', 'string', 'max:120'],
        ]);

        $siteId = $validated['site_id'] ?? $worker->assigned_site_id;
        if (!$siteId) {
            return back()->withErrors(['site_id' => 'No assigned site found for punch recording.']);
        }

        $site = Site::withoutGlobalScopes()->where('company_id', $worker->company_id)->findOrFail($siteId);

        try {
            $punch = $this->attendanceService->recordPunch($worker, $site, [
                'punch_type' => $validated['punch_type'] ?? 'toggle',
                'source' => 'self',
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'accuracy_m' => $validated['accuracy_m'] ?? null,
                'device_id' => $validated['device_id'] ?? ('WEB-' . substr(sha1((string) $request->userAgent()), 0, 12)),
                'ip_address' => $request->ip(),
            ], null);
        } catch (DomainException $e) {
            abort(403, $e->getMessage());
        }

        $fenceNote = $punch->within_geofence === false
            ? ' (Flagged: Outside site geofence radius)'
            : ($punch->within_geofence === true ? ' (Verified within site geofence)' : '');

        return redirect()->route('worker.punch')->with(
            'success',
            sprintf('Punch %s recorded at %s Server Time%s.', strtoupper($punch->punch_type), $punch->punched_at->format('H:i:s'), $fenceNote)
        );
    }

    public function storeLeaveRequest(Request $request): RedirectResponse
    {
        if (!session('worker_auth_id')) {
            return redirect()->route('worker.login');
        }

        $worker = $this->resolveAuthenticatedWorker();

        $validated = $request->validate([
            'leave_type_name' => ['required', 'string', 'max:60'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $daysCount = (int) $start->diffInDays($end) + 1;

        LeaveRequest::create([
            'company_id' => $worker->company_id,
            'worker_id' => $worker->id,
            'site_id' => $worker->assigned_site_id,
            'leave_type_name' => $validated['leave_type_name'],
            'is_paid' => true,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'days_count' => $daysCount,
            'reason' => $validated['reason'],
            'status' => 'pending',
            'requested_by' => null,
        ]);

        return redirect()->route('worker.punch')->with('success', 'Leave request submitted for supervisor/manager approval.');
    }
}


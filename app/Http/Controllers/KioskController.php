<?php

namespace App\Http\Controllers;

use App\Models\AttendancePunch;
use App\Models\AuditLog;
use App\Models\Site;
use App\Models\Worker;
use App\Services\Workforce\AttendanceService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class KioskController extends Controller
{
    public function __construct(
        protected AttendanceService $attendanceService
    ) {}

    /**
     * Authenticated Kiosk Management Hub (/attendance/kiosk).
     * Lets authorized supervisors/managers launch or rotate site-bound kiosk tokens.
     */
    public function hub(Request $request): View
    {
        $user = Auth::user();
        if (!$user || (
            !$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor'])
            && !$user->hasPermission('attendance.record')
        )) {
            abort(403, 'Unauthorized to access Site Kiosk Mode.');
        }

        $hasAllSiteAccess = $user->hasRole(['super_admin', 'company_admin', 'senior_manager']);
        $sites = $hasAllSiteAccess
            ? Site::where('status', 'active')->orderBy('code')->get()
            : $user->sites()->where('status', 'active')->orderBy('code')->get();

        foreach ($sites as $site) {
            $site->ensureKioskToken();
        }

        return view('attendance.kiosk_hub', compact('sites'));
    }

    /**
     * Rotate a site's kiosk device token (invalidates old tablet/kiosk URLs immediately).
     */
    public function rotateToken(Request $request, Site $site): RedirectResponse
    {
        $user = Auth::user();
        if (!$user || !$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager'])) {
            abort(403, 'Only management roles can rotate site kiosk tokens.');
        }

        if (!$user->isAssignedToSite($site)) {
            abort(403, 'You are not authorized to manage kiosk tokens for this site.');
        }

        $site->rotateKioskToken();

        AuditLog::record(
            'site.kiosk_token.rotated',
            $site,
            null,
            ['site_code' => $site->code],
            "Rotated Kiosk device token for site {$site->code}"
        );

        return back()->with('success', "Kiosk device token rotated for {$site->code} — {$site->name}. Previous kiosk URLs are now invalidated.");
    }

    /**
     * Stateless Site-Bound Kiosk Terminal (/kiosk/{token}).
     * Never persists a worker session between scans.
     */
    public function terminal(Request $request, string $token): View
    {
        $site = Site::withoutGlobalScopes()
            ->with('company')
            ->where('kiosk_token', $token)
            ->where('status', 'active')
            ->firstOrFail();

        // Guarantee zero persistent worker session on kiosk devices
        $request->session()->forget(['worker_portal_id', 'worker_portal_company_id']);

        $recentPunches = AttendancePunch::withoutGlobalScopes()
            ->with('worker')
            ->where('company_id', $site->company_id)
            ->where('site_id', $site->id)
            ->whereDate('punched_at', Carbon::today()->toDateString())
            ->latest('punched_at')
            ->limit(10)
            ->get();

        return view('attendance.kiosk', compact('site', 'recentPunches'));
    }

    /**
     * Stateless Kiosk Punch Endpoint (POST /kiosk/{token}/punch).
     * Rate-limited per IP + site token; supports QR token or Worker Code + 6-digit PIN.
     */
    public function punch(Request $request, string $token): RedirectResponse
    {
        $rateKey = 'kiosk-punch:' . $request->ip() . ':' . substr($token, 0, 16);
        if (RateLimiter::tooManyAttempts($rateKey, 20)) {
            $seconds = RateLimiter::availableIn($rateKey);
            abort(429, "Too many kiosk scan attempts. Please wait {$seconds} seconds.");
        }
        RateLimiter::hit($rateKey, 60);

        $site = Site::withoutGlobalScopes()
            ->with('company')
            ->where('kiosk_token', $token)
            ->where('status', 'active')
            ->firstOrFail();

        // Guarantee zero persistent worker session before and after scan
        $request->session()->forget(['worker_portal_id', 'worker_portal_company_id']);

        $validated = $request->validate([
            'mode' => ['nullable', 'in:qr,pin'],
            'qr_token' => ['nullable', 'string', 'max:120'],
            'worker_code' => ['nullable', 'string', 'max:40'],
            'pin' => ['nullable', 'string', 'max:12'],
            'punch_type' => ['nullable', 'in:toggle,in,out'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $worker = null;
        $qrInput = trim((string) ($validated['qr_token'] ?? ''));

        if ($qrInput !== '') {
            // Allow either raw qr_token or a URL ending with the token
            if (str_contains($qrInput, '/')) {
                $qrInput = basename(parse_url($qrInput, PHP_URL_PATH) ?: $qrInput);
            }

            $worker = Worker::withoutGlobalScopes()
                ->where('company_id', $site->company_id)
                ->where(function ($q) use ($qrInput) {
                    $q->where('qr_token', $qrInput)
                      ->orWhere('code', strtoupper($qrInput));
                })
                ->where('status', 'active')
                ->first();

            if (!$worker) {
                return back()->withErrors(['qr_token' => 'Invalid or inactive Worker QR Badge scanned.']);
            }
        } else {
            $workerCode = strtoupper(trim((string) ($validated['worker_code'] ?? '')));
            $pin = trim((string) ($validated['pin'] ?? ''));

            if ($workerCode === '' || $pin === '') {
                return back()->withErrors(['worker_code' => 'Scan a Worker QR Badge or enter both Worker ID and 6-digit PIN.']);
            }

            $worker = Worker::withoutGlobalScopes()
                ->where('company_id', $site->company_id)
                ->where('code', $workerCode)
                ->where('status', 'active')
                ->first();

            if (!$worker || empty($worker->pin) || !Hash::check($pin, $worker->pin)) {
                return back()->withErrors(['worker_code' => 'Invalid Worker ID or 6-digit PIN.']);
            }
        }

        try {
            $punch = $this->attendanceService->recordPunch(
                $worker,
                $site,
                [
                    'punch_type' => $validated['punch_type'] ?? 'toggle',
                    'source' => 'kiosk',
                    'latitude' => $validated['latitude'] ?? $site->latitude,
                    'longitude' => $validated['longitude'] ?? $site->longitude,
                    'device_info' => 'Site Kiosk Terminal (' . $site->code . ')',
                    'notes' => 'Recorded via stateless Site Kiosk',
                ],
                Auth::id()
            );
        } catch (DomainException $e) {
            return back()->withErrors(['kiosk' => $e->getMessage()]);
        }

        // Ensure no worker session persists after punch
        $request->session()->forget(['worker_portal_id', 'worker_portal_company_id']);

        return redirect()
            ->route('kiosk.terminal', $site->kiosk_token)
            ->with('kiosk_punch_result', [
                'worker_name' => $worker->full_name,
                'worker_code' => $worker->code,
                'trade' => $worker->trade_designation,
                'punch_type' => strtoupper($punch->punch_type),
                'punched_at' => $punch->punched_at->timezone(config('app.timezone', 'Asia/Kolkata'))->format('h:i:s A (H:i) \I\S\T'),
            ]);
    }
}


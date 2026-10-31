@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{
    periods: {},
    init() {
        @foreach($assignedWorkers as $worker)
            @php
                $existing = $existingRecords->get($worker->id);
            @endphp
            @if($existing && $existing->isNotEmpty())
                this.periods[{{ $worker->id }}] = [
                    @foreach($existing as $att)
                        {
                            id: {{ $att->id }},
                            clock_in: '{{ $att->clock_in ? \Carbon\Carbon::parse($att->clock_in)->format('H:i') : '' }}',
                            clock_out: '{{ $att->clock_out ? \Carbon\Carbon::parse($att->clock_out)->format('H:i') : '' }}',
                            status: '{{ $att->status }}',
                            has_conflict: {{ $att->has_conflict ? 'true' : 'false' }},
                            conflict_reason: '{{ addslashes($att->conflict_reason ?? '') }}'
                        },
                    @endforeach
                ];
            @else
                this.periods[{{ $worker->id }}] = [
                    {
                        id: null,
                        clock_in: '',
                        clock_out: '',
                        status: 'present',
                        has_conflict: false,
                        conflict_reason: ''
                    }
                ];
            @endif
        @endforeach
    },
    addPeriod(workerId) {
        if (!this.periods[workerId]) {
            this.periods[workerId] = [];
        }
        this.periods[workerId].push({
            id: null,
            clock_in: '',
            clock_out: '',
            status: 'present',
            has_conflict: false,
            conflict_reason: ''
        });
    },
    removePeriod(workerId, index) {
        if (this.periods[workerId].length > 1) {
            this.periods[workerId].splice(index, 1);
        }
    }
}">
    <!-- Site & Date Filter Bar -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm transition-colors duration-200">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-sm font-black">M</span>
                    {{ __('Daily Muster Roll') }} &mdash; {{ __('Site Attendance') }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                    {{ __('Record verified presence and work periods. Unverified hours must never be filled with default estimates.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('attendance.register', ['mode' => 'date', 'date' => $date, 'site_id' => $selectedSiteId]) }}" class="px-3.5 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-sm transition flex items-center gap-1.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    <span>{{ __('Date & Month View') }}</span>
                </a>
                <a href="{{ route('attendance.approvals') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-amber-500/10 hover:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-500/30 transition flex items-center gap-1.5">
                    <span>{{ __('Approvals Inbox') }}</span>
                    @if(($openFlagsCount ?? 0) + ($pendingCorrectionsCount ?? 0) + ($pendingLeavesCount ?? 0) > 0)
                        <span class="px-1.5 py-0.5 rounded-full bg-amber-500 text-slate-950 text-[10px] font-black">
                            {{ ($openFlagsCount ?? 0) + ($pendingCorrectionsCount ?? 0) + ($pendingLeavesCount ?? 0) }}
                        </span>
                    @endif
                </a>
                <a href="{{ route('attendance.shifts_leaves') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700 transition">
                    {{ __('Shifts, Leave & Holidays') }}
                </a>
                <a href="{{ route('attendance.export_payroll', ['site_id' => $selectedSiteId]) }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30 transition">
                    {{ __('Export Payroll CSV') }}
                </a>
                <a href="{{ route('attendance.conflicts') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-rose-600 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-rose-400 dark:border-slate-700 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                    {{ __('Attendance Conflicts') }}
                </a>
                <a href="{{ route('attendance.corrections') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700 transition">
                    {{ __('Attendance Corrections') }}
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('attendance.muster') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2 border-t border-slate-200 dark:border-slate-800/80">
            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">{{ __('Select Site') }} *</label>
                <select name="site_id" required onchange="this.form.submit()" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    @foreach($accessibleSites as $s)
                        <option value="{{ $s->id }}" {{ $selectedSiteId == $s->id ? 'selected' : '' }}>
                            {{ $s->code }} — {{ $s->name }} ({{ $s->location }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">{{ __('Date') }} *</label>
                <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
            </div>

            <div class="flex items-end">
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-amber-600 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-amber-400 dark:border-slate-700 text-xs sm:text-sm font-bold transition">
                    {{ __('Filter') }} &rarr;
                </button>
            </div>
        </form>
    </div>

    <!-- Muster Roll Table / Form -->
    @if($site)
        @if(!empty($holiday))
            <div class="p-4 rounded-2xl bg-sky-500/10 border border-sky-500/30 text-sky-700 dark:text-sky-300 text-xs font-bold flex items-center justify-between">
                <span>{{ __('Holiday Calendar') }}: {{ $holiday->name }} ({{ $holiday->is_paid ? __('Paid Holiday') : __('Unpaid Holiday') }})</span>
                <span class="font-mono">{{ $holiday->holiday_date->format('d M Y') }}</span>
            </div>
        @endif

        <!-- Three-Stage Attendance Lock & Supervisor QR Badge Scan Bar -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
            <!-- Three-Stage Lock Control -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('Three-Stage Attendance Lock') }}</h3>
                        <p class="text-[11px] text-slate-500">{{ __('1. Supervisor Submit → 2. Site Manager Lock → 3. Payroll Lock') }}</p>
                    </div>
                    @php
                        $currentStage = $lock?->stage ?? 'open';
                    @endphp
                    <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase {{ in_array($currentStage, ['site_locked', 'payroll_locked']) ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30' : ($currentStage === 'submitted' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30') }}">
                        {{ __(\App\Models\AttendanceLock::STAGES[$currentStage] ?? 'Open') }}
                    </span>
                </div>

                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                    @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']) && $currentStage !== 'site_locked' && $currentStage !== 'payroll_locked')
                        <form method="POST" action="{{ route('attendance.lock') }}">
                            @csrf
                            <input type="hidden" name="site_id" value="{{ $site->id }}">
                            <input type="hidden" name="attendance_date" value="{{ $date }}">
                            <input type="hidden" name="stage" value="site_locked">
                            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                                {{ __('Approve & Lock Day (Site Manager)') }}
                            </button>
                        </form>
                    @endif

                    @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager']) && $currentStage !== 'payroll_locked')
                        <form method="POST" action="{{ route('attendance.lock') }}">
                            @csrf
                            <input type="hidden" name="site_id" value="{{ $site->id }}">
                            <input type="hidden" name="attendance_date" value="{{ $date }}">
                            <input type="hidden" name="stage" value="payroll_locked">
                            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs transition">
                                {{ __('Apply Payroll Lock') }}
                            </button>
                        </form>
                    @endif

                    @if(auth()->user()->hasRole(['super_admin', 'company_admin']) && in_array($currentStage, ['site_locked', 'payroll_locked']))
                        <form method="POST" action="{{ route('attendance.lock') }}" class="flex items-center gap-2 w-full sm:w-auto mt-2 sm:mt-0">
                            @csrf
                            <input type="hidden" name="site_id" value="{{ $site->id }}">
                            <input type="hidden" name="attendance_date" value="{{ $date }}">
                            <input type="hidden" name="stage" value="open">
                            <input type="text" name="unlock_reason" required placeholder="{{ __('Mandatory admin unlock reason...') }}" class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white">
                            <button type="submit" class="px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-amber-400 font-bold text-xs transition">
                                {{ __('Admin Unlock') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            <!-- Supervisor QR Badge Scan Form -->
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-sm">
                <div>
                    <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('Supervisor QR Badge Scan (Server Time)') }}</h3>
                    <p class="text-[11px] text-slate-500">{{ __('Scan worker QR token or enter Dynamic Worker ID (EMP/SUB) to record an append-only punch.') }}</p>
                </div>
                <form method="POST" action="{{ route('attendance.qr_scan') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-1">
                    @csrf
                    <input type="hidden" name="site_id" value="{{ $site->id }}">
                    <input type="text" name="badge_token" required placeholder="{{ __('Scan QR token or enter Worker Code...') }}"
                        class="flex-1 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2 text-xs font-mono text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    <select name="punch_type" class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
                        <option value="toggle">{{ __('Auto In/Out') }}</option>
                        <option value="in">{{ __('Punch In') }}</option>
                        <option value="out">{{ __('Punch Out') }}</option>
                    </select>
                    <button type="submit" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs transition">
                        {{ __('Record QR Punch') }}
                    </button>
                </form>
            </div>
        </div>

        <form method="POST" action="{{ route('attendance.store_muster') }}">
            @csrf
            <input type="hidden" name="site_id" value="{{ $site->id }}">
            <input type="hidden" name="attendance_date" value="{{ $date }}">

            <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl overflow-hidden transition-colors duration-200">
                <div class="p-4 sm:p-5 bg-slate-50 dark:bg-slate-950/70 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <span>{{ $site->name }}</span>
                            <span class="text-xs font-mono text-amber-600 dark:text-amber-400 font-normal">({{ $site->code }})</span>
                        </h2>
                        <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5">
                            {{ __('Muster for') }} {{ \Carbon\Carbon::parse($date)->format('l, d F Y') }} &bull; {{ $assignedWorkers->count() }} {{ __('Workers') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-500 dark:text-slate-400">{{ __('Touch or click status to toggle presence') }}</span>
                    </div>
                </div>

                <div class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($assignedWorkers as $worker)
                        <div class="p-4 sm:p-5 space-y-3 hover:bg-slate-50 dark:hover:bg-slate-800/20 transition">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div class="flex items-center gap-3">
                                    <span class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 flex items-center justify-center font-bold text-xs">
                                        {{ substr($worker->first_name, 0, 1) }}
                                    </span>
                                    <div>
                                        <div class="font-bold text-sm text-slate-900 dark:text-white flex items-center gap-2">
                                            {{ $worker->full_name }}
                                            <span class="font-mono text-xs text-amber-600 dark:text-amber-400 font-normal">({{ $worker->code }})</span>
                                        </div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 flex flex-wrap items-center gap-2 mt-0.5">
                                            <span>{{ $worker->trade_designation }}</span>
                                            <span>&bull;</span>
                                            @if($worker->worker_type === 'employee')
                                                <span class="text-emerald-600 dark:text-emerald-400 font-semibold">{{ __('Direct Employee') }}</span>
                                            @elseif($worker->worker_type === 'direct_labour')
                                                <span class="text-sky-600 dark:text-sky-400 font-semibold">{{ __('Direct Labour') }}</span>
                                            @else
                                                <span class="text-purple-600 dark:text-purple-400 font-semibold">{{ __('Sub') }}: {{ $worker->subcontractor->name ?? __('External') }}</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                <button type="button" @click="addPeriod({{ $worker->id }})" class="self-start sm:self-auto text-xs font-bold text-amber-600 dark:text-amber-400 hover:text-amber-500 flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                    + {{ __('Add Work Period') }}
                                </button>
                            </div>

                            <!-- Work Periods Container -->
                            <div class="space-y-2 pt-2">
                                <template x-for="(period, idx) in periods[{{ $worker->id }}]" :key="idx">
                                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950/60 border border-slate-200 dark:border-slate-800/80 flex flex-col md:flex-row items-stretch md:items-center justify-between gap-3">
                                        <input type="hidden" :name="`workers[{{ $worker->id }}][${idx}][id]`" :value="period.id">

                                        <!-- Status selector -->
                                        <div class="w-full md:w-36">
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">{{ __('Status') }}</label>
                                            <select :name="`workers[{{ $worker->id }}][${idx}][status]`" x-model="period.status" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-2.5 py-1.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                                                <option value="present">{{ __('Present') }}</option>
                                                <option value="half_day">{{ __('Half Day') }}</option>
                                                <option value="absent">{{ __('Absent') }}</option>
                                                <option value="paid_leave">{{ __('Paid Leave') }}</option>
                                            </select>
                                        </div>

                                        <!-- Clock In -->
                                        <div class="w-full md:w-36">
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">{{ __('Clock In') }} ({{ __('Verified') }})</label>
                                            <input type="time" :name="`workers[{{ $worker->id }}][${idx}][clock_in]`" x-model="period.clock_in" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-2.5 py-1.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                                        </div>

                                        <!-- Clock Out -->
                                        <div class="w-full md:w-36">
                                            <label class="block text-[10px] font-bold text-slate-500 uppercase tracking-wider mb-1">{{ __('Clock Out') }} ({{ __('Verified') }})</label>
                                            <input type="time" :name="`workers[{{ $worker->id }}][${idx}][clock_out]`" x-model="period.clock_out" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 px-2.5 py-1.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                                        </div>

                                        <!-- Overlap conflict indicator -->
                                        <div class="flex-1 px-2" x-show="period.has_conflict">
                                            <div class="text-[11px] font-semibold text-rose-700 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-800/80 rounded-lg p-2 flex items-center gap-2">
                                                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                                                <span x-text="period.conflict_reason"></span>
                                            </div>
                                        </div>

                                        <!-- Remove period button (only for multi-period) -->
                                        <div class="flex items-center self-end md:self-center">
                                            <button type="button" x-show="periods[{{ $worker->id }}].length > 1" @click="removePeriod({{ $worker->id }}, idx)" class="p-1.5 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition" title="Remove Shift">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @empty
                        <div class="p-8 text-center text-slate-500">
                            {{ __('No active workers found assigned to this site. Assign workers in the workforce directory.') }}
                        </div>
                    @endforelse
                </div>

                @if($assignedWorkers->isNotEmpty())
                    <div class="p-5 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                        @if($lock && $lock->isLocked())
                            <div class="text-xs font-bold text-rose-600 dark:text-rose-400">
                                {{ __('This attendance period is locked (:stage). Direct edits are disabled; submit an Attendance Correction Request if needed.', ['stage' => \App\Models\AttendanceLock::STAGES[$lock->stage] ?? $lock->stage]) }}
                            </div>
                        @else
                            <div></div>
                            <button type="submit" class="w-full sm:w-auto px-6 py-3 rounded-xl bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 font-extrabold text-sm shadow-xl shadow-amber-500/20 transition active:scale-[0.99]">
                                {{ __('Save Site Muster Roll') }}
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </form>
    @endif
</div>
@endsection

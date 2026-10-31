@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                {{ __('Unified Approvals & Anomaly Inbox') }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                {{ __('Review attendance corrections, geofence/device anomaly flags, and worker leave requests. Self-approval is strictly prohibited.') }}
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('attendance.shifts_leaves') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 text-xs font-bold transition">
                {{ __('Shifts, Leave & Holidays') }}
            </a>
            <a href="{{ route('attendance.muster') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 text-xs font-bold transition">
                &larr; {{ __('Back to Muster Roll') }}
            </a>
        </div>
    </div>

    <!-- 1. Open Attendance Anomaly Flags -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <div class="p-4 sm:p-5 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">
                    {{ __('Open Attendance Anomaly Flags') }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">{{ __('Out-of-geofence punches, shared devices, impossible travel, and overlapping shifts.') }}</p>
            </div>
            <span class="px-2.5 py-1 rounded-full bg-rose-500/10 text-rose-600 dark:text-rose-400 text-xs font-bold">
                {{ $openFlags->count() }} {{ __('Open') }}
            </span>
        </div>

        <div class="divide-y divide-slate-200 dark:divide-slate-800">
            @forelse($openFlags as $flag)
                <div class="p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase {{ $flag->severity === 'critical' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30' }}">
                                {{ $flag->typeLabel() }}
                            </span>
                            <span class="font-bold text-sm text-slate-900 dark:text-white">{{ $flag->worker?->full_name }}</span>
                            <span class="font-mono text-xs text-amber-600 dark:text-amber-400">({{ $flag->worker?->code }})</span>
                            <span class="text-xs text-slate-500">&bull; {{ $flag->site?->code }} &bull; {{ $flag->attendance_date->format('d M Y') }}</span>
                        </div>
                        <p class="text-xs text-slate-700 dark:text-slate-300">{{ $flag->description }}</p>
                    </div>

                    @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']))
                        <form method="POST" action="{{ route('attendance.flags.resolve', $flag) }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2">
                            @csrf
                            <input type="text" name="resolution_notes" required placeholder="{{ __('Resolution note...') }}"
                                class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white">
                            <button type="submit" name="status" value="resolved" class="px-3 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition">
                                {{ __('Resolve') }}
                            </button>
                            <button type="submit" name="status" value="dismissed" class="px-3 py-1.5 rounded-xl bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs transition">
                                {{ __('Dismiss') }}
                            </button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center text-xs text-slate-500">{{ __('No open attendance anomaly flags.') }}</div>
            @endforelse
        </div>
    </div>

    <!-- 2. Pending Attendance Corrections -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <div class="p-4 sm:p-5 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">
                    {{ __('Pending Attendance Correction Requests') }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">{{ __('Requires approval by a manager other than the requester.') }}</p>
            </div>
            <span class="px-2.5 py-1 rounded-full bg-amber-500/10 text-amber-600 dark:text-amber-400 text-xs font-bold">
                {{ $pendingCorrections->count() }} {{ __('Pending') }}
            </span>
        </div>

        <div class="divide-y divide-slate-200 dark:divide-slate-800">
            @forelse($pendingCorrections as $c)
                <div class="p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="space-y-1 text-xs">
                        <div class="font-bold text-sm text-slate-900 dark:text-white">
                            {{ $c->attendance?->worker?->full_name }}
                            <span class="font-mono text-amber-600 dark:text-amber-400">({{ $c->attendance?->worker?->code }})</span>
                            <span class="text-slate-500 font-normal">&bull; {{ $c->attendance?->site?->name }} &bull; {{ $c->attendance?->attendance_date?->format('d M Y') }}</span>
                        </div>
                        <div class="flex flex-wrap items-center gap-3 font-mono">
                            <span class="text-rose-600 dark:text-rose-400 line-through">{{ $c->previous_clock_in ?? '--:--' }} - {{ $c->previous_clock_out ?? '--:--' }} ({{ $c->previous_status }})</span>
                            <span>&rarr;</span>
                            <span class="text-emerald-600 dark:text-emerald-400 font-bold">{{ $c->proposed_clock_in ?? '--:--' }} - {{ $c->proposed_clock_out ?? '--:--' }} ({{ $c->proposed_status }}, {{ $c->proposed_worked_hours }}h)</span>
                        </div>
                        <div class="text-slate-600 dark:text-slate-400">
                            <strong>{{ __('Reason') }}:</strong> {{ $c->reason }} &bull; {{ __('Requested by') }} {{ $c->requester?->name }}
                        </div>
                    </div>

                    <div>
                        @if((int) $c->requested_by === (int) auth()->id())
                            <span class="px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-500 text-xs font-semibold">
                                {{ __('Awaiting another manager (Cannot self-approve)') }}
                            </span>
                        @elseif(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']))
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('attendance.review_correction', $c) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="approved">
                                    <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition">{{ __('Approve') }}</button>
                                </form>
                                <form method="POST" action="{{ route('attendance.review_correction', $c) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="rejected">
                                    <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-rose-100 hover:bg-rose-200 dark:bg-rose-900/60 text-rose-800 dark:text-rose-200 font-bold text-xs transition">{{ __('Reject') }}</button>
                                </form>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-xs text-slate-500">{{ __('No pending attendance correction requests.') }}</div>
            @endforelse
        </div>
    </div>

    <!-- 3. Pending Leave Requests -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <div class="p-4 sm:p-5 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">
                    {{ __('Pending Worker Leave Requests') }}
                </h2>
                <p class="text-xs text-slate-500 mt-0.5">{{ __('Approved paid leaves automatically populate daily attendance records.') }}</p>
            </div>
            <span class="px-2.5 py-1 rounded-full bg-sky-500/10 text-sky-600 dark:text-sky-400 text-xs font-bold">
                {{ $pendingLeaves->count() }} {{ __('Pending') }}
            </span>
        </div>

        <div class="divide-y divide-slate-200 dark:divide-slate-800">
            @forelse($pendingLeaves as $leave)
                <div class="p-4 sm:p-5 flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <div class="space-y-1 text-xs">
                        <div class="font-bold text-sm text-slate-900 dark:text-white">
                            {{ $leave->worker?->full_name }}
                            <span class="font-mono text-amber-600 dark:text-amber-400">({{ $leave->worker?->code }})</span>
                            <span class="px-2 py-0.5 rounded bg-sky-500/10 text-sky-600 dark:text-sky-400 text-[10px] font-bold ml-1">{{ $leave->leave_type_name }}</span>
                        </div>
                        <div class="font-mono text-slate-600 dark:text-slate-300">
                            {{ $leave->start_date->format('d M Y') }} &rarr; {{ $leave->end_date->format('d M Y') }} ({{ $leave->days_count }} {{ __('day(s)') }})
                        </div>
                        <div class="text-slate-500">{{ __('Reason') }}: {{ $leave->reason }}</div>
                    </div>

                    @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']))
                        <div class="flex items-center gap-2">
                            <form method="POST" action="{{ route('attendance.leaves.review', $leave) }}">
                                @csrf
                                <input type="hidden" name="action" value="approved">
                                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-bold text-xs transition">{{ __('Approve Leave') }}</button>
                            </form>
                            <form method="POST" action="{{ route('attendance.leaves.review', $leave) }}">
                                @csrf
                                <input type="hidden" name="action" value="rejected">
                                <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-rose-100 hover:bg-rose-200 dark:bg-rose-900/60 text-rose-800 dark:text-rose-200 font-bold text-xs transition">{{ __('Reject') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-8 text-center text-xs text-slate-500">{{ __('No pending leave requests.') }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection


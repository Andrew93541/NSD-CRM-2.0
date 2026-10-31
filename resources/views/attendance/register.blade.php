@extends('layouts.app')

@section('content')
@php
    $currentRoute = request()->routeIs('reports.*') ? 'reports.workforce' : 'attendance.register';
    $sitesList = $accessibleSites ?? $sites ?? collect();
    $exportStartDate = $mode === 'month' && $monthMatrix
        ? $monthMatrix['start_date']
        : $date;
    $exportEndDate = $mode === 'month' && $monthMatrix
        ? $monthMatrix['end_date']
        : $date;
@endphp

<div class="space-y-6">
    <!-- Top Header & Mode Switcher -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5 transition-colors duration-200">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white flex items-center gap-3 tracking-tight">
                    <span class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm font-black shadow-sm">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </span>
                    <span>{{ __('Workforce Attendance & Present Register') }}</span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                    {{ __('Admin date-wise present status and month-wise muster matrix across all active construction sites without double-counting.') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Mode Switcher (Date-Wise vs Month-Wise) -->
                <div class="inline-flex rounded-xl bg-slate-100 dark:bg-slate-950 p-1 border border-slate-200 dark:border-slate-800">
                    <a href="{{ route($currentRoute, array_filter(['mode' => 'date', 'date' => $date, 'site_id' => $siteId, 'worker_type' => $workerType, 'search' => $search])) }}"
                       class="px-3.5 py-1.5 rounded-lg text-xs font-extrabold transition flex items-center gap-1.5 {{ $mode === 'date' ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                        <span>{{ __('Date-Wise View') }}</span>
                    </a>
                    <a href="{{ route($currentRoute, array_filter(['mode' => 'month', 'month' => $month, 'site_id' => $siteId, 'worker_type' => $workerType, 'search' => $search])) }}"
                       class="px-3.5 py-1.5 rounded-lg text-xs font-extrabold transition flex items-center gap-1.5 {{ $mode === 'month' ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                        <span>{{ __('Month-Wise Matrix') }}</span>
                    </a>
                </div>

                <a href="{{ route('attendance.muster', array_filter(['site_id' => $siteId, 'date' => $date])) }}"
                   class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 transition">
                    {{ __('Mark Daily Muster') }}
                </a>

                <a href="{{ route('reports.multi_site') }}"
                   class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 transition">
                    {{ __('Multi-Site Report') }}
                </a>

                @if(Auth::user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff']))
                    <a href="{{ route('reports.audit_logs') }}"
                       class="px-3.5 py-2 text-xs font-bold rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30 transition">
                        {{ __('Audit Trail') }}
                    </a>
                @endif

                @if(Auth::user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff']))
                    <a href="{{ route('attendance.export_payroll', array_filter(['site_id' => $siteId, 'start_date' => $exportStartDate, 'end_date' => $exportEndDate])) }}"
                       class="px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30 transition flex items-center gap-1.5">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                        <span>{{ __('Export CSV') }}</span>
                    </a>
                @endif
            </div>
        </div>

        <!-- Filter Form -->
        <form method="GET" action="{{ route($currentRoute) }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
            <input type="hidden" name="mode" value="{{ $mode }}">

            @if($mode === 'month')
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">{{ __('Select Month') }}</label>
                    <input type="month" name="month" value="{{ $month }}" onchange="this.form.submit()"
                           class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            @else
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">{{ __('Select Date') }}</label>
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()"
                           class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            @endif

            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">{{ __('Site') }}</label>
                <select name="site_id" onchange="this.form.submit()"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    <option value="">{{ __('All Sites') }}</option>
                    @foreach($sitesList as $s)
                        <option value="{{ $s->id }}" {{ (int) $siteId === (int) $s->id ? 'selected' : '' }}>
                            {{ $s->code }} — {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">{{ __('Worker Category') }}</label>
                <select name="worker_type" onchange="this.form.submit()"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    <option value="">{{ __('All Categories') }}</option>
                    <option value="employee" {{ $workerType === 'employee' ? 'selected' : '' }}>{{ __('Direct Employee') }}</option>
                    <option value="direct_labour" {{ $workerType === 'direct_labour' ? 'selected' : '' }}>{{ __('Direct Labour') }}</option>
                    <option value="subcontractor_worker" {{ $workerType === 'subcontractor_worker' ? 'selected' : '' }}>{{ __('Subcontractor Worker') }}</option>
                </select>
            </div>

            @if($mode === 'date')
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">{{ __('Present Status') }}</label>
                    <select name="presence_filter" onchange="this.form.submit()"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="all" {{ $presenceFilter === 'all' ? 'selected' : '' }}>{{ __('All Workers') }}</option>
                        <option value="present" {{ $presenceFilter === 'present' ? 'selected' : '' }}>{{ __('Present (P)') }}</option>
                        <option value="half_day" {{ $presenceFilter === 'half_day' ? 'selected' : '' }}>{{ __('Half Day (HD)') }}</option>
                        <option value="paid_leave" {{ $presenceFilter === 'paid_leave' ? 'selected' : '' }}>{{ __('Paid Leave (PL)') }}</option>
                        <option value="absent" {{ $presenceFilter === 'absent' ? 'selected' : '' }}>{{ __('Absent (A)') }}</option>
                        <option value="unmarked" {{ $presenceFilter === 'unmarked' ? 'selected' : '' }}>{{ __('Not Marked (—)') }}</option>
                    </select>
                </div>
            @endif

            <div class="{{ $mode === 'month' ? 'lg:col-span-2' : '' }}">
                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase tracking-wider mb-1">{{ __('Search Worker') }}</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Name, ID code, or trade...') }}"
                       class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
            </div>

            <div class="flex items-end gap-2">
                <button type="submit" class="w-full py-2 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs sm:text-sm shadow transition">
                    {{ __('Filter') }}
                </button>
            </div>
        </form>
    </div>

    @if($mode === 'date' && $dateRegister)
        @php
            $sum = $dateRegister['summary'];
            $parsedDate = \Carbon\Carbon::parse($dateRegister['date']);
            $prevDate = $parsedDate->copy()->subDay()->format('Y-m-d');
            $nextDate = $parsedDate->copy()->addDay()->format('Y-m-d');
        @endphp

        <!-- Date Navigation Banner & Quick Status Pills -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex items-center gap-2">
                <a href="{{ route($currentRoute, array_filter(['mode' => 'date', 'date' => $prevDate, 'site_id' => $siteId, 'worker_type' => $workerType, 'presence_filter' => $presenceFilter, 'search' => $search])) }}"
                   class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 transition">
                    &larr; {{ __('Prev Day') }}
                </a>
                <span class="px-3 py-1.5 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-xs sm:text-sm font-extrabold">
                    {{ $parsedDate->format('D, d M Y') }}
                </span>
                <a href="{{ route($currentRoute, array_filter(['mode' => 'date', 'date' => $nextDate, 'site_id' => $siteId, 'worker_type' => $workerType, 'presence_filter' => $presenceFilter, 'search' => $search])) }}"
                   class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 transition">
                    {{ __('Next Day') }} &rarr;
                </a>
                @if($dateRegister['date'] !== \Carbon\Carbon::today()->format('Y-m-d'))
                    <a href="{{ route($currentRoute, array_filter(['mode' => 'date', 'date' => \Carbon\Carbon::today()->format('Y-m-d'), 'site_id' => $siteId, 'worker_type' => $workerType])) }}"
                       class="px-2.5 py-1.5 rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-xs font-bold">
                        {{ __('Today') }}
                    </a>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="px-2.5 py-1 rounded-lg bg-emerald-500/10 text-emerald-700 dark:text-emerald-300 font-bold border border-emerald-500/20">
                    P: {{ $sum['present_count'] }} {{ __('Present') }}
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-amber-500/10 text-amber-700 dark:text-amber-300 font-bold border border-amber-500/20">
                    HD: {{ $sum['half_day_count'] }} {{ __('Half Day') }}
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-sky-500/10 text-sky-700 dark:text-sky-300 font-bold border border-sky-500/20">
                    PL: {{ $sum['paid_leave_count'] }} {{ __('Paid Leave') }}
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-rose-500/10 text-rose-700 dark:text-rose-300 font-bold border border-rose-500/20">
                    A: {{ $sum['absent_count'] }} {{ __('Absent') }}
                </span>
                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold border border-slate-300 dark:border-slate-700">
                    —: {{ $sum['unmarked_count'] }} {{ __('Unmarked') }}
                </span>
            </div>
        </div>

        <!-- Date-Wise KPI Summary Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total Active Workers') }}</span>
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $sum['total_workers'] }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Assigned / active roster') }}</div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-emerald-500/30 shadow-sm">
                <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">{{ __('Present (Full Day)') }}</span>
                <div class="text-2xl sm:text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ $sum['present_count'] }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Emp') }}: {{ $sum['by_worker_type_present']['employee'] }} &bull;
                    {{ __('Lab') }}: {{ $sum['by_worker_type_present']['direct_labour'] }} &bull;
                    {{ __('Sub') }}: {{ $sum['by_worker_type_present']['subcontractor_worker'] }}
                </div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-amber-500/30 shadow-sm">
                <span class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">{{ __('Half Day (HD)') }}</span>
                <div class="text-2xl sm:text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $sum['half_day_count'] }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('0.5 day credit') }}</div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-sky-500/30 shadow-sm">
                <span class="text-[11px] font-bold text-sky-600 dark:text-sky-400 uppercase tracking-wider">{{ __('Paid Leave (PL)') }}</span>
                <div class="text-2xl sm:text-3xl font-extrabold text-sky-600 dark:text-sky-400 mt-1">{{ $sum['paid_leave_count'] }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Approved leave') }}</div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-rose-500/30 shadow-sm">
                <span class="text-[11px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider">{{ __('Absent / Unmarked') }}</span>
                <div class="text-2xl sm:text-3xl font-extrabold text-rose-600 dark:text-rose-400 mt-1">
                    {{ $sum['absent_count'] }} <span class="text-sm font-normal text-slate-400">/ {{ $sum['unmarked_count'] }}</span>
                </div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Absent / Not marked yet') }}</div>
            </div>

            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-indigo-500/30 shadow-sm">
                <span class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">{{ __('Worked / OT Hours') }}</span>
                <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white mt-1">
                    {{ number_format($sum['total_worked_hours'], 1) }}<span class="text-xs font-normal text-slate-400">h</span>
                </div>
                <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold mt-0.5">
                    +{{ number_format($sum['total_overtime_hours'], 1) }}h {{ __('Overtime') }}
                </div>
            </div>
        </div>

        <!-- Date-Wise Register Table -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                        {{ __('Date-Wise Worker Attendance Register') }} ({{ count($dateRegister['rows']) }})
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Complete present/absent status for every active worker on') }} {{ $parsedDate->format('d M Y') }}.
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs sm:text-sm text-slate-700 dark:text-slate-300">
                    <thead class="text-[11px] uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3 px-4 font-bold">{{ __('Worker') }}</th>
                            <th class="py-3 px-4 font-bold">{{ __('Category / Trade') }}</th>
                            <th class="py-3 px-4 font-bold">{{ __('Site') }}</th>
                            <th class="py-3 px-4 font-bold text-center">{{ __('Present Status') }}</th>
                            <th class="py-3 px-4 font-bold">{{ __('Clock In / Out') }}</th>
                            <th class="py-3 px-4 font-bold text-right">{{ __('Worked Hrs') }}</th>
                            <th class="py-3 px-4 font-bold text-right">{{ __('OT Hrs') }}</th>
                            <th class="py-3 px-4 font-bold text-center">{{ __('Lock / Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                        @forelse($dateRegister['rows'] as $row)
                            @php
                                $w = $row['worker'];
                                $st = $row['status'];
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-3 px-4">
                                    <a href="{{ route('workforce.show', $w) }}" class="font-bold text-slate-900 dark:text-white hover:text-amber-600 dark:hover:text-amber-400">
                                        {{ $w->full_name }}
                                    </a>
                                    <div class="text-[11px] font-mono text-amber-600 dark:text-amber-400">{{ $w->code }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200">{{ $w->trade_designation ?: 'General' }}</span>
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400 capitalize">{{ str_replace('_', ' ', $w->worker_type) }}</div>
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    @if(!empty($row['attended_sites']))
                                        <span class="font-bold text-slate-900 dark:text-white">{{ implode(', ', $row['attended_sites']) }}</span>
                                    @elseif($row['assigned_site'])
                                        <span class="text-slate-500 dark:text-slate-400">{{ $row['assigned_site']->code }} ({{ __('Assigned') }})</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-center">
                                    @if($st === 'present')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-extrabold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            {{ __('Present (P)') }}
                                        </span>
                                    @elseif($st === 'half_day')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-extrabold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            {{ __('Half Day (HD)') }}
                                        </span>
                                    @elseif($st === 'paid_leave')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-extrabold bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30">
                                            {{ __('Paid Leave (PL)') }}
                                        </span>
                                    @elseif($st === 'absent')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-extrabold bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30">
                                            {{ __('Absent (A)') }}
                                        </span>
                                    @elseif($st === 'holiday')
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-extrabold bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">
                                            {{ __('Holiday (H)') }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
                                            {{ __('Not Marked') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-mono text-xs">
                                    @if($row['clock_in'] || $row['clock_out'])
                                        <span class="text-slate-900 dark:text-white">{{ $row['clock_in'] ?? '--:--' }}</span>
                                        <span class="text-slate-400">&rarr;</span>
                                        <span class="text-slate-900 dark:text-white">{{ $row['clock_out'] ?? '--:--' }}</span>
                                        @if($row['late_minutes'] > 0)
                                            <span class="ml-1 px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-600 dark:text-rose-400 text-[10px] font-bold">
                                                +{{ $row['late_minutes'] }}m {{ __('late') }}
                                            </span>
                                        @endif
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 font-mono text-right font-bold text-slate-900 dark:text-white">
                                    {{ $row['worked_hours'] > 0 ? number_format($row['worked_hours'], 2) . ' h' : '0.00 h' }}
                                </td>
                                <td class="py-3 px-4 font-mono text-right font-bold {{ $row['overtime_hours'] > 0 ? 'text-indigo-600 dark:text-indigo-400' : 'text-slate-400' }}">
                                    {{ $row['overtime_hours'] > 0 ? '+' . number_format($row['overtime_hours'], 2) . ' h' : '0.00 h' }}
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ in_array($row['lock_stage'], ['site_locked', 'payroll_locked']) ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-500' }}">
                                            {{ str_replace('_', ' ', $row['lock_stage']) }}
                                        </span>
                                        @if($row['assigned_site'])
                                            <a href="{{ route('attendance.muster', ['site_id' => $row['assigned_site']->id, 'date' => $dateRegister['date']]) }}"
                                               class="text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:underline">
                                                {{ __('Muster') }}
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-slate-500">
                                    {{ __('No matching workers found for the selected filter criteria.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if($mode === 'month' && $monthMatrix)
        @php
            $mSum = $monthMatrix['summary'];
        @endphp

        <!-- Month Navigation & Legend Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-4 py-3 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex items-center gap-2">
                <a href="{{ route($currentRoute, array_filter(['mode' => 'month', 'month' => $monthMatrix['prev_month'], 'site_id' => $siteId, 'worker_type' => $workerType, 'search' => $search])) }}"
                   class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 transition">
                    &larr; {{ $monthMatrix['prev_month'] }}
                </a>
                <span class="px-3.5 py-1.5 rounded-lg bg-amber-500/15 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-xs sm:text-sm font-extrabold">
                    {{ $monthMatrix['month_label'] }}
                </span>
                <a href="{{ route($currentRoute, array_filter(['mode' => 'month', 'month' => $monthMatrix['next_month'], 'site_id' => $siteId, 'worker_type' => $workerType, 'search' => $search])) }}"
                   class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 transition">
                    {{ $monthMatrix['next_month'] }} &rarr;
                </a>
            </div>

            <!-- Legend -->
            <div class="flex flex-wrap items-center gap-2 text-[11px] font-bold">
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">P = {{ __('Present') }}</span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">HD = {{ __('Half Day') }}</span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30">PL = {{ __('Paid Leave') }}</span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30">A = {{ __('Absent') }}</span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30">H = {{ __('Holiday') }}</span>
                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-500">— = {{ __('Unmarked') }}</span>
            </div>
        </div>

        <!-- Monthly Summary KPI Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Workers in Matrix') }}</span>
                <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $mSum['total_workers'] }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $monthMatrix['month_label'] }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-emerald-500/30 shadow-sm">
                <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400 uppercase tracking-wider">{{ __('Total Present Man-Days') }}</span>
                <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ $mSum['total_present_days'] }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Full days (P)') }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-amber-500/30 shadow-sm">
                <span class="text-[11px] font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">{{ __('Half Days (HD)') }}</span>
                <div class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $mSum['total_half_days'] }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Half-day shifts') }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-sky-500/30 shadow-sm">
                <span class="text-[11px] font-bold text-sky-600 dark:text-sky-400 uppercase tracking-wider">{{ __('Paid Leaves (PL)') }}</span>
                <div class="text-2xl font-extrabold text-sky-600 dark:text-sky-400 mt-1">{{ $mSum['total_paid_leaves'] }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Approved paid leaves') }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-rose-500/30 shadow-sm">
                <span class="text-[11px] font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider">{{ __('Absent Days (A)') }}</span>
                <div class="text-2xl font-extrabold text-rose-600 dark:text-rose-400 mt-1">{{ $mSum['total_absent_days'] }}</div>
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Recorded absences') }}</div>
            </div>
            <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-indigo-500/30 shadow-sm">
                <span class="text-[11px] font-bold text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">{{ __('Month Hours / OT') }}</span>
                <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ number_format($mSum['total_worked_hours'], 1) }}h</div>
                <div class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold mt-0.5">+{{ number_format($mSum['total_overtime_hours'], 1) }}h {{ __('OT') }}</div>
            </div>
        </div>

        <!-- Monthly Attendance Calendar Grid -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                        {{ __('Month-Wise Attendance Register Matrix') }} &mdash; {{ $monthMatrix['month_label'] }}
                    </h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Click any day header to open the Date-Wise Register for that specific day.') }}
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300 border-collapse">
                    <thead class="text-[10px] uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-2.5 px-3 font-bold sticky left-0 bg-slate-50 dark:bg-slate-950 z-10 min-w-[175px] border-r border-slate-200 dark:border-slate-800">
                                {{ __('Worker') }}
                            </th>
                            <th class="py-2.5 px-2 font-bold min-w-[90px] border-r border-slate-200 dark:border-slate-800">
                                {{ __('Site') }}
                            </th>
                            @foreach($monthMatrix['days'] as $dayInfo)
                                <th class="py-2 px-1 text-center min-w-[32px] border-r border-slate-200/60 dark:border-slate-800/60 {{ $dayInfo['is_sunday'] ? 'bg-rose-500/5 text-rose-600 dark:text-rose-400' : '' }}">
                                    <a href="{{ route($currentRoute, array_filter(['mode' => 'date', 'date' => $dayInfo['date'], 'site_id' => $siteId, 'worker_type' => $workerType])) }}"
                                       class="block hover:text-amber-600 dark:hover:text-amber-400"
                                       title="{{ __('Open Date-Wise View for') }} {{ $dayInfo['date'] }}">
                                        <div class="font-extrabold text-[11px]">{{ $dayInfo['day_num'] }}</div>
                                        <div class="text-[9px] opacity-75">{{ $dayInfo['day_short'] }}</div>
                                    </a>
                                </th>
                            @endforeach
                            <th class="py-2 px-2 text-center font-extrabold text-emerald-600 dark:text-emerald-400 bg-emerald-500/5 border-l border-slate-200 dark:border-slate-800" title="{{ __('Present Days') }}">P</th>
                            <th class="py-2 px-2 text-center font-extrabold text-amber-600 dark:text-amber-400 bg-amber-500/5" title="{{ __('Half Days') }}">HD</th>
                            <th class="py-2 px-2 text-center font-extrabold text-sky-600 dark:text-sky-400 bg-sky-500/5" title="{{ __('Paid Leaves') }}">PL</th>
                            <th class="py-2 px-2 text-center font-extrabold text-rose-600 dark:text-rose-400 bg-rose-500/5" title="{{ __('Absent Days') }}">A</th>
                            <th class="py-2 px-2.5 text-right font-extrabold text-slate-900 dark:text-white bg-slate-100/70 dark:bg-slate-900" title="{{ __('Total Worked Hours') }}">{{ __('Hrs') }}</th>
                            <th class="py-2 px-2.5 text-right font-extrabold text-indigo-600 dark:text-indigo-400 bg-indigo-500/5" title="{{ __('Total Overtime Hours') }}">{{ __('OT') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                        @forelse($monthMatrix['rows'] as $wRow)
                            @php
                                $w = $wRow['worker'];
                            @endphp
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-2.5 px-3 sticky left-0 bg-white dark:bg-slate-900 z-10 border-r border-slate-200 dark:border-slate-800">
                                    <a href="{{ route('workforce.show', $w) }}" class="font-bold text-slate-900 dark:text-white hover:text-amber-600 dark:hover:text-amber-400 block truncate max-w-[165px]">
                                        {{ $w->full_name }}
                                    </a>
                                    <div class="text-[10px] font-mono text-amber-600 dark:text-amber-400">{{ $w->code }}</div>
                                </td>
                                <td class="py-2.5 px-2 text-[11px] font-mono text-slate-600 dark:text-slate-400 border-r border-slate-200 dark:border-slate-800">
                                    {{ $wRow['assigned_site']?->code ?? '—' }}
                                </td>
                                @foreach($monthMatrix['days'] as $dayInfo)
                                    @php
                                        $cell = $wRow['cells'][$dayInfo['date']];
                                        $cStatus = $cell['status'];
                                        $tooltip = $dayInfo['date'] . ': ' . strtoupper($cStatus);
                                        if ($cell['hours'] > 0) {
                                            $tooltip .= ' (' . $cell['hours'] . 'h' . ($cell['site_code'] ? ' @ ' . $cell['site_code'] : '') . ')';
                                        }
                                    @endphp
                                    <td class="py-1.5 px-0.5 text-center border-r border-slate-200/50 dark:border-slate-800/50 {{ $dayInfo['is_sunday'] ? 'bg-rose-500/[0.03]' : '' }}" title="{{ $tooltip }}">
                                        @if($cStatus === 'present')
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 font-extrabold text-[10px] border border-emerald-500/30">P</span>
                                        @elseif($cStatus === 'half_day')
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-amber-500/15 text-amber-700 dark:text-amber-300 font-extrabold text-[10px] border border-amber-500/30">HD</span>
                                        @elseif($cStatus === 'paid_leave')
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-sky-500/15 text-sky-700 dark:text-sky-300 font-extrabold text-[10px] border border-sky-500/30">PL</span>
                                        @elseif($cStatus === 'absent')
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-rose-500/15 text-rose-700 dark:text-rose-300 font-extrabold text-[10px] border border-rose-500/30">A</span>
                                        @elseif($cStatus === 'holiday')
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-md bg-indigo-500/15 text-indigo-700 dark:text-indigo-300 font-extrabold text-[10px] border border-indigo-500/30">H</span>
                                        @else
                                            <span class="text-slate-300 dark:text-slate-700 text-[10px]">&middot;</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="py-2 px-2 text-center font-mono font-extrabold text-emerald-600 dark:text-emerald-400 bg-emerald-500/5 border-l border-slate-200 dark:border-slate-800">
                                    {{ $wRow['present_days'] }}
                                </td>
                                <td class="py-2 px-2 text-center font-mono font-bold text-amber-600 dark:text-amber-400 bg-amber-500/5">
                                    {{ $wRow['half_days'] }}
                                </td>
                                <td class="py-2 px-2 text-center font-mono font-bold text-sky-600 dark:text-sky-400 bg-sky-500/5">
                                    {{ $wRow['paid_leaves'] }}
                                </td>
                                <td class="py-2 px-2 text-center font-mono font-bold text-rose-600 dark:text-rose-400 bg-rose-500/5">
                                    {{ $wRow['absent_days'] }}
                                </td>
                                <td class="py-2 px-2.5 text-right font-mono font-bold text-slate-900 dark:text-white bg-slate-100/50 dark:bg-slate-900">
                                    {{ number_format($wRow['worked_hours'], 1) }}
                                </td>
                                <td class="py-2 px-2.5 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400 bg-indigo-500/5">
                                    {{ number_format($wRow['overtime_hours'], 1) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ count($monthMatrix['days']) + 8 }}" class="py-8 text-center text-slate-500">
                                    {{ __('No workers found for the selected filter criteria.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if(count($monthMatrix['rows']) > 0)
                        <tfoot class="bg-slate-50 dark:bg-slate-950 border-t-2 border-slate-200 dark:border-slate-800 text-[10px] font-extrabold">
                            <tr>
                                <td class="py-2.5 px-3 sticky left-0 bg-slate-50 dark:bg-slate-950 z-10 text-slate-700 dark:text-slate-300 border-r border-slate-200 dark:border-slate-800">
                                    {{ __('Daily Present Total (P+HD)') }}
                                </td>
                                <td class="py-2.5 px-2 text-center text-slate-500 border-r border-slate-200 dark:border-slate-800">
                                    {{ __('All') }}
                                </td>
                                @foreach($monthMatrix['days'] as $dayInfo)
                                    @php
                                        $dTot = $monthMatrix['daily_totals'][$dayInfo['date']] ?? ['present' => 0, 'half_day' => 0];
                                        $activeCount = $dTot['present'] + $dTot['half_day'];
                                    @endphp
                                    <td class="py-2 px-1 text-center font-mono border-r border-slate-200/50 dark:border-slate-800/50 {{ $activeCount > 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">
                                        {{ $activeCount > 0 ? $activeCount : '0' }}
                                    </td>
                                @endforeach
                                <td class="py-2 px-2 text-center font-mono text-emerald-600 dark:text-emerald-400 border-l border-slate-200 dark:border-slate-800">{{ $mSum['total_present_days'] }}</td>
                                <td class="py-2 px-2 text-center font-mono text-amber-600 dark:text-amber-400">{{ $mSum['total_half_days'] }}</td>
                                <td class="py-2 px-2 text-center font-mono text-sky-600 dark:text-sky-400">{{ $mSum['total_paid_leaves'] }}</td>
                                <td class="py-2 px-2 text-center font-mono text-rose-600 dark:text-rose-400">{{ $mSum['total_absent_days'] }}</td>
                                <td class="py-2 px-2.5 text-right font-mono text-slate-900 dark:text-white">{{ number_format($mSum['total_worked_hours'], 1) }}</td>
                                <td class="py-2 px-2.5 text-right font-mono text-indigo-600 dark:text-indigo-400">{{ number_format($mSum['total_overtime_hours'], 1) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endif
</div>
@endsection


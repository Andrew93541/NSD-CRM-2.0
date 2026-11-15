@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ editShiftId: null, editHolidayId: null, editLeaveTypeId: null }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                {{ __('Shifts, Leave Management & Holiday Calendar') }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                {{ __('Configure shift grace periods, leave quotas, site holidays, and worker leave approvals.') }}
            </p>
        </div>
        <a href="{{ route('attendance.muster') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 text-xs font-bold transition">
            &larr; {{ __('Back to Muster Roll') }}
        </a>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 1. Shift Configuration Card -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Shift Rules & Overtime Thresholds') }}</h2>

            @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']))
                <form method="POST" action="{{ route('attendance.shifts.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Shift Name') }} *</label>
                        <input type="text" name="name" required placeholder="General Day Shift" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Site (Optional)') }}</label>
                        <select name="site_id" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Company-Wide Default') }}</option>
                            @foreach($accessibleSites as $s)
                                <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Grace Minutes') }}</label>
                        <input type="number" name="grace_minutes" value="15" min="0" max="120" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Start Time') }}</label>
                        <input type="time" name="start_time" value="09:00" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('End Time') }}</label>
                        <input type="time" name="end_time" value="18:00" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('OT After (Hours)') }}</label>
                        <input type="number" step="0.5" name="overtime_after_hours" value="8" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                        <input type="hidden" name="half_day_hours" value="4">
                        <input type="hidden" name="full_day_hours" value="8">
                    </div>
                    <div class="sm:col-span-3 flex items-center justify-between">
                        <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                            <input type="checkbox" name="is_default" value="1" class="rounded text-amber-500">
                            <span>{{ __('Default Shift for Scope') }}</span>
                        </label>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                            + {{ __('Save Shift Rule') }}
                        </button>
                    </div>
                </form>
            @endif

            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($shifts as $sh)
                    <div class="py-2.5 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <span class="font-bold text-slate-900 dark:text-white">{{ $sh->name }}</span>
                                <span class="text-slate-500 ml-1">({{ $sh->site?->code ?? __('All Sites') }})</span>
                                @if($sh->is_default)
                                    <span class="ml-1 px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-700 dark:text-amber-300 text-[10px] font-bold">{{ __('Default') }}</span>
                                @endif
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-slate-600 dark:text-slate-300">
                                    {{ substr($sh->start_time, 0, 5) }} - {{ substr($sh->end_time, 0, 5) }} &bull; {{ __('Grace') }}: {{ $sh->grace_minutes }}m &bull; {{ __('OT >') }} {{ $sh->overtime_after_hours }}h
                                </span>
                                @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']))
                                    <button type="button" @click="editShiftId = (editShiftId === {{ $sh->id }} ? null : {{ $sh->id }})" class="font-bold text-amber-600 dark:text-amber-400 hover:underline">{{ __('Edit') }}</button>
                                    <form method="POST" action="{{ route('attendance.shifts.destroy', $sh) }}" class="inline" onsubmit="return confirm('Delete shift {{ addslashes($sh->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-bold text-rose-600 dark:text-rose-400 hover:underline">{{ __('Delete') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']))
                            <form x-show="editShiftId === {{ $sh->id }}" x-cloak method="POST" action="{{ route('attendance.shifts.update', $sh) }}" class="grid grid-cols-2 sm:grid-cols-5 gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $sh->name }}" required class="rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1 text-xs">
                                <input type="time" name="start_time" value="{{ substr($sh->start_time, 0, 5) }}" required class="rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1 text-xs">
                                <input type="time" name="end_time" value="{{ substr($sh->end_time, 0, 5) }}" required class="rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1 text-xs">
                                <input type="number" name="grace_minutes" value="{{ $sh->grace_minutes }}" min="0" max="120" required class="rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1 text-xs">
                                <div class="flex items-center justify-end gap-2">
                                    <input type="hidden" name="site_id" value="{{ $sh->site_id }}">
                                    <input type="hidden" name="half_day_hours" value="{{ $sh->half_day_hours }}">
                                    <input type="hidden" name="full_day_hours" value="{{ $sh->full_day_hours }}">
                                    <input type="hidden" name="overtime_after_hours" value="{{ $sh->overtime_after_hours }}">
                                    <label class="inline-flex items-center gap-1 text-[11px] font-bold">
                                        <input type="checkbox" name="is_default" value="1" {{ $sh->is_default ? 'checked' : '' }} class="rounded text-amber-500">
                                        <span>{{ __('Def') }}</span>
                                    </label>
                                    <button type="submit" class="px-2.5 py-1 rounded bg-amber-500 text-slate-950 font-bold">{{ __('Save') }}</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="py-4 text-center text-slate-500">{{ __('Using site-level shift defaults (09:00 - 18:00, 8h OT threshold).') }}</div>
                @endforelse
            </div>
        </div>

        <!-- 2. Holiday Calendar Card -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Holiday Calendar') }}</h2>

            @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager']))
                <form method="POST" action="{{ route('attendance.holidays.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                    @csrf
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Holiday Name') }} *</label>
                        <input type="text" name="name" required placeholder="Diwali / Maharashtra Day" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Holiday Date') }} *</label>
                        <input type="date" name="holiday_date" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Site Scope') }}</label>
                        <select name="site_id" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('All Company Sites') }}</option>
                            @foreach($accessibleSites as $s)
                                <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-3 flex items-center justify-between">
                        <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                            <input type="checkbox" name="is_paid" value="1" checked class="rounded text-amber-500">
                            <span>{{ __('Paid Holiday') }}</span>
                        </label>
                        <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                            + {{ __('Add Holiday') }}
                        </button>
                    </div>
                </form>
            @endif

            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($holidays as $h)
                    <div class="py-2.5 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <span class="font-bold text-slate-900 dark:text-white">{{ $h->name }}</span>
                                <span class="text-slate-500 ml-1">({{ $h->site?->code ?? __('All Sites') }})</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-amber-600 dark:text-amber-400">
                                    {{ $h->holiday_date->format('d M Y') }} &bull; {{ $h->is_paid ? __('Paid') : __('Unpaid') }}
                                </span>
                                @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager']))
                                    <button type="button" @click="editHolidayId = (editHolidayId === {{ $h->id }} ? null : {{ $h->id }})" class="font-bold text-amber-600 dark:text-amber-400 hover:underline">{{ __('Edit') }}</button>
                                    <form method="POST" action="{{ route('attendance.holidays.destroy', $h) }}" class="inline" onsubmit="return confirm('Remove holiday {{ addslashes($h->name) }}?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="font-bold text-rose-600 dark:text-rose-400 hover:underline">{{ __('Delete') }}</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager']))
                            <form x-show="editHolidayId === {{ $h->id }}" x-cloak method="POST" action="{{ route('attendance.holidays.update', $h) }}" class="grid grid-cols-1 sm:grid-cols-4 gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                @csrf
                                @method('PUT')
                                <input type="text" name="name" value="{{ $h->name }}" required class="rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1 text-xs">
                                <input type="date" name="holiday_date" value="{{ $h->holiday_date->format('Y-m-d') }}" required class="rounded bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1 text-xs">
                                <label class="inline-flex items-center gap-1.5 text-xs font-bold">
                                    <input type="checkbox" name="is_paid" value="1" {{ $h->is_paid ? 'checked' : '' }} class="rounded text-amber-500">
                                    <span>{{ __('Paid') }}</span>
                                </label>
                                <div class="flex justify-end">
                                    <input type="hidden" name="site_id" value="{{ $h->site_id }}">
                                    <button type="submit" class="px-3 py-1 rounded bg-amber-500 text-slate-950 font-bold">{{ __('Save') }}</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @empty
                    <div class="py-4 text-center text-slate-500">{{ __('No holidays scheduled yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 3. Leave Types & Annual Quotas Configuration -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Leave Categories & Annual Quotas') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Set annual leave limits per worker (set 0 for unlimited). Scheduled holidays are automatically excluded from leave days.') }}</p>
            </div>
        </div>

        @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager']) || auth()->user()->hasPermission('attendance.manage'))
            <form method="POST" action="{{ route('attendance.leave_types.store') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 items-end">
                @csrf
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Leave Name') }} *</label>
                    <input type="text" name="name" required placeholder="Casual Leave" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Code') }} *</label>
                    <input type="text" name="code" required placeholder="CL" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs font-mono uppercase">
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Annual Quota (Days)') }} *</label>
                    <input type="number" name="annual_quota" value="12" min="0" max="365" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs font-mono">
                </div>
                <div>
                    <label class="inline-flex items-center gap-2 text-xs font-bold py-2">
                        <input type="checkbox" name="is_paid" value="1" checked class="rounded text-amber-500">
                        <span>{{ __('Paid Leave') }}</span>
                    </label>
                    <input type="hidden" name="is_active" value="1">
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                        + {{ __('Add Leave Type') }}
                    </button>
                </div>
            </form>
        @endif

        @if(($leaveTypes ?? collect())->isNotEmpty())
            <div class="flex flex-wrap gap-2.5 text-xs">
                @foreach($leaveTypes as $lt)
                    <div class="px-3.5 py-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center gap-2.5">
                        <span class="font-bold text-slate-900 dark:text-white">{{ $lt->name }}</span>
                        <span class="font-mono text-slate-500">({{ $lt->code }})</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold {{ $lt->is_paid ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                            {{ $lt->is_paid ? __('Paid') : __('Unpaid') }} &bull; {{ $lt->annual_quota > 0 ? $lt->annual_quota.'d/yr' : __('No Cap') }}
                        </span>
                        @if(!$lt->is_active)
                            <span class="text-[10px] font-bold text-rose-500">{{ __('Inactive') }}</span>
                        @endif
                        @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager']) || auth()->user()->hasPermission('attendance.manage'))
                            <form method="POST" action="{{ route('attendance.leave_types.destroy', $lt) }}" class="inline" onsubmit="return confirm('Delete leave type {{ addslashes($lt->name) }}?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-600 dark:text-rose-400 font-bold hover:underline">×</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- 4. Worker Leave Requests & Review Table -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
        <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Worker Leave Requests') }}</h2>

        <form method="POST" action="{{ route('attendance.leaves.store') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
            @csrf
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Select Worker') }} *</label>
                <select name="worker_id" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    @foreach($workers as $w)
                        <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->full_name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Leave Type') }}</label>
                @php
                    $activeLeaveTypes = ($leaveTypes ?? collect())->where('is_active', true);
                @endphp
                @if($activeLeaveTypes->isNotEmpty())
                    <select name="leave_type_id" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                        @foreach($activeLeaveTypes as $lt)
                            <option value="{{ $lt->id }}">{{ $lt->name }} ({{ $lt->annual_quota > 0 ? $lt->annual_quota.'d/yr' : __('Unlimited') }})</option>
                        @endforeach
                    </select>
                @else
                    <select name="leave_type_name" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                        <option value="Paid Leave">{{ __('Paid Leave') }}</option>
                        <option value="Sick Leave">{{ __('Sick Leave') }}</option>
                        <option value="Casual Leave">{{ __('Casual Leave') }}</option>
                    </select>
                @endif
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Start Date') }} *</label>
                <input type="date" name="start_date" required value="{{ now()->format('Y-m-d') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('End Date') }} *</label>
                <input type="date" name="end_date" required value="{{ now()->format('Y-m-d') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Reason') }} *</label>
                <div class="flex gap-2">
                    <input type="text" name="reason" required placeholder="{{ __('Medical / Family...') }}" class="flex-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    <input type="hidden" name="is_paid" value="1">
                    <button type="submit" class="px-3 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                        {{ __('Submit') }}
                    </button>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead class="uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-2.5 px-3 font-semibold">{{ __('Worker') }}</th>
                        <th class="py-2.5 px-3 font-semibold">{{ __('Leave Type') }}</th>
                        <th class="py-2.5 px-3 font-semibold">{{ __('Dates & Working Days') }}</th>
                        <th class="py-2.5 px-3 font-semibold">{{ __('Reason') }}</th>
                        <th class="py-2.5 px-3 font-semibold">{{ __('Status') }}</th>
                        <th class="py-2.5 px-3 font-semibold text-right">{{ __('Review') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($leaveRequests as $lr)
                        <tr>
                            <td class="py-2.5 px-3 font-bold text-slate-900 dark:text-white">
                                {{ $lr->worker?->full_name ?? '—' }}
                                <span class="font-mono text-slate-500 font-normal">({{ $lr->worker?->code }})</span>
                            </td>
                            <td class="py-2.5 px-3">{{ $lr->leave_type_name }} ({{ $lr->is_paid ? __('Paid') : __('Unpaid') }})</td>
                            <td class="py-2.5 px-3 font-mono">
                                {{ $lr->start_date->format('d M') }} – {{ $lr->end_date->format('d M Y') }}
                                <span class="font-bold text-amber-600 dark:text-amber-400">({{ $lr->days_count }}d)</span>
                            </td>
                            <td class="py-2.5 px-3">{{ $lr->reason }}</td>
                            <td class="py-2.5 px-3">
                                <span class="px-2 py-0.5 rounded-full text-[11px] font-bold {{ $lr->status === 'approved' ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : ($lr->status === 'rejected' ? 'bg-rose-500/15 text-rose-700 dark:text-rose-300' : 'bg-amber-500/15 text-amber-700 dark:text-amber-300') }}">
                                    {{ ucfirst($lr->status) }}
                                </span>
                            </td>
                            <td class="py-2.5 px-3 text-right whitespace-nowrap">
                                @if($lr->status === 'pending' && auth()->user()->hasPermission('attendance.approve') && (int) $lr->requested_by !== (int) auth()->id())
                                    <form method="POST" action="{{ route('attendance.leaves.review', $lr) }}" class="inline">
                                        @csrf
                                        <input type="hidden" name="action" value="approved">
                                        <button type="submit" class="px-2.5 py-1 rounded bg-emerald-600 text-white font-bold hover:bg-emerald-500">{{ __('Approve') }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('attendance.leaves.review', $lr) }}" class="inline ml-1">
                                        @csrf
                                        <input type="hidden" name="action" value="rejected">
                                        <button type="submit" class="px-2.5 py-1 rounded bg-rose-600 text-white font-bold hover:bg-rose-500">{{ __('Reject') }}</button>
                                    </form>
                                @else
                                    <span class="text-slate-400">{{ $lr->reviewer?->name ?? '—' }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500">{{ __('No leave requests submitted yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

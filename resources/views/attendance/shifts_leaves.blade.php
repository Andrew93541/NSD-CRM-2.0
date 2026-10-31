@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                {{ __('Shifts, Leave Management & Holiday Calendar') }}
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                {{ __('Configure shift grace periods, overtime thresholds, site holidays, and worker leave requests.') }}
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
                    <div class="sm:col-span-3 flex justify-end">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                            + {{ __('Save Shift Rule') }}
                        </button>
                    </div>
                </form>
            @endif

            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($shifts as $sh)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $sh->name }}</span>
                            <span class="text-slate-500 ml-1">({{ $sh->site?->code ?? __('All Sites') }})</span>
                        </div>
                        <div class="font-mono text-slate-600 dark:text-slate-300">
                            {{ substr($sh->start_time, 0, 5) }} - {{ substr($sh->end_time, 0, 5) }} &bull; {{ __('Grace') }}: {{ $sh->grace_minutes }}m &bull; {{ __('OT >') }} {{ $sh->overtime_after_hours }}h
                        </div>
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
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-slate-900 dark:text-white">{{ $h->name }}</span>
                            <span class="text-slate-500 ml-1">({{ $h->site?->code ?? __('All Sites') }})</span>
                        </div>
                        <div class="font-mono text-amber-600 dark:text-amber-400">
                            {{ $h->holiday_date->format('d M Y') }} &bull; {{ $h->is_paid ? __('Paid') : __('Unpaid') }}
                        </div>
                    </div>
                @empty
                    <div class="py-4 text-center text-slate-500">{{ __('No holidays scheduled yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- 3. Worker Leave Requests Card -->
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
                <select name="leave_type_name" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    <option value="Paid Leave">{{ __('Paid Leave') }}</option>
                    <option value="Sick Leave">{{ __('Sick Leave') }}</option>
                    <option value="Casual Leave">{{ __('Casual Leave') }}</option>
                </select>
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
    </div>
</div>
@endsection


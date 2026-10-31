@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Header & Navigation Tabs -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    {{ __('Multi-Site Operations & Attendance Comparison') }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                    {{ __('Compare assigned workforce, present man-days, worked hours, overtime, anomaly flags, and site tasks across active sites.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.workforce') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition">
                    {{ __('Date & Month Attendance Register') }}
                </a>
                <a href="{{ route('reports.audit_logs') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-700 dark:text-indigo-300 border border-indigo-500/30 transition">
                    {{ __('Security & Audit Logs') }}
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('reports.multi_site') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">{{ __('Start Date') }}</label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">{{ __('End Date') }}</label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">{{ __('Project') }}</label>
                <select name="project_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    <option value="">{{ __('All Projects') }}</option>
                    @foreach($projects as $proj)
                        <option value="{{ $proj->id }}" {{ (int) $projectId === (int) $proj->id ? 'selected' : '' }}>
                            {{ $proj->code }} — {{ $proj->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full py-2 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs shadow transition">
                    {{ __('Compare Sites') }}
                </button>
            </div>
        </form>
    </div>

    <!-- Site Comparison Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs sm:text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-[11px] uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-bold">{{ __('Site') }}</th>
                        <th class="py-3 px-4 font-bold">{{ __('Project') }}</th>
                        <th class="py-3 px-4 font-bold text-center">{{ __('Assigned Workers') }}</th>
                        <th class="py-3 px-4 font-bold text-center text-emerald-600 dark:text-emerald-400">{{ __('Present (P)') }}</th>
                        <th class="py-3 px-4 font-bold text-center text-amber-600 dark:text-amber-400">{{ __('Half Day (HD)') }}</th>
                        <th class="py-3 px-4 font-bold text-center text-sky-600 dark:text-sky-400">{{ __('Paid Leave (PL)') }}</th>
                        <th class="py-3 px-4 font-bold text-right">{{ __('Worked Hrs') }}</th>
                        <th class="py-3 px-4 font-bold text-right">{{ __('OT Hrs') }}</th>
                        <th class="py-3 px-4 font-bold text-center">{{ __('Open Tasks') }}</th>
                        <th class="py-3 px-4 font-bold text-center">{{ __('Open Flags') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($siteRows as $row)
                        @php $s = $row['site']; @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 px-4">
                                <a href="{{ route('sites.show', $s) }}" class="font-bold text-slate-900 dark:text-white hover:text-amber-600 dark:hover:text-amber-400">
                                    {{ $s->name }}
                                </a>
                                <div class="text-[11px] font-mono text-amber-600 dark:text-amber-400">{{ $s->code }} &bull; {{ $s->city ?: $s->location }}</div>
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-600 dark:text-slate-400">
                                {{ $s->project?->name ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono font-bold text-slate-900 dark:text-white">
                                {{ $row['assigned_workers'] }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono font-bold text-emerald-600 dark:text-emerald-400">
                                {{ $row['present_days'] }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono font-bold text-amber-600 dark:text-amber-400">
                                {{ $row['half_days'] }}
                            </td>
                            <td class="py-3 px-4 text-center font-mono font-bold text-sky-600 dark:text-sky-400">
                                {{ $row['paid_leaves'] }}
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-slate-900 dark:text-white">
                                {{ number_format($row['worked_hours'], 1) }} h
                            </td>
                            <td class="py-3 px-4 text-right font-mono font-bold text-indigo-600 dark:text-indigo-400">
                                +{{ number_format($row['overtime_hours'], 1) }} h
                            </td>
                            <td class="py-3 px-4 text-center font-mono">
                                <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold">
                                    {{ $row['open_tasks'] }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono">
                                <span class="px-2 py-0.5 rounded {{ $row['open_flags'] > 0 ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400 font-extrabold' : 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' }}">
                                    {{ $row['open_flags'] }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="py-8 text-center text-slate-500">{{ __('No active sites found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection


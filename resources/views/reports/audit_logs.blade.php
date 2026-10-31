@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    {{ __('Append-Only Security & Operational Audit Trail') }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                    {{ __('Immutable record of authentication, attendance locks, site transfers, task progressions, and administrative actions.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('reports.multi_site') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition">
                    {{ __('Multi-Site Comparison') }}
                </a>
                @if(Auth::user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']))
                    <a href="{{ route('reports.audit_logs.export', array_filter(['event' => $eventFilter, 'site_id' => $siteId])) }}"
                       class="px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 transition">
                        {{ __('Export Audit CSV') }}
                    </a>
                @endif
            </div>
        </div>

        <form method="GET" action="{{ route('reports.audit_logs') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">{{ __('Event Type') }}</label>
                <select name="event" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    <option value="">{{ __('All Events') }}</option>
                    @foreach($distinctEvents as $evt)
                        <option value="{{ $evt }}" {{ $eventFilter === $evt ? 'selected' : '' }}>{{ $evt }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">{{ __('Site') }}</label>
                <select name="site_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    <option value="">{{ __('All Sites') }}</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" {{ (int) $siteId === (int) $s->id ? 'selected' : '' }}>{{ $s->code }} — {{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">{{ __('From Date') }}</label>
                <input type="date" name="start_date" value="{{ $startDate }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
            </div>
            <div>
                <label class="block text-[11px] font-bold text-slate-500 uppercase tracking-wider mb-1">{{ __('To Date') }}</label>
                <input type="date" name="end_date" value="{{ $endDate }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full py-2 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs shadow transition">
                    {{ __('Filter Audit Logs') }}
                </button>
            </div>
        </form>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead class="text-[11px] uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-bold">{{ __('Timestamp') }}</th>
                        <th class="py-3 px-4 font-bold">{{ __('Event') }}</th>
                        <th class="py-3 px-4 font-bold">{{ __('Actor') }}</th>
                        <th class="py-3 px-4 font-bold">{{ __('Site') }}</th>
                        <th class="py-3 px-4 font-bold">{{ __('Subject') }}</th>
                        <th class="py-3 px-4 font-bold">{{ __('Details') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 px-4 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                                {{ $log->created_at?->format('Y-m-d H:i:s') }}
                            </td>
                            <td class="py-3 px-4 font-mono font-bold text-amber-600 dark:text-amber-400">
                                {{ $log->event }}
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                                {{ $log->actor?->name ?? 'System' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-600 dark:text-slate-400">
                                {{ $log->site?->code ?? '—' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-600 dark:text-slate-400">
                                {{ $log->subject_type ? class_basename($log->subject_type) . ' #' . $log->subject_id : '—' }}
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-slate-500 max-w-md truncate">
                                {{ $log->new_values ? json_encode($log->new_values) : '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">{{ __('No audit logs recorded matching your filters.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection


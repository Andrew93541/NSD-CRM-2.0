@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Workforce Attendance & Man-Hours Summary') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                {{ __('Aggregated daily headcount. Workers attending multiple sites are counted exactly once to prevent double-counting.') }}
            </p>
        </div>
    </div>

    <!-- Filter -->
    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
        <form method="GET" action="{{ route('reports.workforce') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div>
                <input type="date" name="date" value="{{ $date }}" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
            </div>
            <div>
                <select name="site_id" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    <option value="">{{ __('All Sites') }}</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" {{ $siteId == $s->id ? 'selected' : '' }}>{{ $s->code }} — {{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <button type="submit" class="w-full py-2 px-3 rounded-lg bg-amber-500 hover:bg-amber-400 font-bold text-xs text-slate-950 shadow transition">{{ __('Filter') }}</button>
            </div>
        </form>
    </div>

    <!-- Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Active On Sites Today') }}</span>
            <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $report['total_headcount'] }}</div>
            <div class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1 font-semibold">{{ __('Unique individuals (no double-count)') }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Direct Employee') }}</span>
            <div class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ $report['by_worker_type']['employee'] }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Direct Labour') }}</span>
            <div class="text-3xl font-extrabold text-sky-600 dark:text-sky-400 mt-1">{{ $report['by_worker_type']['direct_labour'] }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Subcontractor Worker') }}</span>
            <div class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-1">{{ $report['by_worker_type']['subcontractor_worker'] }}</div>
        </div>
    </div>

    <!-- Detailed List -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Worker') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Site') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Shift Hours') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Hours') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($report['records'] as $r)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $r->worker->full_name }}</div>
                                <div class="text-xs text-amber-600 dark:text-amber-400 font-mono">{{ $r->worker->code }}</div>
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-700 dark:text-slate-300">{{ $r->site->name }}</td>
                            <td class="py-3 px-4 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $r->clock_in ?? '--' }} - {{ $r->clock_out ?? '--' }}</td>
                            <td class="py-3 px-4 font-mono text-right text-xs font-bold text-slate-900 dark:text-white">{{ $r->worked_hours }} {{ __('Hours') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-8 text-center text-slate-500">{{ __('No attendance records on this date.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="sm:hidden space-y-3 p-4">
            @forelse($report['records'] as $r)
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $r->worker->full_name }}</span>
                        <span class="font-mono text-xs text-amber-600 dark:text-amber-400">{{ $r->worker->code }}</span>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $r->site->name }}</div>
                    <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-200 dark:border-slate-800/80">
                        <span class="font-mono text-slate-600 dark:text-slate-400">{{ $r->clock_in ?? '--' }} - {{ $r->clock_out ?? '--' }}</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $r->worked_hours }} {{ __('Hours') }}</span>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-slate-500 text-xs">
                    {{ __('No attendance records on this date.') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

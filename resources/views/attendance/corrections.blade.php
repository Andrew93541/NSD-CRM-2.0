@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Muster Corrections & Audit Trail') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                {{ __('Formal supervisor requests to correct site hours with immutable before/after change tracking.') }}
            </p>
        </div>
        <a href="{{ route('attendance.muster') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 text-xs font-bold transition">
            &larr; {{ __('Back to Muster Roll') }}
        </a>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Worker & Site') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Original Time & Status') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Proposed Time & Status') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Requester Reason') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Status') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($corrections as $c)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $c->attendance->worker->full_name }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $c->attendance->site->name }} &bull; {{ $c->attendance->attendance_date->format('d M Y') }}</div>
                            </td>
                            <td class="py-3 px-4 font-mono text-xs">
                                <div class="text-rose-600 dark:text-rose-400 line-through">{{ $c->previous_clock_in ?? '--:--' }} - {{ $c->previous_clock_out ?? '--:--' }}</div>
                                <div class="text-slate-500">{{ __($c->previous_status) }} ({{ $c->previous_worked_hours }} {{ __('Hours') }})</div>
                            </td>
                            <td class="py-3 px-4 font-mono text-xs">
                                <div class="text-emerald-600 dark:text-emerald-400 font-bold">{{ $c->proposed_clock_in ?? '--:--' }} - {{ $c->proposed_clock_out ?? '--:--' }}</div>
                                <div class="text-slate-700 dark:text-slate-300 font-semibold">{{ __($c->proposed_status) }} ({{ $c->proposed_worked_hours }} {{ __('Hours') }})</div>
                            </td>
                            <td class="py-3 px-4 text-xs text-slate-700 dark:text-slate-300">
                                <div>{{ $c->reason }}</div>
                                <div class="text-[11px] text-slate-500 mt-0.5">{{ __('By') }} {{ $c->requester->name }}</div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase {{ $c->status === 'pending' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30' : ($c->status === 'approved' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400') }}">
                                    {{ __($c->status === 'pending' ? 'Pending' : ($c->status === 'approved' ? 'Approved' : 'Rejected')) }}
                                </span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                @if($c->status === 'pending')
                                    <div class="flex items-center justify-end gap-2">
                                        <form method="POST" action="{{ route('attendance.review_correction', $c) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="approved">
                                            <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-emerald-500 text-slate-950 hover:bg-emerald-400 transition">{{ __('Approve') }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('attendance.review_correction', $c) }}">
                                            @csrf
                                            <input type="hidden" name="action" value="rejected">
                                            <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300 dark:hover:bg-rose-800 transition">{{ __('Reject') }}</button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-500">{{ __('Reviewed') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-500">{{ __('No attendance corrections pending.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="sm:hidden space-y-3 p-4">
            @forelse($corrections as $c)
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $c->attendance->worker->full_name }}</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $c->status === 'pending' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30' : ($c->status === 'approved' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400') }}">
                            {{ __($c->status === 'pending' ? 'Pending' : ($c->status === 'approved' ? 'Approved' : 'Rejected')) }}
                        </span>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $c->attendance->site->name }} &bull; {{ $c->attendance->attendance_date->format('d M Y') }}</div>
                    <div class="grid grid-cols-2 gap-2 text-xs p-2 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase">{{ __('Original') }}</span>
                            <div class="font-mono text-rose-600 dark:text-rose-400 line-through text-xs">{{ $c->previous_clock_in ?? '--:--' }} - {{ $c->previous_clock_out ?? '--:--' }}</div>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-500 uppercase">{{ __('Proposed') }}</span>
                            <div class="font-mono text-emerald-600 dark:text-emerald-400 font-bold text-xs">{{ $c->proposed_clock_in ?? '--:--' }} - {{ $c->proposed_clock_out ?? '--:--' }}</div>
                        </div>
                    </div>
                    <div class="text-xs text-slate-700 dark:text-slate-300">
                        <span class="text-slate-500">{{ __('Reason') }}:</span> {{ $c->reason }}
                    </div>
                    @if($c->status === 'pending')
                        <div class="flex items-center gap-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                            <form method="POST" action="{{ route('attendance.review_correction', $c) }}" class="flex-1">
                                @csrf
                                <input type="hidden" name="action" value="approved">
                                <button type="submit" class="w-full py-1.5 text-xs font-bold rounded-lg bg-emerald-500 text-slate-950 hover:bg-emerald-400 transition">{{ __('Approve') }}</button>
                            </form>
                            <form method="POST" action="{{ route('attendance.review_correction', $c) }}" class="flex-1">
                                @csrf
                                <input type="hidden" name="action" value="rejected">
                                <button type="submit" class="w-full py-1.5 text-xs font-bold rounded-lg bg-rose-100 hover:bg-rose-200 text-rose-800 dark:bg-rose-900/60 dark:text-rose-300 dark:hover:bg-rose-800 transition">{{ __('Reject') }}</button>
                            </form>
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-6 text-center text-slate-500 text-xs">
                    {{ __('No attendance corrections pending.') }}
                </div>
            @endforelse
        </div>

        @if($corrections->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950">
                {{ $corrections->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-rose-500 animate-pulse"></span>
                {{ __('Attendance Conflicts & Multi-Site Collisions') }}
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                {{ __('Real-time conflict detection prevents double-booking workers on multiple sites during intersecting hours.') }}
            </p>
        </div>
        <a href="{{ route('attendance.muster') }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 text-xs font-bold transition">
            &larr; {{ __('Back to Muster Roll') }}
        </a>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3.5 px-4 font-semibold">{{ __('Date') }}</th>
                        <th class="py-3.5 px-4 font-semibold">{{ __('Worker') }}</th>
                        <th class="py-3.5 px-4 font-semibold">{{ __('Site') }}</th>
                        <th class="py-3.5 px-4 font-semibold">{{ __('Recorded Time') }}</th>
                        <th class="py-3.5 px-4 font-semibold">{{ __('Conflict Details') }}</th>
                        <th class="py-3.5 px-4 font-semibold text-right">{{ __('Correction') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($conflicts as $c)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-4 px-4 font-mono text-slate-900 dark:text-white font-bold">{{ $c->attendance_date->format('d M Y') }}</td>
                            <td class="py-4 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $c->worker->full_name }}</div>
                                <div class="text-xs text-amber-600 dark:text-amber-400 font-mono">{{ $c->worker->code }}</div>
                            </td>
                            <td class="py-4 px-4">
                                <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $c->site->name }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $c->site->code }}</div>
                            </td>
                            <td class="py-4 px-4 font-mono text-slate-700 dark:text-slate-200">
                                {{ $c->clock_in ?? '--:--' }} - {{ $c->clock_out ?? '--:--' }}
                            </td>
                            <td class="py-4 px-4">
                                <span class="px-2.5 py-1 rounded-lg text-xs font-bold bg-rose-50 text-rose-700 border border-rose-300 dark:bg-rose-950/80 dark:text-rose-300 dark:border-rose-800/80 flex items-center gap-1.5">
                                    {{ $c->conflict_reason }}
                                </span>
                            </td>
                            <td class="py-4 px-4 text-right" x-data="{ open: false }">
                                <button type="button" @click="open = true" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-amber-500/10 text-amber-600 border border-amber-500/30 hover:bg-amber-500/20 dark:text-amber-400 transition">
                                    {{ __('Request Fix') }}
                                </button>

                                <!-- Correction Modal -->
                                <div x-show="open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 dark:bg-slate-950/80 backdrop-blur-sm" style="display: none;">
                                    <div @click.away="open = false" class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl max-w-md w-full p-6 text-left shadow-2xl space-y-4">
                                        <h3 class="text-lg font-bold text-slate-900 dark:text-white">{{ __('Attendance Correction Request') }}</h3>
                                        <p class="text-xs text-slate-600 dark:text-slate-400">{{ __('Audited change for') }} {{ $c->worker->full_name }} {{ __('on') }} {{ $c->attendance_date->format('d M Y') }}</p>

                                        <form method="POST" action="{{ route('attendance.request_correction', $c) }}" class="space-y-4">
                                            @csrf
                                            <div class="grid grid-cols-2 gap-3">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-400 uppercase mb-1">{{ __('Clock In') }}</label>
                                                    <input type="time" name="proposed_clock_in" value="{{ $c->clock_in ? \Carbon\Carbon::parse($c->clock_in)->format('H:i') : '' }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-400 uppercase mb-1">{{ __('Clock Out') }}</label>
                                                    <input type="time" name="proposed_clock_out" value="{{ $c->clock_out ? \Carbon\Carbon::parse($c->clock_out)->format('H:i') : '' }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                                                </div>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-400 uppercase mb-1">{{ __('Proposed Status') }}</label>
                                                <select name="proposed_status" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                                                    <option value="present">{{ __('Present') }}</option>
                                                    <option value="half_day">{{ __('Half Day') }}</option>
                                                    <option value="absent">{{ __('Absent') }}</option>
                                                    <option value="paid_leave">{{ __('Paid Leave') }}</option>
                                                </select>
                                            </div>

                                            <div>
                                                <label class="block text-xs font-bold text-slate-700 dark:text-slate-400 uppercase mb-1">{{ __('Justification Reason') }}</label>
                                                <textarea name="reason" rows="3" required placeholder="{{ __('Explain shift adjustment...') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none"></textarea>
                                            </div>

                                            <div class="flex justify-end gap-3 pt-2">
                                                <button type="button" @click="open = false" class="px-4 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">{{ __('Cancel') }}</button>
                                                <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow">{{ __('Submit For Approval') }}</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-slate-500">
                                {{ __('No attendance conflicts recorded. All shifts clean!') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($conflicts->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950">
                {{ $conflicts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

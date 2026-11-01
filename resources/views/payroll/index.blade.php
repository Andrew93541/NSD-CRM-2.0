@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ showPeriodModal: false, showAdvanceModal: false }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Payroll, Salary Advances & Payslips') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Multi-category compensation engine (Daily Wage, Monthly Salary, Hourly Rate, and Fixed Contract) with advance deductions and printable payslips.') }}
            </p>
        </div>
        @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff']))
            <div class="flex flex-wrap items-center gap-2.5">
                <button type="button" @click="showAdvanceModal = true" class="px-4 py-2.5 text-xs font-extrabold rounded-xl bg-slate-900 dark:bg-slate-800 hover:bg-slate-800 dark:hover:bg-slate-700 text-white border border-slate-700 transition">
                    + {{ __('Record Salary Advance') }}
                </button>
                <button type="button" @click="showPeriodModal = true" class="px-4 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition">
                    + {{ __('Generate Payroll Period') }}
                </button>
            </div>
        @endif
    </div>

    <!-- Payroll Periods Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Payroll Periods') }}</h2>
            <span class="text-xs font-mono text-slate-500">{{ $periods->total() }} {{ __('Period(s)') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100/80 dark:bg-slate-950/60 text-slate-500 dark:text-slate-400 uppercase font-bold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3.5">{{ __('Period Code') }}</th>
                        <th class="px-4 py-3.5">{{ __('Title & Site Scope') }}</th>
                        <th class="px-4 py-3.5">{{ __('Date Range') }}</th>
                        <th class="px-4 py-3.5 text-center">{{ __('Workers') }}</th>
                        <th class="px-4 py-3.5 text-right">{{ __('Gross Pay') }}</th>
                        <th class="px-4 py-3.5 text-right">{{ __('Deductions') }}</th>
                        <th class="px-4 py-3.5 text-right">{{ __('Net Payable') }}</th>
                        <th class="px-4 py-3.5">{{ __('Status') }}</th>
                        <th class="px-4 py-3.5 text-right">{{ __('Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($periods as $p)
                        <tr class="hover:bg-slate-50/90 dark:hover:bg-slate-800/40 transition">
                            <td class="px-4 py-3.5 font-mono font-bold text-amber-600 dark:text-amber-400">
                                <a href="{{ route('payroll.show', $p) }}" class="hover:underline">{{ $p->period_code }}</a>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $p->title }}</div>
                                <div class="text-[11px] text-slate-500">{{ $p->site ? ($p->site->code . ' — ' . $p->site->name) : __('All Active Sites') }}</div>
                            </td>
                            <td class="px-4 py-3.5 font-mono text-slate-600 dark:text-slate-300">
                                {{ $p->start_date->format('Y-m-d') }} &rarr; {{ $p->end_date->format('Y-m-d') }}
                            </td>
                            <td class="px-4 py-3.5 text-center font-mono font-bold text-slate-800 dark:text-slate-200">{{ $p->total_workers }}</td>
                            <td class="px-4 py-3.5 text-right font-mono text-slate-700 dark:text-slate-300">₹{{ number_format((float) $p->total_gross_amount, 2) }}</td>
                            <td class="px-4 py-3.5 text-right font-mono text-rose-600 dark:text-rose-400">₹{{ number_format((float) $p->total_deductions, 2) }}</td>
                            <td class="px-4 py-3.5 text-right font-mono font-extrabold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) $p->total_net_amount, 2) }}</td>
                            <td class="px-4 py-3.5">
                                @if($p->isLocked())
                                    <span class="px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 font-bold uppercase text-[10px]">{{ __('Locked & Approved') }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-400 font-bold uppercase text-[10px]">{{ strtoupper($p->status) }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <a href="{{ route('payroll.show', $p) }}" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-amber-500 hover:text-slate-950 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-amber-500 dark:hover:text-slate-950 font-bold transition">
                                    {{ __('Open Register') }} &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-6 py-10 text-center text-slate-500">
                                {{ __('No payroll periods generated yet. Click "+ Generate Payroll Period" to calculate worker pay.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($periods->hasPages())
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800">
                {{ $periods->links() }}
            </div>
        @endif
    </div>

    <!-- Recent Salary Advances -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Salary Advances Ledger') }}</h2>
            <span class="text-xs text-slate-500">{{ __('Automatically deducted during Payroll Calculation & Lock') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100/80 dark:bg-slate-950/60 text-slate-500 dark:text-slate-400 uppercase font-bold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">{{ __('Date') }}</th>
                        <th class="px-4 py-3">{{ __('Worker') }}</th>
                        <th class="px-4 py-3">{{ __('Reason / Note') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Advance Amount') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Recovered') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Balance') }}</th>
                        <th class="px-4 py-3">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($recentAdvances as $adv)
                        <tr>
                            <td class="px-4 py-3 font-mono text-slate-600 dark:text-slate-400">{{ $adv->advance_date->format('Y-m-d') }}</td>
                            <td class="px-4 py-3">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $adv->worker?->full_name }}</span>
                                <span class="font-mono text-[11px] text-amber-600 dark:text-amber-400 ml-1">({{ $adv->worker?->code }})</span>
                            </td>
                            <td class="px-4 py-3 text-slate-600 dark:text-slate-300">{{ $adv->reason }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format((float) $adv->amount, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) $adv->deducted_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-rose-600 dark:text-rose-400">₹{{ number_format($adv->remainingBalance(), 2) }}</td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $adv->status === 'deducted' ? 'bg-emerald-500/15 text-emerald-600' : 'bg-amber-500/15 text-amber-600' }}">
                                    {{ $adv->status }}
                                </span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-slate-500">{{ __('No salary advances recorded.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal: Generate Payroll Period -->
    <div x-show="showPeriodModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-xs">
        <div @click.away="showPeriodModal = false" class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Generate Payroll Period') }}</h3>
                <button type="button" @click="showPeriodModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            <form method="POST" action="{{ route('payroll.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Payroll Period Title') }} *</label>
                    <input type="text" name="title" required placeholder="e.g. Monthly Site Payroll — October 2026" value="{{ now()->format('F Y') }} Workforce Payroll" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Site Scope') }}</label>
                    <select name="site_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white">
                        @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff']))
                            <option value="">{{ __('All Active Company Sites') }}</option>
                        @endif
                        @foreach($sites as $site)
                            <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Start Date') }} *</label>
                        <input type="date" name="start_date" required value="{{ now()->startOfMonth()->format('Y-m-d') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm font-mono text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('End Date') }} *</label>
                        <input type="date" name="end_date" required value="{{ now()->endOfMonth()->format('Y-m-d') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm font-mono text-slate-900 dark:text-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Notes (Optional)') }}</label>
                    <textarea name="notes" rows="2" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" @click="showPeriodModal = false" class="px-4 py-2 text-xs font-bold rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">{{ __('Cancel') }}</button>
                    <button type="submit" class="px-5 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950">{{ __('Calculate & Generate') }}</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal: Record Salary Advance -->
    <div x-show="showAdvanceModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/75 backdrop-blur-xs">
        <div @click.away="showAdvanceModal = false" class="w-full max-w-md rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-2xl p-6 space-y-4">
            <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-800">
                <h3 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Record Worker Salary Advance') }}</h3>
                <button type="button" @click="showAdvanceModal = false" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>
            <form method="POST" action="{{ route('payroll.advances.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Select Worker') }} *</label>
                    <select name="worker_id" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white">
                        @foreach($workers as $w)
                            <option value="{{ $w->id }}">{{ $w->code }} — {{ $w->full_name }} ({{ $w->payTypeLabel() }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Advance Date') }} *</label>
                        <input type="date" name="advance_date" required value="{{ now()->format('Y-m-d') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm font-mono text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Amount (₹)') }} *</label>
                        <input type="number" step="0.01" min="1" name="amount" required placeholder="1500.00" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm font-mono text-slate-900 dark:text-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Reason / Voucher Reference') }}</label>
                    <input type="text" name="reason" placeholder="e.g. Festival advance / Medical emergency" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white">
                </div>
                <div class="flex justify-end gap-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <button type="button" @click="showAdvanceModal = false" class="px-4 py-2 text-xs font-bold rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">{{ __('Cancel') }}</button>
                    <button type="submit" class="px-5 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950">{{ __('Save Advance') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection


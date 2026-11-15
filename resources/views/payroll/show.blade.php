@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Breadcrumb & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <a href="{{ route('payroll.index') }}" class="text-xs text-slate-500 hover:text-amber-500 font-semibold">&larr; {{ __('Back to Payroll Periods') }}</a>
            <div class="flex items-center gap-3 mt-1">
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ $period->title }}</h1>
                <span class="px-2.5 py-0.5 rounded-md font-mono text-xs font-bold bg-amber-500/15 text-amber-700 dark:text-amber-400">{{ $period->period_code }}</span>
                @if($period->isLocked())
                    <span class="px-2.5 py-0.5 rounded-md text-xs font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 uppercase">{{ __('Locked & Approved') }}</span>
                @else
                    <span class="px-2.5 py-0.5 rounded-md text-xs font-bold bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 uppercase">{{ $period->status }}</span>
                @endif
            </div>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Period') }}: <span class="font-mono font-bold">{{ $period->start_date->format('Y-m-d') }}</span> {{ __('to') }} <span class="font-mono font-bold">{{ $period->end_date->format('Y-m-d') }}</span>
                &bull; {{ __('Site Scope') }}: <span class="font-bold">{{ $period->site ? ($period->site->code . ' — ' . $period->site->name) : __('All Active Company Sites') }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            @if(!auth()->user()->isExplicitlyDenied('payroll.view'))
                <a href="{{ route('payroll.export_payout', $period) }}" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-white hover:bg-slate-50 dark:bg-slate-900 dark:hover:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition">
                    {{ __('Export Bank Payout CSV') }} &darr;
                </a>
            @endif
            @if(!$period->isLocked() && !auth()->user()->isExplicitlyDenied('payroll.view'))
                @if(auth()->user()->canPerform('payroll.edit', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff']))
                    <form method="POST" action="{{ route('payroll.recalculate', $period) }}">
                        @csrf
                        <button type="submit" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-300 dark:border-slate-700 transition">
                            &#x21bb; {{ __('Recalculate from Attendance') }}
                        </button>
                    </form>
                @endif

                @php
                    $unconfiguredEntries = $entries->filter(fn ($e) => ($e->statutory_config_state ?? 'unconfigured') === 'unconfigured');
                    $isSelfPrepared = $period->calculated_by !== null && (int) $period->calculated_by === (int) auth()->id();
                @endphp
                @if(auth()->user()->canPerform('payroll.approve', ['super_admin', 'company_admin', 'senior_manager', 'site_manager']))
                    @if($unconfiguredEntries->isEmpty() && !$isSelfPrepared && $period->status === 'calculated')
                        <form method="POST" action="{{ route('payroll.lock', $period) }}" onsubmit="return confirm('Approve & Lock this Payroll Period? This will finalize advance deductions and lock attendance rows.')">
                            @csrf
                            <button type="submit" class="px-4 py-2.5 text-xs font-extrabold rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-500/20 transition">
                                {{ __('Approve & Lock Period') }}
                            </button>
                        </form>
                    @else
                        <span class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-200 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border border-slate-300 dark:border-slate-700 cursor-not-allowed" title="{{ $unconfiguredEntries->isNotEmpty() ? __('Configure PF/ESIC/PT for all workers before locking') : ($isSelfPrepared ? __('Maker-checker required: another authorized approver must lock this period') : __('Calculate period first')) }}">
                            {{ __('Approve & Lock Blocked') }}
                        </span>
                    @endif
                @endif
            @endif
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-xs font-bold text-slate-500 uppercase">{{ __('Total Workers') }}</div>
            <div class="text-2xl font-extrabold font-mono text-slate-900 dark:text-white mt-1">{{ $period->total_workers }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-xs font-bold text-slate-500 uppercase">{{ __('Total Gross Earnings') }}</div>
            <div class="text-2xl font-extrabold font-mono text-slate-900 dark:text-white mt-1">₹{{ number_format((float) $period->total_gross_amount, 2) }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-xs font-bold text-slate-500 uppercase">{{ __('Advances & Deductions') }}</div>
            <div class="text-2xl font-extrabold font-mono text-rose-600 dark:text-rose-400 mt-1">₹{{ number_format((float) $period->total_deductions, 2) }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
            <div class="text-xs font-bold text-slate-500 uppercase">{{ __('Net Payable Disbursement') }}</div>
            <div class="text-2xl font-extrabold font-mono text-emerald-600 dark:text-emerald-400 mt-1">₹{{ number_format((float) $period->total_net_amount, 2) }}</div>
        </div>
    </div>

    @php
        $unconfiguredWorkersList = $entries
            ->filter(fn ($e) => ($e->statutory_config_state ?? 'unconfigured') === 'unconfigured')
            ->map(fn ($e) => $e->worker ? ($e->worker->full_name . ' (' . $e->worker->code . ')') : ('#' . $e->id))
            ->values();
        $unconfiguredCount = $unconfiguredWorkersList->count();
    @endphp
    @if($unconfiguredCount > 0)
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 text-amber-800 dark:text-amber-300 text-xs flex flex-col gap-1.5">
            <div>
                <span class="font-extrabold uppercase">{{ __('Statutory Configuration Incomplete — Approval Blocked') }}:</span>
                {{ $unconfiguredCount }} {{ __('worker(s) in this period still have Unconfigured PF, ESIC, or Professional Tax settings (distinct from Not Applicable). Configure each worker and recalculate before locking payroll.') }}
            </div>
            <div class="font-mono text-[11px] font-semibold">
                {{ __('Affected Workers') }}: {{ $unconfiguredWorkersList->implode(', ') }}
            </div>
        </div>
    @endif

    <!-- Entries Register Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Worker Compensation & Payslip Register') }}</h2>
            <span class="text-xs text-slate-500">{{ __('Supports Daily, Monthly, Hourly, Contract & Individual PF/ESIC/PT') }}</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead class="bg-slate-100/80 dark:bg-slate-950/60 text-slate-500 dark:text-slate-400 uppercase font-bold border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="px-4 py-3">{{ __('Worker') }}</th>
                        <th class="px-4 py-3">{{ __('Pay Type & Base Rate') }}</th>
                        <th class="px-4 py-3 text-center">{{ __('Eff. Days / Hrs') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Base Pay') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('OT Pay') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Bonus') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Gross Pay') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Statutory (PF/ESIC/PT)') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Advance Ded.') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Other Ded.') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Net Payable') }}</th>
                        <th class="px-4 py-3 text-right">{{ __('Payslip') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($entries as $entry)
                        @php
                            $statState = $entry->statutory_config_state ?? 'unconfigured';
                            $statEmpTotal = $entry->statutoryEmployeeDeductionTotal();
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                            <td class="px-4 py-3">
                                @if($entry->worker)
                                    <a href="{{ route('workforce.show', $entry->worker) }}" class="font-bold text-slate-900 dark:text-white hover:text-amber-600">{{ $entry->worker->full_name }}</a>
                                @else
                                    <div class="font-bold text-slate-900 dark:text-white">—</div>
                                @endif
                                <div class="font-mono text-[11px] text-amber-600 dark:text-amber-400">{{ $entry->worker?->code }} &bull; {{ $entry->worker?->trade_designation }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-bold uppercase text-[10px] text-slate-700 dark:text-slate-300">{{ $entry->pay_type }}</span>
                                <div class="font-mono text-[11px] text-slate-500 mt-0.5">₹{{ number_format((float) $entry->base_rate, 2) }}</div>
                            </td>
                            <td class="px-4 py-3 text-center font-mono">
                                <span class="font-bold text-slate-900 dark:text-white">{{ number_format((float) $entry->effective_days, 1) }}d</span>
                                <span class="text-slate-400">({{ number_format((float) $entry->worked_hours, 1) }}h / +{{ number_format((float) $entry->overtime_hours, 1) }}h OT)</span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-slate-800 dark:text-slate-200">₹{{ number_format((float) $entry->base_pay, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">₹{{ number_format((float) $entry->overtime_pay, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-slate-700 dark:text-slate-300">₹{{ number_format((float) $entry->bonus_amount, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format((float) $entry->gross_pay, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                @if($statState === 'unconfigured')
                                    <div class="font-mono text-rose-600 dark:text-rose-400">₹{{ number_format($statEmpTotal, 2) }}</div>
                                    <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[9px] font-bold uppercase bg-amber-500/15 text-amber-700 dark:text-amber-300">{{ __('Unconfigured') }}</span>
                                @elseif($statState === 'not_applicable')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-500">{{ __('N/A') }}</span>
                                @elseif($statState === 'not_yet_effective')
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-sky-500/10 text-sky-600 dark:text-sky-400">{{ __('Future Eff.') }}</span>
                                @else
                                    <div class="font-mono font-semibold text-rose-600 dark:text-rose-400">₹{{ number_format($statEmpTotal, 2) }}</div>
                                    <div class="text-[10px] font-mono text-slate-400">
                                        PF:{{ number_format((float) $entry->pf_employee_amount, 0) }} | ESIC:{{ number_format((float) $entry->esic_employee_amount, 0) }} | PT:{{ number_format((float) $entry->pt_amount, 0) }}
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-rose-600 dark:text-rose-400">₹{{ number_format((float) $entry->advance_deduction, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-rose-600 dark:text-rose-400">₹{{ number_format((float) $entry->other_deduction, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono font-extrabold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) $entry->net_pay, 2) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('payroll.payslip', [$period, $entry]) }}" target="_blank" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-amber-500/15 hover:bg-amber-500 text-amber-700 dark:text-amber-400 hover:text-slate-950 font-bold transition">
                                    {{ __('Payslip') }} &nearr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-6 py-8 text-center text-slate-500">{{ __('No worker entries found for this payroll period.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection


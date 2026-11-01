@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-6" x-data="{
    cgstRate: {{ (float) ($workOrder->cgst_rate ?? 9.0) }},
    sgstRate: {{ (float) ($workOrder->sgst_rate ?? 9.0) }},
    retentionPercent: {{ (float) ($workOrder->retention_percent ?? 0.0) }},
    advanceRecovery: 0,
    otherDeductions: 0,
    roundingMode: '{{ $workOrder->company?->invoice_rounding_mode ?? 'paisa' }}',
    rows: @js($wizardItems),
    effectiveClaimed(row) {
        const c = parseFloat(row.claimed_quantity) || 0;
        const h = parseFloat(row.hold_quantity) || 0;
        return Math.max(0, Math.round((c - h) * 1000) / 1000);
    },
    remainingAvailable(row) {
        return Math.max(0, Math.round(((parseFloat(row.ordered_quantity) || 0) - (parseFloat(row.previous_quantity) || 0)) * 1000) / 1000);
    },
    billableQty(row) {
        return Math.max(0, Math.min(this.effectiveClaimed(row), this.remainingAvailable(row)));
    },
    excessQty(row) {
        return Math.max(0, Math.round((this.effectiveClaimed(row) - this.billableQty(row)) * 1000) / 1000);
    },
    cumulativeQty(row) {
        return Math.round(((parseFloat(row.previous_quantity) || 0) + this.billableQty(row)) * 1000) / 1000;
    },
    balanceQty(row) {
        return Math.max(0, Math.round(((parseFloat(row.ordered_quantity) || 0) - this.cumulativeQty(row)) * 1000) / 1000);
    },
    usagePct(row) {
        const ord = parseFloat(row.ordered_quantity) || 0;
        if (ord <= 0) return 0;
        return Math.min(100, Math.round((this.cumulativeQty(row) / ord) * 1000) / 10);
    },
    lineAmount(row) {
        return Math.round(this.billableQty(row) * (parseFloat(row.rate) || 0) * 100) / 100;
    },
    basicTotalA() {
        return Math.round(this.rows.reduce((s, r) => s + this.lineAmount(r), 0) * 100) / 100;
    },
    cgstAmount() {
        return Math.round(this.basicTotalA() * ((parseFloat(this.cgstRate) || 0) / 100) * 100) / 100;
    },
    sgstAmount() {
        return Math.round(this.basicTotalA() * ((parseFloat(this.sgstRate) || 0) / 100) * 100) / 100;
    },
    grossAmount() {
        return Math.round((this.basicTotalA() + this.cgstAmount() + this.sgstAmount()) * 100) / 100;
    },
    netPayable() {
        const ret = Math.round(this.basicTotalA() * ((parseFloat(this.retentionPercent) || 0) / 100) * 100) / 100;
        const adv = parseFloat(this.advanceRecovery) || 0;
        const oth = parseFloat(this.otherDeductions) || 0;
        const raw = Math.round((this.grossAmount() - ret - adv - oth) * 100) / 100;
        return this.roundingMode === 'rupee' ? Math.round(raw) : raw;
    }
}">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <span class="font-mono text-xs font-extrabold px-2.5 py-0.5 rounded bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                {{ __('Interactive RA Bill Wizard') }} &bull; {{ $nextRaLabel }}
            </span>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">
                {{ $workOrder->order_number }} &mdash; {{ $workOrder->title }}
            </h1>
            <p class="text-xs text-slate-500">
                PO #{{ $workOrder->po_number ?? '—' }} &bull; WO Rev {{ $workOrder->current_revision ?? 0 }} &bull; {{ $workOrder->client?->name ?? 'JSW STEEL LIMITED' }}
            </p>
        </div>
        <a href="{{ route('work_orders.show', $workOrder) }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 transition">
            &larr; {{ __('Back to Work Order') }}
        </a>
    </div>

    <form method="POST" action="{{ route('work_orders.ra_bills.store', $workOrder) }}" class="space-y-6">
        @csrf

        <!-- Step 1: Period & Rounding Settings -->
        <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-xs font-extrabold text-slate-800 dark:text-slate-200 uppercase tracking-wider">{{ __('Step 1: Billing Period, Tax Rates & Rounding Mode') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-5 gap-3 text-xs">
                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('RA Bill Label') }}</label>
                    <input type="text" name="ra_label" value="{{ $nextRaLabel }}" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 p-2 font-mono font-bold">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Period From') }} *</label>
                    <input type="date" name="period_from" value="{{ now()->startOfMonth()->format('Y-m-d') }}" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 p-2">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Period To') }} *</label>
                    <input type="date" name="period_to" value="{{ now()->format('Y-m-d') }}" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 p-2">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Invoice Date') }}</label>
                    <input type="date" name="invoice_date" value="{{ now()->format('Y-m-d') }}" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 p-2">
                </div>
                <div>
                    <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Rounding Mode') }}</label>
                    <select name="rounding_mode" x-model="roundingMode" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 p-2 font-mono">
                        <option value="paisa">{{ __('Exact Paisa (2 Decimals)') }}</option>
                        <option value="rupee">{{ __('Nearest Rupee') }}</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Step 2: Interactive Quantity Cap & Hold Matrix -->
        <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-xs font-extrabold text-slate-800 dark:text-slate-200 uppercase tracking-wider">{{ __('Step 2: Line Item Claimed & Hold Quantities (Live Cap Preview)') }}</h2>
            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100 dark:bg-slate-950 text-slate-600 dark:text-slate-400 uppercase font-bold text-[10px]">
                        <tr>
                            <th class="px-2.5 py-2">{{ __('Sr. No.') }}</th>
                            <th class="px-2.5 py-2">{{ __('Description') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Order Qty') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Rate (₹)') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Prev Billed') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Claimed Qty') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Hold Qty') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Billable Qty') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Excess Held') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Balance Qty') }}</th>
                            <th class="px-2.5 py-2 text-center">{{ __('Consumption') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Line Amt (₹)') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        <template x-for="row in rows" :key="row.id">
                            <tr>
                                <td class="px-2.5 py-2 font-mono font-bold text-amber-600 dark:text-amber-400" x-text="row.sr_no"></td>
                                <td class="px-2.5 py-2 font-semibold text-slate-900 dark:text-white" x-text="row.description"></td>
                                <td class="px-2.5 py-2 text-right font-mono" x-text="parseFloat(row.ordered_quantity).toFixed(3)"></td>
                                <td class="px-2.5 py-2 text-right font-mono" x-text="'₹' + parseFloat(row.rate).toFixed(2)"></td>
                                <td class="px-2.5 py-2 text-right font-mono text-slate-500" x-text="parseFloat(row.previous_quantity).toFixed(3)"></td>
                                <td class="px-2.5 py-2 text-right">
                                    <input type="number" step="0.001" min="0" :name="`items[${row.id}][claimed_quantity]`" x-model="row.claimed_quantity" class="w-24 text-right rounded bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-1.5 py-1 text-xs font-mono">
                                </td>
                                <td class="px-2.5 py-2 text-right">
                                    <input type="number" step="0.001" min="0" :name="`items[${row.id}][hold_quantity]`" x-model="row.hold_quantity" class="w-20 text-right rounded bg-slate-50 dark:bg-slate-950 border border-rose-300 dark:border-rose-900 px-1.5 py-1 text-xs font-mono">
                                </td>
                                <td class="px-2.5 py-2 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400" x-text="billableQty(row).toFixed(3)"></td>
                                <td class="px-2.5 py-2 text-right font-mono" :class="excessQty(row) > 0 ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-slate-400'" x-text="excessQty(row).toFixed(3)"></td>
                                <td class="px-2.5 py-2 text-right font-mono" x-text="balanceQty(row).toFixed(3)"></td>
                                <td class="px-2.5 py-2">
                                    <div class="w-24 mx-auto space-y-1">
                                        <div class="w-full h-1.5 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                                            <div class="h-full" :class="(usagePct(row) >= 100 || excessQty(row) > 0) ? 'bg-rose-500' : (usagePct(row) >= 90 ? 'bg-orange-500' : (usagePct(row) >= 80 ? 'bg-amber-500' : 'bg-emerald-500'))" :style="`width: ${usagePct(row)}%`"></div>
                                        </div>
                                        <div class="text-center font-mono text-[10px] font-bold" x-text="usagePct(row) + '%'"></div>
                                    </div>
                                </td>
                                <td class="px-2.5 py-2 text-right font-mono font-bold text-slate-900 dark:text-white" x-text="'₹' + lineAmount(row).toFixed(2)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Live Summary Bar -->
            <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 flex flex-wrap items-center justify-between gap-4 text-xs">
                <div>
                    <span class="text-slate-600 dark:text-slate-400 font-bold uppercase">{{ __('Basic Value (A)') }}:</span>
                    <span class="font-mono font-extrabold text-slate-900 dark:text-white ml-1" x-text="'₹' + basicTotalA().toFixed(2)"></span>
                </div>
                <div>
                    <span class="text-slate-600 dark:text-slate-400 font-bold uppercase">{{ __('CGST + SGST') }}:</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white ml-1" x-text="'₹' + (cgstAmount() + sgstAmount()).toFixed(2)"></span>
                </div>
                <div>
                    <span class="text-slate-600 dark:text-slate-400 font-bold uppercase">{{ __('Gross (A + GST)') }}:</span>
                    <span class="font-mono font-extrabold text-amber-600 dark:text-amber-400 ml-1" x-text="'₹' + grossAmount().toFixed(2)"></span>
                </div>
                <div>
                    <span class="text-emerald-700 dark:text-emerald-400 font-extrabold uppercase">{{ __('Net Payable') }}:</span>
                    <span class="font-mono font-extrabold text-emerald-700 dark:text-emerald-400 text-sm ml-1" x-text="'₹' + netPayable().toFixed(2)"></span>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('work_orders.show', $workOrder) }}" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">{{ __('Cancel') }}</a>
                <button type="submit" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition">
                    {{ __('Generate RA Bill & Open Abstract') }}
                </button>
            </div>
        </div>
    </form>
</div>
@endsection


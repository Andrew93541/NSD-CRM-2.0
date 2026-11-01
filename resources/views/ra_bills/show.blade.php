@extends('layouts.app')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs font-extrabold px-2.5 py-0.5 rounded bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                    {{ $raBill->ra_label }}
                </span>
                @if($raBill->invoice_number)
                    <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                        {{ __('INV') }}: {{ $raBill->invoice_number }}
                    </span>
                @endif
                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                    {{ __('WO Rev') }} {{ $raBill->work_order_revision }}
                </span>
                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase {{ in_array($raBill->status, ['approved', 'invoiced', 'paid']) ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : ($raBill->status === 'rejected' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400') }}">
                    {{ str_replace('_', ' ', $raBill->status) }}
                </span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">
                {{ $workOrder->order_number }} &mdash; {{ $workOrder->title }}
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('Period') }}: {{ $raBill->period_from->format('d.m.Y') }} {{ __('to') }} {{ $raBill->period_to->format('d.m.Y') }}
                &bull; {{ __('PO #') }} {{ $workOrder->po_number ?? $workOrder->client_reference ?? '—' }}
                @if($raBill->client)
                    &bull; {{ $raBill->client->name }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('work_orders.ra_bills.export_pdf', [$workOrder, $raBill, 'tax_invoice_pdf']) }}" class="px-3.5 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-sm transition">
                {{ __('Tax Invoice PDF (INV)') }} &darr;
            </a>
            <a href="{{ route('work_orders.ra_bills.export_pdf', [$workOrder, $raBill, 'abstract_pdf']) }}" class="px-3.5 py-2 text-xs font-extrabold rounded-xl bg-indigo-500 hover:bg-indigo-400 text-white shadow-sm transition">
                {{ __('Abstract PDF (ABSTRACT)') }} &darr;
            </a>
            <a href="{{ route('work_orders.ra_bills.export_pdf', [$workOrder, $raBill, 'combined_pdf']) }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-800 hover:bg-slate-700 text-white transition">
                {{ __('Combined PDF') }} &darr;
            </a>
            <a href="{{ route('work_orders.ra_bills.export_xlsx', [$workOrder, $raBill]) }}" class="px-3.5 py-2 text-xs font-extrabold rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow-sm transition">
                {{ __('Excel (.xlsx with Live Formulas)') }} &darr;
            </a>
            <a href="{{ route('work_orders.show', $workOrder) }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:border-slate-700 transition">
                &larr; {{ __('Back to Work Order') }}
            </a>
        </div>
    </div>

    <!-- Approval Actions -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('RA Bill Two-Tier Certification & Quantity Cap Status') }}</h3>
                <p class="text-[11px] text-slate-500 mt-0.5">
                    {{ __('Rule: Effective Claimed = max(0, Claimed - Hold); Billable = min(Effective Claimed, Ordered - Previous); Excess is held until a Work Order Revision increases Ordered Quantity.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($raBill->status === 'draft')
                    @can('createRaBill', $workOrder)
                        <form method="POST" action="{{ route('work_orders.ra_bills.submit', [$workOrder, $raBill]) }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 transition">
                                {{ __('Submit for Tier 1 Verification') }}
                            </button>
                        </form>
                    @endcan
                @endif

                @can('approveRaBillSite', [$workOrder, $raBill])
                    <form method="POST" action="{{ route('work_orders.ra_bills.approve_site', [$workOrder, $raBill]) }}" class="flex items-center gap-2">
                        @csrf
                        <input type="text" name="notes" placeholder="{{ __('Site Manager verification notes...') }}" class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white">
                        <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition">
                            {{ __('Tier 1: Verify (Site Manager)') }}
                        </button>
                    </form>
                @endcan

                @can('approveRaBillSenior', [$workOrder, $raBill])
                    <form method="POST" action="{{ route('work_orders.ra_bills.approve_senior', [$workOrder, $raBill]) }}" class="flex items-center gap-2">
                        @csrf
                        <input type="text" name="notes" placeholder="{{ __('Senior Manager approval notes...') }}" class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-1.5 text-xs text-slate-900 dark:text-white">
                        <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 transition">
                            {{ __('Tier 2: Final Approve & Lock RA Bill') }}
                        </button>
                    </form>
                @endcan

                @can('rejectRaBill', [$workOrder, $raBill])
                    <form method="POST" action="{{ route('work_orders.ra_bills.reject', [$workOrder, $raBill]) }}" class="flex items-center gap-2">
                        @csrf
                        <input type="text" name="rejection_reason" required placeholder="{{ __('Rejection reason...') }}" class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-rose-300 dark:border-rose-900 px-3 py-1.5 text-xs text-slate-900 dark:text-white">
                        <button type="submit" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-rose-500/15 hover:bg-rose-500 text-rose-600 hover:text-white transition">
                            {{ __('Reject') }}
                        </button>
                    </form>
                @endcan
            </div>
        </div>
    </div>

    <!-- Abstract Sheet & Quantity Cap Table -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('RA Bill Abstract & Work Order Quantity Cap Schedule') }}</h3>
            <span class="text-xs font-mono text-slate-500">{{ __('Rounding Mode') }}: <strong>{{ strtoupper($raBill->rounding_mode) }}</strong></span>
        </div>

        <form method="POST" action="{{ route('work_orders.ra_bills.update', [$workOrder, $raBill]) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-100 dark:bg-slate-950 text-slate-600 dark:text-slate-400 uppercase font-bold text-[10px]">
                        <tr>
                            <th class="px-2.5 py-2">{{ __('Sr. No.') }}</th>
                            <th class="px-2.5 py-2">{{ __('Service Code') }}</th>
                            <th class="px-2.5 py-2">{{ __('Description') }}</th>
                            <th class="px-2.5 py-2">{{ __('UOM') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Order Qty') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Rate (₹)') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Prev Qty') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Claimed Qty') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Hold Qty') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Billable (Cur) Qty') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Excess (Unbilled)') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Cumul Qty') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Balance Qty') }}</th>
                            <th class="px-2.5 py-2 text-center">{{ __('Usage %') }}</th>
                            <th class="px-2.5 py-2 text-right">{{ __('Current Amt (₹)') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($raBill->items as $item)
                            @php
                                $badgeClass = match($item->warning_level) {
                                    'cap_100' => 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30',
                                    'warning_90' => 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/30',
                                    'warning_80' => 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30',
                                    default => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
                                };
                            @endphp
                            <tr>
                                <td class="px-2.5 py-2 font-mono font-bold text-amber-600 dark:text-amber-400">{{ $item->sr_no }}</td>
                                <td class="px-2.5 py-2 font-mono text-slate-500">{{ $item->service_code ?? '—' }}</td>
                                <td class="px-2.5 py-2 font-semibold text-slate-900 dark:text-white">{{ $item->description }}</td>
                                <td class="px-2.5 py-2 font-mono">{{ $item->unit }}</td>
                                <td class="px-2.5 py-2 text-right font-mono">{{ number_format((float) $item->ordered_quantity, 3) }}</td>
                                <td class="px-2.5 py-2 text-right font-mono">₹{{ number_format((float) $item->rate, 2) }}</td>
                                <td class="px-2.5 py-2 text-right font-mono text-slate-500">{{ number_format((float) $item->previous_quantity, 3) }}</td>
                                <td class="px-2.5 py-2 text-right font-mono">
                                    @if(!$raBill->isLocked())
                                        <input type="number" step="0.001" min="0" name="items[{{ $item->work_order_item_id }}][claimed_quantity]" value="{{ (float) $item->claimed_quantity }}" class="w-24 text-right rounded bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-1.5 py-1 text-xs font-mono">
                                    @else
                                        {{ number_format((float) $item->claimed_quantity, 3) }}
                                    @endif
                                </td>
                                <td class="px-2.5 py-2 text-right font-mono">
                                    @if(!$raBill->isLocked())
                                        <input type="number" step="0.001" min="0" name="items[{{ $item->work_order_item_id }}][hold_quantity]" value="{{ (float) $item->hold_quantity }}" class="w-20 text-right rounded bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-1.5 py-1 text-xs font-mono">
                                    @else
                                        {{ number_format((float) $item->hold_quantity, 3) }}
                                    @endif
                                </td>
                                <td class="px-2.5 py-2 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ number_format((float) $item->current_billable_quantity, 3) }}</td>
                                <td class="px-2.5 py-2 text-right font-mono {{ (float) $item->excess_quantity > 0 ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
                                    {{ number_format((float) $item->excess_quantity, 3) }}
                                </td>
                                <td class="px-2.5 py-2 text-right font-mono font-semibold">{{ number_format((float) $item->cumulative_quantity, 3) }}</td>
                                <td class="px-2.5 py-2 text-right font-mono">{{ number_format((float) $item->balance_quantity, 3) }}</td>
                                <td class="px-2.5 py-2 text-center">
                                    <span class="px-2 py-0.5 rounded border font-mono text-[10px] font-bold {{ $badgeClass }}">
                                        {{ number_format((float) $item->consumption_percent, 1) }}%
                                    </span>
                                </td>
                                <td class="px-2.5 py-2 text-right font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format((float) $item->current_amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50 dark:bg-slate-950 font-mono text-xs border-t border-slate-200 dark:border-slate-800">
                        <tr>
                            <td colspan="14" class="px-3 py-2 text-right font-bold text-slate-600 dark:text-slate-400">{{ __('Total Basic Value (A)') }}</td>
                            <td class="px-3 py-2 text-right font-extrabold text-slate-900 dark:text-white">₹{{ number_format((float) $raBill->current_bill_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="14" class="px-3 py-1.5 text-right text-slate-500">{{ __('Add: CGST') }} ({{ number_format((float) $raBill->cgst_rate, 2) }}%)</td>
                            <td class="px-3 py-1.5 text-right font-bold">₹{{ number_format((float) $raBill->cgst_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="14" class="px-3 py-1.5 text-right text-slate-500">{{ __('Add: SGST') }} ({{ number_format((float) $raBill->sgst_rate, 2) }}%)</td>
                            <td class="px-3 py-1.5 text-right font-bold">₹{{ number_format((float) $raBill->sgst_amount, 2) }}</td>
                        </tr>
                        <tr>
                            <td colspan="14" class="px-3 py-2 text-right font-extrabold text-amber-600 dark:text-amber-400">{{ __('Gross Invoice Amount (A + CGST + SGST)') }}</td>
                            <td class="px-3 py-2 text-right font-extrabold text-amber-600 dark:text-amber-400">₹{{ number_format((float) $raBill->gross_amount, 2) }}</td>
                        </tr>
                        @if((float) $raBill->retention_deduction > 0 || (float) $raBill->advance_recovery > 0 || (float) $raBill->other_deductions > 0)
                            <tr>
                                <td colspan="14" class="px-3 py-1.5 text-right text-rose-600 dark:text-rose-400">{{ __('Less: Retention / Advance / Other Deductions') }}</td>
                                <td class="px-3 py-1.5 text-right font-bold text-rose-600 dark:text-rose-400">-₹{{ number_format((float) $raBill->retention_deduction + (float) $raBill->advance_recovery + (float) $raBill->other_deductions, 2) }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="14" class="px-3 py-2 text-right font-extrabold text-emerald-600 dark:text-emerald-400">{{ __('Net Payable Amount') }} ({{ strtoupper($raBill->rounding_mode) }})</td>
                            <td class="px-3 py-2 text-right font-extrabold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) $raBill->net_payable, 2) }}</td>
                        </tr>
                        @if((float) $raBill->excess_unbilled_amount > 0)
                            <tr class="bg-rose-500/5">
                                <td colspan="14" class="px-3 py-2 text-right font-bold text-rose-600 dark:text-rose-400">{{ __('Unbilled Excess Value (Held Above WO Ordered Cap — Requires WO Revision)') }}</td>
                                <td class="px-3 py-2 text-right font-extrabold text-rose-600 dark:text-rose-400">₹{{ number_format((float) $raBill->excess_unbilled_amount, 2) }}</td>
                            </tr>
                        @endif
                    </tfoot>
                </table>
            </div>

            @if(!$raBill->isLocked())
                @can('createRaBill', $workOrder)
                    <div class="flex flex-wrap items-center justify-between gap-4 pt-2">
                        <div class="flex items-center gap-3 text-xs">
                            <label class="font-bold text-slate-600 dark:text-slate-400 uppercase">{{ __('Final Rounding') }}:</label>
                            <select name="rounding_mode" class="rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-2.5 py-1.5 text-xs font-mono">
                                <option value="paisa" @selected($raBill->rounding_mode === 'paisa')>{{ __('Exact Paisa (2 Decimals)') }}</option>
                                <option value="rupee" @selected($raBill->rounding_mode === 'rupee')>{{ __('Nearest Rupee') }}</option>
                            </select>
                        </div>
                        <button type="submit" class="px-5 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 transition">
                            {{ __('Recalculate RA Bill with Cap & Hold Rules') }}
                        </button>
                    </div>
                @endcan
            @endif
        </form>

        <div class="p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs">
            <span class="font-bold text-slate-600 dark:text-slate-400 uppercase">{{ __('Amount in Words (Indian System)') }}:</span>
            <span class="font-extrabold text-slate-900 dark:text-white ml-1">{{ \App\Support\IndianCurrencyWords::toWords((float) $raBill->net_payable) }}</span>
        </div>
    </div>

    <!-- Bill Exports History -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-sm">
        <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Private Vault Document Exports Log') }}</h3>
        @if($raBill->exports->isNotEmpty())
            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @foreach($raBill->exports as $exp)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-mono font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-amber-600 dark:text-amber-400 uppercase text-[10px]">
                                {{ $exp->export_type }}
                            </span>
                            <span class="font-semibold text-slate-900 dark:text-white ml-2">{{ $exp->file_name }}</span>
                            @if($exp->invoice_number)
                                <span class="font-mono text-emerald-600 dark:text-emerald-400 ml-2">({{ $exp->invoice_number }})</span>
                            @endif
                            <span class="text-slate-400 ml-2">&bull; {{ $exp->exporter?->name ?? 'System' }} &bull; {{ $exp->created_at?->format('d M Y, H:i') }}</span>
                        </div>
                        <a href="{{ route('work_orders.ra_bills.exports.download', [$workOrder, $raBill, $exp->id]) }}" class="font-bold text-amber-600 dark:text-amber-400 hover:underline">
                            {{ __('Download') }} &darr;
                        </a>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-xs text-slate-500 italic">{{ __('No PDF or Excel exports generated yet for this RA Bill.') }}</div>
        @endif
    </div>
</div>
@endsection


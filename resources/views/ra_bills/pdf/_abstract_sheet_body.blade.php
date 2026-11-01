<div class="header-box">
    <div style="font-size: 14px; font-weight: bold; text-transform: uppercase; text-align: center;">
        {{ $company->name ?? 'NSD EARTH MOVERS & CONSTRUCTION' }} &mdash; ABSTRACT OF {{ $raBill->ra_label }}
    </div>
    <div style="margin-top: 4px; text-align: center; font-size: 9.5px;">
        <strong>Client:</strong> {{ $raBill->client?->name ?? 'JSW STEEL LIMITED' }} &nbsp;|&nbsp;
        <strong>PO / Service Order #:</strong> {{ $workOrder->po_number ?? $workOrder->order_number }} &nbsp;|&nbsp;
        <strong>Invoice #:</strong> {{ $raBill->invoice_number ?? 'DRAFT' }} &nbsp;|&nbsp;
        <strong>Period:</strong> {{ $raBill->period_from->format('d.m.Y') }} to {{ $raBill->period_to->format('d.m.Y') }}
    </div>
</div>

<table class="abstract-grid">
    <thead>
        <tr>
            <th>Sr. No.</th>
            <th>Service Code</th>
            <th>SAC</th>
            <th style="width: 24%;">Item Description</th>
            <th>UOM</th>
            <th>Order Qty</th>
            <th>Rate (₹)</th>
            <th>Prev Qty</th>
            <th>Cur Billable Qty</th>
            <th>Cumul Qty</th>
            <th>Balance Qty</th>
            <th>Hold Qty</th>
            <th>Excess Unbilled</th>
            <th>Prev Amt (₹)</th>
            <th>Current Amt (₹)</th>
            <th>Cumul Amt (₹)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($raBill->items as $item)
            <tr>
                <td class="text-center font-bold">{{ $item->sr_no }}</td>
                <td class="text-center">{{ $item->service_code ?? '—' }}</td>
                <td class="text-center">{{ $item->sac_code ?? '—' }}</td>
                <td>
                    <div class="font-bold">{{ $item->description }}</div>
                    @if($item->specification)
                        <div style="font-size: 8px; color: #475569;">{{ $item->specification }}</div>
                    @endif
                </td>
                <td class="text-center">{{ $item->unit }}</td>
                <td class="text-right">{{ number_format((float) $item->ordered_quantity, 3) }}</td>
                <td class="text-right">{{ number_format((float) $item->rate, 2) }}</td>
                <td class="text-right">{{ number_format((float) $item->previous_quantity, 3) }}</td>
                <td class="text-right font-bold">{{ number_format((float) $item->current_billable_quantity, 3) }}</td>
                <td class="text-right font-bold">{{ number_format((float) $item->cumulative_quantity, 3) }}</td>
                <td class="text-right">{{ number_format((float) $item->balance_quantity, 3) }}</td>
                <td class="text-right">{{ number_format((float) $item->hold_quantity, 3) }}</td>
                <td class="text-right">{{ number_format((float) $item->excess_quantity, 3) }}</td>
                <td class="text-right">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $item->previous_amount, 2) }}</td>
                <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $item->current_amount, 2) }}</td>
                <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $item->cumulative_amount, 2) }}</td>
            </tr>
        @endforeach
    </tbody>
    <tfoot>
        <tr style="background: #f1f5f9;">
            <td colspan="13" class="text-right font-bold">Total Basic Value (A)</td>
            <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->previous_bill_amount, 2) }}</td>
            <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->current_bill_amount, 2) }}</td>
            <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->cumulative_bill_amount, 2) }}</td>
        </tr>
        <tr>
            <td colspan="14" class="text-right">Add: CGST @ {{ number_format((float) $raBill->cgst_rate, 2) }}% on Current Basic (A)</td>
            <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->cgst_amount, 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="14" class="text-right">Add: SGST @ {{ number_format((float) $raBill->sgst_rate, 2) }}% on Current Basic (A)</td>
            <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->sgst_amount, 2) }}</td>
            <td></td>
        </tr>
        <tr style="background: #fef3c7;">
            <td colspan="14" class="text-right font-bold">Gross Bill Amount (A + CGST + SGST)</td>
            <td class="text-right font-bold">₹ {{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->gross_amount, 2) }}</td>
            <td></td>
        </tr>
        <tr>
            <td colspan="14" class="text-right font-bold">Net Payable Amount ({{ strtoupper($raBill->rounding_mode) }} Rounding)</td>
            <td class="text-right font-bold">₹ {{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->net_payable, 2) }}</td>
            <td></td>
        </tr>
    </tfoot>
</table>

<div style="margin-top: 8px; font-weight: bold;">
    Amount in Words: {{ $amountInWords }}
</div>


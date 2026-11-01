<div class="header-box">
    <div class="company-title">{{ $company->name ?? 'NSD EARTH MOVERS & CONSTRUCTION' }}</div>
    @if($company->registered_address)
        <div class="company-sub">{{ $company->registered_address }}</div>
    @endif
    <div class="company-sub">
        <strong>GSTIN:</strong> {{ $company->gstin ?? '—' }} &nbsp;|&nbsp;
        <strong>PAN:</strong> {{ $company->pan ?? '—' }} &nbsp;|&nbsp;
        <strong>Vendor Code:</strong> {{ $company->vendor_code ?? ($raBill->client?->vendor_code ?? '—') }}
    </div>
</div>

<div class="doc-banner">TAX INVOICE ({{ $raBill->ra_label }})</div>

<table class="meta-grid">
    <tr>
        <td style="width: 52%;">
            <div class="font-bold" style="font-size: 10px; text-transform: uppercase; color: #475569;">Billed To (Recipient / Client):</div>
            <div class="font-bold" style="font-size: 12px; margin-top: 2px;">{{ $raBill->client?->name ?? $workOrder->contractor_name ?? 'JSW STEEL LIMITED' }}</div>
            @if($raBill->client?->address)
                <div style="margin-top: 2px;">{{ $raBill->client->address }}</div>
            @endif
            <div style="margin-top: 4px;">
                <strong>Client GSTIN:</strong> {{ $raBill->client?->gstin ?? $workOrder->contractor_gst ?? '—' }}<br>
                <strong>Client PAN:</strong> {{ $raBill->client?->pan ?? '—' }}
            </div>
        </td>
        <td style="width: 48%;">
            <strong>INVOICE NO:</strong> {{ $raBill->invoice_number ?? 'DRAFT (UNASSIGNED)' }}<br>
            <strong>INVOICE DATE:</strong> {{ ($raBill->invoice_date ?? now())->format('d.m.Y') }}<br>
            <strong>RA BILL NO:</strong> {{ $raBill->ra_label }}<br>
            <strong>SERVICE / PO NO:</strong> {{ $workOrder->po_number ?? $workOrder->order_number }}
            @if($workOrder->po_date)
                &nbsp;<strong>DT:</strong> {{ $workOrder->po_date->format('d.m.Y') }}
            @endif
            <br>
            <strong>AMENDMENT / REV:</strong> Rev {{ $raBill->work_order_revision }}<br>
            <strong>JOB LOCATION:</strong> {{ $workOrder->job_location ?? $workOrder->site?->name ?? '—' }}<br>
            <strong>BILLING PERIOD:</strong> {{ $raBill->period_from->format('d.m.Y') }} to {{ $raBill->period_to->format('d.m.Y') }}
        </td>
    </tr>
</table>

<table class="items-grid">
    <thead>
        <tr>
            <th style="width: 8%;">Sr. No.</th>
            <th style="width: 52%;">Particulars / Description of Works</th>
            <th style="width: 14%;">SAC Code</th>
            <th style="width: 26%;">Amount (INR)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td class="text-center font-bold">1</td>
            <td>
                <div class="font-bold">{{ $workOrder->title }}</div>
                <div style="margin-top: 3px;">
                    Running Account Bill ({{ $raBill->ra_label }}) as per attached Measurement &amp; Abstract Sheet
                    against Service Order / PO No. <strong>{{ $workOrder->po_number ?? $workOrder->order_number }}</strong>.
                </div>
            </td>
            <td class="text-center font-bold">{{ $raBill->items->first()?->sac_code ?? '995419' }}</td>
            <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->current_bill_amount, 2) }}</td>
        </tr>
        <tr>
            <td colspan="3" class="text-right font-bold">Total Basic Taxable Value (A)</td>
            <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->current_bill_amount, 2) }}</td>
        </tr>
        <tr>
            <td colspan="3" class="text-right">Add: CGST @ {{ number_format((float) $raBill->cgst_rate, 2) }}% on (A)</td>
            <td class="text-right">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->cgst_amount, 2) }}</td>
        </tr>
        <tr>
            <td colspan="3" class="text-right">Add: SGST @ {{ number_format((float) $raBill->sgst_rate, 2) }}% on (A)</td>
            <td class="text-right">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->sgst_amount, 2) }}</td>
        </tr>
        <tr style="background: #f1f5f9;">
            <td colspan="3" class="text-right font-bold">Total Gross Invoice Amount (A + CGST + SGST)</td>
            <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->gross_amount, 2) }}</td>
        </tr>
        @if((float) $raBill->retention_deduction > 0 || (float) $raBill->advance_recovery > 0 || (float) $raBill->other_deductions > 0)
            <tr>
                <td colspan="3" class="text-right">Less: Retention / Advance / Other Deductions</td>
                <td class="text-right">-{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->retention_deduction + (float) $raBill->advance_recovery + (float) $raBill->other_deductions, 2) }}</td>
            </tr>
        @endif
        <tr style="background: #fef3c7;">
            <td colspan="3" class="text-right font-bold">Net Payable Amount ({{ strtoupper($raBill->rounding_mode) }} Rounding)</td>
            <td class="text-right font-bold">₹ {{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $raBill->net_payable, 2) }}</td>
        </tr>
    </tbody>
</table>

<div class="words-box">
    Amount in Words: {{ $amountInWords }}
</div>

<table class="footer-grid">
    <tr>
        <td>
            <div class="font-bold" style="text-transform: uppercase; margin-bottom: 4px;">Bank Remittance Details (RTGS / NEFT):</div>
            <strong>Bank Name:</strong> {{ $company->bank_name ?? '—' }}<br>
            <strong>Account No:</strong> {{ $company->bank_account_no ?? '—' }}<br>
            <strong>IFSC Code:</strong> {{ $company->bank_ifsc ?? '—' }}<br>
            <strong>Branch:</strong> {{ $company->bank_branch ?? '—' }}
        </td>
        <td class="text-right">
            <div class="font-bold">For {{ $company->name ?? 'NSD EARTH MOVERS & CONSTRUCTION' }}</div>
            <div style="height: 42px;"></div>
            <div class="font-bold">Authorised Signatory</div>
        </td>
    </tr>
</table>


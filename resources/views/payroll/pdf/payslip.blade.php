<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip — {{ $period->period_code }} — {{ $worker?->code }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; margin: 24px; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 16px; }
        .company-name { font-size: 16px; font-weight: bold; color: #0f172a; }
        .doc-title { font-size: 13px; font-weight: bold; color: #1e40af; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; }
        th { background-color: #f1f5f9; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .net-box { background-color: #f0fdf4; border: 1px solid #86efac; padding: 10px 12px; font-size: 13px; font-weight: bold; color: #14532d; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $company?->legal_name ?: ($company?->name ?? 'NSD Construction') }}</div>
        <div class="doc-title">Salary / Wage Payslip — {{ $period->period_code }} ({{ $period->start_date?->format('d M Y') }} to {{ $period->end_date?->format('d M Y') }})</div>
    </div>

    <table>
        <tr>
            <td style="width: 50%;">
                <strong>Employee / Worker:</strong> {{ $worker?->full_name }} ({{ $worker?->code }})<br>
                <strong>Designation:</strong> {{ $worker?->trade_designation ?: 'Workforce' }}<br>
                <strong>Site:</strong> {{ $entry->site?->name ?? $worker?->assignedSite?->name ?? 'All Sites' }}<br>
                <strong>Pay Type:</strong> {{ ucfirst($worker?->pay_type ?? 'daily') }}
            </td>
            <td style="width: 50%;">
                <strong>Present / Half / Leave Days:</strong> {{ $entry->present_days }} / {{ $entry->half_days }} / {{ $entry->paid_leave_days }}<br>
                <strong>Worked / Overtime Hours:</strong> {{ number_format((float) $entry->worked_hours, 1) }}h / {{ number_format((float) $entry->overtime_hours, 1) }}h<br>
                <strong>Bank / IFSC:</strong> {{ $worker?->bank_account_number ?: '—' }} {{ $worker?->bank_ifsc ? '(' . $worker->bank_ifsc . ')' : '' }}<br>
                @if(!empty($worker?->uan_number))<strong>UAN:</strong> {{ $worker->uan_number }} @endif
                @if(!empty($worker?->esic_ip_number))<strong>ESIC IP:</strong> {{ $worker->esic_ip_number }}@endif
            </td>
        </tr>
    </table>

    @php
        $pfEmp = (float) ($entry->pf_employee_amount ?? 0);
        $esicEmp = (float) ($entry->esic_employee_amount ?? 0);
        $ptAmt = (float) ($entry->pt_amount ?? 0);
        $pfEmployer = (float) ($entry->pf_employer_amount ?? 0);
        $esicEmployer = (float) ($entry->esic_employer_amount ?? 0);
        $totalDeductions = round((float) $entry->advance_deduction + (float) $entry->other_deduction + $pfEmp + $esicEmp + $ptAmt, 2);
        $snapshot = is_array($entry->statutory_snapshot ?? null) ? $entry->statutory_snapshot : [];
    @endphp

    <table>
        <thead>
            <tr>
                <th style="width: 50%;">Earnings</th>
                <th style="width: 20%;" class="text-right">Amount (₹)</th>
                <th style="width: 30%;">Deductions</th>
                <th style="width: 20%;" class="text-right">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Base Wages / Salary</td>
                <td class="text-right">{{ number_format((float) $entry->base_pay, 2) }}</td>
                <td>Salary Advance Recovery</td>
                <td class="text-right">{{ number_format((float) $entry->advance_deduction, 2) }}</td>
            </tr>
            <tr>
                <td>Overtime Pay</td>
                <td class="text-right">{{ number_format((float) $entry->overtime_pay, 2) }}</td>
                <td>Employee PF ({{ strtoupper($snapshot['pf']['status'] ?? ($worker?->pf_status ?? 'N/A')) }})</td>
                <td class="text-right">{{ number_format($pfEmp, 2) }}</td>
            </tr>
            <tr>
                <td>Bonus / Incentive</td>
                <td class="text-right">{{ number_format((float) $entry->bonus_amount, 2) }}</td>
                <td>Employee ESIC ({{ strtoupper($snapshot['esic']['status'] ?? ($worker?->esic_status ?? 'N/A')) }})</td>
                <td class="text-right">{{ number_format($esicEmp, 2) }}</td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td>Professional Tax ({{ strtoupper($snapshot['pt']['status'] ?? ($worker?->pt_status ?? 'N/A')) }})</td>
                <td class="text-right">{{ number_format($ptAmt, 2) }}</td>
            </tr>
            <tr>
                <td></td>
                <td></td>
                <td>Other Deductions {{ $entry->deduction_notes ? '(' . $entry->deduction_notes . ')' : '' }}</td>
                <td class="text-right">{{ number_format((float) $entry->other_deduction, 2) }}</td>
            </tr>
            <tr style="font-weight: bold; background-color: #f8fafc;">
                <td>Gross Earnings</td>
                <td class="text-right">₹{{ number_format((float) $entry->gross_pay, 2) }}</td>
                <td>Total Employee Deductions</td>
                <td class="text-right">₹{{ number_format($totalDeductions, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if($pfEmployer > 0 || $esicEmployer > 0)
        <p style="font-size: 10px; color: #475569;">
            <strong>Employer Statutory Contributions (Non-Deductible from Net Pay):</strong>
            Employer PF: ₹{{ number_format($pfEmployer, 2) }} | Employer ESIC: ₹{{ number_format($esicEmployer, 2) }}
        </p>
    @endif

    <div class="net-box">
        Net Payable Amount: ₹{{ number_format((float) $entry->net_pay, 2) }}
        &nbsp;&bull;&nbsp; Payment Status: {{ strtoupper($entry->payment_status ?? 'pending') }}
    </div>
</body>
</html>

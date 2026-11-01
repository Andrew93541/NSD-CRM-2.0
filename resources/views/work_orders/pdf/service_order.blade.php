<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Service Order — {{ $workOrder->order_number }} (Rev {{ $workOrder->current_revision ?? 0 }})</title>
    <style>
        @page { margin: 20px 24px; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 10px; color: #0f172a; line-height: 1.35; }
        .header-box { border: 2px solid #0f172a; padding: 10px 14px; background: #f8fafc; text-align: center; }
        .company-title { font-size: 16px; font-weight: bold; text-transform: uppercase; letter-spacing: 0.5px; }
        .company-sub { font-size: 9.5px; color: #334155; margin-top: 2px; }
        .doc-banner { background: #0f172a; color: #ffffff; font-weight: bold; font-size: 12px; text-align: center; padding: 5px; letter-spacing: 0.8px; margin-top: 6px; text-transform: uppercase; }
        table.meta-grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.meta-grid td { border: 1px solid #334155; padding: 6px 8px; vertical-align: top; width: 50%; }
        table.boq-grid { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.boq-grid th { background: #e2e8f0; border: 1px solid #0f172a; padding: 5px 6px; font-size: 9px; text-transform: uppercase; }
        table.boq-grid td { border: 1px solid #334155; padding: 5px 6px; vertical-align: top; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .spec-text { font-size: 8.5px; color: #334155; margin-top: 2px; white-space: pre-line; }
        .words-box { border: 1px solid #0f172a; background: #f8fafc; padding: 6px 8px; margin-top: 8px; font-weight: bold; }
        .terms-box { border: 1px solid #334155; padding: 6px 8px; margin-top: 8px; font-size: 9px; white-space: pre-line; }
        table.sign-grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        table.sign-grid td { border: 1px solid #334155; padding: 6px 8px; vertical-align: top; width: 25%; }
    </style>
</head>
<body>
    <div class="header-box">
        <div class="company-title">{{ $company->name ?? 'NSD EARTH MOVERS & CONSTRUCTION' }}</div>
        @if($company?->registered_address)
            <div class="company-sub">{{ $company->registered_address }}</div>
        @endif
        <div class="company-sub">
            <strong>GSTIN:</strong> {{ $company?->gstin ?? '—' }} &nbsp;|&nbsp;
            <strong>PAN:</strong> {{ $company?->pan ?? '—' }} &nbsp;|&nbsp;
            <strong>Vendor Code:</strong> {{ $company?->vendor_code ?? '—' }}
        </div>
    </div>

    <div class="doc-banner">
        SERVICE ORDER / WORK ORDER ({{ $workOrder->order_type === 'client_issued' ? 'RECEIVED CLIENT SERVICE ORDER' : 'SUBCONTRACTOR SERVICE ORDER' }})
    </div>

    <table class="meta-grid">
        <tr>
            <td>
                <div class="font-bold" style="text-transform: uppercase; font-size: 9px; color: #475569;">
                    {{ $workOrder->order_type === 'client_issued' ? 'Principal Employer / Client Organization:' : 'Issued To Subcontractor / Vendor:' }}
                </div>
                <div class="font-bold" style="font-size: 11px; margin-top: 2px;">
                    {{ $workOrder->contractor_name ?: ($workOrder->client?->name ?? 'JSW STEEL LIMITED') }}
                </div>
                @if($workOrder->client?->address)
                    <div style="margin-top: 2px;">{{ $workOrder->client->address }}</div>
                @endif
                <div style="margin-top: 4px;">
                    <strong>GSTIN:</strong> {{ $workOrder->contractor_gst ?: ($workOrder->client?->gstin ?? '—') }}<br>
                    <strong>PAN:</strong> {{ $workOrder->client?->pan ?? '—' }}<br>
                    <strong>Contact Person:</strong> {{ $workOrder->contractor_contact ?: ($workOrder->client?->contact_person ?? '—') }}<br>
                    <strong>Project:</strong> {{ $workOrder->project?->code }} — {{ $workOrder->project?->name }}
                </div>
            </td>
            <td>
                <strong>WORK ORDER NO:</strong> {{ $workOrder->order_number }}<br>
                <strong>SERVICE / PO NO:</strong> {{ $workOrder->po_number ?? $workOrder->client_reference ?? '—' }}<br>
                <strong>PO / ORDER DATE:</strong> {{ ($workOrder->po_date ?? $workOrder->created_at)->format('d.m.Y') }}<br>
                <strong>AMENDMENT / REVISION:</strong> Rev {{ $workOrder->current_revision ?? 0 }}<br>
                <strong>EFFECTIVE DATE:</strong> {{ ($workOrder->effective_date ?? $workOrder->target_start_date)?->format('d.m.Y') ?? 'Immediate' }}<br>
                <strong>COMPLETION DATE:</strong> {{ ($workOrder->completion_date ?? $workOrder->target_completion_date)?->format('d.m.Y') ?? 'As per schedule' }}<br>
                <strong>JOB LOCATION:</strong> {{ $workOrder->job_location ?? $workOrder->site?->name ?? '—' }}<br>
                <strong>USER DEPT / PERSON:</strong> {{ $workOrder->user_department_person ?? '—' }}<br>
                <strong>PAYMENT TERMS:</strong> {{ $workOrder->payment_terms ?: \App\Models\WorkOrder::DEFAULT_PAYMENT_TERMS }}
            </td>
        </tr>
    </table>

    <div style="margin-top: 8px; padding: 6px 8px; border: 1px solid #334155; background: #f8fafc;">
        <strong>SUBJECT:</strong> {{ $workOrder->title }}
        @if($workOrder->description)
            <div style="margin-top: 2px;">{{ $workOrder->description }}</div>
        @endif
    </div>

    <table class="boq-grid">
        <thead>
            <tr>
                <th style="width: 7%;">Sr. No.</th>
                <th style="width: 11%;">Service Code</th>
                <th style="width: 9%;">SAC Code</th>
                <th style="width: 36%;">Item Description &amp; Technical Specification</th>
                <th style="width: 7%;">UOM</th>
                <th style="width: 10%;">Quantity</th>
                <th style="width: 10%;">Rate (INR)</th>
                <th style="width: 10%;">Amount (INR)</th>
            </tr>
        </thead>
        <tbody>
            @if($workOrder->items->isNotEmpty())
                @foreach($workOrder->items as $item)
                    <tr>
                        <td class="text-center font-bold">{{ $item->sr_no }}</td>
                        <td class="text-center">{{ $item->service_code ?? '—' }}</td>
                        <td class="text-center">{{ $item->sac_code ?? '—' }}</td>
                        <td>
                            <div class="font-bold">{{ $item->description }}</div>
                            @if($item->specification)
                                <div class="spec-text">{{ $item->specification }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ $item->unit }}</td>
                        <td class="text-right">{{ number_format((float) $item->ordered_quantity, 3) }}</td>
                        <td class="text-right">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $item->rate, 2) }}</td>
                        <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) $item->line_amount, 2) }}</td>
                    </tr>
                @endforeach
            @elseif(!empty($workOrder->boq_items) && is_array($workOrder->boq_items))
                @foreach($workOrder->boq_items as $idx => $item)
                    <tr>
                        <td class="text-center font-bold">{{ $item['sr_no'] ?? ($idx + 1) }}</td>
                        <td class="text-center">{{ $item['service_code'] ?? '—' }}</td>
                        <td class="text-center">{{ $item['sac_code'] ?? '995419' }}</td>
                        <td>
                            <div class="font-bold">{{ $item['description'] ?? '' }}</div>
                            @if(!empty($item['specification']))
                                <div class="spec-text">{{ $item['specification'] }}</div>
                            @endif
                        </td>
                        <td class="text-center">{{ $item['unit'] ?? 'Cum' }}</td>
                        <td class="text-right">{{ number_format((float) ($item['quantity'] ?? 0), 3) }}</td>
                        <td class="text-right">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) ($item['rate'] ?? 0), 2) }}</td>
                        <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber((float) ($item['amount'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
        <tfoot>
            @php
                $baseVal = (float) ($workOrder->base_order_value ?: $workOrder->estimated_amount);
                $cgstVal = $workOrder->cgstAmount();
                $sgstVal = $workOrder->sgstAmount();
                $grossVal = round($baseVal + $cgstVal + $sgstVal, 2);
            @endphp
            <tr style="background: #f1f5f9;">
                <td colspan="7" class="text-right font-bold">Total Basic Service Order Value (A)</td>
                <td class="text-right font-bold">{{ \App\Support\IndianCurrencyWords::formatIndianNumber($baseVal, 2) }}</td>
            </tr>
            <tr>
                <td colspan="7" class="text-right">Add: CGST @ {{ number_format((float) $workOrder->cgst_rate, 2) }}%</td>
                <td class="text-right">{{ \App\Support\IndianCurrencyWords::formatIndianNumber($cgstVal, 2) }}</td>
            </tr>
            <tr>
                <td colspan="7" class="text-right">Add: SGST @ {{ number_format((float) $workOrder->sgst_rate, 2) }}%</td>
                <td class="text-right">{{ \App\Support\IndianCurrencyWords::formatIndianNumber($sgstVal, 2) }}</td>
            </tr>
            <tr style="background: #fef3c7;">
                <td colspan="7" class="text-right font-bold">Total Gross Service Order Value (Incl. GST) [Retention: {{ number_format((float) $workOrder->retention_percent, 2) }}%]</td>
                <td class="text-right font-bold">₹ {{ \App\Support\IndianCurrencyWords::formatIndianNumber($grossVal, 2) }}</td>
            </tr>
        </tfoot>
    </table>

    <div class="words-box">
        Total Gross Order Value in Words: {{ $amountInWords }}
    </div>

    <div class="terms-box">
        <strong>TERMS &amp; CONDITIONS:</strong><br>{{ $workOrder->terms_and_conditions ?: ($company?->work_order_terms_template ?: \App\Models\WorkOrder::DEFAULT_TERMS) }}
    </div>

    <table class="sign-grid">
        <tr>
            <td>
                <div class="font-bold">Prepared By:</div>
                <div style="margin-top: 4px;">{{ $workOrder->creator?->name ?? 'System' }}</div>
                <div style="font-size: 8.5px; color: #475569;">{{ $workOrder->created_at?->format('d.m.Y H:i') }}</div>
            </td>
            <td>
                <div class="font-bold">Tier 1 (Site Manager):</div>
                @if($workOrder->site_manager_approved_at)
                    <div style="margin-top: 4px;">{{ $workOrder->siteManager?->name }}</div>
                    <div style="font-size: 8.5px; color: #15803d;">Approved: {{ $workOrder->site_manager_approved_at->format('d.m.Y H:i') }}</div>
                @else
                    <div style="margin-top: 4px; color: #64748b;">Pending Site Verification</div>
                @endif
            </td>
            <td>
                <div class="font-bold">Tier 2 (Senior VP):</div>
                @if($workOrder->senior_manager_approved_at)
                    <div style="margin-top: 4px;">{{ $workOrder->seniorManager?->name }}</div>
                    <div style="font-size: 8.5px; color: #15803d;">Authorized: {{ $workOrder->senior_manager_approved_at->format('d.m.Y H:i') }}</div>
                @else
                    <div style="margin-top: 4px; color: #64748b;">Pending Final Sign-off</div>
                @endif
            </td>
            <td>
                <div class="font-bold">Accepted By Contractor:</div>
                <div style="height: 24px;"></div>
                <div style="font-size: 8.5px; color: #475569;">Signature &amp; Seal</div>
            </td>
        </tr>
    </table>
</body>
</html>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Order {{ $order->po_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; margin: 24px; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 16px; }
        .company-name { font-size: 16px; font-weight: bold; color: #0f172a; }
        .doc-title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #1e40af; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background-color: #f1f5f9; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .meta-table td { border: 1px solid #e2e8f0; width: 50%; }
        .total-row td { font-weight: bold; background-color: #f8fafc; font-size: 12px; }
        .signatures { margin-top: 36px; width: 100%; }
        .signatures td { border: none; padding-top: 32px; text-align: center; width: 50%; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $company?->legal_name ?: ($company?->name ?? 'NSD Construction') }}</div>
        <div>
            @if($company?->registered_address){{ $company->registered_address }}<br>@endif
            @if($company?->gstin)GSTIN: {{ $company->gstin }} @endif
            @if($company?->pan)| PAN: {{ $company->pan }} @endif
        </div>
        <div class="doc-title">Purchase Order — {{ $order->po_number }}</div>
    </div>

    <table class="meta-table">
        <tr>
            <td>
                <strong>Supplier / Vendor Details</strong><br>
                {{ $order->vendor?->name ?: $order->supplier_name }}<br>
                @if($order->vendor?->code)Vendor Code: {{ $order->vendor->code }}<br>@endif
                @if($order->vendor?->gstin)GSTIN: {{ $order->vendor->gstin }}<br>@endif
                @if($order->supplier_phone || $order->vendor?->phone)Phone: {{ $order->supplier_phone ?: $order->vendor?->phone }}<br>@endif
                @if($order->vendor?->address){{ $order->vendor->address }}@endif
            </td>
            <td>
                <strong>Order Information</strong><br>
                PO Number: <strong>{{ $order->po_number }}</strong><br>
                Order Date: {{ $order->order_date?->format('d M Y') ?? '—' }}<br>
                Expected Delivery: {{ $order->expected_delivery_date?->format('d M Y') ?? 'As scheduled' }}<br>
                Delivery Site: {{ $order->site?->name ?? 'Central Yard' }}<br>
                Destination Store: {{ $order->warehouse?->name ?? '—' }}<br>
                Source PR: {{ $order->purchaseRequest?->request_number ?? '—' }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 6%;">#</th>
                <th style="width: 16%;">Material Code</th>
                <th style="width: 36%;">Material Description</th>
                <th style="width: 10%;">Unit</th>
                <th style="width: 10%;" class="text-right">Qty Ordered</th>
                <th style="width: 10%;" class="text-right">Unit Rate (₹)</th>
                <th style="width: 12%;" class="text-right">Line Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($order->items as $idx => $item)
                @php
                    $lineTotal = round((float) $item->quantity_ordered * (float) $item->unit_price, 2);
                @endphp
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $item->material?->code ?? '—' }}</td>
                    <td>
                        {{ $item->material?->name ?? 'Material #'.$item->material_id }}
                        @if($item->material?->hsn_code)
                            <br><span style="font-size: 9px; color: #64748b;">HSN: {{ $item->material->hsn_code }}</span>
                        @endif
                    </td>
                    <td>{{ $item->material?->unit_of_measure ?? 'Unit' }}</td>
                    <td class="text-right">{{ number_format((float) $item->quantity_ordered, 2) }}</td>
                    <td class="text-right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="text-right">{{ number_format($lineTotal, 2) }}</td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td colspan="6" class="text-right">Total Purchase Order Value (₹)</td>
                <td class="text-right">₹{{ number_format((float) $order->total_amount, 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if($order->notes)
        <p><strong>Instructions / Notes:</strong> {{ $order->notes }}</p>
    @endif

    <table class="signatures">
        <tr>
            <td>
                _______________________________<br>
                Prepared By ({{ $order->creator?->name ?? 'Procurement' }})
            </td>
            <td>
                _______________________________<br>
                Authorized Signatory ({{ $company?->signatory_designation ?: 'Company Seal & Sign' }})
            </td>
        </tr>
    </table>
</body>
</html>

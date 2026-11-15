<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Goods Receipt Note {{ $transaction->transaction_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; margin: 24px; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 16px; }
        .company-name { font-size: 16px; font-weight: bold; color: #0f172a; }
        .doc-title { font-size: 14px; font-weight: bold; text-transform: uppercase; color: #047857; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background-color: #f1f5f9; font-weight: bold; font-size: 10px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .signatures { margin-top: 36px; width: 100%; }
        .signatures td { border: none; padding-top: 32px; text-align: center; width: 50%; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">{{ $company?->legal_name ?: ($company?->name ?? 'NSD Construction') }}</div>
        <div class="doc-title">Goods Receipt Note (GRN) — {{ $transaction->transaction_number }}</div>
    </div>

    <table>
        <tr>
            <td style="width: 50%;">
                <strong>Supplier &amp; PO Reference</strong><br>
                PO Number: <strong>{{ $transaction->purchaseOrder?->po_number ?? 'Direct Receipt' }}</strong><br>
                Supplier: {{ $transaction->purchaseOrder?->vendor?->name ?: ($transaction->purchaseOrder?->supplier_name ?? '—') }}<br>
                Supplier Challan / Invoice: <strong>{{ $transaction->challan_or_invoice_no ?: '—' }}</strong>
            </td>
            <td style="width: 50%;">
                <strong>Receiving Location</strong><br>
                GRN Date: {{ $transaction->transaction_date?->format('d M Y') ?? $transaction->created_at?->format('d M Y') }}<br>
                Receiving Store: <strong>{{ $transaction->destinationWarehouse?->name ?? '—' }}</strong><br>
                Site: {{ $transaction->site?->name ?? 'Central Yard' }}<br>
                Received By: {{ $transaction->performedBy?->name ?? 'Storekeeper' }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;">#</th>
                <th style="width: 18%;">Material Code</th>
                <th style="width: 44%;">Material Name</th>
                <th style="width: 14%;">Unit</th>
                <th style="width: 16%;" class="text-right">Quantity Received</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transaction->items as $idx => $item)
                <tr>
                    <td>{{ $idx + 1 }}</td>
                    <td>{{ $item->material?->code ?? '—' }}</td>
                    <td>{{ $item->material?->name ?? 'Material #'.$item->material_id }}</td>
                    <td>{{ $item->material?->unit_of_measure ?? 'Unit' }}</td>
                    <td class="text-right"><strong>{{ number_format((float) $item->quantity, 2) }}</strong></td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if($transaction->notes)
        <p><strong>Remarks:</strong> {{ $transaction->notes }}</p>
    @endif

    <table class="signatures">
        <tr>
            <td>
                _______________________________<br>
                Store / Site Receiver ({{ $transaction->performedBy?->name ?? 'Receiver' }})
            </td>
            <td>
                _______________________________<br>
                Verified By ({{ $transaction->confirmedBy?->name ?? 'Site / Store Manager' }})
            </td>
        </tr>
    </table>
</body>
</html>

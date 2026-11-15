<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Measurement Book — {{ $workOrder->order_number }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #1f2937; margin: 20px; }
        .header { border-bottom: 2px solid #0f172a; padding-bottom: 8px; margin-bottom: 12px; }
        .title { font-size: 14px; font-weight: bold; color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 5px 6px; text-align: left; vertical-align: top; }
        th { background-color: #f1f5f9; font-weight: bold; font-size: 9px; text-transform: uppercase; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">{{ $company?->name ?? 'NSD Construction' }} — Site Measurement Book (MB)</div>
        <div>
            Work Order: <strong>{{ $workOrder->order_number }}</strong> ({{ $workOrder->title }}) |
            Client: {{ $workOrder->client?->name ?? $workOrder->client_name }} |
            Site: {{ $workOrder->site?->name ?? '—' }}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Date</th>
                <th style="width: 18%;">BOQ Item</th>
                <th style="width: 14%;">Chainage / Location</th>
                <th style="width: 6%;" class="text-right">Nos</th>
                <th style="width: 7%;" class="text-right">L</th>
                <th style="width: 7%;" class="text-right">B</th>
                <th style="width: 7%;" class="text-right">D/H</th>
                <th style="width: 9%;" class="text-right">Computed Qty</th>
                <th style="width: 7%;" class="text-right">Hold Qty</th>
                <th style="width: 8%;">Status</th>
                <th style="width: 9%;">RA Bill</th>
            </tr>
        </thead>
        <tbody>
            @forelse($measurements as $m)
                <tr>
                    <td>{{ $m->measured_on?->format('d M Y') ?? '—' }}</td>
                    <td>
                        <strong>{{ $m->workOrderItem?->sr_no }}</strong> — {{ $m->workOrderItem?->description }}
                        ({{ $m->workOrderItem?->unit }})
                    </td>
                    <td>{{ $m->location_chainage ?: '—' }}</td>
                    <td class="text-right">{{ number_format((float) $m->nos, 2) }}</td>
                    <td class="text-right">{{ $m->length !== null ? number_format((float) $m->length, 3) : '—' }}</td>
                    <td class="text-right">{{ $m->breadth !== null ? number_format((float) $m->breadth, 3) : '—' }}</td>
                    <td class="text-right">{{ $m->depth_height !== null ? number_format((float) $m->depth_height, 3) : '—' }}</td>
                    <td class="text-right"><strong>{{ number_format((float) $m->computed_quantity, 3) }}</strong></td>
                    <td class="text-right">{{ number_format((float) $m->hold_quantity, 3) }}</td>
                    <td>
                        {{ strtoupper($m->status ?? 'verified') }}
                        @if($m->verifier)
                            <br><span style="font-size: 8px; color: #475569;">By {{ $m->verifier->name }}</span>
                        @endif
                    </td>
                    <td>{{ $m->raBill?->ra_label ?? 'Unbilled' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="11" style="text-align: center; padding: 14px;">No site measurements recorded yet for this work order.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>

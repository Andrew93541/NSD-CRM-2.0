<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Worker ID Card — {{ $worker->code }}</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #0f172a; margin: 36px; }
        .badge-card { width: 320px; border: 2px solid #0f172a; border-radius: 10px; overflow: hidden; margin: 0 auto; }
        .badge-header { background-color: #0f172a; color: #ffffff; padding: 12px 14px; text-align: center; }
        .company-title { font-size: 13px; font-weight: bold; letter-spacing: 0.5px; }
        .badge-sub { font-size: 9px; color: #cbd5e1; margin-top: 2px; text-transform: uppercase; }
        .badge-body { padding: 16px; text-align: center; }
        .worker-name { font-size: 16px; font-weight: bold; margin-bottom: 2px; }
        .worker-code { font-size: 12px; font-weight: bold; color: #1d4ed8; margin-bottom: 6px; }
        .worker-role { font-size: 11px; color: #334155; margin-bottom: 12px; }
        .qr-box { margin: 8px auto 12px auto; padding: 8px; border: 1px solid #cbd5e1; display: inline-block; background: #ffffff; }
        .meta-grid { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 10px; }
        .meta-grid td { padding: 4px 6px; border-top: 1px solid #e2e8f0; text-align: left; }
        .badge-footer { background-color: #f8fafc; border-top: 1px solid #cbd5e1; padding: 8px 12px; font-size: 9px; color: #475569; text-align: center; }
    </style>
</head>
<body>
    <div class="badge-card">
        <div class="badge-header">
            <div class="company-title">{{ $company?->name ?? 'NSD Construction' }}</div>
            <div class="badge-sub">Workforce Attendance &amp; Site Identity Pass</div>
        </div>
        <div class="badge-body">
            <div class="worker-name">{{ $worker->full_name }}</div>
            <div class="worker-code">{{ $worker->code }}</div>
            <div class="worker-role">
                {{ $worker->trade_designation ?: 'Field Workforce' }}
                @if($worker->department) &bull; {{ $worker->department }} @endif
            </div>

            <div class="qr-box">
                <img src="{{ $qrDataUri }}" alt="Worker Attendance QR" width="140" height="140">
            </div>

            <table class="meta-grid">
                <tr>
                    <td><strong>Category:</strong></td>
                    <td>{{ $worker->worker_type === 'subcontractor_worker' ? 'Contractor (' . ($worker->subcontractor?->name ?? 'Vendor') . ')' : 'Direct Employee' }}</td>
                </tr>
                <tr>
                    <td><strong>Assigned Site:</strong></td>
                    <td>{{ $worker->assignedSite?->name ?? 'Unassigned / Floating' }}</td>
                </tr>
                <tr>
                    <td><strong>Joined:</strong></td>
                    <td>{{ $worker->joining_date?->format('d M Y') ?? '—' }}</td>
                </tr>
                @if($worker->emergency_contact_phone)
                    <tr>
                        <td><strong>Emergency:</strong></td>
                        <td>{{ $worker->emergency_contact_name ? $worker->emergency_contact_name . ' — ' : '' }}{{ $worker->emergency_contact_phone }}</td>
                    </tr>
                @endif
            </table>
        </div>
        <div class="badge-footer">
            Scan QR at authorized Site Attendance Terminal. If found, please return to site office.
        </div>
    </div>
</body>
</html>

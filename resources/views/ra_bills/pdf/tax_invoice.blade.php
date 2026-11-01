<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice — {{ $raBill->invoice_number ?? $raBill->ra_label }}</title>
    <style>
        @page { margin: 22px 28px; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 11px; color: #0f172a; line-height: 1.35; }
        .header-box { border: 2px solid #0f172a; padding: 10px 14px; text-align: center; background: #f8fafc; }
        .company-title { font-size: 18px; font-weight: bold; letter-spacing: 0.5px; text-transform: uppercase; color: #0f172a; }
        .company-sub { font-size: 10px; color: #334155; margin-top: 3px; }
        .doc-banner { background: #0f172a; color: #ffffff; font-weight: bold; font-size: 13px; text-align: center; padding: 5px; letter-spacing: 1px; margin-top: 8px; }
        table.meta-grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.meta-grid td, table.meta-grid th { border: 1px solid #334155; padding: 6px 8px; vertical-align: top; }
        table.items-grid { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items-grid th { background: #e2e8f0; border: 1px solid #0f172a; padding: 6px; font-size: 10px; text-transform: uppercase; }
        table.items-grid td { border: 1px solid #334155; padding: 6px 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .words-box { border: 1px solid #0f172a; background: #f8fafc; padding: 8px 10px; margin-top: 10px; font-weight: bold; }
        .footer-grid { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .footer-grid td { border: 1px solid #334155; padding: 8px 10px; vertical-align: top; width: 50%; }
    </style>
</head>
<body>
    @include('ra_bills.pdf._tax_invoice_body', ['workOrder' => $workOrder, 'raBill' => $raBill, 'company' => $company, 'amountInWords' => $amountInWords])
</body>
</html>


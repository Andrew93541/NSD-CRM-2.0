<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tax Invoice & Abstract — {{ $raBill->invoice_number ?? $raBill->ra_label }}</title>
    <style>
        @page { margin: 18px 22px; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 10px; color: #0f172a; line-height: 1.3; }
        .header-box { border: 1.5px solid #0f172a; padding: 8px 12px; text-align: center; background: #f8fafc; }
        .company-title { font-size: 16px; font-weight: bold; text-transform: uppercase; }
        .company-sub { font-size: 9.5px; color: #334155; margin-top: 2px; }
        .doc-banner { background: #0f172a; color: #ffffff; font-weight: bold; font-size: 12px; text-align: center; padding: 4px; margin-top: 6px; }
        table.meta-grid, table.items-grid, table.abstract-grid, table.footer-grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.meta-grid td, table.items-grid td, table.abstract-grid td, table.footer-grid td { border: 1px solid #334155; padding: 5px 6px; vertical-align: top; }
        table.items-grid th, table.abstract-grid th { background: #e2e8f0; border: 1px solid #0f172a; padding: 5px; font-size: 8.5px; text-transform: uppercase; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
        .words-box { border: 1px solid #0f172a; background: #f8fafc; padding: 6px 8px; margin-top: 8px; font-weight: bold; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @include('ra_bills.pdf._tax_invoice_body', ['workOrder' => $workOrder, 'raBill' => $raBill, 'company' => $company, 'amountInWords' => $amountInWords])
    <div class="page-break"></div>
    @include('ra_bills.pdf._abstract_sheet_body', ['workOrder' => $workOrder, 'raBill' => $raBill, 'company' => $company, 'amountInWords' => $amountInWords])
</body>
</html>


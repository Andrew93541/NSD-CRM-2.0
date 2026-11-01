<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Abstract Sheet — {{ $raBill->ra_label }}</title>
    <style>
        @page { margin: 18px 22px; }
        body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 9.5px; color: #0f172a; line-height: 1.3; }
        .header-box { border: 1.5px solid #0f172a; padding: 8px 12px; background: #f8fafc; }
        .title-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 13px; text-transform: uppercase; }
        table.abstract-grid { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.abstract-grid th { background: #e2e8f0; border: 1px solid #0f172a; padding: 4px 5px; font-size: 8.5px; text-transform: uppercase; }
        table.abstract-grid td { border: 1px solid #334155; padding: 4px 5px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .font-bold { font-weight: bold; }
    </style>
</head>
<body>
    @include('ra_bills.pdf._abstract_sheet_body', ['workOrder' => $workOrder, 'raBill' => $raBill, 'company' => $company, 'amountInWords' => $amountInWords])
</body>
</html>


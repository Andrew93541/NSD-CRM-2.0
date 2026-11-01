<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\BillExport;
use App\Models\RaBill;
use App\Models\User;
use App\Models\WorkOrder;
use App\Support\IndianCurrencyWords;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RaBillExportService
{
    /**
     * Compute Indian Financial Year string (e.g. '26-27' for Oct 2026 or Feb 2027).
     */
    public static function resolveFinancialYear(?Carbon $date = null): string
    {
        $date = $date ?? now();
        $year = (int) $date->year;
        $month = (int) $date->month;

        if ($month >= 4) {
            $fyStart = $year % 100;
            $fyEnd = ($year + 1) % 100;
        } else {
            $fyStart = ($year - 1) % 100;
            $fyEnd = $year % 100;
        }

        return sprintf('%02d-%02d', $fyStart, $fyEnd);
    }

    /**
     * Atomically assign a locked financial-year invoice number ({PREFIX}/{SEQ}/{FY}) to an RA Bill.
     * Idempotent: if the RA Bill already has an invoice_number, returns it unchanged.
     */
    public function ensureInvoiceNumberAssigned(RaBill $raBill): string
    {
        if (!empty($raBill->invoice_number)) {
            return $raBill->invoice_number;
        }

        return DB::transaction(function () use ($raBill) {
            /** @var RaBill $lockedBill */
            $lockedBill = RaBill::where('id', $raBill->id)->lockForUpdate()->firstOrFail();

            if (!empty($lockedBill->invoice_number)) {
                $raBill->invoice_number = $lockedBill->invoice_number;
                return $lockedBill->invoice_number;
            }

            $company = $lockedBill->workOrder->company()->firstOrFail();
            $prefix = strtoupper(trim($company->invoice_prefix ?: 'CIVIL'));
            $invoiceDate = $lockedBill->invoice_date ?? $lockedBill->period_to ?? now();
            $fy = self::resolveFinancialYear(Carbon::parse($invoiceDate));

            $startingNumber = max(1, (int) ($company->invoice_starting_number ?? 21));

            $seqRow = DB::table('invoice_sequences')
                ->where('company_id', $company->id)
                ->where('prefix', $prefix)
                ->where('financial_year', $fy)
                ->lockForUpdate()
                ->first();

            if (!$seqRow) {
                DB::table('invoice_sequences')->insert([
                    'company_id' => $company->id,
                    'prefix' => $prefix,
                    'financial_year' => $fy,
                    'last_sequence' => $startingNumber - 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $seqRow = DB::table('invoice_sequences')
                    ->where('company_id', $company->id)
                    ->where('prefix', $prefix)
                    ->where('financial_year', $fy)
                    ->lockForUpdate()
                    ->first();
            }

            $nextSeq = max($startingNumber, ((int) $seqRow->last_sequence) + 1);

            DB::table('invoice_sequences')
                ->where('id', $seqRow->id)
                ->update([
                    'last_sequence' => $nextSeq,
                    'updated_at' => now(),
                ]);

            $invoiceNumber = sprintf('%s/%02d/%s', $prefix, $nextSeq, $fy);

            $lockedBill->update([
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $lockedBill->invoice_date ?? now()->toDateString(),
            ]);

            $raBill->fill([
                'invoice_number' => $lockedBill->invoice_number,
                'invoice_date' => $lockedBill->invoice_date,
            ]);

            return $invoiceNumber;
        });
    }

    /**
     * Generate a PDF export ('tax_invoice_pdf', 'abstract_pdf', or 'combined_pdf'),
     * store it in private storage, and record a BillExport entry.
     */
    public function exportPdf(RaBill $raBill, string $exportType, User $actor): BillExport
    {
        if (!in_array($exportType, ['tax_invoice_pdf', 'abstract_pdf', 'combined_pdf'], true)) {
            throw new InvalidArgumentException("Unsupported PDF export type: {$exportType}");
        }

        $raBill->load(['items', 'workOrder.company', 'client', 'site']);
        $workOrder = $raBill->workOrder;
        $company = $workOrder->company;

        if ($raBill->isLocked() && empty($raBill->invoice_number)) {
            $this->ensureInvoiceNumberAssigned($raBill);
            $raBill->refresh();
        }

        $amountInWords = IndianCurrencyWords::toWords((float) $raBill->net_payable);

        $viewName = match ($exportType) {
            'tax_invoice_pdf' => 'ra_bills.pdf.tax_invoice',
            'abstract_pdf' => 'ra_bills.pdf.abstract_sheet',
            'combined_pdf' => 'ra_bills.pdf.combined',
        };

        $orientation = $exportType === 'tax_invoice_pdf' ? 'portrait' : 'landscape';

        $html = view($viewName, [
            'workOrder' => $workOrder,
            'raBill' => $raBill,
            'company' => $company,
            'amountInWords' => $amountInWords,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', $orientation);
        $dompdf->render();

        $pdfBinary = $dompdf->output();

        $safeLabel = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($raBill->ra_label));
        $fileName = sprintf(
            '%s-%s-%s-%s.pdf',
            $workOrder->order_number,
            $safeLabel,
            $exportType,
            now()->format('YmdHis')
        );
        $relativePath = "companies/{$workOrder->company_id}/work_orders/{$workOrder->id}/ra_bills/{$raBill->id}/exports/{$fileName}";

        Storage::disk('local')->put($relativePath, $pdfBinary);

        $export = BillExport::create([
            'company_id' => $workOrder->company_id,
            'work_order_id' => $workOrder->id,
            'ra_bill_id' => $raBill->id,
            'export_type' => $exportType,
            'file_name' => $fileName,
            'file_path' => $relativePath,
            'invoice_number' => $raBill->invoice_number,
            'exported_by' => $actor->id,
        ]);

        AuditLog::record(
            "ra_bill.export.{$exportType}",
            $raBill,
            [],
            [
                'export_id' => $export->id,
                'file_name' => $fileName,
                'invoice_number' => $raBill->invoice_number,
            ],
            "Exported {$exportType} for {$raBill->ra_label}"
        );

        return $export;
    }

    /**
     * Generate a 2-sheet Excel (.xlsx) workbook ('INV' and 'ABSTRACT') with live Excel formulas,
     * store it in private storage, and record a BillExport entry.
     */
    public function exportXlsx(RaBill $raBill, User $actor): BillExport
    {
        $raBill->load(['items', 'workOrder.company', 'client', 'site']);
        $workOrder = $raBill->workOrder;
        $company = $workOrder->company;

        if ($raBill->isLocked() && empty($raBill->invoice_number)) {
            $this->ensureInvoiceNumberAssigned($raBill);
            $raBill->refresh();
        }

        $spreadsheet = new Spreadsheet();

        // Sheet 1: INV (Tax Invoice)
        $invSheet = $spreadsheet->getActiveSheet();
        $invSheet->setTitle('INV');

        // Sheet 2: ABSTRACT (Measurement & Abstract Sheet)
        $abstractSheet = $spreadsheet->createSheet();
        $abstractSheet->setTitle('ABSTRACT');

        // Populate ABSTRACT sheet first so we know exact summary row coordinates for INV cross-sheet formulas
        $abstractSheet->setCellValue('A1', strtoupper($company->name ?? 'NSD EARTH MOVERS & CONSTRUCTION') . ' — ABSTRACT OF ' . $raBill->ra_label);
        $abstractSheet->setCellValue('A2', 'Client: ' . ($raBill->client?->name ?? 'JSW STEEL LIMITED'));
        $abstractSheet->setCellValue('D2', 'PO / Service Order No: ' . ($workOrder->po_number ?? $workOrder->order_number));
        $abstractSheet->setCellValue('H2', 'Invoice No: ' . ($raBill->invoice_number ?? 'DRAFT'));
        $abstractSheet->setCellValue('L2', 'Period: ' . $raBill->period_from->format('d.m.Y') . ' to ' . $raBill->period_to->format('d.m.Y'));

        $headers = [
            'A5' => 'Sr. No.',
            'B5' => 'Service Code',
            'C5' => 'SAC Code',
            'D5' => 'Item Description',
            'E5' => 'UOM',
            'F5' => 'Order Qty',
            'G5' => 'Rate (Rs.)',
            'H5' => 'Previous Qty',
            'I5' => 'Current Billable Qty',
            'J5' => 'Cumulative Qty',
            'K5' => 'Previous Amount',
            'L5' => 'Current Amount',
            'M5' => 'Cumulative Amount',
            'N5' => 'Balance Qty',
            'O5' => 'Hold Qty',
            'P5' => 'Excess Unbilled Qty',
        ];
        foreach ($headers as $cell => $label) {
            $abstractSheet->setCellValue($cell, $label);
        }

        $firstDataRow = 6;
        $row = $firstDataRow;
        foreach ($raBill->items as $item) {
            // Preserve '1.10' as explicit string in Excel
            $abstractSheet->setCellValueExplicit("A{$row}", (string) $item->sr_no, DataType::TYPE_STRING);
            $abstractSheet->setCellValueExplicit("B{$row}", (string) ($item->service_code ?? ''), DataType::TYPE_STRING);
            $abstractSheet->setCellValueExplicit("C{$row}", (string) ($item->sac_code ?? ''), DataType::TYPE_STRING);
            $abstractSheet->setCellValue("D{$row}", $item->description);
            $abstractSheet->setCellValue("E{$row}", $item->unit);
            $abstractSheet->setCellValue("F{$row}", (float) $item->ordered_quantity);
            $abstractSheet->setCellValue("G{$row}", (float) $item->rate);
            $abstractSheet->setCellValue("H{$row}", (float) $item->previous_quantity);
            $abstractSheet->setCellValue("I{$row}", (float) $item->current_billable_quantity);

            // Live Excel formulas on ABSTRACT sheet
            $abstractSheet->setCellValue("J{$row}", "=H{$row}+I{$row}");
            $abstractSheet->setCellValue("K{$row}", "=ROUND(H{$row}*G{$row},2)");
            $abstractSheet->setCellValue("L{$row}", "=ROUND(I{$row}*G{$row},2)");
            $abstractSheet->setCellValue("M{$row}", "=ROUND(J{$row}*G{$row},2)");
            $abstractSheet->setCellValue("N{$row}", "=F{$row}-J{$row}");
            $abstractSheet->setCellValue("O{$row}", (float) $item->hold_quantity);
            $abstractSheet->setCellValue("P{$row}", (float) $item->excess_quantity);

            $row++;
        }

        $lastDataRow = max($firstDataRow, $row - 1);
        $totalBasicRow = $lastDataRow + 1;
        $cgstRow = $totalBasicRow + 1;
        $sgstRow = $totalBasicRow + 2;
        $grossRow = $totalBasicRow + 3;

        $cgstDecimal = round(((float) $raBill->cgst_rate) / 100, 4);
        $sgstDecimal = round(((float) $raBill->sgst_rate) / 100, 4);

        $abstractSheet->setCellValue("D{$totalBasicRow}", 'TOTAL BASIC VALUE (A)');
        $abstractSheet->setCellValue("K{$totalBasicRow}", "=SUM(K{$firstDataRow}:K{$lastDataRow})");
        $abstractSheet->setCellValue("L{$totalBasicRow}", "=SUM(L{$firstDataRow}:L{$lastDataRow})");
        $abstractSheet->setCellValue("M{$totalBasicRow}", "=SUM(M{$firstDataRow}:M{$lastDataRow})");

        $abstractSheet->setCellValue("D{$cgstRow}", "ADD: CGST @ {$raBill->cgst_rate}%");
        $abstractSheet->setCellValue("L{$cgstRow}", "=ROUND(L{$totalBasicRow}*{$cgstDecimal},2)");

        $abstractSheet->setCellValue("D{$sgstRow}", "ADD: SGST @ {$raBill->sgst_rate}%");
        $abstractSheet->setCellValue("L{$sgstRow}", "=ROUND(L{$totalBasicRow}*{$sgstDecimal},2)");

        $abstractSheet->setCellValue("D{$grossRow}", 'TOTAL GROSS VALUE (A + CGST + SGST)');
        $abstractSheet->setCellValue("L{$grossRow}", "=L{$totalBasicRow}+L{$cgstRow}+L{$sgstRow}");

        // Populate Sheet 1: INV (Tax Invoice) with cross-sheet formula referencing ABSTRACT!L{$totalBasicRow}
        $invSheet->setCellValue('A1', strtoupper($company->name ?? 'NSD EARTH MOVERS & CONSTRUCTION'));
        $invSheet->setCellValue('A2', $company->registered_address ?? '');
        $invSheet->setCellValue('A3', 'GSTIN: ' . ($company->gstin ?? '') . ' | PAN: ' . ($company->pan ?? '') . ' | Vendor Code: ' . ($company->vendor_code ?? ''));
        $invSheet->setCellValue('A4', 'TAX INVOICE');

        $invSheet->setCellValue('A6', 'Billed To:');
        $invSheet->setCellValue('B6', $raBill->client?->name ?? 'JSW STEEL LIMITED');
        $invSheet->setCellValue('A7', 'Client Address:');
        $invSheet->setCellValue('B7', $raBill->client?->address ?? '');
        $invSheet->setCellValue('A8', 'Client GSTIN:');
        $invSheet->setCellValueExplicit('B8', (string) ($raBill->client?->gstin ?? ''), DataType::TYPE_STRING);

        $invSheet->setCellValue('E6', 'INVOICE NO:');
        $invSheet->setCellValueExplicit('F6', (string) ($raBill->invoice_number ?? 'DRAFT'), DataType::TYPE_STRING);
        $invSheet->setCellValue('E7', 'INVOICE DATE:');
        $invSheet->setCellValue('F7', ($raBill->invoice_date ?? now())->format('d.m.Y'));
        $invSheet->setCellValue('E8', 'RA BILL NO:');
        $invSheet->setCellValue('F8', $raBill->ra_label);
        $invSheet->setCellValue('E9', 'PO / SERVICE ORDER NO:');
        $invSheet->setCellValueExplicit('F9', (string) ($workOrder->po_number ?? $workOrder->order_number), DataType::TYPE_STRING);
        $invSheet->setCellValue('E10', 'JOB LOCATION:');
        $invSheet->setCellValue('F10', $workOrder->job_location ?? $workOrder->site?->name ?? '');
        $invSheet->setCellValue('E11', 'BILLING PERIOD:');
        $invSheet->setCellValue('F11', $raBill->period_from->format('d.m.Y') . ' to ' . $raBill->period_to->format('d.m.Y'));

        $invSheet->setCellValue('A13', 'Sr. No.');
        $invSheet->setCellValue('B13', 'Particulars / Description of Works');
        $invSheet->setCellValue('E13', 'SAC Code');
        $invSheet->setCellValue('G13', 'Amount (INR)');

        $invSheet->setCellValue('A14', 1);
        $invSheet->setCellValue('B14', $workOrder->title . ' — As per attached Abstract Sheet (' . $raBill->ra_label . ')');
        $invSheet->setCellValueExplicit('E14', (string) ($raBill->items->first()?->sac_code ?? '995419'), DataType::TYPE_STRING);
        // Cross-sheet formula referencing ABSTRACT Basic Total
        $invSheet->setCellValue('G14', "=ABSTRACT!L{$totalBasicRow}");

        $invSheet->setCellValue('B15', "Add: CGST @ {$raBill->cgst_rate}%");
        $invSheet->setCellValue('G15', "=ROUND(G14*{$cgstDecimal},2)");

        $invSheet->setCellValue('B16', "Add: SGST @ {$raBill->sgst_rate}%");
        $invSheet->setCellValue('G16', "=ROUND(G14*{$sgstDecimal},2)");

        $invSheet->setCellValue('B17', 'Total Gross Invoice Amount (A + CGST + SGST)');
        $invSheet->setCellValue('G17', '=G14+G15+G16');

        $totalDeductions = round((float) $raBill->retention_deduction + (float) $raBill->advance_recovery + (float) $raBill->other_deductions, 2);
        $invSheet->setCellValue('B18', 'Less: Retention / Advance / Other Deductions');
        $invSheet->setCellValue('G18', $totalDeductions);

        $invSheet->setCellValue('B19', 'Net Payable Amount (' . strtoupper($raBill->rounding_mode) . ')');
        $netFormula = $raBill->rounding_mode === 'rupee' ? '=ROUND(G17-G18,0)' : '=ROUND(G17-G18,2)';
        $invSheet->setCellValue('G19', $netFormula);

        $invSheet->setCellValue('A21', 'Amount in Words:');
        $invSheet->setCellValue('B21', IndianCurrencyWords::toWords((float) $raBill->net_payable));

        $invSheet->setCellValue('A23', 'Bank Name:');
        $invSheet->setCellValue('B23', $company->bank_name ?? '');
        $invSheet->setCellValue('A24', 'Account No:');
        $invSheet->setCellValueExplicit('B24', (string) ($company->bank_account_no ?? ''), DataType::TYPE_STRING);
        $invSheet->setCellValue('A25', 'IFSC Code:');
        $invSheet->setCellValueExplicit('B25', (string) ($company->bank_ifsc ?? ''), DataType::TYPE_STRING);

        $safeLabel = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower($raBill->ra_label));
        $fileName = sprintf(
            '%s-%s-ra-bill-%s.xlsx',
            $workOrder->order_number,
            $safeLabel,
            now()->format('YmdHis')
        );
        $relativePath = "companies/{$workOrder->company_id}/work_orders/{$workOrder->id}/ra_bills/{$raBill->id}/exports/{$fileName}";

        $tempPath = tempnam(sys_get_temp_dir(), 'nsd_rabill_') . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        // Disable pre-calculate formulas on save only if needed, or leave true so Excel has both formula and cached value
        $writer->setPreCalculateFormulas(true);
        $writer->save($tempPath);

        Storage::disk('local')->put($relativePath, file_get_contents($tempPath));
        @unlink($tempPath);

        $export = BillExport::create([
            'company_id' => $workOrder->company_id,
            'work_order_id' => $workOrder->id,
            'ra_bill_id' => $raBill->id,
            'export_type' => 'ra_bill_xlsx',
            'file_name' => $fileName,
            'file_path' => $relativePath,
            'invoice_number' => $raBill->invoice_number,
            'exported_by' => $actor->id,
        ]);

        AuditLog::record(
            'ra_bill.export.ra_bill_xlsx',
            $raBill,
            [],
            [
                'export_id' => $export->id,
                'file_name' => $fileName,
                'invoice_number' => $raBill->invoice_number,
            ],
            "Exported live-formula Excel (.xlsx) for {$raBill->ra_label}"
        );

        return $export;
    }

    /**
     * Generate a JSW Service Order / Subcontractor Work Order PDF ('work_order_pdf'),
     * store it in private storage, and record a BillExport entry.
     */
    public function exportWorkOrderPdf(WorkOrder $workOrder, User $actor): BillExport
    {
        $workOrder->load(['company', 'client', 'project', 'site', 'creator', 'siteManager', 'seniorManager', 'items']);
        $company = $workOrder->company;

        $baseVal = (float) ($workOrder->base_order_value ?: $workOrder->estimated_amount);
        $grossVal = round($baseVal + $workOrder->cgstAmount() + $workOrder->sgstAmount(), 2);
        $amountInWords = IndianCurrencyWords::toWords($grossVal);

        $html = view('work_orders.pdf.service_order', [
            'workOrder' => $workOrder,
            'company' => $company,
            'amountInWords' => $amountInWords,
        ])->render();

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfBinary = $dompdf->output();

        $rev = (int) ($workOrder->current_revision ?? 0);
        $fileName = sprintf(
            '%s-rev-%d-service-order-%s.pdf',
            $workOrder->order_number,
            $rev,
            now()->format('YmdHis')
        );
        $relativePath = "companies/{$workOrder->company_id}/work_orders/{$workOrder->id}/exports/{$fileName}";

        Storage::disk('local')->put($relativePath, $pdfBinary);

        $export = BillExport::create([
            'company_id' => $workOrder->company_id,
            'work_order_id' => $workOrder->id,
            'ra_bill_id' => null,
            'export_type' => 'work_order_pdf',
            'file_name' => $fileName,
            'file_path' => $relativePath,
            'invoice_number' => null,
            'exported_by' => $actor->id,
        ]);

        AuditLog::record(
            'work_order.export.work_order_pdf',
            $workOrder,
            [],
            [
                'export_id' => $export->id,
                'file_name' => $fileName,
                'revision' => $rev,
            ],
            "Exported JSW Service Order PDF for {$workOrder->order_number} (Rev {$rev})"
        );

        return $export;
    }
}

<?php

namespace App\Services\Operations;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\IOFactory;

class JswWorkOrderPdfReaderService
{
    /**
     * Parse a JSW Service Order PDF from local disk path without any external API calls.
     */
    public function parsePdfFile(string $absolutePath, string $originalFilename = ''): array
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return $this->unreadableResult('The uploaded PDF file could not be read from storage.');
        }

        $raw = (string) file_get_contents($absolutePath);
        if (!str_starts_with($raw, '%PDF-')) {
            return $this->unreadableResult('The uploaded file is not a valid PDF document.');
        }

        $pages = $this->extractPositionedLinesPerPage($raw);
        if (empty($pages)) {
            return $this->unreadableResult(
                'Scanned or unreadable PDF detected. No machine-readable text layer was found. Please enter the work order manually or import the BOQ lines via CSV/Excel.'
            );
        }

        $isDraftPo = $this->detectDraftWatermarkOrText($raw, $pages, $originalFilename);
        $parsed = $this->parseJswStructure($pages, $isDraftPo);

        if (empty($parsed['items']) || empty($parsed['header']['po_number'])) {
            return $this->unreadableResult(
                'This PDF does not match the JSW Steel Service Order layout or appears to be a scanned image. You can enter the details manually or import BOQ lines from CSV/Excel below.',
                $parsed
            );
        }

        $validationErrors = $this->validateExtractedPayload($parsed);

        return [
            'is_scanned_or_unreadable' => false,
            'is_draft_po' => $isDraftPo,
            'payload' => $parsed,
            'validation_errors' => $validationErrors,
        ];
    }

    /**
     * Validate arithmetic consistency of extracted or reviewed payload.
     *
     * Rules:
     * 1. quantity * unit_price == line_total for each line
     * 2. sum(line_total) == base_value
     * 3. CGST + SGST == total_taxes
     * 4. base_value + total_taxes == total_po_value
     */
    public function validateExtractedPayload(array $payload): array
    {
        $errors = [];
        $items = $payload['items'] ?? [];
        $totals = $payload['totals'] ?? [];

        if (empty($items)) {
            $errors['items'] = 'At least one work order line item is required.';
            return $errors;
        }

        $sumLineTotals = 0.0;

        foreach ($items as $idx => $item) {
            $srNo = (string) ($item['sr_no'] ?? ($idx + 1));
            $qty = round((float) ($item['ordered_qty'] ?? 0), 3);
            $rate = round((float) ($item['rate'] ?? 0), 4);
            $lineTotal = round((float) ($item['line_total'] ?? 0), 2);
            $expectedLineTotal = round($qty * $rate, 2);

            if (abs($expectedLineTotal - $lineTotal) > 0.05) {
                $errors["line_{$srNo}"] = sprintf(
                    'Line %s mismatch: Qty (%s) × Rate (%s) = %s, but Line Total is %s.',
                    $srNo,
                    number_format($qty, 3, '.', ''),
                    number_format($rate, 2, '.', ''),
                    number_format($expectedLineTotal, 2, '.', ''),
                    number_format($lineTotal, 2, '.', '')
                );
            }

            $sumLineTotals = round($sumLineTotals + $lineTotal, 2);
        }

        $baseValue = round((float) ($totals['base_value'] ?? 0), 2);
        $cgstAmount = round((float) ($totals['cgst_amount'] ?? 0), 2);
        $sgstAmount = round((float) ($totals['sgst_amount'] ?? 0), 2);
        $totalTaxes = round((float) ($totals['total_taxes'] ?? ($cgstAmount + $sgstAmount)), 2);
        $totalPoValue = round((float) ($totals['total_po_value'] ?? 0), 2);

        if (abs($sumLineTotals - $baseValue) > 0.05) {
            $errors['base_value'] = sprintf(
                'Sum of line totals (%s) does not equal Total Base Value (%s). Difference: %s.',
                number_format($sumLineTotals, 2, '.', ','),
                number_format($baseValue, 2, '.', ','),
                number_format(abs($sumLineTotals - $baseValue), 2, '.', ',')
            );
        }

        $expectedTaxes = round($cgstAmount + $sgstAmount, 2);
        if (abs($expectedTaxes - $totalTaxes) > 0.05) {
            $errors['total_taxes'] = sprintf(
                'CGST (%s) + SGST (%s) = %s does not equal Total Taxes (%s).',
                number_format($cgstAmount, 2, '.', ','),
                number_format($sgstAmount, 2, '.', ','),
                number_format($expectedTaxes, 2, '.', ','),
                number_format($totalTaxes, 2, '.', ',')
            );
        }

        $expectedPoValue = round($baseValue + $totalTaxes, 2);
        if (abs($expectedPoValue - $totalPoValue) > 0.05) {
            $errors['total_po_value'] = sprintf(
                'Base Value (%s) + Total Taxes (%s) = %s does not equal Total PO Value (%s).',
                number_format($baseValue, 2, '.', ','),
                number_format($totalTaxes, 2, '.', ','),
                number_format($expectedPoValue, 2, '.', ','),
                number_format($totalPoValue, 2, '.', ',')
            );
        }

        return $errors;
    }

    /**
     * Parse BOQ line items from an uploaded CSV or Excel file (fallback for scanned PDFs).
     */
    public function parseSpreadsheetLines(string $absolutePath): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, false);

        $items = [];
        foreach ($rows as $rowIndex => $row) {
            $firstCol = trim((string) ($row[0] ?? ''));
            if ($firstCol === '' || stripos($firstCol, 'sr') === 0 || stripos($firstCol, 'item') === 0) {
                continue;
            }

            $srNo = $firstCol;
            $serviceCode = trim((string) ($row[1] ?? ''));
            $description = trim((string) ($row[2] ?? ''));
            $spec = trim((string) ($row[3] ?? ''));
            $unit = strtoupper(trim((string) ($row[4] ?? 'NOS')));
            $qty = round((float) str_replace(',', '', (string) ($row[5] ?? 0)), 3);
            $rate = round((float) str_replace(',', '', (string) ($row[6] ?? 0)), 4);
            $lineTotalRaw = str_replace(',', '', (string) ($row[7] ?? ''));
            $lineTotal = $lineTotalRaw !== '' ? round((float) $lineTotalRaw, 2) : round($qty * $rate, 2);

            if ($description === '' && $serviceCode === '') {
                continue;
            }

            $items[] = [
                'sr_no' => $srNo,
                'service_code' => $serviceCode,
                'sac_code' => '9954',
                'description' => $description ?: $serviceCode,
                'specification_text' => $spec,
                'unit' => $unit ?: 'NOS',
                'ordered_qty' => $qty,
                'rate' => round($rate, 2),
                'line_total' => $lineTotal,
            ];
        }

        return $items;
    }

    /**
     * Mask any bank account number so only the last 4 digits are visible.
     */
    public static function maskBankAccount(?string $rawAccount): ?string
    {
        if ($rawAccount === null) {
            return null;
        }

        $clean = preg_replace('/\s+/', '', trim($rawAccount));
        if ($clean === '') {
            return null;
        }

        $len = strlen($clean);
        if ($len <= 4) {
            return str_repeat('X', $len);
        }

        return str_repeat('X', $len - 4) . substr($clean, -4);
    }

    protected function unreadableResult(string $message, array $partialPayload = []): array
    {
        $defaultPayload = array_replace_recursive([
            'header' => [
                'po_number' => '',
                'client_reference' => '',
                'po_date' => now()->toDateString(),
                'order_type_code' => 'PRJ-DOM',
                'service_type' => 'JBN',
                'completion_date' => null,
                'effective_date' => null,
                'contract_tenure' => '12 Months',
                'retention_percent' => 5.0,
                'defect_liability_months' => 12,
                'liquidated_damages' => 'NA',
                'price_basis' => '',
                'payment_terms' => '',
                'subject' => '',
                'user_department_person' => '',
                'buyer_name' => '',
                'client_name' => 'JSW Steel Limited',
                'client_address' => '',
                'client_gstin' => '',
            ],
            'vendor' => [
                'vendor_code' => '',
                'name' => '',
                'address' => '',
                'email' => '',
                'pan' => '',
                'gst' => '',
                'phone' => '',
                'bank_account_masked' => null,
            ],
            'items' => [],
            'totals' => [
                'base_value' => 0.0,
                'cgst_rate' => 9.0,
                'cgst_amount' => 0.0,
                'sgst_rate' => 9.0,
                'sgst_amount' => 0.0,
                'total_taxes' => 0.0,
                'total_po_value' => 0.0,
                'amount_in_words' => '',
            ],
            'general_terms_text' => '',
            'warnings' => [$message],
        ], $partialPayload);

        return [
            'is_scanned_or_unreadable' => true,
            'is_draft_po' => false,
            'payload' => $defaultPayload,
            'validation_errors' => ['unreadable' => $message],
        ];
    }

    /**
     * Extract positioned text rows from each PDF page stream.
     *
     * @return array<int, array<int, array{y: float, text: string, segments: array<int, array{x: float, text: string}>}>>
     */
    protected function extractPositionedLinesPerPage(string $rawPdf): array
    {
        $pages = [];
        if (!preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $rawPdf, $matches)) {
            return [];
        }

        foreach ($matches[1] as $streamData) {
            $decodedStream = $this->decodeStreamIfNeeded($streamData);
            if ($decodedStream === null || !str_contains($decodedStream, 'BT') || !str_contains($decodedStream, 'ET')) {
                continue;
            }

            $positioned = [];

            // Match SAP RSTXPDF3 hex strings: BT <x> <y> Td <hex>Tj ET
            if (preg_match_all('/BT\s+([\d\.\-]+)\s+([\d\.\-]+)\s+Td\s*<([0-9A-Fa-f]+)>\s*Tj\s*ET/s', $decodedStream, $hexMatches, PREG_SET_ORDER)) {
                foreach ($hexMatches as $m) {
                    $x = (float) $m[1];
                    $y = (float) $m[2];
                    $txt = @hex2bin($m[3]);
                    if ($txt !== false && $txt !== '') {
                        $positioned[] = [
                            'x' => $x,
                            'y' => $y,
                            'text' => mb_convert_encoding($txt, 'UTF-8', 'ISO-8859-1'),
                        ];
                    }
                }
            }

            // Also support standard literal parenthesized strings: BT <x> <y> Td (text)Tj ET
            if (preg_match_all('/BT\s+([\d\.\-]+)\s+([\d\.\-]+)\s+Td\s*\((.*?)\)\s*Tj\s*ET/s', $decodedStream, $litMatches, PREG_SET_ORDER)) {
                foreach ($litMatches as $m) {
                    $x = (float) $m[1];
                    $y = (float) $m[2];
                    $txt = stripcslashes($m[3]);
                    if (trim($txt) !== '') {
                        $positioned[] = [
                            'x' => $x,
                            'y' => $y,
                            'text' => $txt,
                        ];
                    }
                }
            }

            if (empty($positioned)) {
                continue;
            }

            // Sort top-to-bottom (y descending), then left-to-right (x ascending)
            usort($positioned, function (array $a, array $b): int {
                if (abs($a['y'] - $b['y']) > 2.5) {
                    return $b['y'] <=> $a['y'];
                }

                return $a['x'] <=> $b['x'];
            });

            $rows = [];
            foreach ($positioned as $seg) {
                $placed = false;
                foreach ($rows as &$row) {
                    if (abs($row['y'] - $seg['y']) <= 2.5) {
                        $row['segments'][] = ['x' => $seg['x'], 'text' => $seg['text']];
                        $placed = true;
                        break;
                    }
                }
                unset($row);

                if (!$placed) {
                    $rows[] = [
                        'y' => $seg['y'],
                        'segments' => [
                            ['x' => $seg['x'], 'text' => $seg['text']],
                        ],
                    ];
                }
            }

            foreach ($rows as &$row) {
                usort($row['segments'], fn (array $a, array $b) => $a['x'] <=> $b['x']);
                $row['text'] = $this->joinRowSegments($row['segments']);
            }
            unset($row);

            $pages[] = $rows;
        }

        return $pages;
    }

    protected function joinRowSegments(array $segments): string
    {
        $out = '';
        $prevEnd = null;
        foreach ($segments as $seg) {
            $txt = $seg['text'];
            if ($out === '') {
                $out = $txt;
            } else {
                $gap = $prevEnd !== null ? ($seg['x'] - $prevEnd) : 0.0;
                if ($gap > 18.0 && !str_ends_with($out, ' ') && !str_starts_with($txt, ' ')) {
                    $out .= ' ' . $txt;
                } else {
                    $out .= $txt;
                }
            }
            $prevEnd = $seg['x'] + (strlen($txt) * 4.5);
        }

        return trim(preg_replace('/\s+/', ' ', $out));
    }

    protected function decodeStreamIfNeeded(string $streamData): ?string
    {
        if (str_contains($streamData, 'BT') && str_contains($streamData, 'ET')) {
            return $streamData;
        }

        $inflated = @gzuncompress($streamData);
        if ($inflated !== false && str_contains($inflated, 'BT')) {
            return $inflated;
        }

        $inflatedRaw = @gzinflate($streamData);
        if ($inflatedRaw !== false && str_contains($inflatedRaw, 'BT')) {
            return $inflatedRaw;
        }

        return null;
    }

    protected function detectDraftWatermarkOrText(string $rawPdf, array $pages, string $originalFilename): bool
    {
        if (stripos($originalFilename, 'draft') !== false) {
            return true;
        }

        // JSW SAP RSTXPDF3 embeds the diagonal DRAFT PO image at 475.20 x 464.65 (60, 189)
        if (str_contains($rawPdf, '475.20 0 0 464.65 60 189 cm')) {
            return true;
        }

        foreach ($pages as $rows) {
            foreach ($rows as $row) {
                if (preg_match('/\bDRAFT\s*(PO|ORDER)?\b/i', $row['text'])) {
                    return true;
                }
            }
        }

        return false;
    }

    protected function isRepeatingHeaderOrFooterRow(int $pageIdx, array $row): bool
    {
        $y = $row['y'];
        $text = trim($row['text']);

        // Footer zone on every page (y <= 195)
        if ($y <= 195.0) {
            return true;
        }

        if (preg_match('/^(Thanking You|Yours sincerely|FOR JSW STEEL LIMITED|AUTHORISED SIGNATORY|Plant GST No|JSW Centre,\s*Bandra Kurla)/i', $text)) {
            return true;
        }

        // Repeating top header on Page 2+
        if ($pageIdx > 0 && $y >= 755.0) {
            if (
                str_contains($text, 'SERVICE SHORT DESCRIPTION')
                || $text === 'NO.'
                || preg_match('/^[A-Z0-9\-\/]+\/\d{10}$/', $text)
            ) {
                return true;
            }
        }

        return false;
    }

    protected function parseJswStructure(array $pages, bool $isDraftPo): array
    {
        $header = [
            'po_number' => '',
            'client_reference' => '',
            'po_date' => null,
            'order_type_code' => '',
            'service_type' => '',
            'completion_date' => null,
            'effective_date' => null,
            'contract_tenure' => '12 Months',
            'retention_percent' => 5.0,
            'defect_liability_months' => 12,
            'liquidated_damages' => 'NA',
            'price_basis' => '',
            'payment_terms' => '',
            'subject' => '',
            'order_short_description' => '',
            'user_department_person' => '',
            'buyer_name' => '',
            'client_name' => 'JSW Steel Limited',
            'client_address' => '',
            'client_gstin' => '',
        ];

        $vendor = [
            'vendor_code' => '',
            'name' => '',
            'address' => '',
            'email' => '',
            'pan' => '',
            'gst' => '',
            'phone' => '',
            'bank_account_masked' => null,
        ];

        $totals = [
            'base_value' => 0.0,
            'cgst_rate' => 9.0,
            'cgst_amount' => 0.0,
            'sgst_rate' => 9.0,
            'sgst_amount' => 0.0,
            'total_taxes' => 0.0,
            'total_po_value' => 0.0,
            'amount_in_words' => '',
        ];

        // 1. Parse Page 1 Header & Vendor Block & Plant GST from footer
        if (!empty($pages[0])) {
            $plantLines = [];
            $vendorAddressLines = [];

            foreach ($pages[0] as $row) {
                $y = $row['y'];
                $text = $row['text'];

                if (preg_match('/Plant GST No\s*:\s*([0-9A-Z]{15})/i', $text, $m)) {
                    $header['client_gstin'] = $m[1];
                }

                // Split page 1 top region into left (Vendor box x < 340) and right (Plant/PO box x >= 340)
                if ($y >= 605.0 && $y <= 792.0) {
                    $leftSegs = array_values(array_filter($row['segments'], fn ($s) => $s['x'] < 340.0));
                    $rightSegs = array_values(array_filter($row['segments'], fn ($s) => $s['x'] >= 340.0));

                    $leftText = $this->joinRowSegments($leftSegs);
                    $rightText = $this->joinRowSegments($rightSegs);

                    if ($rightText !== '') {
                        if ($y >= 725.0 && !str_starts_with($rightText, 'Tel No') && !str_starts_with($rightText, 'Fax No')) {
                            $plantLines[] = $rightText;
                        }
                        if (preg_match('/P\.O\.No\.?\s*:\s*([A-Z0-9\-\/]+)/i', str_replace(' ', '', $rightText), $m)) {
                            $header['client_reference'] = $m[1];
                            if (preg_match('/(\d{8,12})$/', $m[1], $numMatch)) {
                                $header['po_number'] = $numMatch[1];
                            } else {
                                $header['po_number'] = $m[1];
                            }
                        }
                        if (preg_match('/P\.O\.Date\s*:\s*(\d{2}\.\d{2}\.\d{4})/i', $rightText, $m)) {
                            $header['po_date'] = $this->parseDotDate($m[1]);
                        }
                        if (preg_match('/Type\s*:\s*([A-Z0-9\-]+)/i', $rightText, $m)) {
                            $header['order_type_code'] = trim($m[1]);
                        }
                        if (preg_match('/Service\s+type\s*:\s*([A-Z0-9\-]+)/i', $rightText, $m)) {
                            $header['service_type'] = trim($m[1]);
                        }
                    }

                    if ($leftText !== '') {
                        if (preg_match('/Vendor Code\s*:\s*([0-9A-Za-z]+)/i', $leftText, $m)) {
                            $vendor['vendor_code'] = trim($m[1]);
                        } elseif (preg_match('/^Name\s*:\s*(.+)$/i', $leftText, $m)) {
                            $vendor['name'] = trim($m[1]);
                        } elseif (preg_match('/^Address\s*:\s*(.+)$/i', $leftText, $m)) {
                            $vendorAddressLines[] = trim($m[1]);
                        } elseif ($y >= 715.0 && $y <= 725.0 && !str_contains($leftText, ':')) {
                            $vendorAddressLines[] = trim($leftText);
                        } elseif (preg_match('/E-mail\s*:\s*([^\s]+)/i', $leftText, $m)) {
                            $vendor['email'] = trim($m[1]);
                        } elseif (preg_match('/PAN NO\s*:\s*([A-Z0-9]{10})/i', $leftText, $m)) {
                            $vendor['pan'] = trim($m[1]);
                        } elseif (preg_match('/GST No\.?\s*:\s*([0-9A-Z]{15})/i', $leftText, $m)) {
                            $vendor['gst'] = trim($m[1]);
                        } elseif (preg_match('/Bank Account\s*:\s*([0-9A-Za-z]+)/i', $leftText, $m)) {
                            // Mask immediately; never store raw bank account number
                            $vendor['bank_account_masked'] = self::maskBankAccount($m[1]);
                        } elseif (preg_match('/Ph\.No\s*:?\s*(.+)$/i', $leftText, $m)) {
                            $vendor['phone'] = trim(preg_replace('/\s+/', ' ', $m[1]));
                        }
                    }
                }

                // Header line of the order (SR. NO. = 1, e.g. y ~ 473.8)
                if ($y >= 465.0 && $y <= 482.0) {
                    $segs = $row['segments'];
                    $CompletionCandidate = null;
                    $descParts = [];
                    foreach ($segs as $seg) {
                        if ($seg['x'] >= 450.0 && preg_match('/(\d{2}\.\d{2}\.\d{4})/', $seg['text'], $cm)) {
                            $CompletionCandidate = $this->parseDotDate($cm[1]);
                        } elseif ($seg['x'] >= 25.0 && $seg['x'] < 265.0) {
                            $descParts[] = trim($seg['text']);
                        }
                    }
                    if ($CompletionCandidate) {
                        $header['completion_date'] = $CompletionCandidate;
                    }
                    if (!empty($descParts)) {
                        $header['order_short_description'] = implode(' ', $descParts);
                    }
                }
            }

            $header['client_address'] = implode(', ', array_filter($plantLines));
            $vendor['address'] = implode(', ', array_filter($vendorAddressLines));
        }

        // 2. Walk across all pages to parse Service Lines (1.1 .. 1.N), Totals, Note to Supplier, and Terms
        $items = [];
        $currentItemIndex = null;
        $inTotalsOrAfter = false;
        $inGeneralTerms = false;
        $generalTermsLines = [];

        foreach ($pages as $pageIdx => $rows) {
            foreach ($rows as $row) {
                if ($this->isRepeatingHeaderOrFooterRow($pageIdx, $row)) {
                    continue;
                }

                $y = $row['y'];
                $text = $row['text'];

                // Skip Page 1 preamble above the service table (y > 430 on page 0)
                if ($pageIdx === 0 && $y > 430.0) {
                    continue;
                }

                // Check if we reached Totals block
                if (str_starts_with($text, 'Total Duties/Taxes') || str_starts_with($text, 'Total Base Value:')) {
                    $inTotalsOrAfter = true;
                }

                if (!$inTotalsOrAfter) {
                    $itemRow = $this->tryParseServiceItemHeaderRow($row);
                    if ($itemRow !== null) {
                        $items[] = $itemRow;
                        $currentItemIndex = count($items) - 1;
                        continue;
                    }

                    if ($currentItemIndex !== null) {
                        // First line immediately below the item row at x ~ 58-67 is the short service description
                        $firstSegX = $row['segments'][0]['x'] ?? 0.0;
                        if ($items[$currentItemIndex]['description'] === '' && $firstSegX >= 50.0 && $firstSegX <= 80.0) {
                            $items[$currentItemIndex]['description'] = $text;
                        } else {
                            $items[$currentItemIndex]['spec_lines'][] = $text;
                        }
                    }
                    continue;
                }

                // Parse Totals, Note to Supplier, and Terms
                if (preg_match('/CGST\s*@\s*([\d\.]+)%\s*-\s*([\d,]+\.\d{2})/i', $text, $m)) {
                    $totals['cgst_rate'] = (float) $m[1];
                    $totals['cgst_amount'] = (float) str_replace(',', '', $m[2]);
                }
                if (preg_match('/SGST\s*@\s*([\d\.]+)%\s*-\s*([\d,]+\.\d{2})/i', $text, $m)) {
                    $totals['sgst_rate'] = (float) $m[1];
                    $totals['sgst_amount'] = (float) str_replace(',', '', $m[2]);
                }
                if (preg_match('/Total Base Value\s*:\s*([\d,]+\.\d{2})/i', $text, $m)) {
                    $totals['base_value'] = (float) str_replace(',', '', $m[1]);
                }
                if (preg_match('/Total Taxes\s*\/\s*Charges\s*:\s*([\d,]+\.\d{2})/i', $text, $m)) {
                    $totals['total_taxes'] = (float) str_replace(',', '', $m[1]);
                }
                if (preg_match('/Total PO Value\s*:\s*([\d,]+\.\d{2})/i', $text, $m)) {
                    $totals['total_po_value'] = (float) str_replace(',', '', $m[1]);
                }
                if (preg_match('/Total Value \(In Words\)\s*:\s*(.+)$/i', $text, $m)) {
                    $totals['amount_in_words'] = trim($m[1]);
                }
                if (preg_match('/^PRICE BASIS\s*:\s*(.+)$/i', $text, $m)) {
                    $header['price_basis'] = trim($m[1]);
                }
                if (preg_match('/^PAYMENT TERMS\s*:\s*(.+)$/i', $text, $m)) {
                    $header['payment_terms'] = trim($m[1]);
                }
                if (preg_match('/^Sub\.?\s*:\s*(.+)$/i', $text, $m)) {
                    $header['subject'] = trim($m[1]);
                }
                if (preg_match('/^User\s*:\s*(.+)$/i', $text, $m)) {
                    $header['user_department_person'] = trim($m[1]);
                }
                if (preg_match('/^Buyer\s*:\s*(.+)$/i', $text, $m)) {
                    $header['buyer_name'] = trim($m[1]);
                }
                if (preg_match('/Contract Tenure\s*:\s*(.+)$/i', $text, $m)) {
                    $header['contract_tenure'] = trim($m[1]);
                }
                if (preg_match('/Effective Date\s*:?\s*(\d{2}\.\d{2}\.\d{4})/i', $text, $m)) {
                    $header['effective_date'] = $this->parseDotDate($m[1]);
                }
                if (preg_match('/(\d{1,2})\s*%\s*of the PO price shall be deducted from each RA bill/i', $text, $m)) {
                    $header['retention_percent'] = (float) $m[1];
                }
                if (preg_match('/Defect Liability\s*:\s*(\d+)\s*Months/i', $text, $m)) {
                    $header['defect_liability_months'] = (int) $m[1];
                }
                if (preg_match('/Liquidated damages\s*:\s*(.+)$/i', $text, $m)) {
                    $val = trim($m[1]);
                    if ($val !== '') {
                        $header['liquidated_damages'] = $val;
                    }
                }

                if (str_contains($text, 'GENERAL TERMS:') || str_contains($text, 'General Terms and Conditions')) {
                    $inGeneralTerms = true;
                }
                if ($inGeneralTerms) {
                    $generalTermsLines[] = $text;
                }
            }
        }

        // Finalize item specification text (join cleanly across pages without hyphen/space breaks)
        foreach ($items as &$item) {
            $specLines = $item['spec_lines'] ?? [];
            unset($item['spec_lines']);
            $joinedSpec = trim(preg_replace('/\s+/', ' ', implode(' ', $specLines)));
            $joinedSpec = str_replace([' ;', ' ,'], [';', ','], $joinedSpec);
            $item['specification_text'] = $joinedSpec;
            if ($item['description'] === '') {
                $item['description'] = $item['service_code'];
            }
        }
        unset($item);

        if ($header['subject'] === '' && $header['order_short_description'] !== '') {
            $header['subject'] = $header['order_short_description'];
        }
        if ($header['effective_date'] === null && $header['po_date'] !== null) {
            $header['effective_date'] = $header['po_date'];
        }
        if ($totals['total_taxes'] <= 0 && ($totals['cgst_amount'] > 0 || $totals['sgst_amount'] > 0)) {
            $totals['total_taxes'] = round($totals['cgst_amount'] + $totals['sgst_amount'], 2);
        }

        return [
            'header' => $header,
            'vendor' => $vendor,
            'items' => $items,
            'totals' => $totals,
            'general_terms_text' => implode("\n", $generalTermsLines),
            'warnings' => $isDraftPo
                ? ['DRAFT PO watermark detected on the uploaded service order. Work Order will be created in Draft PO status (tracking enabled, billing locked until final PO revision).']
                : [],
        ];
    }

    /**
     * Detect if a row is a JSW line item row:
     * Has sr_no (e.g. 1.1 .. 1.16) at x ~ 30-50, service_code at x ~ 55-75,
     * qty at x ~ 310-350, unit at x ~ 350-380, unit_price at x ~ 390-440, line_total at x >= 500.
     */
    protected function tryParseServiceItemHeaderRow(array $row): ?array
    {
        $srNo = null;
        $serviceCode = null;
        $unit = null;
        $qty = null;
        $rate = null;
        $lineTotal = null;

        foreach ($row['segments'] as $seg) {
            $x = $seg['x'];
            $t = trim($seg['text']);
            if ($t === '') {
                continue;
            }

            if ($x >= 25.0 && $x <= 55.0 && preg_match('/^\d+\.\d+$/', $t)) {
                $srNo = $t;
            } elseif ($x >= 55.0 && $x <= 120.0 && preg_match('/^[A-Z]{3}\d{10,16}$/', $t)) {
                $serviceCode = $t;
            } elseif ($x >= 300.0 && $x < 352.0 && preg_match('/^[\d,]+\.\d{3,4}$/', $t)) {
                $qty = round((float) str_replace(',', '', $t), 3);
            } elseif ($x >= 352.0 && $x <= 385.0 && preg_match('/^[A-Z0-9]{1,6}$/', $t)) {
                $unit = $t;
            } elseif ($x > 385.0 && $x < 460.0 && preg_match('/^[\d,]+\.\d{2,4}$/', $t)) {
                $rate = round((float) str_replace(',', '', $t), 2);
            } elseif ($x >= 490.0 && preg_match('/^[\d,]+\.\d{2,4}$/', $t)) {
                $lineTotal = round((float) str_replace(',', '', $t), 2);
            }
        }

        if ($srNo !== null && $serviceCode !== null && $qty !== null && $rate !== null && $lineTotal !== null) {
            return [
                'sr_no' => $srNo,
                'service_code' => $serviceCode,
                'sac_code' => '9954',
                'description' => '',
                'spec_lines' => [],
                'unit' => $unit ?: 'NOS',
                'ordered_qty' => $qty,
                'rate' => $rate,
                'line_total' => $lineTotal,
            ];
        }

        return null;
    }

    protected function parseDotDate(string $dotDate): ?string
    {
        try {
            return Carbon::createFromFormat('d.m.Y', trim($dotDate))->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }
}


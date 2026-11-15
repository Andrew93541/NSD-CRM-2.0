<?php

namespace App\Services\Operations;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\CrmNotification;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderAttachment;
use App\Models\WorkOrderItem;
use App\Models\WorkOrderRevision;
use App\Models\WorkOrderUpload;
use App\Services\Storage\VaultStorage;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WorkOrderUploadService
{
    public function __construct(
        protected JswWorkOrderPdfReaderService $pdfReader,
        protected WorkOrderService $workOrderService
    ) {}

    /**
     * Store uploaded PDF privately and extract structured JSW Service Order draft.
     */
    public function uploadAndExtract(
        int $companyId,
        UploadedFile $pdfFile,
        User $actor,
        bool $forceDraftFlag = false
    ): WorkOrderUpload {
        $tempSourcePath = $pdfFile->getRealPath();
        $parsedResult = $this->pdfReader->parsePdfFile(
            $tempSourcePath !== false && $tempSourcePath !== '' ? $tempSourcePath : $pdfFile->getPathname(),
            $pdfFile->getClientOriginalName()
        );

        $safeName = Str::slug(pathinfo($pdfFile->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'jsw-work-order';
        $ext = strtolower($pdfFile->getClientOriginalExtension() ?: 'pdf');
        $stored = VaultStorage::storeUploaded(
            $pdfFile,
            "work-order-uploads/{$companyId}",
            now()->format('YmdHis').'-'.Str::random(8).'-'.$safeName.'.'.$ext
        );
        $storedPath = $stored['path'];
        $diskName = $stored['disk'];

        $isDraftPo = $forceDraftFlag || (bool) ($parsedResult['is_draft_po'] ?? false);
        $isUnreadable = (bool) ($parsedResult['is_scanned_or_unreadable'] ?? false);

        try {
            $upload = DB::transaction(function () use ($companyId, $actor, $pdfFile, $storedPath, $diskName, $isUnreadable, $isDraftPo, $parsedResult) {
                $uploadPayload = [
                    'company_id' => $companyId,
                    'uploaded_by' => $actor->id,
                    'original_filename' => $pdfFile->getClientOriginalName(),
                    'file_path' => $storedPath,
                    'file_size' => (int) $pdfFile->getSize(),
                    'mime_type' => $pdfFile->getClientMimeType() ?: 'application/pdf',
                    'status' => $isUnreadable ? WorkOrderUpload::STATUS_UNREADABLE : WorkOrderUpload::STATUS_UPLOADED,
                    'is_scanned_or_unreadable' => $isUnreadable,
                    'is_draft_po' => $isDraftPo,
                    'extracted_payload' => $parsedResult['payload'],
                    'reviewed_payload' => $parsedResult['payload'],
                    'validation_errors' => $parsedResult['validation_errors'],
                ];
                if (Schema::hasColumn('work_order_uploads', 'storage_disk')) {
                    $uploadPayload['storage_disk'] = $diskName;
                }
                $upload = WorkOrderUpload::create($uploadPayload);

                AuditLog::record(
                    'work_order_upload.uploaded',
                    $upload,
                    null,
                    [
                        'upload_id' => $upload->id,
                        'po_number' => $parsedResult['payload']['header']['po_number'] ?? null,
                        'items_count' => count($parsedResult['payload']['items'] ?? []),
                        'is_draft_po' => $isDraftPo,
                        'is_scanned_or_unreadable' => $isUnreadable,
                    ],
                    sprintf('Work order PDF "%s" uploaded and parsed (%s).', $upload->original_filename, $isUnreadable ? 'scanned/unreadable' : 'structured draft ready'),
                    $actor->id,
                    $companyId
                );

                return $upload;
            });
        } catch (\Throwable $e) {
            VaultStorage::delete($storedPath, $diskName);

            throw $e;
        }

        return $upload;
    }

    /**
     * Save user edits from the Review screen and re-run arithmetic validation.
     */
    public function saveReviewEdits(
        WorkOrderUpload $upload,
        array $reviewedInput,
        User $actor
    ): WorkOrderUpload {
        $current = $upload->activePayload();

        $header = array_merge($current['header'] ?? [], $reviewedInput['header'] ?? []);
        $vendor = array_merge($current['vendor'] ?? [], $reviewedInput['vendor'] ?? []);

        // Never store unmasked bank account numbers
        if (isset($vendor['bank_account_masked'])) {
            $vendor['bank_account_masked'] = JswWorkOrderPdfReaderService::maskBankAccount((string) $vendor['bank_account_masked']);
        }
        unset($vendor['bank_account']);

        $items = [];
        foreach ($reviewedInput['items'] ?? ($current['items'] ?? []) as $idx => $item) {
            $srNo = trim((string) ($item['sr_no'] ?? ($idx + 1)));
            $desc = trim((string) ($item['description'] ?? ''));
            $serviceCode = trim((string) ($item['service_code'] ?? ''));
            if ($desc === '' && $serviceCode === '') {
                continue;
            }

            $qty = round((float) ($item['ordered_qty'] ?? 0), 3);
            $rate = round((float) ($item['rate'] ?? 0), 2);
            $lineTotal = isset($item['line_total']) && $item['line_total'] !== ''
                ? round((float) $item['line_total'], 2)
                : round($qty * $rate, 2);

            $items[] = [
                'sr_no' => $srNo !== '' ? $srNo : (string) ($idx + 1),
                'service_code' => $serviceCode,
                'sac_code' => trim((string) ($item['sac_code'] ?? '9954')),
                'description' => $desc !== '' ? $desc : $serviceCode,
                'specification_text' => trim((string) ($item['specification_text'] ?? '')),
                'unit' => strtoupper(trim((string) ($item['unit'] ?? 'NOS'))),
                'ordered_qty' => $qty,
                'rate' => $rate,
                'line_total' => $lineTotal,
            ];
        }

        $totals = array_merge($current['totals'] ?? [], $reviewedInput['totals'] ?? []);
        $totals['base_value'] = round((float) ($totals['base_value'] ?? 0), 2);
        $totals['cgst_rate'] = round((float) ($totals['cgst_rate'] ?? 9.0), 2);
        $totals['cgst_amount'] = round((float) ($totals['cgst_amount'] ?? 0), 2);
        $totals['sgst_rate'] = round((float) ($totals['sgst_rate'] ?? 9.0), 2);
        $totals['sgst_amount'] = round((float) ($totals['sgst_amount'] ?? 0), 2);
        $totals['total_taxes'] = isset($reviewedInput['totals']['total_taxes']) && $reviewedInput['totals']['total_taxes'] !== ''
            ? round((float) $reviewedInput['totals']['total_taxes'], 2)
            : round($totals['cgst_amount'] + $totals['sgst_amount'], 2);
        $totals['total_po_value'] = round((float) ($totals['total_po_value'] ?? 0), 2);

        $updatedPayload = [
            'header' => $header,
            'vendor' => $vendor,
            'items' => $items,
            'totals' => $totals,
            'general_terms_text' => $reviewedInput['general_terms_text'] ?? ($current['general_terms_text'] ?? ''),
            'warnings' => $current['warnings'] ?? [],
        ];

        $validationErrors = $this->pdfReader->validateExtractedPayload($updatedPayload);
        $isDraftPo = array_key_exists('is_draft_po', $reviewedInput)
            ? (bool) $reviewedInput['is_draft_po']
            : (bool) $upload->is_draft_po;

        $upload->reviewed_payload = $updatedPayload;
        $upload->validation_errors = $validationErrors;
        $upload->is_draft_po = $isDraftPo;
        if (! empty($items) && ! empty($header['po_number'])) {
            $upload->is_scanned_or_unreadable = false;
            $upload->status = WorkOrderUpload::STATUS_REVIEW_PENDING;
        }
        $upload->save();

        AuditLog::record(
            'work_order_upload.review_edited',
            $upload,
            null,
            [
                'upload_id' => $upload->id,
                'po_number' => $header['po_number'] ?? null,
                'items_count' => count($items),
                'validation_errors' => $validationErrors,
                'is_draft_po' => $isDraftPo,
            ],
            sprintf('Work order upload #%d review draft edited by %s.', $upload->id, $actor->name),
            $actor->id,
            (int) $upload->company_id
        );

        return $upload;
    }

    /**
     * Import BOQ lines from CSV or Excel into the upload review draft.
     */
    public function importLinesFromSpreadsheet(
        WorkOrderUpload $upload,
        UploadedFile $spreadsheetFile,
        User $actor
    ): WorkOrderUpload {
        $items = $this->pdfReader->parseSpreadsheetLines($spreadsheetFile->getRealPath());
        if (empty($items)) {
            throw ValidationException::withMessages([
                'lines_file' => 'No valid BOQ line items could be read from the uploaded CSV/Excel file.',
            ]);
        }

        $payload = $upload->activePayload();
        $payload['items'] = $items;

        $baseValue = round(collect($items)->sum('line_total'), 2);
        $cgstRate = (float) ($payload['totals']['cgst_rate'] ?? 9.0);
        $sgstRate = (float) ($payload['totals']['sgst_rate'] ?? 9.0);
        $cgstAmount = round($baseValue * ($cgstRate / 100), 2);
        $sgstAmount = round($baseValue * ($sgstRate / 100), 2);
        $totalTaxes = round($cgstAmount + $sgstAmount, 2);

        $payload['totals']['base_value'] = $baseValue;
        $payload['totals']['cgst_amount'] = $cgstAmount;
        $payload['totals']['sgst_amount'] = $sgstAmount;
        $payload['totals']['total_taxes'] = $totalTaxes;
        $payload['totals']['total_po_value'] = round($baseValue + $totalTaxes, 2);

        $validationErrors = $this->pdfReader->validateExtractedPayload($payload);

        $upload->reviewed_payload = $payload;
        $upload->validation_errors = $validationErrors;
        $upload->is_scanned_or_unreadable = false;
        $upload->status = WorkOrderUpload::STATUS_REVIEW_PENDING;
        $upload->save();

        AuditLog::record(
            'work_order_upload.lines_imported',
            $upload,
            null,
            ['items_count' => count($items), 'base_value' => $baseValue],
            sprintf('Imported %d BOQ lines from "%s" into upload #%d.', count($items), $spreadsheetFile->getClientOriginalName(), $upload->id),
            $actor->id,
            (int) $upload->company_id
        );

        return $upload;
    }

    /**
     * Confirm an extracted/reviewed Work Order in a SINGLE atomic database transaction:
     * - Blocks if arithmetic validation mismatches exist without an override reason.
     * - If PO number already exists for the company -> opens/applies a Revision on the existing Work Order and creates NO new site.
     * - Otherwise -> creates Client (if new), Project, Site (status 'setup_pending', or attaches to existing site if chosen), Work Order, Items, and Revision 0.
     *
     * @return array{work_order: WorkOrder, site: Site, is_revision: bool}
     */
    public function confirmUpload(
        WorkOrderUpload $upload,
        User $actor,
        ?int $attachToSiteId = null,
        ?string $overrideReason = null,
        ?bool $isDraftPoOverride = null
    ): array {
        if ($upload->status === WorkOrderUpload::STATUS_CONFIRMED && $upload->workOrder) {
            return [
                'work_order' => $upload->workOrder,
                'site' => $upload->site ?? $upload->workOrder->site,
                'is_revision' => false,
            ];
        }

        $payload = $upload->activePayload();
        $header = $payload['header'] ?? [];
        $items = $payload['items'] ?? [];
        $totals = $payload['totals'] ?? [];

        $poNumber = trim((string) ($header['po_number'] ?? ''));
        if ($poNumber === '') {
            throw ValidationException::withMessages([
                'po_number' => 'PO Number is required before confirming the work order.',
            ]);
        }

        if (empty($items)) {
            throw ValidationException::withMessages([
                'items' => 'At least one work order line item is required before confirming.',
            ]);
        }

        $validationErrors = $this->pdfReader->validateExtractedPayload($payload);
        $upload->validation_errors = $validationErrors;

        if (! empty($validationErrors) && trim((string) $overrideReason) === '') {
            $upload->save();
            throw ValidationException::withMessages([
                'validation_mismatch' => 'Confirm is blocked because arithmetic totals do not match: '.implode(' ', array_values($validationErrors)).' Fix the quantities/totals on the review screen or provide an override reason.',
            ]);
        }

        $isDraftPo = $isDraftPoOverride !== null ? $isDraftPoOverride : (bool) $upload->is_draft_po;

        return DB::transaction(function () use ($upload, $actor, $payload, $header, $items, $totals, $poNumber, $attachToSiteId, $overrideReason, $isDraftPo) {
            $companyId = (int) $upload->company_id;

            // 1. Find or create Client
            $clientName = trim((string) ($header['client_name'] ?? 'JSW Steel Limited')) ?: 'JSW Steel Limited';
            $client = Client::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('name', $clientName)
                ->first();

            if (! $client) {
                $client = Client::create([
                    'company_id' => $companyId,
                    'name' => $clientName,
                    'code' => 'CL-'.strtoupper(Str::random(5)),
                    'gstin' => $header['client_gstin'] ?: null,
                    'address' => $header['client_address'] ?: null,
                    'contact_person' => $header['user_department_person'] ?: ($header['buyer_name'] ?: null),
                    'is_active' => true,
                ]);
            }

            // 2. Check if a Work Order with this PO number already exists for this company
            $existingWorkOrder = WorkOrder::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('po_number', $poNumber)
                ->lockForUpdate()
                ->first();

            if ($existingWorkOrder) {
                $site = $existingWorkOrder->site;
                $workOrder = $this->applyUploadAsRevision(
                    $existingWorkOrder,
                    $upload,
                    $payload,
                    $actor,
                    $isDraftPo,
                    $overrideReason
                );

                $upload->update([
                    'work_order_id' => $workOrder->id,
                    'site_id' => $site->id,
                    'status' => WorkOrderUpload::STATUS_CONFIRMED,
                    'is_draft_po' => $isDraftPo,
                    'override_reason' => $overrideReason,
                    'confirmed_by' => $actor->id,
                    'confirmed_at' => now(),
                ]);

                return [
                    'work_order' => $workOrder,
                    'site' => $site,
                    'is_revision' => true,
                ];
            }

            // 3. Resolve Site & Project (either attach to existing site or create new Project + Site in setup_pending)
            $subjectName = trim((string) ($header['subject'] ?? ''))
                ?: (trim((string) ($header['order_short_description'] ?? '')) ?: "JSW PO {$poNumber}");

            if ($attachToSiteId !== null) {
                $site = Site::withoutGlobalScopes()
                    ->where('company_id', $companyId)
                    ->findOrFail($attachToSiteId);
                $project = $site->project;
            } else {
                $projectCode = $this->uniqueProjectCode($companyId, $poNumber);
                $project = Project::create([
                    'company_id' => $companyId,
                    'code' => $projectCode,
                    'name' => Str::limit($subjectName, 180, ''),
                    'client_name' => $client->name,
                    'description' => "Auto-created from JSW Service Order PO {$poNumber}",
                    'status' => 'active',
                    'start_date' => $header['effective_date'] ?: ($header['po_date'] ?: now()->toDateString()),
                    'end_date' => $header['completion_date'] ?: null,
                    'budget_amount' => (float) ($totals['base_value'] ?? 0),
                ]);

                $siteCode = $this->uniqueSiteCode($companyId, $poNumber);
                $site = Site::create([
                    'company_id' => $companyId,
                    'project_id' => $project->id,
                    'code' => $siteCode,
                    'name' => Str::limit($subjectName, 180, ''),
                    'location' => $header['price_basis'] ?: 'JSW Dolvi Works',
                    'address' => $header['client_address'] ?: 'Dolvi Works, Geetapuram, Dolvi, Raigad, Maharashtra',
                    'status' => 'setup_pending',
                    'geofence_radius_m' => 200,
                    'latitude' => null,
                    'longitude' => null,
                    'site_manager_id' => null,
                    'supervisor_id' => null,
                    'description' => "Created from PO {$poNumber}. Pending GPS location, geofence and Site Manager setup.",
                ]);

                if (Schema::hasTable('project_sites')) {
                    DB::table('project_sites')->updateOrInsert(
                        ['project_id' => $project->id, 'site_id' => $site->id],
                        [
                            'company_id' => $companyId,
                            'is_primary' => true,
                            'start_date' => $header['effective_date'] ?: now()->toDateString(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

                $this->notifyManagersSiteNeedsSetup($companyId, $site, $poNumber);
            }

            // 4. Create the Work Order and its Line Items
            $orderNumber = $this->workOrderService->nextOrderNumber($companyId);
            $baseValue = round((float) ($totals['base_value'] ?? 0), 2);

            $workOrder = WorkOrder::create([
                'company_id' => $companyId,
                'project_id' => $project->id,
                'site_id' => $site->id,
                'client_id' => $client->id,
                'order_type' => 'received_service_order',
                'service_type' => $header['service_type'] ?: 'JBN',
                'order_number' => $orderNumber,
                'po_number' => $poNumber,
                'po_date' => $header['po_date'] ?: now()->toDateString(),
                'job_location' => $header['price_basis'] ?: $site->name,
                'user_department_person' => $header['user_department_person'] ?: null,
                'buyer_name' => $header['buyer_name'] ?: null,
                'effective_date' => $header['effective_date'] ?: ($header['po_date'] ?: now()->toDateString()),
                'completion_date' => $header['completion_date'] ?: null,
                'contract_tenure' => $header['contract_tenure'] ?: '12 Months',
                'client_reference' => $header['client_reference'] ?: $poNumber,
                'contractor_name' => $client->name,
                'contractor_gst' => $client->gstin,
                'payment_terms' => $header['payment_terms'] ?: WorkOrder::DEFAULT_PAYMENT_TERMS,
                'price_basis' => $header['price_basis'] ?: null,
                'title' => Str::limit($subjectName, 200, ''),
                'subject' => Str::limit($subjectName, 250, ''),
                'description' => "JSW Service Order {$poNumber} — {$subjectName}",
                'scope_of_work' => $subjectName,
                'terms_and_conditions' => WorkOrder::DEFAULT_TERMS,
                'general_terms_text' => $payload['general_terms_text'] ?? null,
                'estimated_amount' => $baseValue,
                'base_order_value' => $baseValue,
                'cgst_rate' => (float) ($totals['cgst_rate'] ?? 9.0),
                'sgst_rate' => (float) ($totals['sgst_rate'] ?? 9.0),
                'retention_percent' => (float) ($header['retention_percent'] ?? 5.0),
                'defect_liability_months' => (int) ($header['defect_liability_months'] ?? 12),
                'liquidated_damages' => $header['liquidated_damages'] ?? 'NA',
                'current_revision' => 0,
                'target_start_date' => $header['effective_date'] ?: ($header['po_date'] ?: now()->toDateString()),
                'target_completion_date' => $header['completion_date'] ?: null,
                'status' => $isDraftPo ? 'draft' : 'approved',
                'lifecycle_status' => $isDraftPo ? WorkOrder::LIFECYCLE_DRAFT_PO : WorkOrder::LIFECYCLE_CONFIRMED,
                'is_draft_po' => $isDraftPo,
                'created_by' => $actor->id,
            ]);

            $boqSnapshot = [];
            foreach (array_values($items) as $idx => $line) {
                $qty = round((float) ($line['ordered_qty'] ?? 0), 3);
                $rate = round((float) ($line['rate'] ?? 0), 2);
                $amount = isset($line['line_total']) ? round((float) $line['line_total'], 2) : round($qty * $rate, 2);

                $createdItem = WorkOrderItem::create([
                    'company_id' => $companyId,
                    'work_order_id' => $workOrder->id,
                    'sr_no' => (string) ($line['sr_no'] ?? ($idx + 1)),
                    'service_code' => $line['service_code'] ?? null,
                    'sac_code' => $line['sac_code'] ?? '9954',
                    'description' => $line['description'] ?? 'Service item',
                    'specification' => $line['specification_text'] ?? ($line['specification'] ?? null),
                    'unit' => strtoupper(trim((string) ($line['unit'] ?? 'NOS'))),
                    'ordered_quantity' => $qty,
                    'rate' => $rate,
                    'line_amount' => $amount,
                    'completion_date' => $header['completion_date'] ?: null,
                    'sort_order' => $idx + 1,
                ]);

                $boqSnapshot[] = [
                    'id' => $createdItem->id,
                    'sr_no' => $createdItem->sr_no,
                    'service_code' => $createdItem->service_code,
                    'sac_code' => $createdItem->sac_code,
                    'description' => $createdItem->description,
                    'specification' => $createdItem->specification,
                    'unit' => $createdItem->unit,
                    'ordered_quantity' => (float) $createdItem->ordered_quantity,
                    'quantity' => (float) $createdItem->ordered_quantity,
                    'rate' => (float) $createdItem->rate,
                    'line_amount' => (float) $createdItem->line_amount,
                    'amount' => (float) $createdItem->line_amount,
                ];
            }

            $workOrder->boq_items = $boqSnapshot;
            $workOrder->save();

            WorkOrderRevision::create([
                'company_id' => $companyId,
                'work_order_id' => $workOrder->id,
                'revision_number' => 0,
                'status' => 'approved',
                'reason' => "Confirmed from uploaded JSW Service Order PDF ({$upload->original_filename})".($overrideReason ? " [Override: {$overrideReason}]" : ''),
                'previous_base_value' => 0,
                'new_base_value' => $baseValue,
                'items_snapshot' => $boqSnapshot,
                'proposed_items' => $boqSnapshot,
                'changed_by' => $actor->id,
                'approved_by' => $actor->id,
                'approved_at' => now(),
            ]);

            $attachmentPayload = [
                'work_order_id' => $workOrder->id,
                'uploaded_by' => $actor->id,
                'file_name' => $upload->original_filename,
                'file_path' => $upload->file_path,
                'mime_type' => $upload->mime_type,
                'file_size' => $upload->file_size,
            ];
            if (Schema::hasColumn('work_order_attachments', 'storage_disk')) {
                $attachmentPayload['storage_disk'] = $upload->storage_disk ?: VaultStorage::activeDiskName();
            }
            WorkOrderAttachment::create($attachmentPayload);

            $upload->update([
                'work_order_id' => $workOrder->id,
                'site_id' => $site->id,
                'status' => WorkOrderUpload::STATUS_CONFIRMED,
                'is_draft_po' => $isDraftPo,
                'override_reason' => $overrideReason,
                'confirmed_by' => $actor->id,
                'confirmed_at' => now(),
            ]);

            AuditLog::record(
                'work_order_upload.confirmed',
                $workOrder,
                null,
                [
                    'upload_id' => $upload->id,
                    'work_order_id' => $workOrder->id,
                    'site_id' => $site->id,
                    'po_number' => $poNumber,
                    'base_order_value' => $baseValue,
                    'is_draft_po' => $isDraftPo,
                    'override_reason' => $overrideReason,
                ],
                sprintf('Confirmed Work Order %s (PO %s) and Site "%s" (%s) from uploaded PDF.', $workOrder->order_number, $poNumber, $site->name, $site->status),
                $actor->id,
                $companyId
            );

            return [
                'work_order' => $workOrder->fresh(['items', 'site', 'project', 'client', 'revisions']),
                'site' => $site->fresh(),
                'is_revision' => false,
            ];
        });
    }

    /**
     * Transition a Work Order through its lifecycle stages:
     * draft_po -> confirmed -> in_progress -> work_complete -> final_bill_issued -> defect_liability -> closed
     */
    public function transitionLifecycleStatus(
        WorkOrder $workOrder,
        string $nextLifecycleStatus,
        User $actor,
        ?string $notes = null
    ): WorkOrder {
        if (! array_key_exists($nextLifecycleStatus, WorkOrder::LIFECYCLE_STATUSES)) {
            throw ValidationException::withMessages([
                'lifecycle_status' => 'Invalid work order lifecycle status.',
            ]);
        }

        $previousLifecycle = $workOrder->effectiveLifecycleStatus();
        $workOrder->lifecycle_status = $nextLifecycleStatus;
        $workOrder->is_draft_po = ($nextLifecycleStatus === WorkOrder::LIFECYCLE_DRAFT_PO);

        if ($nextLifecycleStatus === WorkOrder::LIFECYCLE_CONFIRMED && $workOrder->status === 'draft') {
            $workOrder->status = 'approved';
        }

        if (in_array($nextLifecycleStatus, [
            WorkOrder::LIFECYCLE_WORK_COMPLETE,
            WorkOrder::LIFECYCLE_FINAL_BILL_ISSUED,
            WorkOrder::LIFECYCLE_DEFECT_LIABILITY,
        ], true)) {
            $workOrder->status = 'completed';
            $dlpMonths = (int) ($workOrder->defect_liability_months ?: 12);
            if (! $workOrder->defect_liability_ends_on) {
                $workOrder->defect_liability_ends_on = Carbon::today()->addMonths($dlpMonths);
            }
            if (! $workOrder->retention_release_due_on) {
                $workOrder->retention_release_due_on = $workOrder->defect_liability_ends_on;
            }
        }

        if ($nextLifecycleStatus === WorkOrder::LIFECYCLE_CLOSED) {
            $workOrder->status = 'completed';
            if (! $workOrder->retention_released_at) {
                $workOrder->retention_released_at = now();
            }
        }

        $workOrder->save();

        AuditLog::record(
            'work_order.lifecycle_transition',
            $workOrder,
            ['lifecycle_status' => $previousLifecycle],
            ['lifecycle_status' => $nextLifecycleStatus, 'notes' => $notes],
            sprintf('Work Order %s moved from %s to %s.', $workOrder->order_number, $previousLifecycle, $nextLifecycleStatus),
            $actor->id,
            (int) $workOrder->company_id
        );

        return $workOrder;
    }

    protected function applyUploadAsRevision(
        WorkOrder $workOrder,
        WorkOrderUpload $upload,
        array $payload,
        User $actor,
        bool $isDraftPo,
        ?string $overrideReason
    ): WorkOrder {
        $header = $payload['header'] ?? [];
        $items = $payload['items'] ?? [];
        $totals = $payload['totals'] ?? [];

        $previousRevisionNumber = (int) $workOrder->current_revision;
        $nextRevisionNumber = $previousRevisionNumber + 1;
        $previousBaseValue = (float) $workOrder->base_order_value;

        $existingBySr = $workOrder->items()->get()->keyBy('sr_no');
        $newSnapshot = [];
        $newBaseValue = 0.0;

        foreach (array_values($items) as $idx => $line) {
            $srNo = (string) ($line['sr_no'] ?? ($idx + 1));
            $qty = round((float) ($line['ordered_qty'] ?? 0), 3);
            $rate = round((float) ($line['rate'] ?? 0), 2);
            $amount = isset($line['line_total']) ? round((float) $line['line_total'], 2) : round($qty * $rate, 2);

            /** @var WorkOrderItem|null $itemModel */
            $itemModel = $existingBySr->get($srNo);
            if ($itemModel) {
                $itemModel->update([
                    'service_code' => $line['service_code'] ?? $itemModel->service_code,
                    'sac_code' => $line['sac_code'] ?? $itemModel->sac_code,
                    'description' => $line['description'] ?? $itemModel->description,
                    'specification' => $line['specification_text'] ?? ($line['specification'] ?? $itemModel->specification),
                    'unit' => strtoupper(trim((string) ($line['unit'] ?? $itemModel->unit))),
                    'ordered_quantity' => $qty,
                    'rate' => $rate,
                    'line_amount' => $amount,
                    'sort_order' => $idx + 1,
                ]);
            } else {
                $itemModel = WorkOrderItem::create([
                    'company_id' => $workOrder->company_id,
                    'work_order_id' => $workOrder->id,
                    'sr_no' => $srNo,
                    'service_code' => $line['service_code'] ?? null,
                    'sac_code' => $line['sac_code'] ?? '9954',
                    'description' => $line['description'] ?? 'Service item',
                    'specification' => $line['specification_text'] ?? ($line['specification'] ?? null),
                    'unit' => strtoupper(trim((string) ($line['unit'] ?? 'NOS'))),
                    'ordered_quantity' => $qty,
                    'rate' => $rate,
                    'line_amount' => $amount,
                    'sort_order' => $idx + 1,
                ]);
            }

            $newBaseValue = round($newBaseValue + $amount, 2);
            $newSnapshot[] = [
                'id' => $itemModel->id,
                'sr_no' => $itemModel->sr_no,
                'service_code' => $itemModel->service_code,
                'sac_code' => $itemModel->sac_code,
                'description' => $itemModel->description,
                'specification' => $itemModel->specification,
                'unit' => $itemModel->unit,
                'ordered_quantity' => (float) $itemModel->ordered_quantity,
                'quantity' => (float) $itemModel->ordered_quantity,
                'rate' => (float) $itemModel->rate,
                'line_amount' => (float) $itemModel->line_amount,
                'amount' => (float) $itemModel->line_amount,
            ];
        }

        $finalBase = round((float) ($totals['base_value'] ?? $newBaseValue), 2);
        $nextLifecycle = $isDraftPo
            ? WorkOrder::LIFECYCLE_DRAFT_PO
            : ($workOrder->isDraftPo() ? WorkOrder::LIFECYCLE_CONFIRMED : $workOrder->effectiveLifecycleStatus());

        $workOrder->update([
            'current_revision' => $nextRevisionNumber,
            'base_order_value' => $finalBase,
            'estimated_amount' => $finalBase,
            'boq_items' => $newSnapshot,
            'completion_date' => $header['completion_date'] ?: $workOrder->completion_date,
            'target_completion_date' => $header['completion_date'] ?: $workOrder->target_completion_date,
            'is_draft_po' => $isDraftPo,
            'lifecycle_status' => $nextLifecycle,
            'status' => $isDraftPo ? 'draft' : 'approved',
        ]);

        WorkOrderRevision::create([
            'company_id' => $workOrder->company_id,
            'work_order_id' => $workOrder->id,
            'revision_number' => $nextRevisionNumber,
            'status' => 'approved',
            'reason' => "Revision {$nextRevisionNumber} uploaded from PO {$workOrder->po_number} ({$upload->original_filename})".($overrideReason ? " [Override: {$overrideReason}]" : ''),
            'previous_base_value' => $previousBaseValue,
            'new_base_value' => $finalBase,
            'items_snapshot' => $newSnapshot,
            'proposed_items' => $newSnapshot,
            'changed_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);

        $revisionAttachmentPayload = [
            'work_order_id' => $workOrder->id,
            'uploaded_by' => $actor->id,
            'file_name' => $upload->original_filename,
            'file_path' => $upload->file_path,
            'mime_type' => $upload->mime_type,
            'file_size' => $upload->file_size,
        ];
        if (Schema::hasColumn('work_order_attachments', 'storage_disk')) {
            $revisionAttachmentPayload['storage_disk'] = $upload->storage_disk ?: VaultStorage::activeDiskName();
        }
        WorkOrderAttachment::create($revisionAttachmentPayload);

        AuditLog::record(
            'work_order.revised_from_po',
            $workOrder,
            ['revision' => $previousRevisionNumber, 'base_order_value' => $previousBaseValue],
            ['revision' => $nextRevisionNumber, 'base_order_value' => $finalBase, 'is_draft_po' => $isDraftPo],
            sprintf('Work Order %s updated to Revision %d via duplicate PO %s upload (no new site created).', $workOrder->order_number, $nextRevisionNumber, $workOrder->po_number),
            $actor->id,
            (int) $workOrder->company_id
        );

        return $workOrder->fresh(['items', 'site', 'project', 'client', 'revisions']);
    }

    protected function uniqueProjectCode(int $companyId, string $poNumber): string
    {
        $base = 'PRJ-'.substr(preg_replace('/[^A-Z0-9]/i', '', $poNumber), -10);
        $candidate = $base;
        $suffix = 1;
        while (Project::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $candidate)->exists()) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }

    protected function uniqueSiteCode(int $companyId, string $poNumber): string
    {
        $base = 'PO-'.substr(preg_replace('/[^A-Z0-9]/i', '', $poNumber), -12);
        $candidate = $base;
        $suffix = 1;
        while (Site::withoutGlobalScopes()->where('company_id', $companyId)->where('code', $candidate)->exists()) {
            $suffix++;
            $candidate = $base.'-'.$suffix;
        }

        return $candidate;
    }

    protected function notifyManagersSiteNeedsSetup(int $companyId, Site $site, string $poNumber): void
    {
        if (! Schema::hasTable('crm_notifications')) {
            return;
        }

        $managers = DB::table('company_user')
            ->join('users', 'users.id', '=', 'company_user.user_id')
            ->join('roles', 'roles.id', '=', 'company_user.role_id')
            ->where('company_user.company_id', $companyId)
            ->where('company_user.status', 'active')
            ->where('users.is_active', true)
            ->whereIn('roles.slug', ['super_admin', 'company_admin', 'senior_manager', 'site_manager'])
            ->pluck('users.id')
            ->unique();

        foreach ($managers as $userId) {
            CrmNotification::notifyUser(
                (int) $userId,
                'site.setup_pending',
                "New Site Setup Pending: {$site->name}",
                "Site {$site->name} ({$site->code}) was created from PO {$poNumber}. Please configure GPS map coordinates, geofence radius, and assign a Site Manager.",
                route('sites.edit', $site, false),
                $site->id,
                $companyId
            );
        }
    }
}

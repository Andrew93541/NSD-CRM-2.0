@extends('layouts.app')

@section('content')
@php
    $header = $payload['header'] ?? [];
    $vendor = $payload['vendor'] ?? [];
    $items = $payload['items'] ?? [];
    $totals = $payload['totals'] ?? [];
    $validationErrors = $upload->validation_errors ?? [];
    $hasMismatches = !empty($validationErrors);
@endphp

<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-semibold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                <span>Upload Review & Verification</span>
                <span>•</span>
                <span>{{ $upload->original_filename }}</span>
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white mt-1">
                Review Extracted Work Order {{ !empty($header['po_number']) ? '— PO ' . $header['po_number'] : '' }}
            </h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                Verify extracted header fields, line items, specifications, and arithmetic totals before confirming. Nothing is written to Work Orders or Sites until you click Confirm.
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('work_orders.uploads.pdf', $upload) }}" target="_blank" class="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                Open Original PDF ↗
            </a>
            <a href="{{ route('work_orders.uploads.create') }}" class="px-4 py-2 rounded-lg border border-slate-300 dark:border-slate-700 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                ← Upload Another
            </a>
        </div>
    </div>

    @if ($upload->is_scanned_or_unreadable)
        <div class="rounded-xl border-2 border-red-400 dark:border-red-700 bg-red-50 dark:bg-red-950/50 p-5 space-y-3">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-red-900 dark:text-red-200">Scanned or Non-Standard PDF Detected</h2>
                    <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                        {{ $validationErrors['unreadable'] ?? 'This PDF appears to be a scanned image or uses another client layout. You can enter the header and line items manually below, import BOQ lines from CSV/Excel, or use the manual work order form.' }}
                    </p>
                </div>
                <a href="{{ route('work_orders.create') }}" class="shrink-0 px-4 py-2 rounded-lg bg-red-700 text-white text-xs font-semibold hover:bg-red-600">
                    Open Manual Entry Form →
                </a>
            </div>

            <form action="{{ route('work_orders.uploads.import_lines', $upload) }}" method="POST" enctype="multipart/form-data" class="flex flex-wrap items-center gap-3 pt-2 border-t border-red-200 dark:border-red-800">
                @csrf
                <label class="text-xs font-semibold text-red-900 dark:text-red-200">Import BOQ Lines (CSV / Excel):</label>
                <input type="file" name="lines_file" accept=".csv,.xlsx,.xls" required class="text-xs text-slate-700 dark:text-slate-200">
                <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-xs font-semibold">
                    Import Lines
                </button>
            </form>
        </div>
    @endif

    @if ($hasMismatches)
        <div class="rounded-xl border-2 border-red-500 bg-red-50 dark:bg-red-950/60 p-5 text-red-800 dark:text-red-200">
            <h2 class="text-base font-bold flex items-center gap-2">
                <span>Validation Mismatch — Confirm Blocked</span>
            </h2>
            <p class="text-xs mt-1">
                One or more arithmetic checks failed. Fix the quantities/rates/totals below and click <strong>Save Review Changes</strong>, or provide a mandatory override reason when confirming.
            </p>
            <ul class="mt-2 list-disc list-inside text-sm font-medium space-y-1">
                @foreach ($validationErrors as $key => $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </div>
    @else
        <div class="rounded-xl border border-emerald-300 dark:border-emerald-800 bg-emerald-50 dark:bg-emerald-950/40 p-4 text-sm text-emerald-800 dark:text-emerald-200 flex items-center justify-between">
            <div>
                <strong>Arithmetic Verification Passed:</strong> Sum of {{ count($items) }} line totals equals Base Value (₹{{ number_format((float) ($totals['base_value'] ?? 0), 2) }}), CGST + SGST equals Total Taxes (₹{{ number_format((float) ($totals['total_taxes'] ?? 0), 2) }}), and Base + Taxes equals Total PO Value (₹{{ number_format((float) ($totals['total_po_value'] ?? 0), 2) }}).
            </div>
            <span class="px-3 py-1 rounded-full bg-emerald-600 text-white text-xs font-bold">READY TO CONFIRM</span>
        </div>
    @endif

    @if ($existingPoWorkOrder)
        <div class="rounded-xl border border-amber-300 dark:border-amber-700 bg-amber-50 dark:bg-amber-950/40 p-4 text-sm text-amber-900 dark:text-amber-200">
            <strong>Existing PO Number Detected ({{ $existingPoWorkOrder->po_number }}):</strong>
            Work Order <a href="{{ route('work_orders.show', $existingPoWorkOrder) }}" class="underline font-semibold">{{ $existingPoWorkOrder->order_number }}</a> already exists for site <strong>{{ $existingPoWorkOrder->site?->name }}</strong>. Confirming this upload will apply it as <strong>Revision #{{ $existingPoWorkOrder->current_revision + 1 }}</strong> and will not create a duplicate site.
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
        <!-- Left 8 columns: Editable Review Form -->
        <div class="lg:col-span-8 space-y-6">
            <form action="{{ route('work_orders.uploads.update_review', $upload) }}" method="POST" class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm p-6 space-y-6">
                @csrf
                @method('PUT')

                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white">1. Order Header & Contract Terms</h2>
                    <label class="inline-flex items-center gap-2 text-xs font-semibold text-amber-700 dark:text-amber-300">
                        <input type="hidden" name="is_draft_po" value="0">
                        <input type="checkbox" name="is_draft_po" value="1" @checked($upload->is_draft_po) class="rounded border-slate-300 text-amber-600">
                        <span>Draft PO (Billing Locked)</span>
                    </label>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-sm">
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">PO Number *</label>
                        <input type="text" name="header[po_number]" value="{{ old('header.po_number', $header['po_number'] ?? '') }}" required class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Full PO Reference</label>
                        <input type="text" name="header[client_reference]" value="{{ old('header.client_reference', $header['client_reference'] ?? '') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">PO Date</label>
                        <input type="date" name="header[po_date]" value="{{ old('header.po_date', $header['po_date'] ?? '') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Subject (Site Name Source)</label>
                        <input type="text" name="header[subject]" value="{{ old('header.subject', $header['subject'] ?? '') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Completion Date</label>
                        <input type="date" name="header[completion_date]" value="{{ old('header.completion_date', $header['completion_date'] ?? '') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Effective Date</label>
                        <input type="date" name="header[effective_date]" value="{{ old('header.effective_date', $header['effective_date'] ?? '') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">User Dept Person</label>
                        <input type="text" name="header[user_department_person]" value="{{ old('header.user_department_person', $header['user_department_person'] ?? '') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Buyer</label>
                        <input type="text" name="header[buyer_name]" value="{{ old('header.buyer_name', $header['buyer_name'] ?? '') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Service Type</label>
                        <input type="text" name="header[service_type]" value="{{ old('header.service_type', $header['service_type'] ?? 'JBN') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Price Basis</label>
                        <input type="text" name="header[price_basis]" value="{{ old('header.price_basis', $header['price_basis'] ?? '') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Contract Tenure</label>
                        <input type="text" name="header[contract_tenure]" value="{{ old('header.contract_tenure', $header['contract_tenure'] ?? '12 Months') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Retention (%)</label>
                        <input type="number" step="0.01" name="header[retention_percent]" value="{{ old('header.retention_percent', $header['retention_percent'] ?? 5) }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Defect Liability (Months)</label>
                        <input type="number" name="header[defect_liability_months]" value="{{ old('header.defect_liability_months', $header['defect_liability_months'] ?? 12) }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Liquidated Damages</label>
                        <input type="text" name="header[liquidated_damages]" value="{{ old('header.liquidated_damages', $header['liquidated_damages'] ?? 'NA') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Payment Terms</label>
                        <input type="text" name="header[payment_terms]" value="{{ old('header.payment_terms', $header['payment_terms'] ?? '') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Client Name</label>
                        <input type="text" name="header[client_name]" value="{{ old('header.client_name', $header['client_name'] ?? 'JSW Steel Limited') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Client Plant GSTIN</label>
                        <input type="text" name="header[client_gstin]" value="{{ old('header.client_gstin', $header['client_gstin'] ?? '') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Vendor Bank (Masked)</label>
                        <input type="text" readonly value="{{ $vendor['bank_account_masked'] ?? 'N/A' }}" class="w-full rounded-lg border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-800/50 text-xs font-mono text-slate-500">
                    </div>
                    <div class="sm:col-span-3">
                        <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Client Plant Address (Site Address Source)</label>
                        <input type="text" name="header[client_address]" value="{{ old('header.client_address', $header['client_address'] ?? '') }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                    </div>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-800 pt-4">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-3">2. Extracted Service Lines ({{ count($items) }})</h2>
                    <div class="space-y-4">
                        @forelse ($items as $idx => $line)
                            @php
                                $lineErrorKey = 'line_' . ($line['sr_no'] ?? ($idx + 1));
                                $hasLineError = isset($validationErrors[$lineErrorKey]);
                            @endphp
                            <div class="p-4 rounded-xl border {{ $hasLineError ? 'border-red-500 bg-red-50/40 dark:bg-red-950/30' : 'border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40' }} space-y-3">
                                <div class="grid grid-cols-2 sm:grid-cols-6 gap-3">
                                    <div>
                                        <label class="block text-[11px] font-semibold uppercase text-slate-500">Sr. No.</label>
                                        <input type="text" name="items[{{ $idx }}][sr_no]" value="{{ $line['sr_no'] }}" class="w-full rounded border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-xs font-mono">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label class="block text-[11px] font-semibold uppercase text-slate-500">Service Code</label>
                                        <input type="text" name="items[{{ $idx }}][service_code]" value="{{ $line['service_code'] ?? '' }}" class="w-full rounded border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-xs font-mono">
                                        <input type="hidden" name="items[{{ $idx }}][sac_code]" value="{{ $line['sac_code'] ?? '9954' }}">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold uppercase text-slate-500">Unit</label>
                                        <input type="text" name="items[{{ $idx }}][unit]" value="{{ $line['unit'] ?? 'M3' }}" class="w-full rounded border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-xs font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold uppercase text-slate-500">Ordered Qty</label>
                                        <input type="number" step="0.001" name="items[{{ $idx }}][ordered_qty]" value="{{ $line['ordered_qty'] ?? 0 }}" class="w-full rounded border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-xs font-mono">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold uppercase text-slate-500">Unit Price (₹)</label>
                                        <input type="number" step="0.01" name="items[{{ $idx }}][rate]" value="{{ $line['rate'] ?? 0 }}" class="w-full rounded border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-xs font-mono">
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                                    <div class="sm:col-span-3">
                                        <label class="block text-[11px] font-semibold uppercase text-slate-500">Short Description</label>
                                        <input type="text" name="items[{{ $idx }}][description]" value="{{ $line['description'] ?? '' }}" class="w-full rounded border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-xs">
                                    </div>
                                    <div>
                                        <label class="block text-[11px] font-semibold uppercase text-slate-500">Line Total (₹)</label>
                                        <input type="number" step="0.01" name="items[{{ $idx }}][line_total]" value="{{ $line['line_total'] ?? 0 }}" class="w-full rounded border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-xs font-mono font-bold">
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-semibold uppercase text-slate-500">Full Specification Text (Joined Across Pages)</label>
                                    <textarea name="items[{{ $idx }}][specification_text]" rows="2" class="w-full rounded border-slate-300 dark:border-slate-700 dark:bg-slate-900 text-xs font-mono">{{ $line['specification_text'] ?? '' }}</textarea>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500 italic">No lines extracted yet. Import a CSV/Excel BOQ above or use manual entry.</p>
                        @endforelse
                    </div>
                </div>

                <div class="border-t border-slate-200 dark:border-slate-800 pt-4">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white mb-3">3. Extracted Order Totals</h2>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Total Base Value (₹)</label>
                            <input type="number" step="0.01" name="totals[base_value]" value="{{ $totals['base_value'] ?? 0 }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono font-bold">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">CGST ({{ $totals['cgst_rate'] ?? 9 }}%)</label>
                            <input type="hidden" name="totals[cgst_rate]" value="{{ $totals['cgst_rate'] ?? 9 }}">
                            <input type="number" step="0.01" name="totals[cgst_amount]" value="{{ $totals['cgst_amount'] ?? 0 }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">SGST ({{ $totals['sgst_rate'] ?? 9 }}%)</label>
                            <input type="hidden" name="totals[sgst_rate]" value="{{ $totals['sgst_rate'] ?? 9 }}">
                            <input type="number" step="0.01" name="totals[sgst_amount]" value="{{ $totals['sgst_amount'] ?? 0 }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Total Taxes (₹)</label>
                            <input type="number" step="0.01" name="totals[total_taxes]" value="{{ $totals['total_taxes'] ?? 0 }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 dark:text-slate-400 mb-1">Total PO Value (₹)</label>
                            <input type="number" step="0.01" name="totals[total_po_value]" value="{{ $totals['total_po_value'] ?? 0 }}" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm font-mono font-bold">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-2">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-slate-900 dark:bg-slate-100 text-white dark:text-slate-900 text-sm font-semibold hover:opacity-90">
                        Save Review Changes & Re-Validate
                    </button>
                </div>
            </form>

            <!-- Confirm Box -->
            <div class="bg-white dark:bg-slate-900 rounded-xl border-2 {{ $hasMismatches ? 'border-red-400 dark:border-red-700' : 'border-emerald-500 dark:border-emerald-600' }} shadow-sm p-6">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">4. Confirm & Provision Work Order + Site</h2>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                    Confirming runs in a single database transaction: creates the Client (if new), Work Order, all {{ count($items) }} line items, and provisions a new Site in <span class="font-mono font-semibold">setup_pending</span> status (unless attached to an existing site or updating an existing PO revision).
                </p>

                <form action="{{ route('work_orders.uploads.confirm', $upload) }}" method="POST" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="is_draft_po" value="{{ $upload->is_draft_po ? '1' : '0' }}">

                    @if (!$existingPoWorkOrder)
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
                                Site Provisioning Option
                            </label>
                            <select name="attach_to_site_id" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 text-sm">
                                <option value="">Create a NEW Site in "Setup pending" named "{{ $header['subject'] ?: ($header['order_short_description'] ?: 'PO ' . ($header['po_number'] ?? '')) }}" (Recommended)</option>
                                @foreach ($existingSites as $existingSite)
                                    <option value="{{ $existingSite->id }}">Attach to existing site: {{ $existingSite->name }} ({{ $existingSite->code }})</option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($hasMismatches)
                        <div>
                            <label class="block text-xs font-semibold text-red-700 dark:text-red-300 mb-1">
                                Mandatory Override Reason (Required only if confirming with validation mismatches)
                            </label>
                            <input
                                type="text"
                                name="override_reason"
                                placeholder="Explain why totals differ from line items if overriding..."
                                class="w-full rounded-lg border-red-300 dark:border-red-700 dark:bg-slate-800 text-sm"
                            >
                        </div>
                    @endif

                    <div class="flex items-center justify-end gap-3">
                        <button
                            type="submit"
                            class="px-6 py-2.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-sm"
                        >
                            {{ $existingPoWorkOrder ? 'Confirm as Revision #' . ($existingPoWorkOrder->current_revision + 1) : 'Confirm & Create Work Order + Site' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right 4 columns: Original PDF Preview -->
        <div class="lg:col-span-4">
            <div class="sticky top-6 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-slate-600 dark:text-slate-300">Original PDF Preview</span>
                    <a href="{{ route('work_orders.uploads.pdf', $upload) }}" target="_blank" class="text-xs font-semibold text-amber-600 hover:underline">Full Screen ↗</a>
                </div>
                <iframe
                    src="{{ route('work_orders.uploads.pdf', $upload) }}"
                    class="w-full h-[760px] bg-slate-100 dark:bg-slate-950"
                    title="Uploaded Work Order PDF"
                ></iframe>
            </div>
        </div>
    </div>
</div>
@endsection


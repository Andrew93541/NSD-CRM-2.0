<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Order — {{ $workOrder->order_number }} (Rev {{ $workOrder->current_revision ?? 0 }})</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 p-4 sm:p-8 font-sans">
    <div class="max-w-5xl mx-auto space-y-4">
        <div class="no-print flex flex-wrap items-center justify-between gap-3 bg-slate-900 text-white px-5 py-3 rounded-xl">
            <div class="text-xs">
                <span class="font-bold text-amber-400">{{ __('JSW / NSD Service Order & BOQ Document') }}</span>
                &bull; {{ $workOrder->order_number }}
                &bull; Rev {{ $workOrder->current_revision ?? 0 }}
            </div>
            <div class="flex items-center gap-2">
                <a href="{{ route('work_orders.show', $workOrder) }}" class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition">
                    &larr; {{ __('Back') }}
                </a>
                <a href="{{ route('work_orders.export_pdf', $workOrder) }}" class="px-4 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-slate-950 text-xs font-extrabold transition">
                    {{ __('Download Formal PDF (Private Vault)') }} &darr;
                </a>
                <button onclick="window.print()" class="px-4 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold transition">
                    {{ __('Print Browser Copy') }}
                </button>
            </div>
        </div>

        <div class="bg-white border-2 border-slate-900 rounded-xl p-6 sm:p-10 shadow-sm space-y-6">
            <!-- Header / Letterhead -->
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-6 border-b-2 border-slate-900">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-widest text-amber-600">{{ __('JSW Service Order / NSD Work Order Format') }}</div>
                    <h1 class="text-2xl font-black text-slate-900 uppercase mt-0.5">{{ $workOrder->company?->name ?? 'NSD Earth Movers & Construction' }}</h1>
                    @if($workOrder->company?->registered_address)
                        <p class="text-xs text-slate-600 mt-0.5">{{ $workOrder->company->registered_address }}</p>
                    @endif
                    <p class="text-xs text-slate-600 mt-1 font-mono">
                        GSTIN: <strong>{{ $workOrder->company?->gstin ?? '—' }}</strong> &bull;
                        PAN: <strong>{{ $workOrder->company?->pan ?? '—' }}</strong> &bull;
                        Vendor Code: <strong>{{ $workOrder->company?->vendor_code ?? '—' }}</strong>
                    </p>
                </div>
                <div class="sm:text-right space-y-1">
                    <div class="inline-block px-3.5 py-1 bg-slate-900 text-amber-400 font-mono text-sm font-black uppercase rounded">
                        {{ $workOrder->order_number }} (Rev {{ $workOrder->current_revision ?? 0 }})
                    </div>
                    @if($workOrder->po_number)
                        <div class="text-xs font-mono font-bold text-slate-800">
                            PO / Service Order #: {{ $workOrder->po_number }}
                        </div>
                    @endif
                    <div class="text-xs text-slate-600 font-mono">
                        {{ __('Date') }}: {{ ($workOrder->po_date ?? $workOrder->created_at)->format('d.m.Y') }}
                    </div>
                    <div>
                        @if($workOrder->status === 'senior_manager_approved')
                            <span class="px-2.5 py-0.5 rounded bg-emerald-100 text-emerald-800 border border-emerald-400 text-[11px] font-extrabold uppercase">
                                &#x2713; {{ __('FINAL APPROVED FOR EXECUTION') }}
                            </span>
                        @elseif($workOrder->status === 'site_manager_approved')
                            <span class="px-2.5 py-0.5 rounded bg-sky-100 text-sky-800 border border-sky-400 text-[11px] font-extrabold uppercase">
                                {{ __('TIER 1 SITE APPROVED — PENDING VP') }}
                            </span>
                        @elseif($workOrder->status === 'rejected')
                            <span class="px-2.5 py-0.5 rounded bg-rose-100 text-rose-800 border border-rose-400 text-[11px] font-extrabold uppercase">
                                {{ __('REJECTED') }}
                            </span>
                        @else
                            <span class="px-2.5 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-400 text-[11px] font-extrabold uppercase">
                                {{ __('SUBMITTED — PENDING APPROVAL') }}
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Contractor & JSW Service Order Metadata Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs bg-slate-50 p-4 rounded-lg border border-slate-300">
                <div class="space-y-1.5">
                    <div class="text-[10px] font-extrabold uppercase text-slate-500">{{ __('Client / Executing Contractor Party') }}</div>
                    <div class="font-extrabold text-sm text-slate-900">{{ $workOrder->contractor_name ?: ($workOrder->client?->name ?? __('Client-Issued Direct Execution')) }}</div>
                    @if($workOrder->client?->address)
                        <div class="text-slate-700">{{ $workOrder->client->address }}</div>
                    @endif
                    <div class="font-mono text-slate-700">{{ __('GSTIN') }}: {{ $workOrder->contractor_gst ?: ($workOrder->client?->gstin ?? '—') }}</div>
                    @if($workOrder->contractor_contact)
                        <div class="text-slate-700">{{ __('Contact') }}: {{ $workOrder->contractor_contact }}</div>
                    @endif
                    <div><span class="text-slate-500">{{ __('Project') }}:</span> <span class="font-bold">{{ $workOrder->project?->code }} — {{ $workOrder->project?->name }}</span></div>
                </div>
                <div class="space-y-1.5">
                    <div class="text-[10px] font-extrabold uppercase text-slate-500">{{ __('Service Order & Schedule Particulars') }}</div>
                    <div><span class="text-slate-500">{{ __('PO / Client Ref #') }}:</span> <span class="font-mono font-bold">{{ $workOrder->po_number ?: ($workOrder->client_reference ?: 'N/A') }}</span></div>
                    <div><span class="text-slate-500">{{ __('Job Location') }}:</span> <span class="font-semibold">{{ $workOrder->job_location ?: ($workOrder->site?->name ?: 'Project Wide') }}</span></div>
                    <div><span class="text-slate-500">{{ __('User Dept / Person') }}:</span> <span class="font-semibold">{{ $workOrder->user_department_person ?: '—' }}</span></div>
                    <div><span class="text-slate-500">{{ __('Effective Period') }}:</span> <span class="font-mono font-bold">{{ ($workOrder->effective_date ?? $workOrder->target_start_date)?->format('d.m.Y') ?: 'Immediate' }} to {{ ($workOrder->completion_date ?? $workOrder->target_completion_date)?->format('d.m.Y') ?: 'As per schedule' }}</span></div>
                    <div><span class="text-slate-500">{{ __('Payment Terms') }}:</span> <span class="font-semibold">{{ $workOrder->payment_terms ?: \App\Models\WorkOrder::DEFAULT_PAYMENT_TERMS }}</span></div>
                </div>
            </div>

            <!-- Subject & Scope -->
            <div class="space-y-2 text-xs">
                <div class="font-extrabold text-sm text-slate-900 uppercase border-b border-slate-200 pb-1">
                    {{ __('Subject') }}: {{ $workOrder->title }}
                </div>
                @if($workOrder->description)
                    <p class="text-slate-700 leading-relaxed">{{ $workOrder->description }}</p>
                @endif
                @if($workOrder->scope_of_work)
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded font-mono whitespace-pre-line text-slate-800">{{ $workOrder->scope_of_work }}</div>
                @endif
            </div>

            <!-- JSW Service Order Line Items Table -->
            <div class="space-y-2">
                <div class="text-xs font-extrabold uppercase tracking-wider text-slate-900">{{ __('Schedule of Service Line Items & Rates (BOQ)') }}</div>
                <div class="border border-slate-900 rounded-lg overflow-hidden">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-900 text-white uppercase font-bold">
                            <tr>
                                <th class="px-3 py-2.5 w-16">{{ __('Sr. No.') }}</th>
                                <th class="px-3 py-2.5 w-24">{{ __('Service Code') }}</th>
                                <th class="px-3 py-2.5 w-20">{{ __('SAC') }}</th>
                                <th class="px-3 py-2.5">{{ __('Item Description & Specification') }}</th>
                                <th class="px-3 py-2.5 w-16">{{ __('UOM') }}</th>
                                <th class="px-3 py-2.5 text-right w-24">{{ __('Qty') }}</th>
                                <th class="px-3 py-2.5 text-right w-28">{{ __('Rate (₹)') }}</th>
                                <th class="px-3 py-2.5 text-right w-32">{{ __('Amount (₹)') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-300">
                            @if($workOrder->items->isNotEmpty())
                                @foreach($workOrder->items as $item)
                                    <tr>
                                        <td class="px-3 py-2.5 font-mono font-bold">{{ $item->sr_no }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ $item->service_code ?? '—' }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ $item->sac_code ?? '—' }}</td>
                                        <td class="px-3 py-2.5">
                                            <div class="font-semibold">{{ $item->description }}</div>
                                            @if($item->specification)
                                                <div class="text-[11px] text-slate-600 mt-0.5 whitespace-pre-line">{{ $item->specification }}</div>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2.5 font-mono">{{ $item->unit }}</td>
                                        <td class="px-3 py-2.5 text-right font-mono">{{ number_format((float) $item->ordered_quantity, 3) }}</td>
                                        <td class="px-3 py-2.5 text-right font-mono">₹{{ number_format((float) $item->rate, 2) }}</td>
                                        <td class="px-3 py-2.5 text-right font-mono font-bold">₹{{ number_format((float) $item->line_amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            @elseif(!empty($workOrder->boq_items) && is_array($workOrder->boq_items))
                                @foreach($workOrder->boq_items as $idx => $item)
                                    <tr>
                                        <td class="px-3 py-2.5 font-mono font-bold">{{ $item['sr_no'] ?? ($idx + 1) }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ $item['service_code'] ?? '—' }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ $item['sac_code'] ?? '995419' }}</td>
                                        <td class="px-3 py-2.5 font-semibold">{{ $item['description'] ?? '' }}</td>
                                        <td class="px-3 py-2.5 font-mono">{{ $item['unit'] ?? 'Cum' }}</td>
                                        <td class="px-3 py-2.5 text-right font-mono">{{ number_format((float) ($item['quantity'] ?? 0), 3) }}</td>
                                        <td class="px-3 py-2.5 text-right font-mono">₹{{ number_format((float) ($item['rate'] ?? 0), 2) }}</td>
                                        <td class="px-3 py-2.5 text-right font-mono font-bold">₹{{ number_format((float) ($item['amount'] ?? 0), 2) }}</td>
                                    </tr>
                                @endforeach
                            @endif
                        </tbody>
                        @php
                            $baseVal = (float) ($workOrder->base_order_value ?: $workOrder->estimated_amount);
                            $cgstVal = $workOrder->cgstAmount();
                            $sgstVal = $workOrder->sgstAmount();
                            $grossVal = round($baseVal + $cgstVal + $sgstVal, 2);
                        @endphp
                        <tfoot class="bg-slate-100 border-t-2 border-slate-900 font-bold">
                            <tr>
                                <td colspan="7" class="px-3 py-2 text-right uppercase">{{ __('Total Basic Value (A)') }}</td>
                                <td class="px-3 py-2 text-right font-mono">₹{{ number_format($baseVal, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="7" class="px-3 py-1.5 text-right text-slate-600">{{ __('Add: CGST') }} ({{ number_format((float) $workOrder->cgst_rate, 2) }}%) + {{ __('SGST') }} ({{ number_format((float) $workOrder->sgst_rate, 2) }}%)</td>
                                <td class="px-3 py-1.5 text-right font-mono">₹{{ number_format($cgstVal + $sgstVal, 2) }}</td>
                            </tr>
                            <tr class="bg-amber-50 font-extrabold">
                                <td colspan="7" class="px-3 py-2.5 text-right uppercase">{{ __('Total Gross Service Order Value (Incl. GST)') }}</td>
                                <td class="px-3 py-2.5 text-right font-mono text-sm">₹{{ number_format($grossVal, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="p-3 rounded-lg bg-slate-50 border border-slate-300 text-xs font-bold">
                    {{ __('Order Value in Words') }}: {{ \App\Support\IndianCurrencyWords::toWords($grossVal) }}
                </div>
            </div>

            <!-- Terms & Conditions -->
            <div class="space-y-1.5 text-xs">
                <div class="font-extrabold uppercase text-slate-900">{{ __('Terms & Conditions') }}</div>
                <div class="text-[11px] text-slate-700 whitespace-pre-line leading-relaxed bg-slate-50 p-3.5 rounded-lg border border-slate-200">{{ $workOrder->terms_and_conditions ?: \App\Models\WorkOrder::DEFAULT_TERMS }}</div>
            </div>

            <!-- Two-Tier Digital Approval Stamps -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-4 border-t-2 border-slate-900 text-xs">
                <div class="p-3 rounded-lg border border-slate-300 bg-slate-50">
                    <div class="text-[10px] font-bold uppercase text-slate-500">{{ __('Prepared & Submitted By') }}</div>
                    <div class="font-extrabold text-slate-900 mt-1">{{ $workOrder->creator?->name ?? 'System' }}</div>
                    <div class="font-mono text-[11px] text-slate-500">{{ $workOrder->created_at->format('d M Y, H:i') }}</div>
                </div>

                <div class="p-3 rounded-lg border {{ $workOrder->site_manager_approved_at ? 'border-emerald-500 bg-emerald-50/50' : 'border-slate-300 bg-slate-50' }}">
                    <div class="text-[10px] font-bold uppercase {{ $workOrder->site_manager_approved_at ? 'text-emerald-700' : 'text-slate-500' }}">
                        {{ __('Tier 1: Site Manager Approval') }}
                    </div>
                    @if($workOrder->site_manager_approved_at)
                        <div class="font-extrabold text-slate-900 mt-1">{{ $workOrder->siteManager?->name }}</div>
                        <div class="font-mono text-[11px] text-emerald-700">&#x2713; {{ $workOrder->site_manager_approved_at->format('d M Y, H:i') }}</div>
                    @else
                        <div class="text-slate-400 italic mt-1">{{ __('Pending Tier 1 Sign-off') }}</div>
                    @endif
                </div>

                <div class="p-3 rounded-lg border {{ $workOrder->senior_manager_approved_at ? 'border-emerald-600 bg-emerald-50' : 'border-slate-300 bg-slate-50' }}">
                    <div class="text-[10px] font-bold uppercase {{ $workOrder->senior_manager_approved_at ? 'text-emerald-800' : 'text-slate-500' }}">
                        {{ __('Tier 2: Senior VP Authorization') }}
                    </div>
                    @if($workOrder->senior_manager_approved_at)
                        <div class="font-extrabold text-slate-900 mt-1">{{ $workOrder->seniorManager?->name }}</div>
                        <div class="font-mono text-[11px] text-emerald-800">&#x2713; {{ $workOrder->senior_manager_approved_at->format('d M Y, H:i') }}</div>
                    @else
                        <div class="text-slate-400 italic mt-1">{{ __('Pending Tier 2 Sign-off') }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</body>
</html>

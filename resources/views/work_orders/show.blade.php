@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-amber-600 dark:text-amber-400 border border-slate-200 dark:border-slate-700">{{ $workOrder->order_number }}</span>
                @if($workOrder->po_number)
                    <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 border border-emerald-500/30">
                        PO #{{ $workOrder->po_number }}
                    </span>
                @endif
                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-indigo-500/10 text-indigo-600 dark:text-indigo-400">
                    Rev {{ $workOrder->current_revision ?? 0 }}
                </span>
                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase {{ $workOrder->order_type === 'client_issued' ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400' }}">
                    {{ $workOrder->order_type === 'client_issued' ? __('Client Issued') : __('Internal Work') }}
                </span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $workOrder->title }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ $workOrder->project->name }} &bull; {{ $workOrder->site->name ?? __('Project Wide') }}
                @if($workOrder->client)
                    &bull; <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $workOrder->client->name }}</span>
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('work_orders.index') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:border-slate-700 transition">
                &larr; {{ __('Back to Orders') }}
            </a>
            <a href="{{ route('work_orders.export_pdf', $workOrder) }}" class="px-3.5 py-2 text-xs font-semibold rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-800 transition">
                {{ __('Download JSW Service Order PDF') }} &darr;
            </a>
            <a href="{{ route('work_orders.print', $workOrder) }}" target="_blank" class="px-4 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-sm transition">
                {{ __('Print / Download Work Order (BOQ)') }} &nearr;
            </a>
        </div>
    </div>

    <!-- Phase G & H: Work Order Lifecycle, Draft PO Lock, Alerts & Per-Site Summary -->
    @php
        $lifecycleStatus = $workOrder->effectiveLifecycleStatus();
        $lifecycleLabel = \App\Models\WorkOrder::LIFECYCLE_STATUSES[$lifecycleStatus] ?? ucfirst(str_replace('_', ' ', $lifecycleStatus));
        $completionAlert = $workOrder->completionAlertLevel();
        $dlpSummary = $workOrder->retentionAndDlpSummary();
        $siteSummary = $workOrder->site ? $workOrder->site->workOrderSummary() : null;
    @endphp

    @if($workOrder->isDraftPo())
        <div class="rounded-2xl border-2 border-amber-400 dark:border-amber-600 bg-amber-50 dark:bg-amber-950/40 p-4 text-xs text-amber-900 dark:text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <div class="font-extrabold uppercase tracking-wider text-amber-700 dark:text-amber-300">Draft PO Status — Field Tracking Active, Billing Locked</div>
                <p class="mt-0.5">Site setup and field measurements are allowed against this Draft PO, but raising RA Bills is blocked until the final PO is uploaded or confirmed.</p>
            </div>
            @can('uploadPo', \App\Models\WorkOrder::class)
                <a href="{{ route('work_orders.uploads.create') }}" class="shrink-0 px-3.5 py-2 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold">
                    Upload Final PO Revision →
                </a>
            @endcan
        </div>
    @endif

    @if($completionAlert !== 'normal')
        <div class="rounded-2xl border {{ $completionAlert === 'overdue' ? 'border-red-400 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-200' : 'border-amber-400 bg-amber-50 dark:bg-amber-950/40 text-amber-900 dark:text-amber-200' }} p-4 text-xs font-semibold flex items-center justify-between">
            <span>
                @if($completionAlert === 'overdue')
                    Completion Date Overdue: Target completion date ({{ optional($workOrder->completion_date ?? $workOrder->target_completion_date)->format('d M Y') }}) has passed.
                @elseif($completionAlert === 'due_7_days')
                    Completion Date Warning (Within 7 Days): Order completion due on {{ optional($workOrder->completion_date ?? $workOrder->target_completion_date)->format('d M Y') }}.
                @else
                    Completion Date Notice (Within 30 Days): Order completion due on {{ optional($workOrder->completion_date ?? $workOrder->target_completion_date)->format('d M Y') }}.
                @endif
            </span>
            <span class="px-2.5 py-1 rounded-full text-[11px] font-bold uppercase bg-white/80 dark:bg-slate-900/80">{{ strtoupper(str_replace('_', ' ', $completionAlert)) }}</span>
        </div>
    @endif

    <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Contract Lifecycle Stage</span>
                <div class="flex items-center gap-2 mt-1">
                    <span class="px-3 py-1 rounded-full text-xs font-extrabold {{ $workOrder->isDraftPo() ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300' }}">
                        {{ $lifecycleLabel }}
                    </span>
                    <span class="text-xs text-slate-500">
                        Measured: <strong>{{ number_format($workOrder->measuredConsumptionPercent(), 1) }}%</strong> &bull;
                        Billed: <strong>{{ number_format($workOrder->consumptionPercent(), 1) }}%</strong>
                    </span>
                </div>
            </div>

            @can('transitionLifecycle', $workOrder)
                <form action="{{ route('work_orders.lifecycle', $workOrder) }}" method="POST" class="flex items-center gap-2">
                    @csrf
                    <select name="lifecycle_status" class="rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-800 text-xs px-3 py-1.5 font-semibold">
                        @foreach(\App\Models\WorkOrder::LIFECYCLE_STATUSES as $statusKey => $statusText)
                            <option value="{{ $statusKey }}" @selected($lifecycleStatus === $statusKey)>{{ $statusText }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-slate-900 dark:bg-white text-white dark:text-slate-900 text-xs font-bold">
                        Update Stage
                    </button>
                </form>
            @endcan
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950">
                <div class="text-slate-500">Retention ({{ number_format($dlpSummary['retention_percent'], 1) }}%) Held</div>
                <div class="text-sm font-extrabold text-slate-900 dark:text-white mt-0.5">₹{{ number_format($dlpSummary['retention_held_amount'], 2) }}</div>
            </div>
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950">
                <div class="text-slate-500">Defect Liability ({{ $dlpSummary['defect_liability_months'] }}M)</div>
                <div class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ $dlpSummary['defect_liability_ends_on'] ?? 'Starts on completion' }}</div>
            </div>
            @if($siteSummary)
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950">
                    <div class="text-slate-500">Site Manpower / Measured Qty</div>
                    <div class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">{{ $siteSummary['manpower_days'] }} days / {{ number_format($siteSummary['measured_quantity'], 3) }}</div>
                </div>
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950">
                    <div class="text-slate-500">Site Order Balance</div>
                    <div class="text-sm font-extrabold text-emerald-600 dark:text-emerald-400 mt-0.5">₹{{ number_format($siteSummary['balance_amount'], 2) }}</div>
                </div>
            @endif
        </div>
    </div>

    <!-- 2-Tier Approval Stepper -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm transition-colors duration-200">
        <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Multi-Tier Approval Lifecycle') }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
            <!-- Stage 1: Submission -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-bold mb-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    1. {{ __('Registered') }}
                </div>
                <div class="text-slate-800 dark:text-slate-300 font-semibold">{{ $workOrder->creator->name }}</div>
                <div class="text-[11px] text-slate-500">{{ $workOrder->created_at->format('d M Y, H:i') }}</div>
            </div>

            <!-- Stage 2: Site Manager Approval -->
            <div class="p-4 rounded-xl {{ $workOrder->site_manager_approved_at ? 'bg-slate-50 dark:bg-slate-950 border-emerald-500/40' : ($workOrder->status === 'submitted' ? 'bg-amber-500/10 border-amber-500/40' : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800') }} border">
                <div class="flex items-center gap-2 {{ $workOrder->site_manager_approved_at ? 'text-emerald-600 dark:text-emerald-400' : ($workOrder->status === 'submitted' ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500') }} font-bold mb-1">
                    @if($workOrder->site_manager_approved_at)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @endif
                    2. {{ __('Site Manager') }} ({{ __('Tier 1') }})
                </div>
                @if($workOrder->site_manager_approved_at)
                    <div class="text-slate-800 dark:text-slate-300 font-semibold">{{ $workOrder->siteManager->name }}</div>
                    <div class="text-[11px] text-slate-500">{{ $workOrder->site_manager_approved_at->format('d M Y, H:i') }}</div>
                    @if($workOrder->site_manager_notes)
                        <div class="text-[11px] text-slate-600 dark:text-slate-400 mt-1 italic">"{{ $workOrder->site_manager_notes }}"</div>
                    @endif
                @else
                    <div class="text-slate-500 dark:text-slate-400">{{ __('Awaiting Site Verification') }}</div>
                @endif
            </div>

            <!-- Stage 3: Senior Manager Approval -->
            <div class="p-4 rounded-xl {{ $workOrder->senior_manager_approved_at ? 'bg-slate-50 dark:bg-slate-950 border-emerald-500/40' : ($workOrder->status === 'site_manager_approved' ? 'bg-sky-500/10 border-sky-500/40' : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800') }} border">
                <div class="flex items-center gap-2 {{ $workOrder->senior_manager_approved_at ? 'text-emerald-600 dark:text-emerald-400' : ($workOrder->status === 'site_manager_approved' ? 'text-sky-600 dark:text-sky-400' : 'text-slate-500') }} font-bold mb-1">
                    @if($workOrder->senior_manager_approved_at)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @endif
                    3. {{ __('Senior VP') }} ({{ __('Tier 2') }})
                </div>
                @if($workOrder->senior_manager_approved_at)
                    <div class="text-slate-800 dark:text-slate-300 font-semibold">{{ $workOrder->seniorManager->name }}</div>
                    <div class="text-[11px] text-slate-500">{{ $workOrder->senior_manager_approved_at->format('d M Y, H:i') }}</div>
                    @if($workOrder->senior_manager_notes)
                        <div class="text-[11px] text-slate-600 dark:text-slate-400 mt-1 italic">"{{ $workOrder->senior_manager_notes }}"</div>
                    @endif
                @else
                    <div class="text-slate-500 dark:text-slate-400">{{ __('Awaiting Executive Sign-off') }}</div>
                @endif
            </div>
        </div>

        <!-- Approval Action Buttons (Role Protected) -->
        <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-3">
            @can('approveSite', $workOrder)
                <form method="POST" action="{{ route('work_orders.approve_site', $workOrder) }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    @csrf
                    <input type="text" name="notes" placeholder="{{ __('Site manager verification notes...') }}" class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white outline-none w-full sm:w-64">
                    <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition">
                        {{ __('Tier 1: Approve as Site Manager') }}
                    </button>
                </form>
            @endcan

            @can('approveSenior', $workOrder)
                <form method="POST" action="{{ route('work_orders.approve_senior', $workOrder) }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    @csrf
                    <input type="text" name="notes" placeholder="{{ __('Senior manager authorization notes...') }}" class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white outline-none w-full sm:w-64">
                    <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 transition">
                        {{ __('Tier 2: Final Executive Authorization') }}
                    </button>
                </form>
            @endcan

            @can('reject', $workOrder)
                <form method="POST" action="{{ route('work_orders.reject', $workOrder) }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    @csrf
                    <input type="text" name="rejection_reason" required placeholder="{{ __('Mandatory rejection reason...') }}" class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-rose-300 dark:border-rose-900/60 px-3 py-2 text-xs text-slate-900 dark:text-white outline-none w-full sm:w-64">
                    <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-rose-100 hover:bg-rose-200 text-rose-800 border border-rose-300 dark:bg-rose-900/80 dark:hover:bg-rose-800 dark:text-rose-200 dark:border-rose-700 transition">
                        {{ __('Reject Work Order') }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <!-- JSW Service Order & Specifications Card -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm transition-colors duration-200">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Service Order Header & Specifications') }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
            <div>
                <span class="text-slate-500">{{ __('Client / Issuer') }}:</span>
                <span class="font-semibold text-slate-900 dark:text-white ml-2">{{ $workOrder->client?->name ?? ($workOrder->client_reference ?? __('Internal Order')) }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ __('PO / Service Order #') }}:</span>
                <span class="font-mono text-slate-900 dark:text-white ml-2">{{ $workOrder->po_number ?? $workOrder->client_reference ?? '—' }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ __('PO Date') }}:</span>
                <span class="text-slate-900 dark:text-white ml-2">{{ $workOrder->po_date?->format('d.m.Y') ?? '—' }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ __('Job Location') }}:</span>
                <span class="text-slate-900 dark:text-white ml-2">{{ $workOrder->job_location ?? $workOrder->site?->name ?? '—' }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ __('User Dept / Person') }}:</span>
                <span class="text-slate-900 dark:text-white ml-2">{{ $workOrder->user_department_person ?? '—' }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ __('Current Revision') }}:</span>
                <span class="font-mono font-bold text-indigo-600 dark:text-indigo-400 ml-2">Rev {{ $workOrder->current_revision ?? 0 }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ __('Base Order Value (A)') }}:</span>
                <span class="font-mono text-amber-600 dark:text-amber-400 font-bold ml-2">₹{{ number_format((float) ($workOrder->base_order_value ?: $workOrder->estimated_amount), 2) }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ __('Effective Period') }}:</span>
                <span class="text-slate-800 dark:text-white ml-2">
                    {{ ($workOrder->effective_date ?? $workOrder->target_start_date)?->format('d.m.Y') ?? '—' }}
                    to
                    {{ ($workOrder->completion_date ?? $workOrder->target_completion_date)?->format('d.m.Y') ?? '—' }}
                </span>
            </div>
            <div>
                <span class="text-slate-500">{{ __('Tax & Retention') }}:</span>
                <span class="font-mono text-slate-800 dark:text-white ml-2">
                    @if($workOrder->isIgst())
                        IGST {{ number_format((float) $workOrder->igst_rate, 1) }}% | Ret {{ number_format((float) $workOrder->retention_percent, 1) }}%
                    @else
                        CGST {{ number_format((float) $workOrder->cgst_rate, 1) }}% + SGST {{ number_format((float) $workOrder->sgst_rate, 1) }}% | Ret {{ number_format((float) $workOrder->retention_percent, 1) }}%
                    @endif
                </span>
            </div>
        </div>

        @if($workOrder->description)
            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-xs">
                <span class="text-slate-500 block mb-1">{{ __('Description') }}:</span>
                <p class="text-slate-700 dark:text-slate-300 leading-relaxed">{{ $workOrder->description }}</p>
            </div>
        @endif

        @if($workOrder->scope_of_work)
            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-xs">
                <span class="text-slate-500 block mb-1">{{ __('Scope of Work') }}:</span>
                <div class="text-slate-800 dark:text-slate-300 font-mono whitespace-pre-line bg-slate-50 dark:bg-slate-950 p-3 rounded-xl border border-slate-200 dark:border-slate-800">{{ $workOrder->scope_of_work }}</div>
            </div>
        @endif

        <!-- Structured Work Order Line Items (Phase A `work_order_items` or fallback `boq_items`) -->
        @if($workOrder->items->isNotEmpty())
            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-xs space-y-2">
                <span class="text-slate-500 font-bold uppercase block">{{ __('Service Order Line Items (BOQ Schedule)') }}:</span>
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100 dark:bg-slate-950 text-slate-500 uppercase font-bold">
                            <tr>
                                <th class="px-3 py-2">{{ __('Sr. No.') }}</th>
                                <th class="px-3 py-2">{{ __('Service Code') }}</th>
                                <th class="px-3 py-2">{{ __('SAC') }}</th>
                                <th class="px-3 py-2">{{ __('Description & Specification') }}</th>
                                <th class="px-3 py-2">{{ __('UOM') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Ordered Qty') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Billed Qty') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Balance Qty') }}</th>
                                <th class="px-3 py-2 text-center">{{ __('Consumption') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Rate (₹)') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Amount (₹)') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            @foreach($workOrder->items as $item)
                                @php
                                    $billedQty = $item->approvedBilledQuantity();
                                    $balanceQty = $item->balanceQuantity();
                                    $excessQty = $item->latestUnbilledExcessQuantity();
                                    $usagePct = $item->consumptionPercent();
                                    $warn = $item->warningLevel();
                                    $barClass = match($warn) {
                                        'cap_100' => 'bg-rose-500',
                                        'warning_90' => 'bg-orange-500',
                                        'warning_80' => 'bg-amber-500',
                                        default => 'bg-emerald-500',
                                    };
                                    $pillClass = match($warn) {
                                        'cap_100' => 'bg-rose-500/15 text-rose-600 dark:text-rose-400 border-rose-500/30',
                                        'warning_90' => 'bg-orange-500/15 text-orange-600 dark:text-orange-400 border-orange-500/30',
                                        'warning_80' => 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30',
                                        default => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30',
                                    };
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 font-mono font-bold text-amber-600 dark:text-amber-400">{{ $item->sr_no }}</td>
                                    <td class="px-3 py-2 font-mono text-slate-600 dark:text-slate-400">{{ $item->service_code ?? '—' }}</td>
                                    <td class="px-3 py-2 font-mono text-slate-600 dark:text-slate-400">{{ $item->sac_code ?? '—' }}</td>
                                    <td class="px-3 py-2">
                                        <div class="font-semibold text-slate-900 dark:text-white">{{ $item->description }}</div>
                                        @if($item->specification)
                                            <div class="text-[11px] text-slate-500 mt-0.5">{{ $item->specification }}</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 font-mono text-slate-600 dark:text-slate-300">{{ $item->unit }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ number_format((float) $item->ordered_quantity, 3) }}</td>
                                    <td class="px-3 py-2 text-right font-mono text-emerald-600 dark:text-emerald-400 font-semibold">{{ number_format($billedQty, 3) }}</td>
                                    <td class="px-3 py-2 text-right font-mono">
                                        {{ number_format($balanceQty, 3) }}
                                        @if($excessQty > 0)
                                            <div class="text-[10px] font-bold text-rose-600 dark:text-rose-400">+{{ number_format($excessQty, 3) }} Excess</div>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2">
                                        <div class="w-24 mx-auto space-y-1">
                                            <div class="w-full h-1.5 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                                                <div class="h-full {{ $barClass }}" style="width: {{ $usagePct }}%"></div>
                                            </div>
                                            <div class="text-center">
                                                <span class="px-1.5 py-0.5 rounded border font-mono text-[10px] font-bold {{ $pillClass }}">
                                                    {{ number_format($usagePct, 1) }}%
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono">₹{{ number_format((float) $item->rate, 2) }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format((float) $item->line_amount, 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50 dark:bg-slate-950 font-mono text-xs border-t border-slate-200 dark:border-slate-800">
                            <tr>
                                <td colspan="10" class="px-3 py-2 text-right font-bold text-slate-600 dark:text-slate-400">{{ __('Total Basic Value (A)') }}</td>
                                <td class="px-3 py-2 text-right font-extrabold text-slate-900 dark:text-white">₹{{ number_format((float) $workOrder->base_order_value, 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="10" class="px-3 py-1.5 text-right text-slate-500">{{ __('CGST') }} ({{ number_format((float) $workOrder->cgst_rate, 2) }}%) + {{ __('SGST') }} ({{ number_format((float) $workOrder->sgst_rate, 2) }}%)</td>
                                <td class="px-3 py-1.5 text-right font-bold text-slate-700 dark:text-slate-300">₹{{ number_format($workOrder->cgstAmount() + $workOrder->sgstAmount(), 2) }}</td>
                            </tr>
                            <tr>
                                <td colspan="10" class="px-3 py-2 text-right font-extrabold text-amber-600 dark:text-amber-400">{{ __('Gross Order Value (Incl. GST)') }}</td>
                                <td class="px-3 py-2 text-right font-extrabold text-amber-600 dark:text-amber-400">₹{{ number_format($workOrder->grossOrderValue(), 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        @elseif(!empty($workOrder->boq_items) && is_array($workOrder->boq_items))
            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-xs space-y-2">
                <span class="text-slate-500 font-bold uppercase block">{{ __('Itemized Bill of Quantities (BOQ)') }}:</span>
                <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100 dark:bg-slate-950 text-slate-500 uppercase font-bold">
                            <tr>
                                <th class="px-3 py-2">#</th>
                                <th class="px-3 py-2">{{ __('Description') }}</th>
                                <th class="px-3 py-2">{{ __('Unit') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Qty') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Rate (₹)') }}</th>
                                <th class="px-3 py-2 text-right">{{ __('Amount (₹)') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                            @foreach($workOrder->boq_items as $idx => $item)
                                <tr>
                                    <td class="px-3 py-2 font-mono text-slate-400">{{ $item['sr_no'] ?? ($idx + 1) }}</td>
                                    <td class="px-3 py-2 font-semibold text-slate-900 dark:text-white">{{ $item['description'] ?? '' }}</td>
                                    <td class="px-3 py-2 font-mono text-slate-600 dark:text-slate-300">{{ $item['unit'] ?? 'Cum' }}</td>
                                    <td class="px-3 py-2 text-right font-mono">{{ number_format((float) ($item['quantity'] ?? 0), 2) }}</td>
                                    <td class="px-3 py-2 text-right font-mono">₹{{ number_format((float) ($item['rate'] ?? 0), 2) }}</td>
                                    <td class="px-3 py-2 text-right font-mono font-bold text-slate-900 dark:text-white">₹{{ number_format((float) ($item['amount'] ?? 0), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- Document Attachments -->
        <details class="pt-3 border-t border-slate-200 dark:border-slate-800 text-xs group" {{ $workOrder->attachments->isNotEmpty() ? 'open' : '' }}>
            <summary class="cursor-pointer list-none flex items-center justify-between">
                <span class="text-slate-500 font-bold uppercase">{{ __('Work Order Attachments') }} ({{ $workOrder->attachments->count() }})</span>
                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-[11px] font-semibold text-slate-600 dark:text-slate-300">{{ __('Manage Files') }}</span>
            </summary>

            <div class="space-y-3 pt-3">
                @if($workOrder->attachments->isNotEmpty())
                    <div class="space-y-2">
                        @foreach($workOrder->attachments as $att)
                            <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                                <div>
                                    <span class="text-slate-800 dark:text-slate-200 font-medium">{{ $att->file_name }}</span>
                                    <span class="text-slate-400 ml-1">({{ round($att->file_size / 1024, 1) }} KB)</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    @can('downloadAttachment', [$workOrder, $att])
                                        <a href="{{ route('work_orders.attachments.download', [$workOrder, $att->id]) }}" class="text-amber-600 dark:text-amber-400 font-bold hover:underline">
                                            {{ __('Download') }}
                                        </a>
                                    @endcan
                                    @can('deleteAttachment', [$workOrder, $att])
                                        <form method="POST" action="{{ route('work_orders.attachments.destroy', [$workOrder, $att->id]) }}" onsubmit="return confirm('Delete this attachment from private storage?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 dark:text-rose-400 font-bold hover:underline">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-slate-400 italic">{{ __('No files attached to this work order.') }}</div>
                @endif

                @can('uploadAttachment', $workOrder)
                    <form method="POST" action="{{ route('work_orders.attachments.store', $workOrder) }}" enctype="multipart/form-data" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 pt-2">
                        @csrf
                        <input type="file" name="attachment" required accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.webp" class="text-xs text-slate-600 dark:text-slate-300 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-slate-200 dark:file:bg-slate-800 file:text-slate-800 dark:file:text-slate-200">
                        <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                            {{ __('Upload Attachment') }}
                        </button>
                    </form>
                @endcan
            </div>
        </details>
    </div>

    <!-- Work Order Revisions & Amendment Control -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm transition-colors duration-200" x-data="{ showRevisionForm: false }">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Work Order Revisions & Quantity Amendments') }}</h3>
                <p class="text-[11px] text-slate-500">{{ __('Revisions snapshot existing line items and require Senior Manager approval before updating ordered quantities.') }}</p>
            </div>
            @can('createRevision', $workOrder)
                <button type="button" @click="showRevisionForm = !showRevisionForm" class="px-3.5 py-1.5 rounded-xl bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30 text-xs font-bold transition">
                    + {{ __('Propose Order Revision (Rev') }} {{ ($workOrder->current_revision ?? 0) + 1 }})
                </button>
            @endcan
        </div>

        @can('createRevision', $workOrder)
            <div x-show="showRevisionForm" x-cloak class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-4 text-xs">
                <form method="POST" action="{{ route('work_orders.revisions.store', $workOrder) }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Amendment / Revision Reason') }} *</label>
                        <input type="text" name="reason" required placeholder="e.g. JSW Amendment #1 — Additional excavation quantity approved for SMS-2" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-slate-900 dark:text-white">
                    </div>
                    <div class="space-y-2">
                        <div class="font-bold text-slate-600 dark:text-slate-400 uppercase">{{ __('Amended Line Items') }}</div>
                        @foreach($workOrder->items as $idx => $item)
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-center">
                                <input type="hidden" name="items[{{ $idx }}][service_code]" value="{{ $item->service_code }}">
                                <input type="hidden" name="items[{{ $idx }}][sac_code]" value="{{ $item->sac_code }}">
                                <input type="hidden" name="items[{{ $idx }}][specification]" value="{{ $item->specification }}">
                                <div class="sm:col-span-2">
                                    <input type="text" name="items[{{ $idx }}][sr_no]" value="{{ $item->sr_no }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-1.5 font-mono">
                                </div>
                                <div class="sm:col-span-5">
                                    <input type="text" name="items[{{ $idx }}][description]" value="{{ $item->description }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-1.5">
                                </div>
                                <div class="sm:col-span-1">
                                    <input type="text" name="items[{{ $idx }}][unit]" value="{{ $item->unit }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-1.5 font-mono">
                                </div>
                                <div class="sm:col-span-2">
                                    <input type="number" step="0.001" name="items[{{ $idx }}][ordered_quantity]" value="{{ (float) $item->ordered_quantity }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-1.5 font-mono">
                                </div>
                                <div class="sm:col-span-2">
                                    <input type="number" step="0.01" name="items[{{ $idx }}][rate]" value="{{ (float) $item->rate }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-1.5 font-mono">
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" @click="showRevisionForm = false" class="px-3 py-1.5 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold">{{ __('Cancel') }}</button>
                        <button type="submit" class="px-4 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold">{{ __('Submit Revision for Senior Manager Approval') }}</button>
                    </div>
                </form>
            </div>
        @endcan

        <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
            @forelse($workOrder->revisions as $rev)
                <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-mono font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-800 dark:text-slate-200">Rev {{ $rev->revision_number }}</span>
                            <span class="font-bold uppercase text-[10px] px-2 py-0.5 rounded {{ $rev->status === 'approved' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400' }}">
                                {{ str_replace('_', ' ', $rev->status) }}
                            </span>
                            <span class="font-mono text-slate-600 dark:text-slate-400">
                                ₹{{ number_format((float) $rev->previous_base_value, 2) }} &rarr; <strong>₹{{ number_format((float) $rev->new_base_value, 2) }}</strong>
                            </span>
                        </div>
                        <div class="text-slate-700 dark:text-slate-300 mt-1">{{ $rev->reason }}</div>
                        <div class="text-[11px] text-slate-500 mt-0.5">
                            {{ __('Created by') }} {{ $rev->changer?->name ?? 'System' }} ({{ $rev->created_at?->format('d M Y, H:i') }})
                            @if($rev->approver)
                                &bull; {{ __('Approved by') }} {{ $rev->approver->name }} ({{ $rev->approved_at?->format('d M Y, H:i') }})
                            @endif
                        </div>
                    </div>

                    @can('approveRevision', [$workOrder, $rev])
                        <form method="POST" action="{{ route('work_orders.revisions.approve', [$workOrder, $rev->id]) }}">
                            @csrf
                            <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-xs transition">
                                {{ __('Approve Rev') }} {{ $rev->revision_number }}
                            </button>
                        </form>
                    @endcan
                </div>
            @empty
                <div class="text-slate-500 py-2 italic">{{ __('No revisions recorded yet.') }}</div>
            @endforelse
        </div>
    </div>

    <!-- Phase B: Site Measurements & Running Account (RA) Billing -->
    @if($workOrder->items->isNotEmpty())
        <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-5 shadow-sm transition-colors duration-200" x-data="{ showMeasurementForm: false, showRaBillForm: false }">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Site Measurements & Running Account (RA) Bills') }}</h3>
                    <p class="text-[11px] text-slate-500">{{ __('Record dimensional (L × B × D) or direct measurements, hold quantities, and generate Quantity-Capped RA Bills.') }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('work_orders.measurements.pdf', $workOrder) }}" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-700 text-xs font-bold transition">
                        {{ __('Measurement Book PDF') }} &darr;
                    </a>
                    @can('recordMeasurement', $workOrder)
                        <a href="{{ route('work_orders.measurements.create', $workOrder) }}" class="px-3.5 py-1.5 rounded-xl bg-indigo-500/15 hover:bg-indigo-500/25 text-indigo-600 dark:text-indigo-400 border border-indigo-500/30 text-xs font-extrabold transition">
                            {{ __('Mobile Field Measurement Sheet') }} &nearr;
                        </a>
                        <button type="button" @click="showMeasurementForm = !showMeasurementForm" class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 text-xs font-bold transition">
                            + {{ __('Quick Measurement') }}
                        </button>
                    @endcan
                    @can('createRaBill', $workOrder)
                        <a href="{{ route('work_orders.ra_bills.create', $workOrder) }}" class="px-3.5 py-1.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold transition">
                            {{ __('Launch RA Bill Wizard') }} &rarr;
                        </a>
                    @endcan
                </div>
            </div>

            <!-- Record Site Measurement Form -->
            @can('recordMeasurement', $workOrder)
                <div x-show="showMeasurementForm" x-cloak class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3 text-xs">
                    <h4 class="font-extrabold text-slate-800 dark:text-slate-200 uppercase">{{ __('New Site Measurement Sheet Entry') }}</h4>
                    <form method="POST" action="{{ route('work_orders.measurements.store', $workOrder) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div class="sm:col-span-2">
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Work Order Line Item') }} *</label>
                                <select name="work_order_item_id" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-slate-900 dark:text-white">
                                    @foreach($workOrder->items as $item)
                                        <option value="{{ $item->id }}">{{ $item->sr_no }} — {{ $item->description }} ({{ $item->unit }}, Ord: {{ number_format((float) $item->ordered_quantity, 3) }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Measurement Date') }} *</label>
                                <input type="date" name="measured_on" value="{{ now()->format('Y-m-d') }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Chainage / Grid Location') }}</label>
                                <input type="text" name="location_chainage" placeholder="e.g. SMS-2 Grid B4-B8" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 sm:grid-cols-6 gap-2">
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Nos') }}</label>
                                <input type="number" step="0.001" name="nos" value="1" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 font-mono">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Length (L)') }}</label>
                                <input type="number" step="0.001" name="length" placeholder="m" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 font-mono">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Breadth (B)') }}</label>
                                <input type="number" step="0.001" name="breadth" placeholder="m" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 font-mono">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Depth/Ht (D)') }}</label>
                                <input type="number" step="0.001" name="depth_height" placeholder="m" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 font-mono">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Or Direct Qty') }}</label>
                                <input type="number" step="0.001" name="direct_quantity" placeholder="Direct" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 font-mono">
                            </div>
                            <div>
                                <label class="block font-bold text-rose-600 dark:text-rose-400 uppercase mb-1">{{ __('Hold Qty') }}</label>
                                <input type="number" step="0.001" name="hold_quantity" value="0" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 font-mono">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Remarks') }}</label>
                                <input type="text" name="remarks" placeholder="Joint measurement with JSW Engineer..." class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Site Photo / Joint Sheet (Private Vault)') }}</label>
                                <input type="file" name="photo" accept=".jpg,.jpeg,.png,.webp,.pdf" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-1.5">
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showMeasurementForm = false" class="px-3 py-1.5 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold">{{ __('Cancel') }}</button>
                            <button type="submit" class="px-4 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold">{{ __('Save Measurement') }}</button>
                        </div>
                    </form>
                </div>
            @endcan

            <!-- Generate RA Bill Form -->
            @can('createRaBill', $workOrder)
                <div x-show="showRaBillForm" x-cloak class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3 text-xs">
                    <h4 class="font-extrabold text-slate-800 dark:text-slate-200 uppercase">{{ __('Generate Running Account (RA) Bill') }}</h4>
                    <form method="POST" action="{{ route('work_orders.ra_bills.store', $workOrder) }}" class="space-y-3">
                        @csrf
                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Period From') }} *</label>
                                <input type="date" name="period_from" value="{{ now()->startOfMonth()->format('Y-m-d') }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Period To') }} *</label>
                                <input type="date" name="period_to" value="{{ now()->format('Y-m-d') }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Invoice Date') }}</label>
                                <input type="date" name="invoice_date" value="{{ now()->format('Y-m-d') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2">
                            </div>
                            <div>
                                <label class="block font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Rounding Mode') }}</label>
                                <select name="rounding_mode" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 font-mono">
                                    <option value="paisa">{{ __('Exact Paisa (2 Decimals)') }}</option>
                                    <option value="rupee">{{ __('Nearest Rupee') }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2">
                            <button type="button" @click="showRaBillForm = false" class="px-3 py-1.5 rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold">{{ __('Cancel') }}</button>
                            <button type="submit" class="px-4 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold">{{ __('Calculate & Create RA Bill') }}</button>
                        </div>
                    </form>
                </div>
            @endcan

            <!-- Recorded Site Measurements List -->
            @if($workOrder->measurements->isNotEmpty())
                <div class="space-y-2 text-xs">
                    <div class="font-bold text-slate-500 uppercase">{{ __('Site Measurement Entries') }} ({{ $workOrder->measurements->count() }})</div>
                    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 dark:bg-slate-950 text-slate-500 uppercase font-bold">
                                <tr>
                                    <th class="px-3 py-2">{{ __('Date') }}</th>
                                    <th class="px-3 py-2">{{ __('BOQ Item') }}</th>
                                    <th class="px-3 py-2">{{ __('Location') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Computed Qty') }}</th>
                                    <th class="px-3 py-2">{{ __('Status') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @foreach($workOrder->measurements as $m)
                                    <tr>
                                        <td class="px-3 py-2 font-mono text-slate-600 dark:text-slate-400">{{ $m->measured_on?->format('d.m.Y') }}</td>
                                        <td class="px-3 py-2 font-semibold">{{ $m->workOrderItem?->sr_no }} — {{ $m->workOrderItem?->description }}</td>
                                        <td class="px-3 py-2 text-slate-500">{{ $m->location_chainage ?: '—' }}</td>
                                        <td class="px-3 py-2 text-right font-mono font-bold">{{ number_format((float) $m->computed_quantity, 3) }} {{ $m->workOrderItem?->unit }}</td>
                                        <td class="px-3 py-2">
                                            @if($m->ra_bill_id)
                                                <span class="px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-700 dark:text-emerald-400 text-[10px] font-bold uppercase">{{ __('Billed') }}</span>
                                            @elseif($m->status === 'draft')
                                                <span class="px-2 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-400 text-[10px] font-bold uppercase">{{ __('Draft — Awaiting Verification') }}</span>
                                            @else
                                                <span class="px-2 py-0.5 rounded bg-sky-500/15 text-sky-700 dark:text-sky-400 text-[10px] font-bold uppercase">{{ __('Verified') }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-right">
                                            <div class="inline-flex items-center gap-2">
                                                @if($m->photo_path)
                                                    <a href="{{ route('work_orders.measurements.photo', [$workOrder, $m->id]) }}" class="text-sky-600 dark:text-sky-400 font-bold hover:underline">{{ __('Photo') }}</a>
                                                @endif
                                                @if(!$m->ra_bill_id && $m->status === 'draft' && (int) $m->recorded_by !== (int) auth()->id() && auth()->user()->canPerform('measurements.approve', ['super_admin', 'company_admin', 'senior_manager', 'site_manager']))
                                                    <form method="POST" action="{{ route('work_orders.measurements.verify', [$workOrder, $m->id]) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="px-2.5 py-1 rounded bg-emerald-600 text-white font-bold text-[11px] hover:bg-emerald-700">{{ __('Verify') }}</button>
                                                    </form>
                                                @endif
                                                @if(!$m->ra_bill_id)
                                                    @can('recordMeasurement', $workOrder)
                                                        <form method="POST" action="{{ route('work_orders.measurements.destroy', [$workOrder, $m->id]) }}" class="inline" onsubmit="return confirm('Delete this unbilled site measurement?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-rose-600 dark:text-rose-400 font-bold hover:underline">{{ __('Delete') }}</button>
                                                        </form>
                                                    @endcan
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <!-- RA Bills List -->
            @if($workOrder->raBills->isNotEmpty())
                <div class="space-y-2 text-xs">
                    <div class="font-bold text-slate-500 uppercase">{{ __('Generated RA Bills') }}</div>
                    <div class="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-slate-100 dark:bg-slate-950 text-slate-500 uppercase font-bold">
                                <tr>
                                    <th class="px-3 py-2">{{ __('RA Bill') }}</th>
                                    <th class="px-3 py-2">{{ __('Period') }}</th>
                                    <th class="px-3 py-2">{{ __('WO Rev') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Basic (A)') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Gross (Incl. GST)') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Unbilled Excess') }}</th>
                                    <th class="px-3 py-2">{{ __('Status') }}</th>
                                    <th class="px-3 py-2 text-right">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                                @foreach($workOrder->raBills as $bill)
                                    <tr>
                                        <td class="px-3 py-2 font-mono font-bold text-amber-600 dark:text-amber-400">{{ $bill->ra_label }}</td>
                                        <td class="px-3 py-2 font-mono text-slate-600 dark:text-slate-400">{{ $bill->period_from->format('d.m.y') }} - {{ $bill->period_to->format('d.m.y') }}</td>
                                        <td class="px-3 py-2 font-mono">Rev {{ $bill->work_order_revision }}</td>
                                        <td class="px-3 py-2 text-right font-mono font-bold">₹{{ number_format((float) $bill->current_bill_amount, 2) }}</td>
                                        <td class="px-3 py-2 text-right font-mono font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) $bill->gross_amount, 2) }}</td>
                                        <td class="px-3 py-2 text-right font-mono {{ (float) $bill->excess_unbilled_amount > 0 ? 'font-bold text-rose-600 dark:text-rose-400' : 'text-slate-400' }}">
                                            ₹{{ number_format((float) $bill->excess_unbilled_amount, 2) }}
                                        </td>
                                        <td class="px-3 py-2 uppercase font-bold text-[10px]">{{ str_replace('_', ' ', $bill->status) }}</td>
                                        <td class="px-3 py-2 text-right">
                                            <a href="{{ route('work_orders.ra_bills.show', [$workOrder, $bill]) }}" class="font-bold text-amber-600 dark:text-amber-400 hover:underline">
                                                {{ __('Open Abstract') }} &rarr;
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Audited Approval History -->
    <details class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200 group">
        <summary class="cursor-pointer list-none flex items-center justify-between">
            <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Approval & Change History') }} ({{ $workOrder->approvals->count() }})</h3>
            <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-[11px] font-semibold text-slate-600 dark:text-slate-300">{{ __('Show / Hide') }}</span>
        </summary>
        <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs pt-3 mt-3 border-t border-slate-200 dark:border-slate-800">
            @forelse($workOrder->approvals as $audit)
                <div class="py-2.5 flex items-start justify-between">
                    <div>
                        <div class="font-bold text-slate-900 dark:text-white">{{ $audit->actor->name }} ({{ str_replace('_', ' ', $audit->stage) }})</div>
                        <div class="text-slate-500 dark:text-slate-400 italic mt-0.5">{{ $audit->comments ?? __('No comment provided') }}</div>
                    </div>
                    <div class="text-right">
                        <span class="font-bold uppercase {{ $audit->action === 'approved' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">{{ __($audit->action === 'approved' ? 'Approved' : 'Rejected') }}</span>
                        <div class="text-[11px] text-slate-500">{{ $audit->created_at->format('d M Y, H:i') }}</div>
                    </div>
                </div>
            @empty
                <div class="text-slate-500 py-2">{{ __('No approval events recorded yet.') }}</div>
            @endforelse
        </div>
    </details>
</div>
@endsection

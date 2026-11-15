@extends('layouts.app')

@section('title', 'Pending Approvals')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Pending Approvals</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                Items across your authorized sites that currently await your verification or approval.
            </p>
        </div>
        <div class="inline-flex items-center gap-2 rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 px-4 py-2 text-sm">
            <span class="text-slate-500 dark:text-slate-400">Actionable Items:</span>
            <span class="font-bold text-slate-900 dark:text-white">{{ $counts['all'] }}</span>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        @php
            $tabs = [
                'all' => 'All Pending (' . $counts['all'] . ')',
                'work_orders' => 'Work Orders (' . $counts['work_orders'] . ')',
                'ra_bills' => 'RA Bills (' . $counts['ra_bills'] . ')',
                'measurements' => 'Measurements (' . $counts['measurements'] . ')',
                'procurement' => 'Purchase Requests (' . $counts['procurement'] . ')',
                'inventory' => 'Stock Adjustments (' . $counts['inventory'] . ')',
                'attendance' => 'Attendance & Leave (' . $counts['attendance'] . ')',
            ];
        @endphp
        @foreach($tabs as $key => $label)
            <a href="{{ route('approvals.index', $key === 'all' ? [] : ['category' => $key]) }}"
               class="rounded-lg px-3.5 py-2 text-xs font-semibold transition {{ $category === $key ? 'bg-slate-900 text-white dark:bg-blue-600' : 'bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if($counts['all'] === 0)
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-12 text-center">
            <h3 class="text-base font-semibold text-slate-900 dark:text-white">All Caught Up</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                There are no pending approvals awaiting your review right now across Work Orders, RA Bills, Measurements, Procurement, Inventory, or Attendance.
            </p>
        </div>
    @else
        @if(($category === 'all' || $category === 'work_orders') && ($pendingWorkOrders->isNotEmpty() || $pendingRevisions->isNotEmpty()))
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                <div class="border-b border-slate-200 dark:border-slate-800 px-5 py-3.5 font-semibold text-slate-900 dark:text-white">
                    Work Orders &amp; Scope Revisions ({{ $counts['work_orders'] }})
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach($pendingWorkOrders as $wo)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 text-sm">
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $wo->order_number }} — {{ $wo->title }}</div>
                                <div class="text-xs text-slate-500">
                                    Site: {{ $wo->site?->name ?? 'Unassigned' }} &bull; Status: {{ ucwords(str_replace('_', ' ', $wo->status)) }} &bull; Value: ₹{{ number_format((float) $wo->base_order_value, 2) }}
                                </div>
                            </div>
                            <a href="{{ route('work_orders.show', $wo) }}" class="inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                Review Work Order
                            </a>
                        </div>
                    @endforeach
                    @foreach($pendingRevisions as $rev)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 text-sm">
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-white">
                                    Revision #{{ $rev->revision_number }} on {{ $rev->workOrder?->order_number }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    Requested by {{ $rev->changer?->name ?? 'Staff' }} &bull; Proposed Value: ₹{{ number_format((float) $rev->new_base_value, 2) }}
                                </div>
                            </div>
                            <a href="{{ route('work_orders.show', $rev->workOrder) }}" class="inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                Review Revision
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(($category === 'all' || $category === 'ra_bills') && $pendingRaBills->isNotEmpty())
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                <div class="border-b border-slate-200 dark:border-slate-800 px-5 py-3.5 font-semibold text-slate-900 dark:text-white">
                    RA Bills &amp; Tax Invoices ({{ $pendingRaBills->count() }})
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach($pendingRaBills as $bill)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 text-sm">
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-white">
                                    {{ $bill->ra_label }} — {{ $bill->workOrder?->order_number }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    Site: {{ $bill->site?->name ?? '—' }} &bull; Stage: {{ ucwords(str_replace('_', ' ', $bill->status)) }} &bull; Net Payable: ₹{{ number_format((float) $bill->net_payable, 2) }}
                                </div>
                            </div>
                            <a href="{{ route('work_orders.ra_bills.show', [$bill->workOrder, $bill]) }}" class="inline-flex items-center rounded-lg bg-blue-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                Review RA Bill
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(($category === 'all' || $category === 'measurements') && $pendingMeasurements->isNotEmpty())
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                <div class="border-b border-slate-200 dark:border-slate-800 px-5 py-3.5 font-semibold text-slate-900 dark:text-white">
                    Draft Site Measurements Awaiting Verification ({{ $pendingMeasurements->count() }})
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach($pendingMeasurements as $entry)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 text-sm">
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-white">
                                    {{ $entry->workOrder?->order_number }} — {{ $entry->workOrderItem?->sr_no }} {{ $entry->workOrderItem?->description }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    Qty: {{ number_format((float) $entry->computed_quantity, 3) }} {{ $entry->workOrderItem?->unit }}
                                    &bull; Recorded by {{ $entry->recorder?->name ?? 'Supervisor' }}
                                    @if($entry->location_chainage) &bull; {{ $entry->location_chainage }} @endif
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('work_orders.measurements.verify', [$entry->workOrder, $entry->id]) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                        Verify Measurement
                                    </button>
                                </form>
                                <a href="{{ route('work_orders.show', $entry->workOrder) }}" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200">
                                    View WO
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(($category === 'all' || $category === 'procurement') && $pendingPurchaseRequests->isNotEmpty())
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                <div class="border-b border-slate-200 dark:border-slate-800 px-5 py-3.5 font-semibold text-slate-900 dark:text-white">
                    Purchase Requests ({{ $pendingPurchaseRequests->count() }})
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach($pendingPurchaseRequests as $pr)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 text-sm">
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-white">
                                    {{ $pr->request_number }} ({{ strtoupper($pr->urgency) }})
                                </div>
                                <div class="text-xs text-slate-500">
                                    Site: {{ $pr->site?->name ?? 'Central' }} &bull; Requested by {{ $pr->requester?->name ?? 'Staff' }} &bull; {{ $pr->items->count() }} item(s)
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('procurement.approve_request', $pr) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                        Approve
                                    </button>
                                </form>
                                <a href="{{ route('procurement.requests') }}" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200">
                                    Open PRs
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(($category === 'all' || $category === 'inventory') && $pendingAdjustments->isNotEmpty())
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                <div class="border-b border-slate-200 dark:border-slate-800 px-5 py-3.5 font-semibold text-slate-900 dark:text-white">
                    Stock Adjustments ({{ $pendingAdjustments->count() }})
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach($pendingAdjustments as $tx)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 text-sm">
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $tx->transaction_number }}</div>
                                <div class="text-xs text-slate-500">
                                    Store: {{ $tx->sourceWarehouse?->name ?: ($tx->destinationWarehouse?->name ?? '—') }}
                                    &bull; Requested by {{ $tx->performedBy?->name ?? 'Staff' }}
                                    &bull; Reason: {{ $tx->adjustment_reason }}
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <form method="POST" action="{{ route('inventory.adjustments.approve', $tx) }}">
                                    @csrf
                                    <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">
                                        Approve
                                    </button>
                                </form>
                                <a href="{{ route('inventory.transactions', ['type' => 'stock_adjustment', 'status' => 'pending_confirmation']) }}"
                                   class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-medium text-slate-700 dark:text-slate-200">
                                    Details
                                </a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if(($category === 'all' || $category === 'attendance') && ($pendingCorrections->isNotEmpty() || $pendingLeaves->isNotEmpty()))
            <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
                <div class="border-b border-slate-200 dark:border-slate-800 px-5 py-3.5 font-semibold text-slate-900 dark:text-white">
                    Attendance Corrections &amp; Worker Leave ({{ $counts['attendance'] }})
                </div>
                <div class="divide-y divide-slate-200 dark:divide-slate-800">
                    @foreach($pendingCorrections as $corr)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 text-sm">
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-white">
                                    Attendance Correction — {{ $corr->attendance?->worker?->full_name ?? 'Worker' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    Date: {{ $corr->attendance?->attendance_date?->format('d M Y') }} &bull; Proposed: {{ ucfirst($corr->proposed_status) }} &bull; Requested by {{ $corr->requester?->name ?? 'Supervisor' }}
                                </div>
                            </div>
                            <a href="{{ route('attendance.approvals') }}" class="rounded-lg bg-blue-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                Review in Attendance
                            </a>
                        </div>
                    @endforeach
                    @foreach($pendingLeaves as $leave)
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-5 py-3.5 text-sm">
                            <div>
                                <div class="font-semibold text-slate-900 dark:text-white">
                                    Leave Request — {{ $leave->worker?->full_name ?? 'Worker' }}
                                </div>
                                <div class="text-xs text-slate-500">
                                    {{ $leave->start_date?->format('d M Y') }} to {{ $leave->end_date?->format('d M Y') }} ({{ $leave->total_days }}d) &bull; {{ $leave->reason }}
                                </div>
                            </div>
                            <a href="{{ route('attendance.approvals') }}" class="rounded-lg bg-blue-600 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-blue-700">
                                Review Leave
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>
@endsection

@extends('layouts.app')

@section('title', 'Invoices & RA Bills')

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="{ showCreateLauncher: false }">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">Invoices &amp; RA Bills</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                Central register of progressive Running Account (RA) bills and tax invoices across your authorized sites.
            </p>
        </div>

        @if($createEligibleWorkOrders->isNotEmpty())
            <button type="button"
                @click="showCreateLauncher = !showCreateLauncher"
                class="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-blue-700 transition">
                <span>+ New RA Bill</span>
            </button>
        @endif
    </div>

    @if($createEligibleWorkOrders->isNotEmpty())
        <div x-show="showCreateLauncher" x-cloak class="rounded-xl border border-blue-200 dark:border-blue-900/50 bg-blue-50/70 dark:bg-slate-800 p-4">
            <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                <div class="flex-1">
                    <label for="launch_work_order" class="block text-xs font-semibold uppercase tracking-wide text-slate-700 dark:text-slate-300 mb-1">
                        Select Work Order to Prepare RA Bill
                    </label>
                    <select id="launch_work_order" x-ref="woSelect" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-900 dark:text-white text-sm">
                        @foreach($createEligibleWorkOrders as $wo)
                            <option value="{{ route('work_orders.ra_bills.create', $wo) }}">
                                {{ $wo->order_number }} — {{ $wo->title }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                        @click="window.location.href = $refs.woSelect.value"
                        class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                        Continue to Billing Wizard
                    </button>
                    <button type="button"
                        @click="showCreateLauncher = false"
                        class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-white dark:hover:bg-slate-700">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Matching RA Bills</div>
            <div class="mt-1 text-2xl font-bold text-slate-900 dark:text-white">{{ number_format($totalCount) }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Awaiting Review / Approval</div>
            <div class="mt-1 text-2xl font-bold text-amber-600 dark:text-amber-400">{{ number_format($pendingCount) }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
            <div class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-slate-400">Approved Net Payable</div>
            <div class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format($approvedNetTotal, 2) }}</div>
        </div>
    </div>

    <form method="GET" action="{{ route('ra_bills.index') }}" class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 p-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Search</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="RA label, invoice, WO..."
                    class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Site</label>
                <select name="site_id" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-sm">
                    <option value="">All Authorized Sites</option>
                    @foreach($sites as $siteOption)
                        <option value="{{ $siteOption->id }}" @selected($siteId === $siteOption->id)>{{ $siteOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Client</label>
                <select name="client_id" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-sm">
                    <option value="">All Clients</option>
                    @foreach($clients as $clientOption)
                        <option value="{{ $clientOption->id }}" @selected($clientId === $clientOption->id)>{{ $clientOption->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-slate-600 dark:text-slate-400 mb-1">Status</label>
                <select name="status" class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-sm">
                    <option value="">All Statuses</option>
                    @foreach(['draft' => 'Draft', 'submitted' => 'Submitted', 'site_manager_approved' => 'Site Verified', 'approved' => 'Approved & Locked', 'rejected' => 'Rejected'] as $statusKey => $statusLabel)
                        <option value="{{ $statusKey }}" @selected($status === $statusKey)>{{ $statusLabel }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="rounded-lg bg-slate-900 dark:bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">
                    Filter
                </button>
                <a href="{{ route('ra_bills.index') }}" class="rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800">
                    Reset
                </a>
            </div>
        </div>
    </form>

    <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900 overflow-hidden">
        @if($raBills->isEmpty())
            <div class="p-10 text-center">
                <h3 class="text-base font-semibold text-slate-900 dark:text-white">No RA Bills or Invoices Found</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400 max-w-md mx-auto">
                    Prepare Running Account bills directly from an active Work Order once site measurements have been verified.
                </p>
                <div class="mt-4">
                    <a href="{{ route('work_orders.index') }}" class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                        View Work Orders
                    </a>
                </div>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 dark:divide-slate-800 text-sm">
                    <thead class="bg-slate-50 dark:bg-slate-800/60">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-slate-300">RA Bill / Invoice</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-slate-300">Work Order &amp; Site</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-slate-300">Client</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-slate-300">GST Mode</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700 dark:text-slate-300">Current Basic</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700 dark:text-slate-300">Net Payable</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-700 dark:text-slate-300">Status</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-700 dark:text-slate-300">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($raBills as $bill)
                            <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-4 py-3">
                                    <div class="font-semibold text-slate-900 dark:text-white">{{ $bill->ra_label }}</div>
                                    <div class="text-xs text-slate-500">
                                        {{ $bill->invoice_number ?: 'Invoice issued on final approval' }}
                                        @if($bill->invoice_date) &bull; {{ $bill->invoice_date->format('d M Y') }} @endif
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    @if($bill->workOrder)
                                        <a href="{{ route('work_orders.show', $bill->workOrder) }}" class="font-medium text-blue-600 dark:text-blue-400 hover:underline">
                                            {{ $bill->workOrder->order_number }}
                                        </a>
                                    @else
                                        <span class="text-slate-500">—</span>
                                    @endif
                                    <div class="text-xs text-slate-500">{{ $bill->site?->name ?? 'Unassigned Site' }}</div>
                                </td>
                                <td class="px-4 py-3 text-slate-700 dark:text-slate-300">
                                    {{ $bill->client?->name ?? $bill->workOrder?->client_name ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs text-slate-600 dark:text-slate-400">
                                    @if($bill->isIgst())
                                        <span class="inline-flex rounded-full bg-indigo-50 dark:bg-indigo-900/30 px-2 py-0.5 font-medium text-indigo-700 dark:text-indigo-300">
                                            IGST {{ number_format((float) $bill->igst_rate, 1) }}%
                                        </span>
                                    @else
                                        <span class="inline-flex rounded-full bg-slate-100 dark:bg-slate-800 px-2 py-0.5 font-medium text-slate-700 dark:text-slate-300">
                                            CGST+SGST
                                        </span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-medium text-slate-800 dark:text-slate-200">
                                    ₹{{ number_format((float) $bill->current_bill_amount, 2) }}
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-slate-900 dark:text-white">
                                    ₹{{ number_format((float) $bill->net_payable, 2) }}
                                </td>
                                <td class="px-4 py-3">
                                    @php
                                        $badgeColors = [
                                            'draft' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
                                            'submitted' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
                                            'site_manager_approved' => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
                                            'approved' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
                                            'rejected' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
                                        ];
                                    @endphp
                                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium {{ $badgeColors[$bill->status] ?? $badgeColors['draft'] }}">
                                        {{ ucwords(str_replace('_', ' ', $bill->status)) }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($bill->workOrder)
                                        <a href="{{ route('work_orders.ra_bills.show', [$bill->workOrder, $bill]) }}"
                                            class="inline-flex items-center rounded-lg border border-slate-300 dark:border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800">
                                            Open Bill
                                        </a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($raBills->hasPages())
                <div class="border-t border-slate-200 dark:border-slate-800 px-4 py-3">
                    {{ $raBills->links() }}
                </div>
            @endif
        @endif
    </div>
</div>
@endsection

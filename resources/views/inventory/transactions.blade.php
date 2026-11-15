@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Materials & Stock Sub-Navigation -->
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
        <a href="{{ route('procurement.requests') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Purchase Requests') }}
        </a>
        <a href="{{ route('procurement.orders') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Purchase Orders & GRN') }}
        </a>
        <a href="{{ route('procurement.vendors') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Suppliers') }}
        </a>
        <a href="{{ route('inventory.stocks') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Warehouse & Yard Stock') }}
        </a>
        <a href="{{ route('inventory.transactions') }}" class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 text-slate-950 shadow-sm">
            {{ __('Stock Movement Ledger') }}
        </a>
        <a href="{{ route('inventory.warehouses') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Storage Locations') }}
        </a>
        <a href="{{ route('inventory.materials') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Material Catalog') }}
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Stock Movement & Consumption Ledger') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('History of material deliveries, site issues, yard transfers, returns, and audited stock adjustments.') }}</p>
        </div>
        <a href="{{ route('inventory.stocks') }}" class="px-4 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-sm transition">
            + {{ __('Record Stock Movement') }}
        </a>
    </div>

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300 space-y-1">
            @foreach($errors->all() as $err)
                <div class="font-semibold">&bull; {{ $err }}</div>
            @endforeach
        </div>
    @endif

    <!-- Filter Bar -->
    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
        <form method="GET" action="{{ route('inventory.transactions') }}" class="flex flex-wrap items-center gap-3">
            <select name="type" onchange="this.form.submit()" class="rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
                <option value="">{{ __('All Movement Types') }}</option>
                <option value="goods_receipt" {{ request('type') === 'goods_receipt' ? 'selected' : '' }}>{{ __('Goods Receipt (GRN)') }}</option>
                <option value="warehouse_issue" {{ request('type') === 'warehouse_issue' ? 'selected' : '' }}>{{ __('Issued to Site') }}</option>
                <option value="inter_site_transfer" {{ request('type') === 'inter_site_transfer' ? 'selected' : '' }}>{{ __('Yard Transfer') }}</option>
                <option value="site_return" {{ request('type') === 'site_return' ? 'selected' : '' }}>{{ __('Returned from Site') }}</option>
                <option value="stock_adjustment" {{ request('type') === 'stock_adjustment' ? 'selected' : '' }}>{{ __('Stock Adjustment') }}</option>
            </select>
            <select name="status" onchange="this.form.submit()" class="rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="pending_confirmation" {{ request('status') === 'pending_confirmation' ? 'selected' : '' }}>{{ __('Pending Approval') }}</option>
                <option value="confirmed" {{ request('status') === 'confirmed' ? 'selected' : '' }}>{{ __('Confirmed') }}</option>
                <option value="rejected" {{ request('status') === 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
            </select>
            @if(request()->filled('type') || request()->filled('status'))
                <a href="{{ route('inventory.transactions') }}" class="text-xs font-bold text-slate-500 hover:text-slate-900 dark:hover:text-white underline">
                    {{ __('Clear filters') }}
                </a>
            @endif
        </form>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Reference #') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Movement Type') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Date') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Route / Location') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Materials & Reason') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Status') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Recorded By / Action') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @php
                        $typeLabels = [
                            'goods_receipt' => __('Goods Receipt'),
                            'warehouse_issue' => __('Issued to Site'),
                            'inter_site_transfer' => __('Yard Transfer'),
                            'site_return' => __('Returned from Site'),
                            'stock_adjustment' => __('Stock Adjustment'),
                        ];
                    @endphp
                    @forelse($transactions as $tx)
                        @php
                            $canApproveTx = auth()->check() && auth()->user()->can('approveAdjustment', [\App\Models\InventoryStock::class, $tx]);
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition align-top" x-data="{ showReject: false }">
                            <td class="py-3.5 px-4">
                                <div class="font-mono font-bold text-amber-600 dark:text-amber-400 text-xs">{{ $tx->transaction_number }}</div>
                                @if($tx->challan_or_invoice_no)
                                    <div class="text-[11px] text-slate-500 font-mono mt-0.5">{{ __('Slip') }}: {{ $tx->challan_or_invoice_no }}</div>
                                @endif
                                @if($tx->transaction_type === 'goods_receipt' && \Illuminate\Support\Facades\Route::has('inventory.grn_pdf'))
                                    <a href="{{ route('inventory.grn_pdf', $tx) }}" class="inline-block mt-1 text-[11px] font-bold text-sky-600 dark:text-sky-400 hover:underline">
                                        {{ __('Download GRN PDF') }}
                                    </a>
                                @endif
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    {{ $typeLabels[$tx->transaction_type] ?? ucwords(str_replace('_', ' ', $tx->transaction_type)) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs text-slate-700 dark:text-slate-300">
                                {{ $tx->transaction_date->format('d M Y') }}
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-800 dark:text-slate-200 space-y-0.5">
                                @if($tx->transaction_type === 'inter_site_transfer')
                                    <div><span class="text-slate-500">{{ __('From') }}:</span> {{ $tx->sourceWarehouse?->name ?? '—' }}</div>
                                    <div><span class="text-slate-500">{{ __('To') }}:</span> {{ $tx->destinationWarehouse?->name ?? '—' }}</div>
                                @elseif($tx->transaction_type === 'warehouse_issue')
                                    <div><span class="text-slate-500">{{ __('From') }}:</span> {{ $tx->sourceWarehouse?->name ?? '—' }}</div>
                                    <div><span class="text-slate-500">{{ __('Site') }}:</span> {{ $tx->site?->name ?? '—' }}</div>
                                @elseif($tx->transaction_type === 'site_return')
                                    <div><span class="text-slate-500">{{ __('Site') }}:</span> {{ $tx->site?->name ?? '—' }}</div>
                                    <div><span class="text-slate-500">{{ __('Into') }}:</span> {{ $tx->destinationWarehouse?->name ?? '—' }}</div>
                                @else
                                    <div>{{ $tx->destinationWarehouse?->name ?? $tx->sourceWarehouse?->name ?? $tx->site?->name ?? __('Central Storage') }}</div>
                                @endif
                                @if($tx->relationLoaded('workOrder') && $tx->workOrder)
                                    <div class="text-[11px] font-mono text-slate-500">WO: {{ $tx->workOrder->order_number }}</div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs space-y-1">
                                @foreach($tx->items as $item)
                                    <div class="font-semibold text-slate-900 dark:text-white">
                                        @if($tx->transaction_type === 'stock_adjustment' && (float) $item->quantity > 0)
                                            +{{ number_format((float) $item->quantity, 2) }}
                                        @else
                                            {{ number_format((float) $item->quantity, 2) }}
                                        @endif
                                        {{ $item->material?->unit_of_measure }} &mdash; {{ $item->material?->name }}
                                    </div>
                                @endforeach
                                @if(!empty($tx->adjustment_reason) || !empty($tx->notes))
                                    <div class="text-[11px] text-slate-500 dark:text-slate-400">
                                        {{ $tx->adjustment_reason ?: $tx->notes }}
                                    </div>
                                @endif
                                @if($tx->status === 'rejected' && !empty($tx->rejection_reason))
                                    <div class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">
                                        {{ __('Rejected') }}: {{ $tx->rejection_reason }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                @if($tx->status === 'pending_confirmation')
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">
                                        {{ __('Pending Approval') }}
                                    </span>
                                @elseif($tx->status === 'rejected')
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30">
                                        {{ __('Rejected') }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                                        {{ __('Confirmed') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-right space-y-2">
                                <div class="text-slate-600 dark:text-slate-400">{{ $tx->performedBy?->name }}</div>
                                @if($tx->confirmedBy && (int) $tx->confirmed_by !== (int) $tx->performed_by)
                                    <div class="text-[11px] text-slate-500">
                                        {{ $tx->status === 'rejected' ? __('Reviewed by') : __('Approved by') }}: {{ $tx->confirmedBy->name }}
                                    </div>
                                @endif

                                @if($canApproveTx)
                                    <div class="flex items-center justify-end gap-1.5 pt-1">
                                        <form method="POST" action="{{ route('inventory.adjustments.approve', $tx) }}" onsubmit="return confirm('Approve this stock adjustment and update warehouse quantities?');">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded-lg text-[11px] font-extrabold bg-emerald-600 hover:bg-emerald-500 text-white transition">
                                                {{ __('Approve') }}
                                            </button>
                                        </form>
                                        <button type="button" @click="showReject = !showReject" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-rose-600/10 text-rose-600 dark:text-rose-400 border border-rose-500/30 hover:bg-rose-600/20 transition">
                                            {{ __('Reject') }}
                                        </button>
                                    </div>
                                    <form x-show="showReject" x-cloak method="POST" action="{{ route('inventory.adjustments.reject', $tx) }}" class="pt-2 space-y-1.5 text-left">
                                        @csrf
                                        <input type="text" name="rejection_reason" required minlength="3" placeholder="{{ __('Reason for rejection') }}" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-2.5 py-1.5 text-xs text-slate-900 dark:text-white">
                                        <button type="submit" class="w-full px-2.5 py-1 rounded-lg text-[11px] font-bold bg-rose-600 text-white hover:bg-rose-500 transition">
                                            {{ __('Confirm Rejection') }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-500">{{ __('No transactions recorded yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($transactions->hasPages())
        <div>
            {{ $transactions->links() }}
        </div>
    @endif
</div>
@endsection

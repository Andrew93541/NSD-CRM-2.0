@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Purchase Orders & GRN') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Track purchase order delivery status and confirm warehouse or site receipts.') }}</p>
        </div>
        <a href="{{ route('procurement.requests') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 transition">
            {{ __('Purchase Requests') }} &rarr;
        </a>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Order #') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Supplier') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Destination') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Total Value') }} (₹)</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Status') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($orders as $po)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-amber-600 dark:text-amber-400 text-xs">{{ $po->po_number }}</td>
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $po->supplier_name }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $po->order_date->format('d M Y') }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                {{ $po->site->name ?? $po->warehouse->name ?? __('Head Depot') }}
                            </td>
                            <td class="py-3.5 px-4 font-mono text-slate-700 dark:text-slate-200 text-xs">
                                ₹{{ number_format($po->total_amount, 2) }}
                            </td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase {{ $po->status === 'received' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-sky-500/10 text-sky-600 dark:text-sky-400' }}">
                                    {{ __($po->status === 'received' ? 'Received' : ($po->status === 'partially_received' ? 'Partially Received' : 'Ordered')) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                @if($po->status !== 'received')
                                    <a href="{{ route('procurement.receive_goods', $po) }}" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-emerald-500 text-slate-950 hover:bg-emerald-400 transition">
                                        {{ __('Confirm Delivery Receipt') }} &rarr;
                                    </a>
                                @else
                                    <span class="text-xs text-slate-500">{{ __('Fully Received') }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">{{ __('No purchase orders found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="sm:hidden space-y-3 p-4">
            @forelse($orders as $po)
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="font-mono font-bold text-amber-600 dark:text-amber-400 text-xs">{{ $po->po_number }}</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $po->status === 'received' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' : 'bg-sky-500/10 text-sky-600 dark:text-sky-400' }}">
                            {{ __($po->status === 'received' ? 'Received' : ($po->status === 'partially_received' ? 'Partially Received' : 'Ordered')) }}
                        </span>
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 dark:text-white text-sm">{{ $po->supplier_name }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $po->site->name ?? $po->warehouse->name ?? __('Head Depot') }} &bull; {{ $po->order_date->format('d M Y') }}</div>
                    </div>
                    <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-200 dark:border-slate-800/80">
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200">₹{{ number_format($po->total_amount, 2) }}</span>
                        @if($po->status !== 'received')
                            <a href="{{ route('procurement.receive_goods', $po) }}" class="px-3 py-1 text-xs font-bold rounded-lg bg-emerald-500 text-slate-950 hover:bg-emerald-400 transition">
                                {{ __('Receive Goods') }} &rarr;
                            </a>
                        @else
                            <span class="text-xs text-slate-500">{{ __('Fully Received') }}</span>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-slate-500 text-xs">
                    {{ __('No purchase orders found.') }}
                </div>
            @endforelse
        </div>

        @if($orders->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="pb-4 border-b border-slate-200 dark:border-slate-800">
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Confirm Delivery Receipt') }} (GRN)</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Confirmed by authorized site supervisor or warehouse storekeeper.') }}</p>
    </div>

    <div class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl transition-colors duration-200">
        <form method="POST" action="{{ route('procurement.confirm_receipt', $purchaseOrder) }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Destination Warehouse') }} *</label>
                    <select name="destination_warehouse_id" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->code }} — {{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Delivery Challan / Invoice #') }}</label>
                    <input type="text" name="challan_or_invoice_no" placeholder="e.g. CH-2026-9901" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>

            <div class="space-y-3 pt-2">
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Quantity Received') }} ({{ __('Physical Verification') }})</label>
                @foreach($purchaseOrder->items as $item)
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
                        <div class="flex-1">
                            <div class="font-bold text-slate-900 dark:text-white text-sm">{{ $item->material->name }}</div>
                            <div class="text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ __('Quantity Ordered') }}: {{ $item->quantity_ordered }} {{ $item->material->unit_of_measure }} &bull; {{ __('Received') }}: {{ $item->quantity_received }}
                            </div>
                        </div>
                        <div class="w-full sm:w-44 flex items-center gap-2">
                            <span class="text-slate-600 dark:text-slate-400 whitespace-nowrap">{{ __('Received') }}:</span>
                            <input type="number" step="0.01" name="received[{{ $item->material_id }}]" value="{{ max(0, $item->quantity_ordered - $item->quantity_received) }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white font-mono focus:border-amber-500 outline-none">
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <a href="{{ route('procurement.orders') }}" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:border-slate-700 transition">{{ __('Cancel') }}</a>
                <button type="submit" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 shadow-lg shadow-emerald-500/20 transition">{{ __('Confirm Delivery Receipt') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

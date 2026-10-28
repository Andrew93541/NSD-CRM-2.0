@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="pb-4 border-b border-slate-200 dark:border-slate-800">
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Generate Purchase Order') }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Converts approved requisition') }} <span class="font-mono text-amber-600 dark:text-amber-400 font-bold">{{ $purchaseRequest->request_number }}</span> {{ __('into a formal vendor purchase order.') }}</p>
    </div>

    <div class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl transition-colors duration-200">
        <form method="POST" action="{{ route('procurement.store_po', $purchaseRequest) }}" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Vendor / Supplier Name') }} *</label>
                    <input type="text" name="supplier_name" required placeholder="e.g. UltraTech Cement Corp / Indian Oil" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Supplier Phone') }}</label>
                    <input type="text" name="supplier_phone" placeholder="+91 ..." class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Expected Delivery Date') }}</label>
                <input type="date" name="expected_delivery_date" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
            </div>

            <div class="space-y-3 pt-2">
                <label class="block text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Ordered Quantities & Unit Rates') }}</label>
                @foreach($purchaseRequest->items as $item)
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4 text-xs">
                        <div class="flex-1">
                            <span class="font-bold text-slate-900 dark:text-white">{{ $item->material->name }}</span>
                            <span class="text-slate-500 dark:text-slate-400 ml-2">({{ $item->quantity_requested }} {{ $item->material->unit_of_measure }})</span>
                        </div>
                        <div class="w-40 flex items-center gap-2">
                            <span class="text-slate-500 dark:text-slate-400">₹ {{ __('Rate') }}:</span>
                            <input type="number" step="0.01" name="unit_prices[{{ $item->material_id }}]" value="{{ $item->material->standard_unit_cost }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-1.5 text-xs text-slate-900 dark:text-white font-mono focus:border-amber-500 outline-none">
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <a href="{{ route('procurement.requests') }}" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">{{ __('Cancel') }}</a>
                <button type="submit" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 shadow-lg shadow-sky-500/20 transition">{{ __('Issue Purchase Order') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

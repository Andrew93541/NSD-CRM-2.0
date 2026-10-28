@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Material Valuation & Inventory Report') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Estimated total balance value across central logistics depots and site yards.') }}</p>
        </div>
        <div class="sm:text-right">
            <div class="text-xs text-slate-500 dark:text-slate-400 uppercase font-bold">{{ __('Total Stock Valuation') }}</div>
            <div class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 font-mono mt-0.5">₹{{ number_format($totalValuation, 2) }}</div>
        </div>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Storage Depot') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Material Item') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Quantity') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Unit Price') }} (₹)</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Estimated Value') }} (₹)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($stocks as $s)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">{{ $s->warehouse->name }}</td>
                            <td class="py-3 px-4 text-slate-800 dark:text-slate-200">{{ $s->material->name }}</td>
                            <td class="py-3 px-4 font-mono text-right text-xs text-slate-700 dark:text-slate-300">{{ number_format($s->quantity, 2) }} {{ $s->material->unit_of_measure }}</td>
                            <td class="py-3 px-4 font-mono text-right text-xs text-slate-600 dark:text-slate-400">₹{{ number_format($s->material->standard_unit_cost, 2) }}</td>
                            <td class="py-3 px-4 font-mono text-right text-xs font-bold text-amber-600 dark:text-amber-400">
                                ₹{{ number_format($s->quantity * $s->material->standard_unit_cost, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-slate-500">{{ __('No inventory stock records found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="sm:hidden space-y-3 p-4">
            @forelse($stocks as $s)
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $s->material->name }}</span>
                        <span class="font-mono font-bold text-amber-600 dark:text-amber-400 text-xs">₹{{ number_format($s->quantity * $s->material->standard_unit_cost, 2) }}</span>
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">{{ $s->warehouse->name }}</div>
                    <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-200 dark:border-slate-800/80 text-slate-600 dark:text-slate-300">
                        <span>{{ number_format($s->quantity, 2) }} {{ $s->material->unit_of_measure }}</span>
                        <span class="text-slate-400 dark:text-slate-500">@ ₹{{ number_format($s->material->standard_unit_cost, 2) }}/unit</span>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-slate-500 text-xs">
                    {{ __('No inventory stock records found.') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

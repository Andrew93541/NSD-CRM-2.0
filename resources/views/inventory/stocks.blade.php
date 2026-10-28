@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Warehouse & Yard Stock') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Live physical stock positions across central depots and construction site storage camps.') }}</p>
        </div>
        <a href="{{ route('procurement.orders') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 transition">
            {{ __('Purchase Orders & GRN') }} &rarr;
        </a>
    </div>

    <!-- Filter by warehouse -->
    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
        <form method="GET" action="{{ route('inventory.stocks') }}" class="flex items-center gap-3">
            <select name="warehouse_id" onchange="this.form.submit()" class="rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none w-full sm:w-64">
                <option value="">{{ __('All Storage Locations') }}</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ $selectedWarehouseId == $wh->id ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->code }})</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Location') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Material Item') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Category') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('On Hand Qty') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Min Threshold') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($stocks as $s)
                        @php
                            $isLow = $s->quantity <= $s->material->minimum_stock_level;
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $s->warehouse->name }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $s->warehouse->code }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $s->material->name }}</div>
                                <div class="text-xs text-amber-600 dark:text-amber-400 font-mono">{{ $s->material->code }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-500 dark:text-slate-400">{{ $s->material->category->name }}</td>
                            <td class="py-3.5 px-4 font-mono font-bold text-right {{ $isLow ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ number_format($s->quantity, 2) }} {{ $s->material->unit_of_measure }}
                            </td>
                            <td class="py-3.5 px-4 font-mono text-slate-500 dark:text-slate-400 text-right text-xs">
                                {{ number_format($s->material->minimum_stock_level, 2) }} {{ $s->material->unit_of_measure }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                @if($isLow)
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                        {{ __('LOW STOCK') }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                        {{ __('HEALTHY') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">{{ __('No inventory stock records found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="sm:hidden space-y-3 p-4">
            @forelse($stocks as $s)
                @php
                    $isLow = $s->quantity <= $s->material->minimum_stock_level;
                @endphp
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $s->material->name }}</span>
                        @if($isLow)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                {{ __('LOW STOCK') }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                {{ __('HEALTHY') }}
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $s->warehouse->name }} &bull; {{ $s->material->category->name }}
                    </div>
                    <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-200 dark:border-slate-800/80">
                        <span class="text-slate-500">{{ __('On Hand') }}:</span>
                        <span class="font-mono font-bold {{ $isLow ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ number_format($s->quantity, 2) }} {{ $s->material->unit_of_measure }}
                        </span>
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

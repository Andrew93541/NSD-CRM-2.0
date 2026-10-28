@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="pb-4 border-b border-slate-200 dark:border-slate-800">
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Stock Movement & Consumption Ledger') }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Audit trail for all goods receipts, site transfers, and material issues.') }}</p>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Tx #') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Type') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Date') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Location / Site') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Items') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Performed By') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($transactions as $tx)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-amber-600 dark:text-amber-400 text-xs">{{ $tx->transaction_number }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">
                                    {{ str_replace('_', ' ', $tx->transaction_type) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 font-mono text-xs text-slate-700 dark:text-slate-300">{{ $tx->transaction_date->format('d M Y') }}</td>
                            <td class="py-3.5 px-4 text-xs text-slate-800 dark:text-slate-200">
                                {{ $tx->destinationWarehouse->name ?? $tx->site->name ?? __('Central Storage') }}
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                @foreach($tx->items as $item)
                                    <div>{{ $item->quantity }} {{ $item->material->unit_of_measure }} — {{ $item->material->name }}</div>
                                @endforeach
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-500 dark:text-slate-400">
                                {{ $tx->performedBy->name }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">{{ __('No transactions recorded yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Work Orders Execution Funnel') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Summary of client purchase orders and internal site work orders.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('reports.multi_site') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 transition">{{ __('Site Comparison') }}</a>
            <a href="{{ route('work_orders.index') }}" class="px-4 py-2 text-xs font-extrabold rounded-xl bg-amber-500 text-slate-950 transition">{{ __('Open Work Orders') }} &rarr;</a>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total Orders') }}</span>
            <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $totalOrders }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Client Issued') }}</span>
            <div class="text-3xl font-extrabold text-sky-600 dark:text-sky-400 mt-1">{{ $byType['client_issued'] ?? 0 }}</div>
        </div>
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
            <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Internal Work') }}</span>
            <div class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-1">{{ $byType['internal'] ?? 0 }}</div>
        </div>
    </div>

    <!-- Paginated Work Orders Register -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                <thead class="text-[11px] uppercase bg-slate-50 dark:bg-slate-950 text-slate-500 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4">{{ __('Order No') }}</th>
                        <th class="py-3 px-4">{{ __('Title') }}</th>
                        <th class="py-3 px-4">{{ __('Site / Project') }}</th>
                        <th class="py-3 px-4">{{ __('Type') }}</th>
                        <th class="py-3 px-4">{{ __('Status') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('Value') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($orders as $wo)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 px-4 font-mono font-bold text-amber-600 dark:text-amber-400">
                                <a href="{{ route('work_orders.show', $wo) }}">{{ $wo->order_number }}</a>
                            </td>
                            <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">{{ $wo->title }}</td>
                            <td class="py-3 px-4">{{ $wo->site?->code ?? '—' }} {{ $wo->project ? '· ' . $wo->project->code : '' }}</td>
                            <td class="py-3 px-4 uppercase text-[11px] font-bold">{{ str_replace('_', ' ', $wo->order_type ?? 'internal') }}</td>
                            <td class="py-3 px-4 uppercase text-[11px] font-bold">{{ str_replace('_', ' ', $wo->status) }}</td>
                            <td class="py-3 px-4 text-right font-mono font-bold">₹{{ number_format((float) ($wo->base_order_value ?: $wo->estimated_amount), 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">{{ __('No work orders found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders instanceof \Illuminate\Pagination\AbstractPaginator && $orders->hasPages())
            <div class="px-4 py-3 border-t border-slate-200 dark:border-slate-800">
                {{ $orders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

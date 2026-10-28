@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="pb-4 border-b border-slate-200 dark:border-slate-800">
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Work Orders Execution Funnel') }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Lifecycle distribution of client-issued contracts and internal task orders.') }}</p>
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
</div>
@endsection

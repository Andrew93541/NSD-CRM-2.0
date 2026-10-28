@extends('layouts.app')

@section('content')
<div class="space-y-6 sm:space-y-8">
    <!-- Header banner -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Operations Dashboard') }}</h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Live tracking for active projects, muster roll, and 2-tier approval workflows.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <a href="{{ route('attendance.muster') }}" class="inline-flex items-center gap-2 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs sm:text-sm shadow-lg shadow-amber-500/20 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                {{ __('Take Muster Roll') }}
            </a>
            @if(Auth::user()->hasRole('warehouse_manager'))
                <a href="{{ route('procurement.orders') }}" class="inline-flex items-center gap-2 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-white dark:border-slate-700 font-bold text-xs sm:text-sm transition">
                    <svg class="w-4 h-4 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    {{ __('Receive Goods') }}
                </a>
            @else
                <a href="{{ route('work_orders.create') }}" class="inline-flex items-center gap-2 px-3.5 sm:px-4 py-2 sm:py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-white dark:border-slate-700 font-bold text-xs sm:text-sm transition">
                    <svg class="w-4 h-4 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    {{ __('Raise Work Order') }}
                </a>
            @endif
        </div>
    </div>

    {{-- ROLE-ADAPTIVE FOCUS SECTION --}}
    @if(Auth::user()->hasRole('site_supervisor'))
        <!-- Site Supervisor Today's Work Banner -->
        <div class="p-5 sm:p-6 rounded-2xl bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent dark:from-amber-500/10 dark:via-slate-900 dark:to-slate-900 border border-amber-500/30 dark:border-amber-500/20 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">{{ __("Today's Work") }} &mdash; {{ __('Site Supervisor') }}</h2>
                        <p class="text-xs text-slate-600 dark:text-slate-400">{{ __('Record daily attendance and verify worker presence for your sites.') }}</p>
                    </div>
                </div>
                <a href="{{ route('workforce.create') }}" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-amber-600 dark:text-amber-400 text-xs font-bold border border-slate-200 dark:border-slate-700 transition">
                    + {{ __('Add New Worker') }}
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-2">
                @forelse($assignedSites as $site)
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition flex items-center justify-between shadow-sm">
                        <div>
                            <div class="text-sm font-bold text-slate-900 dark:text-white">{{ $site->name }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 font-mono mt-0.5">{{ $site->code }} &bull; {{ $site->location }}</div>
                        </div>
                        <a href="{{ route('attendance.muster', ['site_id' => $site->id]) }}" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-sm transition">
                            {{ __('Take Attendance') }} &rarr;
                        </a>
                    </div>
                @empty
                    <div class="p-4 rounded-xl bg-white/50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 col-span-full">
                        {{ __('No sites assigned directly. Select a site from the muster roll screen.') }}
                    </div>
                @endforelse
            </div>
        </div>

    @elseif(Auth::user()->hasRole(['site_manager', 'senior_manager']))
        <!-- Approvals Queue / Action Required Banner -->
        <div class="p-5 sm:p-6 rounded-2xl bg-gradient-to-r from-sky-500/10 via-sky-500/5 to-transparent dark:from-sky-500/10 dark:via-slate-900 dark:to-slate-900 border border-sky-500/30 dark:border-sky-500/20 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-sky-500/20 text-sky-600 dark:text-sky-400 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">{{ __('Action Required') }} &mdash; {{ __('Pending Approvals') }}</h2>
                        <p class="text-xs text-slate-600 dark:text-slate-400">{{ __('Review work orders and procurement requisitions requiring your sign-off.') }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-2">
                @if(Auth::user()->hasRole('site_manager'))
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-950/70 border border-amber-500/30 flex items-center justify-between shadow-sm">
                        <div>
                            <span class="text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">{{ __('Awaiting Site Manager (Tier 1)') }}</span>
                            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $pendingSiteApprovals }}</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Work orders awaiting site inspection') }}</div>
                        </div>
                        <a href="{{ route('work_orders.index', ['status' => 'submitted']) }}" class="px-3 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                            {{ __('Review') }} &rarr;
                        </a>
                    </div>
                @endif

                @if(Auth::user()->hasRole(['senior_manager', 'company_admin']))
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-950/70 border border-sky-500/30 flex items-center justify-between shadow-sm">
                        <div>
                            <span class="text-xs font-bold text-sky-600 dark:text-sky-400 uppercase tracking-wider">{{ __('Awaiting Senior VP (Tier 2)') }}</span>
                            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $pendingSeniorApprovals }}</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Final executive sign-off required') }}</div>
                        </div>
                        <a href="{{ route('work_orders.index', ['status' => 'site_manager_approved']) }}" class="px-3 py-1.5 rounded-lg bg-sky-500 hover:bg-sky-400 text-slate-950 font-bold text-xs transition">
                            {{ __('Authorize') }} &rarr;
                        </a>
                    </div>
                @endif

                <div class="p-4 rounded-xl bg-white dark:bg-slate-950/70 border border-purple-500/30 flex items-center justify-between shadow-sm">
                    <div>
                        <span class="text-xs font-bold text-purple-600 dark:text-purple-400 uppercase tracking-wider">{{ __('Purchase Requests') }}</span>
                        <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $pendingPurchases }}</div>
                        <div class="text-[11px] {{ $emergencyPurchases > 0 ? 'text-rose-500 font-bold' : 'text-slate-500 dark:text-slate-400' }} mt-0.5">
                            {{ $emergencyPurchases > 0 ? "{$emergencyPurchases} " . __('Emergency Exception') : __('Standard priority') }}
                        </div>
                    </div>
                    <a href="{{ route('procurement.requests') }}" class="px-3 py-1.5 rounded-lg bg-purple-500 hover:bg-purple-400 text-white font-bold text-xs transition">
                        {{ __('Review') }} &rarr;
                    </a>
                </div>
            </div>
        </div>

    @elseif(Auth::user()->hasRole('warehouse_manager'))
        <!-- Warehouse Depot Stock & Deliveries Banner -->
        <div class="p-5 sm:p-6 rounded-2xl bg-gradient-to-r from-cyan-500/10 via-cyan-500/5 to-transparent dark:from-cyan-500/10 dark:via-slate-900 dark:to-slate-900 border border-cyan-500/30 dark:border-cyan-500/20 space-y-4">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-cyan-500/20 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </div>
                    <div>
                        <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">{{ __('Materials & Deliveries') }} &mdash; {{ __('Central Depot Keeper') }}</h2>
                        <p class="text-xs text-slate-600 dark:text-slate-400">{{ __('Confirm delivery challans, goods receipt (MRN), and monitor yard stock.') }}</p>
                    </div>
                </div>
                <a href="{{ route('inventory.stocks') }}" class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700 text-cyan-600 dark:text-cyan-400 text-xs font-bold border border-slate-200 dark:border-slate-700 transition">
                    {{ __('Warehouse & Yard Stock') }} &rarr;
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 pt-2">
                @forelse($pendingGoodsReceiptOrders as $po)
                    <div class="p-4 rounded-xl bg-white dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 hover:border-cyan-500/40 transition flex items-center justify-between shadow-sm">
                        <div>
                            <div class="text-xs font-mono font-bold text-slate-900 dark:text-white">{{ $po->po_number }}</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 truncate max-w-[160px]">{{ $po->supplier_name }}</div>
                            <div class="text-[11px] text-cyan-600 dark:text-cyan-400 font-semibold mt-0.5">{{ $po->items->count() }} {{ __('Material Item') }}(s)</div>
                        </div>
                        <a href="{{ route('procurement.receive_goods', $po) }}" class="px-3 py-1.5 rounded-lg bg-cyan-500 hover:bg-cyan-400 text-slate-950 font-bold text-xs transition">
                            {{ __('Receive Goods') }} &rarr;
                        </a>
                    </div>
                @empty
                    <div class="p-4 rounded-xl bg-white/50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400 col-span-full">
                        {{ __('No purchase orders awaiting delivery confirmation.') }}
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- Universal Operational KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Card 1: Workers Today -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm transition-colors duration-200">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Active On Sites Today') }}</span>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $uniqueWorkersToday }}</div>
                <div class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold mt-1 flex items-center gap-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    {{ $totalHoursToday }} {{ __('man-hours recorded') }}
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-600 dark:text-emerald-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
        </div>

        <!-- Card 2: Shift Overlaps -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm transition-colors duration-200">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Shift Overlaps') }}</span>
                <div class="text-3xl font-extrabold {{ $conflictsToday > 0 ? 'text-rose-500' : 'text-slate-800 dark:text-slate-200' }} mt-1">
                    {{ $conflictsToday }}
                </div>
                <a href="{{ route('attendance.conflicts') }}" class="text-xs {{ $conflictsToday > 0 ? 'text-rose-600 dark:text-rose-400 underline font-semibold' : 'text-slate-500 dark:text-slate-400' }} mt-1 inline-block">
                    {{ $conflictsToday > 0 ? __('Review conflicts') : __('Zero shift collisions') }}
                </a>
            </div>
            <div class="w-12 h-12 rounded-xl {{ $conflictsToday > 0 ? 'bg-rose-500/10 border-rose-500/20 text-rose-500' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }} border flex items-center justify-center">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
        </div>

        <!-- Card 3: Pending Work Order Approvals -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm transition-colors duration-200">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Pending Approvals') }}</span>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $pendingSiteApprovals + $pendingSeniorApprovals }}</div>
                <div class="text-xs text-amber-600 dark:text-amber-400 mt-1 font-semibold">
                    {{ $pendingSiteApprovals }} T1 &bull; {{ $pendingSeniorApprovals }} T2
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-500 dark:text-amber-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
            </div>
        </div>

        <!-- Card 4: Purchase Requests -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm transition-colors duration-200">
            <div>
                <span class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Purchase Requests') }}</span>
                <div class="text-3xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $pendingPurchases }}</div>
                <div class="text-xs {{ $emergencyPurchases > 0 ? 'text-rose-500 font-bold' : 'text-slate-500 dark:text-slate-400' }} mt-1">
                    {{ $emergencyPurchases > 0 ? "{$emergencyPurchases} " . __('Emergency Exception') : __('Standard priority') }}
                </div>
            </div>
            <div class="w-12 h-12 rounded-xl bg-cyan-500/10 border border-cyan-500/20 flex items-center justify-center text-cyan-600 dark:text-cyan-400">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
            </div>
        </div>
    </div>

    <!-- Active Projects & Sites Footprint Bar -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-gradient-to-r dark:from-slate-900 dark:via-slate-900 dark:to-slate-800/80 border border-slate-200 dark:border-slate-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 shadow-sm transition-colors duration-200">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-amber-500/20 border border-amber-500/30 flex items-center justify-center text-amber-500 dark:text-amber-400 shrink-0">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
            </div>
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Heavy Infrastructure Footprint') }}</h3>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-0.5"><span class="text-amber-600 dark:text-amber-400 font-bold">{{ $activeProjectsCount }} {{ __('Active Projects') }}</span> {{ __('spanning') }} <span class="text-slate-900 dark:text-white font-bold">{{ $activeSitesCount }} {{ __('Construction Sites') }}</span></p>
            </div>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('sites.index') }}" class="px-3.5 py-2 text-xs font-bold rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 hover:text-slate-950 border border-amber-500/30 dark:text-amber-400 dark:hover:text-slate-950 transition">
                {{ __('View Sites Directory') }}
            </a>
            <a href="{{ route('reports.workforce') }}" class="px-3.5 py-2 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 transition">
                {{ __('View Site Workforce Reports') }}
            </a>
            <a href="{{ route('inventory.stocks') }}" class="px-3.5 py-2 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 transition">
                {{ __('Inspect Site Stores') }}
            </a>
        </div>
    </div>

    <!-- Active Work Orders Pipeline & Approvals -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm transition-colors duration-200">
        <div class="flex items-center justify-between">
            <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <svg class="w-5 h-5 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                {{ __('Active Work Orders Pipeline') }}
            </h2>
            <a href="{{ route('work_orders.index') }}" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">{{ __('View All Orders') }} &rarr;</a>
        </div>

        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950/60 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Order #') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Type') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Title & Site') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Estimated') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Workflow Stage') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                    @forelse($recentWorkOrders as $wo)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 px-4 font-mono font-bold text-slate-900 dark:text-white">{{ $wo->order_number }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase {{ $wo->order_type === 'client_issued' ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/30' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/30' }}">
                                    {{ $wo->order_type === 'client_issued' ? __('Client Issued') : __('Internal Work') }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $wo->title }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">{{ $wo->site->name ?? __('Project Wide') }}</div>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-700 dark:text-slate-200">
                                ₹{{ number_format($wo->estimated_amount, 2) }}
                            </td>
                            <td class="py-3 px-4">
                                @if($wo->status === 'submitted')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                        {{ __('Pending Site Manager (Tier 1)') }}
                                    </span>
                                @elseif($wo->status === 'site_manager_approved')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                        {{ __('Pending Senior VP (Tier 2)') }}
                                    </span>
                                @elseif($wo->status === 'senior_manager_approved')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                        {{ __('Authorized for Execution') }}
                                    </span>
                                @elseif($wo->status === 'rejected')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                        {{ __('Rejected') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('work_orders.show', $wo) }}" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-amber-400 dark:border-slate-700 transition">
                                    {{ __('Review') }} &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-slate-500">{{ __('No work orders recorded yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="sm:hidden space-y-3">
            @forelse($recentWorkOrders as $wo)
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="font-mono font-bold text-slate-900 dark:text-white text-xs">{{ $wo->order_number }}</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $wo->order_type === 'client_issued' ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/30' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/30' }}">
                            {{ $wo->order_type === 'client_issued' ? __('Client Issued') : __('Internal Work') }}
                        </span>
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 dark:text-white text-sm">{{ $wo->title }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $wo->site->name ?? __('Project Wide') }}</div>
                    </div>
                    <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-200 dark:border-slate-800/60">
                        <span class="font-mono font-bold text-amber-600 dark:text-amber-400">₹{{ number_format($wo->estimated_amount, 2) }}</span>
                        <a href="{{ route('work_orders.show', $wo) }}" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-amber-400 dark:border-slate-700">
                            {{ __('Review') }} &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-4 rounded-xl bg-white/50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 text-center text-xs text-slate-500">
                    {{ __('No work orders recorded yet.') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

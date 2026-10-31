@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-amber-600 dark:text-amber-400 border border-slate-200 dark:border-slate-700">{{ $site->code }}</span>
                @if($site->status === 'active')
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">{{ __('Active') }}</span>
                @elseif($site->status === 'paused')
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">{{ __('Paused') }}</span>
                @else
                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/30">{{ __('Closed') }}</span>
                @endif
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1.5">{{ $site->name }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Project') }}: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $site->project->code ?? '' }} — {{ $site->project->name ?? __('Unassigned') }}</span>
                @if($site->project?->client_name)
                    &bull; {{ $site->project->client_name }}
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('sites.index') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:border-slate-700 transition">
                &larr; {{ __('Back to Sites') }}
            </a>
            @can('recordAttendance', $site)
                <a href="{{ route('attendance.muster', ['site_id' => $site->id]) }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-emerald-500/10 hover:bg-emerald-500 text-emerald-700 hover:text-slate-950 border border-emerald-500/30 dark:text-emerald-400 dark:hover:text-slate-950 transition">
                    {{ __('Take Attendance') }}
                </a>
            @endcan
            @can('update', $site)
                <a href="{{ route('sites.edit', $site) }}" class="px-4 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition">
                    {{ __('Edit Site') }}
                </a>
            @endcan
        </div>
    </div>

    <!-- Real Operational Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Assigned Workers') }}</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $site->workers_count }}</div>
            <a href="{{ route('workforce.index', ['site_id' => $site->id]) }}" class="inline-block mt-2 text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                {{ __('View Site Workers') }} &rarr;
            </a>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Present Today') }} ({{ $today }})</div>
            <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">
                {{ $uniqueWorkersPresentToday }}
                <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">({{ $hoursToday }} {{ __('Hours') }})</span>
            </div>
            <a href="{{ route('reports.workforce', ['site_id' => $site->id]) }}" class="inline-block mt-2 text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                {{ __('View Site Workforce Reports') }} &rarr;
            </a>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Work Orders') }}</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $site->work_orders_count }}</div>
            <a href="{{ route('work_orders.index', ['site_id' => $site->id]) }}" class="inline-block mt-2 text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                {{ __('View Site Work Orders') }} &rarr;
            </a>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Site Stores & Requests') }}</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">
                {{ $site->warehouses_count }} <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Stores') }} &bull; {{ $site->purchase_requests_count }} {{ __('Requests') }}</span>
            </div>
            <a href="{{ route('procurement.requests') }}" class="inline-block mt-2 text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                {{ __('Purchase Requests') }} &rarr;
            </a>
        </div>
    </div>

    <!-- Site Details & Location Information -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Location & Geolocation Card -->
        <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm transition-colors duration-200">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Site Location & Address') }}</h2>
                @if($site->hasValidCoordinates())
                    <a href="{{ $site->mapUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-amber-500/10 hover:bg-amber-500 text-amber-700 hover:text-slate-950 border border-amber-500/30 dark:text-amber-400 dark:hover:text-slate-950 font-bold text-xs transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                        {{ __('Open in Map') }}
                    </a>
                @endif
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div class="sm:col-span-2">
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('Corridor / Location Landmark') }}</span>
                    <span class="font-semibold text-slate-900 dark:text-white">{{ $site->location ?: __('Not specified') }}</span>
                </div>

                <div class="sm:col-span-2">
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('Street Address') }}</span>
                    <span class="font-semibold text-slate-900 dark:text-white">{{ $site->address ?: __('Not specified') }}</span>
                </div>

                <div>
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('City / District') }}</span>
                    <span class="font-semibold text-slate-900 dark:text-white">{{ $site->city ?: __('Not specified') }}</span>
                </div>

                <div>
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('State & Postal Code') }}</span>
                    <span class="font-semibold text-slate-900 dark:text-white">
                        {{ $site->state ?: __('Not specified') }}{{ $site->postal_code ? ' — ' . $site->postal_code : '' }}
                    </span>
                </div>

                <div class="sm:col-span-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 dark:text-slate-400 block mb-1">{{ __('GPS Coordinates') }}</span>
                    @if($site->hasValidCoordinates())
                        <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                            <span class="font-mono font-bold text-slate-900 dark:text-white">
                                {{ number_format((float) $site->latitude, 6) }}, {{ number_format((float) $site->longitude, 6) }}
                            </span>
                            <a href="{{ $site->mapUrl() }}" target="_blank" rel="noopener noreferrer" class="text-amber-600 dark:text-amber-400 font-bold hover:underline">
                                {{ __('Open in Map') }} &rarr;
                            </a>
                        </div>
                    @else
                        <span class="text-slate-500 dark:text-slate-400 italic">{{ __('Coordinates not recorded') }}</span>
                    @endif
                </div>

                <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('Geofence Radius') }}</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $site->geofence_radius_m ?? 200 }} m</span>
                </div>

                <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('Shift Hours') }}</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white">
                        {{ $site->shift_start ? substr($site->shift_start, 0, 5) : '09:00' }} — {{ $site->shift_end ? substr($site->shift_end, 0, 5) : '18:00' }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Management & Project Context Card -->
        <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm transition-colors duration-200">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Management & Project Details') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('Site Manager') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $site->siteManager->name ?? __('Unassigned') }}</span>
                    @if($site->siteManager?->email)
                        <div class="text-[11px] text-slate-500 font-mono">{{ $site->siteManager->email }}</div>
                    @endif
                </div>

                <div>
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('Site Supervisor / Manager') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $site->supervisor->name ?? __('Unassigned') }}</span>
                    @if($site->supervisor?->email)
                        <div class="text-[11px] text-slate-500 font-mono">{{ $site->supervisor->email }}</div>
                    @endif
                </div>

                <div>
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('Project Manager') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $site->project?->manager?->name ?? __('Unassigned') }}</span>
                </div>

                @if($site->project)
                <div>
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('Linked Project') }}</span>
                    <a href="{{ route('projects.show', $site->project) }}" class="font-bold text-amber-600 dark:text-amber-400 hover:underline">
                        {{ $site->project->code }} — {{ $site->project->name }} &rarr;
                    </a>
                </div>
                @endif

                @if($site->description)
                    <div class="sm:col-span-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                        <span class="text-slate-500 dark:text-slate-400 block mb-1">{{ __('Description') }}</span>
                        <p class="text-slate-700 dark:text-slate-300 leading-relaxed">{{ $site->description }}</p>
                    </div>
                @endif

                <div class="sm:col-span-2 pt-3 border-t border-slate-200 dark:border-slate-800">
                    <span class="text-slate-500 dark:text-slate-400 block mb-2">{{ __('Assigned Site Personnel') }} ({{ $site->assignedUsers->count() }})</span>
                    @if($site->assignedUsers->isNotEmpty())
                        <div class="flex flex-wrap gap-2">
                            @foreach($site->assignedUsers as $u)
                                <span class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-slate-800 dark:text-slate-200 font-semibold text-xs">
                                    {{ $u->name }}
                                </span>
                            @endforeach
                        </div>
                    @else
                        <span class="text-slate-500 dark:text-slate-400 italic">{{ __('No personnel explicitly attached.') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Related Assigned Workers & Work Orders -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Assigned Workers -->
        <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Assigned Workers') }} ({{ $site->workers_count }})</h2>
                <a href="{{ route('workforce.index', ['site_id' => $site->id]) }}" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                    {{ __('View All') }} &rarr;
                </a>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($assignedWorkers as $w)
                    <div class="py-2.5 flex items-center justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">
                                <span class="font-mono text-amber-600 dark:text-amber-400 mr-1.5">{{ $w->code }}</span>
                                {{ $w->full_name }}
                            </div>
                            <div class="text-slate-500 dark:text-slate-400 mt-0.5">{{ $w->trade_designation }}</div>
                        </div>
                        <a href="{{ route('workforce.edit', $w) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-amber-500 hover:text-slate-950 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 font-bold transition">
                            {{ __('Edit') }}
                        </a>
                    </div>
                @empty
                    <div class="py-4 text-slate-500 dark:text-slate-400">{{ __('No workers currently assigned to this site.') }}</div>
                @endforelse
            </div>
        </div>

        <!-- Site Work Orders -->
        <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Work Orders') }} ({{ $site->work_orders_count }})</h2>
                <a href="{{ route('work_orders.index', ['site_id' => $site->id]) }}" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                    {{ __('View All Orders') }} &rarr;
                </a>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($recentWorkOrders as $wo)
                    <div class="py-2.5 flex items-center justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">
                                <span class="font-mono text-amber-600 dark:text-amber-400 mr-1.5">{{ $wo->order_number }}</span>
                                {{ $wo->title }}
                            </div>
                            <div class="text-slate-500 dark:text-slate-400 mt-0.5">
                                ₹{{ number_format($wo->estimated_amount, 2) }} &bull; {{ str_replace('_', ' ', $wo->status) }}
                            </div>
                        </div>
                        @can('view', $wo)
                            <a href="{{ route('work_orders.show', $wo) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-amber-500 hover:text-slate-950 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 font-bold transition whitespace-nowrap">
                                {{ __('Review') }}
                            </a>
                        @endcan
                    </div>
                @empty
                    <div class="py-4 text-slate-500 dark:text-slate-400">{{ __('No work orders recorded for this site.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Site Warehouses & Material Purchase Requests -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Site Storage Yards -->
        <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Site Storage Yards') }} ({{ $site->warehouses->count() }})</h2>
                <a href="{{ route('inventory.stocks') }}" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                    {{ __('Warehouse & Yard Stock') }} &rarr;
                </a>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($site->warehouses as $wh)
                    <div class="py-2.5 flex items-center justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">
                                <span class="font-mono text-amber-600 dark:text-amber-400 mr-1.5">{{ $wh->code }}</span>
                                {{ $wh->name }}
                            </div>
                            <div class="text-slate-500 dark:text-slate-400 mt-0.5">{{ $wh->address ?? __('Site Store') }}</div>
                        </div>
                        <a href="{{ route('inventory.stocks', ['warehouse_id' => $wh->id]) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-amber-500 hover:text-slate-950 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 font-bold transition whitespace-nowrap">
                            {{ __('Inspect Site Stores') }}
                        </a>
                    </div>
                @empty
                    <div class="py-4 text-slate-500 dark:text-slate-400">{{ __('No storage yards linked to this site.') }}</div>
                @endforelse
            </div>
        </div>

        <!-- Site Material Requisitions -->
        <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Purchase Requests') }} ({{ $site->purchase_requests_count }})</h2>
                <a href="{{ route('procurement.requests') }}" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                    {{ __('Purchase Requests') }} &rarr;
                </a>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($recentPurchaseRequests as $pr)
                    <div class="py-2.5 flex items-center justify-between gap-2">
                        <div>
                            <div class="font-bold text-slate-900 dark:text-white">
                                <span class="font-mono text-amber-600 dark:text-amber-400 mr-1.5">{{ $pr->request_number }}</span>
                                {{ $pr->requester->name ?? '' }}
                            </div>
                            <div class="text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ $pr->items->count() }} {{ __('items') }} &bull; {{ strtoupper($pr->status) }}
                            </div>
                        </div>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                            {{ $pr->urgency }}
                        </span>
                    </div>
                @empty
                    <div class="py-4 text-slate-500 dark:text-slate-400">{{ __('No material purchase requests for this site.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Dated Site Assignment & Transfer History -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-5 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Dated Site Assignments & Transfer History') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Tracks staff and worker assignments to this site with start and end dates. Transfers automatically close prior site assignments.') }}</p>
            </div>
        </div>

        @can('update', $site)
            <form method="POST" action="{{ route('sites.assignments.store', $site) }}" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-4">
                @csrf
                <div class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase">{{ __('Assign or Transfer Staff / Worker to This Site') }}</div>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Assignment Type') }}</label>
                        <select name="assignment_type" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="worker">{{ __('Worker') }}</option>
                            <option value="user">{{ __('Staff / User') }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Select Worker') }}</label>
                        <select name="worker_id" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('— Select Worker —') }}</option>
                            @foreach($companyWorkers ?? [] as $cw)
                                <option value="{{ $cw->id }}">{{ $cw->code }} — {{ $cw->full_name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Or Select Staff User') }}</label>
                        <select name="user_id" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('— Select Staff —') }}</option>
                            @foreach($companyUsers ?? [] as $cu)
                                <option value="{{ $cu->id }}">{{ $cu->name }} ({{ $cu->email }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Role on Site') }}</label>
                        <input type="text" name="role_on_site" value="worker" required maxlength="60" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Start Date') }}</label>
                        <input type="date" name="start_date" value="{{ now()->toDateString() }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs font-mono text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
                    <input type="text" name="notes" placeholder="{{ __('Optional transfer reason or deployment note...') }}" maxlength="255" class="flex-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs transition whitespace-nowrap">
                        {{ __('Record Site Assignment') }}
                    </button>
                </div>
            </form>
        @endcan

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 uppercase text-[11px]">
                        <th class="py-2.5 pr-4">{{ __('Person') }}</th>
                        <th class="py-2.5 px-4">{{ __('Role on Site') }}</th>
                        <th class="py-2.5 px-4">{{ __('Start Date') }}</th>
                        <th class="py-2.5 px-4">{{ __('End Date') }}</th>
                        <th class="py-2.5 px-4">{{ __('Status') }}</th>
                        <th class="py-2.5 pl-4">{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($siteAssignments ?? [] as $sa)
                        <tr>
                            <td class="py-2.5 pr-4 font-bold text-slate-900 dark:text-white">
                                @if($sa->worker)
                                    <span class="font-mono text-amber-600 dark:text-amber-400 mr-1">{{ $sa->worker->code }}</span> {{ $sa->worker->full_name }}
                                @elseif($sa->user)
                                    {{ $sa->user->name }} <span class="text-slate-400 font-normal">({{ $sa->user->email }})</span>
                                @else
                                    {{ __('Unknown') }}
                                @endif
                            </td>
                            <td class="py-2.5 px-4 text-slate-700 dark:text-slate-300">{{ $sa->role_on_site }}</td>
                            <td class="py-2.5 px-4 font-mono text-slate-700 dark:text-slate-300">{{ $sa->start_date?->format('Y-m-d') }}</td>
                            <td class="py-2.5 px-4 font-mono text-slate-500 dark:text-slate-400">{{ $sa->end_date ? $sa->end_date->format('Y-m-d') : '—' }}</td>
                            <td class="py-2.5 px-4">
                                @if($sa->is_active)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">{{ __('Active') }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/30">{{ __('Historical') }}</span>
                                @endif
                            </td>
                            <td class="py-2.5 pl-4 text-slate-500 dark:text-slate-400">{{ $sa->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-slate-500 dark:text-slate-400">{{ __('No dated assignment records logged for this site yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection


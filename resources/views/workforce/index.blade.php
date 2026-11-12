@extends('layouts.app')

@section('content')
@php
    $selectedSiteFilter = request()->has('site_id') ? request('site_id') : session('active_site_id');
    $hasActiveFilters = filled(request('search')) || filled(request('type')) || filled(request('site_id')) || filled(request('compliance_filter'));
@endphp
<div class="space-y-6" x-data="{ showMoreFilters: {{ $hasActiveFilters ? 'true' : 'false' }} }">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Workforce Directory') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Manage direct employees, site labourers, and subcontractor workers.') }}</p>
        </div>
        @can('create', \App\Models\Worker::class)
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="{{ route('workforce.import') }}" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-white dark:bg-slate-900 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 font-semibold text-xs transition">
                    {{ __('Bulk CSV Upload') }}
                </a>
                <a href="{{ route('workforce.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs shadow-sm transition">
                    + {{ __('Add New Worker') }}
                </a>
            </div>
        @endcan
    </div>

    <!-- Compliance Alert Strip -->
    @if(($expiredDocsCount ?? 0) > 0 || ($expiringSoonDocsCount ?? 0) > 0)
        <div class="flex flex-wrap items-center gap-3 p-3.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-xs">
            <span class="font-bold text-slate-900 dark:text-white">{{ __('Document Reminders') }}:</span>
            @if(($expiredDocsCount ?? 0) > 0)
                <a href="{{ route('workforce.index', ['compliance_filter' => 'expired']) }}" class="px-2.5 py-1 rounded-lg bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30 font-bold hover:underline">
                    {{ $expiredDocsCount }} {{ __('Expired Document(s)') }}
                </a>
            @endif
            @if(($expiringSoonDocsCount ?? 0) > 0)
                <a href="{{ route('workforce.index', ['compliance_filter' => 'expiring_soon']) }}" class="px-2.5 py-1 rounded-lg bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30 font-bold hover:underline">
                    {{ $expiringSoonDocsCount }} {{ __('Expiring Within 30 Days') }}
                </a>
            @endif
        </div>
    @endif

    <!-- Search & Collapsible Filters -->
    <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
        <form method="GET" action="{{ route('workforce.index') }}" class="space-y-3">
            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                <div class="flex-1">
                    <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by worker name, ID code, or phone number...') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="showMoreFilters = !showMoreFilters" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 text-xs font-bold transition">
                        <span x-text="showMoreFilters ? '{{ __('Hide Filters') }}' : '{{ __('More Filters') }}'">{{ __('More Filters') }}</span>
                    </button>
                    <button type="submit" class="py-2 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-amber-500 dark:hover:bg-amber-400 dark:text-slate-950 text-xs font-extrabold transition">{{ __('Search') }}</button>
                    @if($hasActiveFilters)
                        <a href="{{ route('workforce.index') }}" class="py-2 px-3 rounded-xl bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-950 dark:hover:bg-slate-800 dark:text-slate-400 dark:border-slate-800 text-xs font-bold transition">{{ __('Reset') }}</a>
                    @endif
                </div>
            </div>

            <div x-show="showMoreFilters" x-cloak class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Worker Category') }}</label>
                    <select name="type" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="">{{ __('All Worker Types') }}</option>
                        <option value="employee" {{ request('type') == 'employee' ? 'selected' : '' }}>{{ __('Direct Employee') }}</option>
                        <option value="direct_labour" {{ request('type') == 'direct_labour' ? 'selected' : '' }}>{{ __('Direct Labour') }}</option>
                        <option value="subcontractor_worker" {{ request('type') == 'subcontractor_worker' ? 'selected' : '' }}>{{ __('Subcontractor Worker') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Assigned Site') }}</label>
                    <select name="site_id" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="">{{ __('All Sites') }}</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}" {{ (string) $selectedSiteFilter === (string) $s->id ? 'selected' : '' }}>{{ $s->code }} - {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Document Expiry') }}</label>
                    <select name="compliance_filter" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="">{{ __('All Document Statuses') }}</option>
                        <option value="expiring_soon" {{ request('compliance_filter') === 'expiring_soon' ? 'selected' : '' }}>{{ __('Expiring Soon (30d)') }}</option>
                        <option value="expired" {{ request('compliance_filter') === 'expired' ? 'selected' : '' }}>{{ __('Expired Documents') }}</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Worker ID') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Name & Phone') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Category') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Trade / Skill') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Assigned Site') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Daily Rate') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($workers as $w)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-amber-600 dark:text-amber-400 text-xs">
                                <a href="{{ route('workforce.show', $w) }}" class="hover:underline">{{ $w->code }}</a>
                            </td>
                            <td class="py-3.5 px-4">
                                <a href="{{ route('workforce.show', $w) }}" class="font-bold text-slate-900 dark:text-white hover:text-amber-600 dark:hover:text-amber-400 transition">{{ $w->full_name }}</a>
                                <div class="text-xs text-slate-500 dark:text-slate-400 font-mono">{{ $w->phone ?? __('No phone') }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                @if($w->worker_type === 'employee')
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">{{ __('Direct Employee') }}</span>
                                @elseif($w->worker_type === 'direct_labour')
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/30">{{ __('Direct Labour') }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/30">
                                        {{ __('Sub') }}: {{ $w->subcontractor->name ?? __('External') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-300">{{ $w->trade_designation }}</td>
                            <td class="py-3.5 px-4 text-xs text-slate-600 dark:text-slate-300">{{ $w->assignedSite->name ?? __('Unassigned') }}</td>
                            <td class="py-3.5 px-4 font-mono text-slate-700 dark:text-slate-200 text-right text-xs">
                                ₹{{ number_format($w->daily_rate, 2) }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center justify-end gap-1.5">
                                    <a href="{{ route('workforce.show', $w) }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700 font-bold text-xs transition">
                                        {{ __('Profile') }}
                                    </a>
                                    @can('update', $w)
                                        <a href="{{ route('workforce.edit', $w) }}" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 hover:text-slate-950 border border-amber-500/30 dark:text-amber-400 dark:hover:text-slate-950 font-bold text-xs transition">
                                            {{ __('Edit') }}
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 px-4 text-center">
                                <div class="max-w-md mx-auto space-y-2.5">
                                    <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ __('No workers match the specified filters.') }}</div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $hasActiveFilters ? __('Try clearing your search filters or switching to All Sites.') : __('Start by adding your first company employee or site worker.') }}
                                    </p>
                                    @if(!$hasActiveFilters)
                                        @can('create', \App\Models\Worker::class)
                                            <div class="pt-1">
                                                <a href="{{ route('workforce.create') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-sm transition">
                                                    + {{ __('Add Your First Worker') }}
                                                </a>
                                            </div>
                                        @endcan
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="sm:hidden space-y-3 p-4">
            @forelse($workers as $w)
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <a href="{{ route('workforce.show', $w) }}" class="font-mono font-bold text-amber-600 dark:text-amber-400 text-xs hover:underline">{{ $w->code }}</a>
                        @if($w->worker_type === 'employee')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">{{ __('Direct Employee') }}</span>
                        @elseif($w->worker_type === 'direct_labour')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/30">{{ __('Direct Labour') }}</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/30">
                                {{ __('Sub') }}: {{ $w->subcontractor->name ?? __('External') }}
                            </span>
                        @endif
                    </div>
                    <div>
                        <a href="{{ route('workforce.show', $w) }}" class="font-bold text-slate-900 dark:text-white text-sm hover:text-amber-600 dark:hover:text-amber-400 transition block">{{ $w->full_name }}</a>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $w->trade_designation }} &bull; {{ $w->assignedSite->name ?? __('Unassigned') }}</div>
                        @if($w->phone)
                            <div class="text-[11px] text-slate-500 font-mono mt-0.5">{{ $w->phone }}</div>
                        @endif
                    </div>
                    <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-200 dark:border-slate-800/80">
                        <div>
                            <span class="font-mono font-bold text-slate-800 dark:text-slate-200">₹{{ number_format($w->daily_rate, 2) }}/day</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('workforce.show', $w) }}" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 transition">
                                {{ __('Profile') }}
                            </a>
                            @can('update', $w)
                                <a href="{{ route('workforce.edit', $w) }}" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 hover:text-slate-950 border border-amber-500/30 dark:text-amber-400 dark:hover:text-slate-950 transition">
                                    {{ __('Edit') }}
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-6 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 text-center text-xs text-slate-500 space-y-2">
                    <div>{{ __('No workers match the specified filters.') }}</div>
                    @if(!$hasActiveFilters)
                        @can('create', \App\Models\Worker::class)
                            <a href="{{ route('workforce.create') }}" class="inline-flex items-center px-3.5 py-1.5 rounded-lg bg-amber-500 text-slate-950 font-extrabold">
                                + {{ __('Add Your First Worker') }}
                            </a>
                        @endcan
                    @endif
                </div>
            @endforelse
        </div>

        @if($workers->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950">
                {{ $workers->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

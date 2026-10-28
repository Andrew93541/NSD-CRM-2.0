@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Construction Sites') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Active highway sectors, metro packages, and field operations locations.') }}</p>
        </div>
        @can('create', \App\Models\Site::class)
            <a href="{{ route('sites.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-sm shadow-lg shadow-amber-500/20 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Add New Site') }}
            </a>
        @endcan
    </div>

    <!-- Search & Filters -->
    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
        <form method="GET" action="{{ route('sites.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search site name, code or location...') }}" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
            </div>
            <div>
                <select name="project_id" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    <option value="">{{ __('All Projects') }}</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ (string) request('project_id') === (string) $p->id ? 'selected' : '' }}>{{ $p->code }} — {{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <select name="status" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                    <option value="paused" {{ request('status') === 'paused' ? 'selected' : '' }}>{{ __('Paused') }}</option>
                    <option value="closed" {{ request('status') === 'closed' ? 'selected' : '' }}>{{ __('Closed') }}</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-white text-xs font-bold transition">{{ __('Filter') }}</button>
                <a href="{{ route('sites.index') }}" class="py-2 px-3 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-950 dark:hover:bg-slate-800 dark:text-slate-400 dark:border-slate-800 text-xs font-bold transition">{{ __('Reset') }}</a>
            </div>
        </form>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Site Code') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Site Name & Project') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Location & Coordinates') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Supervisor / Manager') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Status') }}</th>
                        <th class="py-3 px-4 font-semibold text-center">{{ __('Activity') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($sites as $s)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4 font-mono font-bold text-amber-600 dark:text-amber-400 text-xs whitespace-nowrap">
                                <a href="{{ route('sites.show', $s) }}" class="hover:underline">{{ $s->code }}</a>
                            </td>
                            <td class="py-3.5 px-4">
                                <a href="{{ route('sites.show', $s) }}" class="font-bold text-slate-900 dark:text-white hover:text-amber-600 dark:hover:text-amber-400 transition">
                                    {{ $s->name }}
                                </a>
                                <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                    {{ $s->project->code ?? '' }} &bull; {{ $s->project->name ?? __('Unassigned') }}
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <div class="text-slate-700 dark:text-slate-200">
                                    {{ $s->formattedAddress() !== '' ? $s->formattedAddress() : ($s->location ?: __('Not specified')) }}
                                </div>
                                @if($s->hasValidCoordinates())
                                    <div class="mt-1 flex items-center gap-2">
                                        <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                            {{ number_format((float) $s->latitude, 4) }}, {{ number_format((float) $s->longitude, 4) }}
                                        </span>
                                        <a href="{{ $s->mapUrl() }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:underline">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                            {{ __('Open Map') }}
                                        </a>
                                    </div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-700 dark:text-slate-300">
                                {{ $s->supervisor->name ?? __('Unassigned') }}
                            </td>
                            <td class="py-3.5 px-4">
                                @if($s->status === 'active')
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">{{ __('Active') }}</span>
                                @elseif($s->status === 'paused')
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">{{ __('Paused') }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/30">{{ __('Closed') }}</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center text-xs text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $s->workers_count }}</span> {{ __('Workers') }}
                                <span class="mx-1 text-slate-300 dark:text-slate-700">&bull;</span>
                                <span class="font-bold text-slate-900 dark:text-white">{{ $s->work_orders_count }}</span> {{ __('Work Orders') }}
                            </td>
                            <td class="py-3.5 px-4 text-right whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('sites.show', $s) }}" class="px-2.5 py-1 rounded-lg bg-amber-500/10 hover:bg-amber-500 text-amber-700 hover:text-slate-950 border border-amber-500/30 dark:text-amber-400 dark:hover:text-slate-950 font-bold text-xs transition">
                                        {{ __('View Details') }}
                                    </a>
                                    @can('update', $s)
                                        <a href="{{ route('sites.edit', $s) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-300 dark:border-slate-700 font-bold text-xs transition">
                                            {{ __('Edit') }}
                                        </a>
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="py-10 px-4 text-center">
                                @if($hasFilters)
                                    <div class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ __('No sites match your search or filter criteria.') }}</div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Try clearing your search query or selecting a different project or status filter.') }}</p>
                                    <a href="{{ route('sites.index') }}" class="inline-block mt-3 px-3.5 py-1.5 rounded-lg bg-amber-500 text-slate-950 font-bold text-xs transition">
                                        {{ __('Reset') }}
                                    </a>
                                @else
                                    <div class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ __('No construction sites are currently assigned or registered.') }}</div>
                                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Sites will appear here once created or assigned to your account.') }}</p>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="sm:hidden space-y-3 p-4">
            @forelse($sites as $s)
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono font-bold text-amber-600 dark:text-amber-400 text-xs">{{ $s->code }}</span>
                        @if($s->status === 'active')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">{{ __('Active') }}</span>
                        @elseif($s->status === 'paused')
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">{{ __('Paused') }}</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/30">{{ __('Closed') }}</span>
                        @endif
                    </div>

                    <div>
                        <a href="{{ route('sites.show', $s) }}" class="font-bold text-slate-900 dark:text-white text-sm hover:text-amber-600 dark:hover:text-amber-400 transition block">
                            {{ $s->name }}
                        </a>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $s->project->code ?? '' }} &bull; {{ $s->project->name ?? __('Unassigned') }}
                        </div>
                    </div>

                    <div class="text-xs text-slate-600 dark:text-slate-300 space-y-1">
                        <div>
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Location') }}:</span>
                            {{ $s->formattedAddress() !== '' ? $s->formattedAddress() : ($s->location ?: __('Not specified')) }}
                        </div>
                        <div>
                            <span class="text-slate-500 dark:text-slate-400">{{ __('Site Supervisor') }}:</span>
                            {{ $s->supervisor->name ?? __('Unassigned') }}
                        </div>
                        @if($s->hasValidCoordinates())
                            <div class="flex items-center gap-2 pt-0.5">
                                <span class="font-mono text-[11px] text-slate-500 dark:text-slate-400">
                                    {{ number_format((float) $s->latitude, 4) }}, {{ number_format((float) $s->longitude, 4) }}
                                </span>
                                <a href="{{ $s->mapUrl() }}" target="_blank" rel="noopener noreferrer" class="text-[11px] font-bold text-amber-600 dark:text-amber-400 hover:underline">
                                    {{ __('Open Map') }} &rarr;
                                </a>
                            </div>
                        @endif
                    </div>

                    <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-200 dark:border-slate-800/80">
                        <span class="text-slate-600 dark:text-slate-400">
                            <strong class="text-slate-900 dark:text-white">{{ $s->workers_count }}</strong> {{ __('Workers') }} &bull;
                            <strong class="text-slate-900 dark:text-white">{{ $s->work_orders_count }}</strong> {{ __('Work Orders') }}
                        </span>
                        <div class="flex items-center gap-1.5">
                            <a href="{{ route('sites.show', $s) }}" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-amber-500 text-slate-950 transition">
                                {{ __('View Details') }}
                            </a>
                            @can('update', $s)
                                <a href="{{ route('sites.edit', $s) }}" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 transition">
                                    {{ __('Edit') }}
                                </a>
                            @endcan
                        </div>
                    </div>
                </div>
            @empty
                <div class="p-5 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 text-center space-y-2">
                    @if($hasFilters)
                        <div class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('No sites match your search or filter criteria.') }}</div>
                        <a href="{{ route('sites.index') }}" class="inline-block px-3 py-1 rounded-lg bg-amber-500 text-slate-950 font-bold text-xs">
                            {{ __('Reset') }}
                        </a>
                    @else
                        <div class="text-xs font-bold text-slate-700 dark:text-slate-300">{{ __('No construction sites are currently assigned or registered.') }}</div>
                    @endif
                </div>
            @endforelse
        </div>

        @if($sites->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950">
                {{ $sites->links() }}
            </div>
        @endif
    </div>
</div>
@endsection


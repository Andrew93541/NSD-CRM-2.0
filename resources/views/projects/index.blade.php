@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Projects Portfolio') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Track civil infrastructure projects, multi-site allocations, milestones, and versioned drawings.') }}</p>
        </div>

        @can('create', \App\Models\Project::class)
            <a href="{{ route('projects.create') }}" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                {{ __('Create Project') }}
            </a>
        @endcan
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('projects.index') }}" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
        <div class="flex-1">
            <input type="text" name="search" value="{{ $search }}" placeholder="{{ __('Search by project code, title, or client...') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 outline-none focus:border-amber-500">
        </div>
        <div class="w-full sm:w-48">
            <select name="status" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white outline-none focus:border-amber-500">
                <option value="">{{ __('All Statuses') }}</option>
                <option value="planning" {{ $status === 'planning' ? 'selected' : '' }}>{{ __('Planning') }}</option>
                <option value="active" {{ $status === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                <option value="on_hold" {{ $status === 'on_hold' ? 'selected' : '' }}>{{ __('On Hold') }}</option>
                <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
            </select>
        </div>
        <div class="flex items-center gap-2">
            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-amber-500 dark:hover:bg-amber-400 dark:text-slate-950 font-bold text-xs transition">
                {{ __('Filter') }}
            </button>
            @if($search || $status)
                <a href="{{ route('projects.index') }}" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300 text-xs font-bold transition">
                    {{ __('Reset') }}
                </a>
            @endif
        </div>
    </form>

    <!-- Projects Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
        @forelse($projects as $project)
            @php
                $progress = $project->overallProgressPercent();
                $siteCount = max($project->sites_count ?? 0, $project->linked_sites_count ?? 0);
            @endphp
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm hover:border-amber-500/40 transition flex flex-col justify-between space-y-4">
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-amber-600 dark:text-amber-400 border border-slate-200 dark:border-slate-700">
                            {{ $project->code }}
                        </span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase
                            @if($project->status === 'active') bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30
                            @elseif($project->status === 'planning') bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/30
                            @elseif($project->status === 'on_hold') bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30
                            @else bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/30 @endif">
                            {{ str_replace('_', ' ', $project->status) }}
                        </span>
                    </div>

                    <div>
                        <a href="{{ route('projects.show', $project) }}" class="text-base font-extrabold text-slate-900 dark:text-white hover:text-amber-600 dark:hover:text-amber-400 transition">
                            {{ $project->name }}
                        </a>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            {{ $project->client_name ?: __('Internal / Unspecified Client') }}
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 pt-2 text-xs">
                        <div class="p-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200/70 dark:border-slate-800">
                            <div class="text-[10px] uppercase text-slate-400 font-bold">{{ __('Sites') }}</div>
                            <div class="font-extrabold text-slate-900 dark:text-white mt-0.5">{{ $siteCount }}</div>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200/70 dark:border-slate-800">
                            <div class="text-[10px] uppercase text-slate-400 font-bold">{{ __('Milestones') }}</div>
                            <div class="font-extrabold text-slate-900 dark:text-white mt-0.5">{{ $project->milestones_count }}</div>
                        </div>
                        <div class="p-2 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200/70 dark:border-slate-800">
                            <div class="text-[10px] uppercase text-slate-400 font-bold">{{ __('Documents') }}</div>
                            <div class="font-extrabold text-slate-900 dark:text-white mt-0.5">{{ $project->documents_count }}</div>
                        </div>
                    </div>

                    <div class="pt-1">
                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-600 dark:text-slate-400 mb-1">
                            <span>{{ __('Milestone Completion') }}</span>
                            <span class="font-mono text-amber-600 dark:text-amber-400">{{ $progress }}%</span>
                        </div>
                        <div class="w-full h-2 rounded-full bg-slate-100 dark:bg-slate-800 overflow-hidden">
                            <div class="h-full bg-amber-500 rounded-full" style="width: {{ $progress }}%"></div>
                        </div>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                    <span class="text-slate-500 dark:text-slate-400">
                        {{ __('Manager') }}: <strong class="text-slate-800 dark:text-slate-200">{{ $project->manager->name ?? __('Unassigned') }}</strong>
                    </span>
                    <a href="{{ route('projects.show', $project) }}" class="font-bold text-amber-600 dark:text-amber-400 hover:underline">
                        {{ __('Open Project') }} &rarr;
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center text-sm text-slate-500 dark:text-slate-400">
                {{ __('No projects match the current filter criteria.') }}
            </div>
        @endforelse
    </div>

    <div>
        {{ $projects->links() }}
    </div>
</div>
@endsection


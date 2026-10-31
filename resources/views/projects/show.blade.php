@extends('layouts.app')

@section('content')
@php
    $activeTab = request('tab', 'overview');
@endphp
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex flex-wrap items-center gap-2.5">
                <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-amber-600 dark:text-amber-400 border border-slate-200 dark:border-slate-700">{{ $project->code }}</span>
                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase
                    @if($project->status === 'active') bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30
                    @elseif($project->status === 'planning') bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/30
                    @elseif($project->status === 'on_hold') bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30
                    @else bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/30 @endif">
                    {{ str_replace('_', ' ', $project->status) }}
                </span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1.5">{{ $project->name }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Client') }}: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $project->client_name ?: __('Internal') }}</span>
                &bull; {{ __('Project Manager') }}: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $project->manager->name ?? __('Unassigned') }}</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('projects.index') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 transition">
                &larr; {{ __('Back to Projects') }}
            </a>
            @can('update', $project)
                <a href="{{ route('projects.edit', $project) }}" class="px-4 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition">
                    {{ __('Edit Project') }}
                </a>
            @endcan
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Overall Progress') }}</div>
            <div class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $overallProgress }}%</div>
            <div class="w-full h-1.5 rounded-full bg-slate-100 dark:bg-slate-800 mt-2 overflow-hidden">
                <div class="h-full bg-amber-500 rounded-full" style="width: {{ $overallProgress }}%"></div>
            </div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Linked Sites') }}</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $allSites->count() }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ __('Active project corridors') }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Milestones') }}</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $project->milestones->count() }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ $project->milestones->where('status', 'completed')->count() }} {{ __('Completed') }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Versioned Documents') }}</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $visibleDocuments->count() }}</div>
            <div class="text-xs text-slate-500 mt-1">{{ __('Drawings, BOQs & Contracts') }}</div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
        @foreach(['overview' => __('Overview'), 'sites' => __('Sites'), 'milestones' => __('Milestones'), 'documents' => __('Documents'), 'activity' => __('Activity')] as $tabKey => $tabLabel)
            <a href="{{ route('projects.show', [$project, 'tab' => $tabKey]) }}"
               class="px-4 py-2 rounded-xl text-xs font-bold transition {{ $activeTab === $tabKey ? 'bg-amber-500 text-slate-950 shadow-sm' : 'bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-slate-800' }}">
                {{ $tabLabel }}
            </a>
        @endforeach
    </div>

    <!-- Tab 1: Overview -->
    <div class="{{ $activeTab === 'overview' ? 'block' : 'hidden' }} space-y-6">
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Project Overview & Timeline') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                <div>
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('Start Date') }}</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $project->start_date ? $project->start_date->format('Y-m-d') : __('Not set') }}</span>
                </div>
                <div>
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('Target Completion Date') }}</span>
                    <span class="font-mono font-bold text-slate-900 dark:text-white">{{ $project->end_date ? $project->end_date->format('Y-m-d') : __('Not set') }}</span>
                </div>
                <div>
                    <span class="text-slate-500 dark:text-slate-400 block mb-0.5">{{ __('Project Manager') }}</span>
                    <span class="font-bold text-slate-900 dark:text-white">{{ $project->manager->name ?? __('Unassigned') }}</span>
                </div>
            </div>
            @if($project->description)
                <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-xs">
                    <span class="text-slate-500 dark:text-slate-400 block mb-1">{{ __('Scope Description') }}</span>
                    <p class="text-slate-700 dark:text-slate-300 leading-relaxed">{{ $project->description }}</p>
                </div>
            @endif
        </div>
    </div>

    <!-- Tab 2: Linked Sites -->
    <div class="{{ $activeTab === 'sites' || $activeTab === 'overview' ? 'block' : 'hidden' }} space-y-4">
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Linked Sites') }} ({{ $allSites->count() }})</h2>
                @can('create', \App\Models\Site::class)
                    <a href="{{ route('sites.create') }}" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">+ {{ __('Register Site') }}</a>
                @endcan
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse($allSites as $s)
                    <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-bold text-amber-600 dark:text-amber-400">{{ $s->code }}</span>
                                <span class="text-sm font-bold text-slate-900 dark:text-white">{{ $s->name }}</span>
                            </div>
                            <div class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                                {{ $s->location ?: ($s->city ?: __('No landmark')) }} &bull; {{ __('Geofence') }}: {{ $s->geofence_radius_m ?? 200 }}m
                            </div>
                        </div>
                        @can('view', $s)
                            <a href="{{ route('sites.show', $s) }}" class="px-3 py-1.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-xs font-bold text-slate-800 dark:text-slate-200 hover:border-amber-500 transition">
                                {{ __('Inspect') }} &rarr;
                            </a>
                        @endcan
                    </div>
                @empty
                    <div class="col-span-full text-xs text-slate-500">{{ __('No sites linked to this project yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Tab 3: Milestones -->
    <div class="{{ $activeTab === 'milestones' || $activeTab === 'overview' ? 'block' : 'hidden' }} space-y-4">
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-5 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Project Milestones') }} ({{ $project->milestones->count() }})</h2>

            @can('manageMilestones', $project)
                <form method="POST" action="{{ route('projects.milestones.store', $project) }}" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
                    @csrf
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase">{{ __('Add Project Milestone') }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                        <div class="lg:col-span-2">
                            <input type="text" name="title" required maxlength="180" placeholder="{{ __('Milestone Title (e.g. Pier P1-P12 Foundation Completed)') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                        </div>
                        <div>
                            <select name="site_id" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                                <option value="">{{ __('All Project Sites') }}</option>
                                @foreach($allSites as $s)
                                    <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <input type="date" name="due_date" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs font-mono text-slate-900 dark:text-white">
                        </div>
                        <div class="flex items-center gap-2">
                            <input type="number" name="percent_complete" min="0" max="100" value="0" class="w-20 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs font-mono text-slate-900 dark:text-white">
                            <input type="hidden" name="status" value="pending">
                            <button type="submit" class="flex-1 px-3 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs transition">
                                {{ __('Add') }}
                            </button>
                        </div>
                    </div>
                </form>
            @endcan

            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($project->milestones as $ms)
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $ms->title }}</span>
                                @if($ms->site)
                                    <span class="font-mono text-[11px] px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-amber-600 dark:text-amber-400">{{ $ms->site->code }}</span>
                                @endif
                                @if($ms->isOverdue())
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30">{{ __('Overdue') }}</span>
                                @endif
                            </div>
                            <div class="text-slate-500 dark:text-slate-400">
                                {{ __('Due') }}: <span class="font-mono">{{ $ms->due_date ? $ms->due_date->format('Y-m-d') : '—' }}</span>
                                &bull; {{ __('Progress') }}: <strong class="font-mono text-amber-600 dark:text-amber-400">{{ $ms->percent_complete }}%</strong>
                            </div>
                        </div>

                        @can('manageMilestones', $project)
                            <form method="POST" action="{{ route('projects.milestones.update', [$project, $ms]) }}" class="flex items-center gap-2">
                                @csrf
                                @method('PUT')
                                <select name="status" class="rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-2 py-1 text-xs">
                                    @foreach(\App\Models\ProjectMilestone::STATUSES as $stKey => $stLabel)
                                        <option value="{{ $stKey }}" {{ $ms->status === $stKey ? 'selected' : '' }}>{{ __($stLabel) }}</option>
                                    @endforeach
                                </select>
                                <input type="number" name="percent_complete" min="0" max="100" value="{{ $ms->percent_complete }}" class="w-16 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-2 py-1 text-xs font-mono">
                                <button type="submit" class="px-3 py-1 rounded-lg bg-slate-900 dark:bg-amber-500 text-white dark:text-slate-950 font-bold text-xs">{{ __('Update') }}</button>
                            </form>
                        @endcan
                    </div>
                @empty
                    <div class="py-4 text-slate-500">{{ __('No milestones created for this project yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Tab 4: Versioned Project Documents -->
    <div class="{{ $activeTab === 'documents' || $activeTab === 'overview' ? 'block' : 'hidden' }} space-y-4">
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-5 shadow-sm">
            <div>
                <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Versioned Project Documents') }} ({{ $visibleDocuments->count() }})</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Uploading a document with an existing title and type automatically increments its version (v1, v2, v3...).') }}</p>
            </div>

            @can('uploadDocument', $project)
                <form method="POST" action="{{ route('projects.documents.store', $project) }}" enctype="multipart/form-data" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
                    @csrf
                    <div class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase">{{ __('Upload Project Drawing / BOQ / Document') }}</div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Title') }} *</label>
                            <input type="text" name="title" required maxlength="180" placeholder="e.g.GAD Flyover Drawing" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Document Type') }} *</label>
                            <select name="document_type" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                                @foreach(\App\Models\ProjectDocument::DOCUMENT_TYPES as $dtKey => $dtLabel)
                                    <option value="{{ $dtKey }}">{{ __($dtLabel) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Site (Optional)') }}</label>
                            <select name="site_id" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                                <option value="">{{ __('Project-Wide') }}</option>
                                @foreach($allSites as $s)
                                    <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('File (PDF, JPG, PNG, XLSX, DOCX)') }} *</label>
                            <input type="file" name="document" required class="w-full text-xs text-slate-700 dark:text-slate-300">
                        </div>
                    </div>
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs transition">
                            {{ __('Upload Versioned Document') }}
                        </button>
                    </div>
                </form>
            @endcan

            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($visibleDocuments as $doc)
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded font-mono font-extrabold text-[11px] bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">v{{ $doc->version }}</span>
                                <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $doc->title }}</span>
                                <span class="text-slate-500">({{ \App\Models\ProjectDocument::DOCUMENT_TYPES[$doc->document_type] ?? $doc->document_type }})</span>
                            </div>
                            <div class="text-slate-500 dark:text-slate-400 mt-0.5">
                                {{ $doc->original_name }} &bull; {{ number_format($doc->file_size / 1024, 1) }} KB
                                @if($doc->uploader)
                                    &bull; {{ __('Uploaded by') }} {{ $doc->uploader->name }}
                                @endif
                            </div>
                        </div>

                        <div class="flex items-center gap-2">
                            <a href="{{ route('projects.documents.download', [$project, $doc]) }}" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-amber-500 hover:text-slate-950 text-slate-700 dark:bg-slate-800 dark:text-slate-300 font-bold transition">
                                {{ __('Download') }}
                            </a>
                            @can('update', $project)
                                <form method="POST" action="{{ route('projects.documents.destroy', [$project, $doc]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white font-bold transition">
                                        {{ __('Delete') }}
                                    </button>
                                </form>
                            @endcan
                        </div>
                    </div>
                @empty
                    <div class="py-4 text-slate-500">{{ __('No documents uploaded for this project yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Tab 5: Activity -->
    <div class="{{ $activeTab === 'activity' ? 'block' : 'hidden' }} space-y-4">
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Recent Project Activity') }}</h2>
            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($recentLogs as $log)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-mono font-bold text-amber-600 dark:text-amber-400">{{ $log->action }}</span>
                            <span class="text-slate-700 dark:text-slate-300 ml-2">{{ $log->user->name ?? __('System') }}</span>
                        </div>
                        <span class="font-mono text-slate-500">{{ $log->created_at?->format('Y-m-d H:i') }}</span>
                    </div>
                @empty
                    <div class="py-4 text-slate-500">{{ __('No recent activity logs recorded for this project.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('content')
@php
    $reopenTaskDrawer = $errors->any() && old('form_context') === 'create_task';
    $reopenNoteDrawer = $errors->any() && old('form_context') === 'create_note';
@endphp
<div class="space-y-6" x-data="{ showNewTask: {{ $reopenTaskDrawer ? 'true' : 'false' }}, showNewNote: {{ $reopenNoteDrawer ? 'true' : 'false' }} }">
    @if ($errors->any())
        <div class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/70 border border-rose-300 dark:border-rose-500/40 text-rose-800 dark:text-rose-200 text-xs space-y-1">
            <div class="font-extrabold uppercase tracking-wider">{{ __('Please correct the following errors:') }}</div>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- KPI Summary Strip -->
    <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-7 gap-3">
        <a href="{{ route('tasks.index', array_merge(request()->except(['status', 'overdue', 'my_tasks']), ['view' => $viewMode === 'notes' ? 'board' : $viewMode])) }}"
           class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 hover:border-amber-500/50 transition shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wider text-slate-500">{{ __('Total Active') }}</div>
            <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $kpis['total_active'] ?? 0 }}</div>
        </a>
        <a href="{{ route('tasks.index', array_merge(request()->except(['overdue', 'my_tasks']), ['status' => 'in_progress', 'view' => $viewMode === 'notes' ? 'board' : $viewMode])) }}"
           class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border {{ request('status') === 'in_progress' ? 'border-sky-500 ring-1 ring-sky-500/30' : 'border-slate-200 dark:border-slate-800' }} hover:border-sky-500/50 transition shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">{{ __('In Progress') }}</div>
            <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $kpis['in_progress'] ?? 0 }}</div>
        </a>
        <a href="{{ route('tasks.index', array_merge(request()->except(['overdue', 'my_tasks']), ['status' => 'review', 'view' => $viewMode === 'notes' ? 'board' : $viewMode])) }}"
           class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border {{ request('status') === 'review' ? 'border-amber-500 ring-1 ring-amber-500/30' : 'border-slate-200 dark:border-slate-800' }} hover:border-amber-500/50 transition shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">{{ __('In Review') }}</div>
            <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $kpis['in_review'] ?? 0 }}</div>
        </a>
        <a href="{{ route('tasks.index', array_merge(request()->except(['overdue', 'my_tasks']), ['status' => 'blocked', 'view' => $viewMode === 'notes' ? 'board' : $viewMode])) }}"
           class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border {{ request('status') === 'blocked' ? 'border-rose-500 ring-1 ring-rose-500/30' : 'border-slate-200 dark:border-slate-800' }} hover:border-rose-500/50 transition shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">{{ __('Blocked') }}</div>
            <div class="text-xl font-extrabold text-rose-600 dark:text-rose-400 mt-1">{{ $kpis['blocked'] ?? 0 }}</div>
        </a>
        <a href="{{ route('tasks.index', array_merge(request()->except(['status', 'my_tasks']), ['overdue' => 1, 'view' => $viewMode === 'notes' ? 'board' : $viewMode])) }}"
           class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border {{ request()->boolean('overdue') ? 'border-rose-500 ring-1 ring-rose-500/30' : 'border-slate-200 dark:border-slate-800' }} hover:border-rose-500/50 transition shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">{{ __('Overdue') }}</div>
            <div class="text-xl font-extrabold text-rose-600 dark:text-rose-400 mt-1">{{ $kpis['overdue'] ?? 0 }}</div>
        </a>
        <a href="{{ route('tasks.index', array_merge(request()->except(['status', 'overdue']), ['my_tasks' => 1, 'view' => $viewMode === 'notes' ? 'board' : $viewMode])) }}"
           class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border {{ request()->boolean('my_tasks') ? 'border-emerald-500 ring-1 ring-emerald-500/30' : 'border-slate-200 dark:border-slate-800' }} hover:border-emerald-500/50 transition shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __('My Tasks') }}</div>
            <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $kpis['my_tasks'] ?? 0 }}</div>
        </a>
        <a href="{{ route('tasks.index', array_merge(request()->query(), ['view' => 'notes'])) }}"
           class="p-3.5 rounded-2xl bg-white dark:bg-slate-900 border {{ $viewMode === 'notes' ? 'border-sky-500 ring-1 ring-sky-500/30' : 'border-slate-200 dark:border-slate-800' }} hover:border-sky-500/50 transition shadow-sm">
            <div class="text-[10px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">{{ __('Pinned Site Notes') }}</div>
            <div class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $kpis['pinned_notes'] ?? 0 }}</div>
        </a>
    </div>

    <!-- Header & View Mode Tabs -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    {{ __('Site Tasks, Field Notes & Activity Log') }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                    {{ __('Every task and note carries a site and project scope. Track progress via Kanban Board, Table List, or Field Notes & Handover.') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('tasks.index', array_merge(request()->query(), ['view' => 'board'])) }}"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold border transition {{ $viewMode === 'board' ? 'bg-amber-500 text-slate-950 border-amber-500 shadow' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700' }}">
                    {{ __('Kanban Board') }}
                </a>
                <a href="{{ route('tasks.index', array_merge(request()->query(), ['view' => 'list'])) }}"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold border transition {{ $viewMode === 'list' ? 'bg-amber-500 text-slate-950 border-amber-500 shadow' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700' }}">
                    {{ __('Table List') }}
                </a>
                <a href="{{ route('tasks.index', array_merge(request()->query(), ['view' => 'notes'])) }}"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold border transition {{ $viewMode === 'notes' ? 'bg-amber-500 text-slate-950 border-amber-500 shadow' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 border-slate-200 dark:border-slate-700' }}">
                    {{ __('Field Notes & Handover') }} ({{ $siteNotes->count() }})
                </a>
                @can('create', \App\Models\Task::class)
                    <button type="button" @click="showNewNote = !showNewNote; if (showNewNote) showNewTask = false;" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-sky-500/10 hover:bg-sky-500/20 text-sky-600 dark:text-sky-400 border border-sky-500/30 transition">
                        + {{ __('Log Site Note') }}
                    </button>
                    <button type="button" @click="showNewTask = !showNewTask; if (showNewTask) showNewNote = false;" class="px-4 py-2 rounded-xl text-xs font-extrabold bg-amber-500 hover:bg-amber-400 text-slate-950 shadow transition">
                        + {{ __('New Site Task') }}
                    </button>
                @endcan
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" action="{{ route('tasks.index') }}" class="grid grid-cols-1 sm:grid-cols-5 gap-3 pt-3 border-t border-slate-200 dark:border-slate-800">
            <input type="hidden" name="view" value="{{ $viewMode }}">
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Site') }}</label>
                <select name="site_id" onchange="this.form.submit()" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    <option value="">{{ __('All Accessible Sites') }}</option>
                    @foreach($accessibleSites as $s)
                        <option value="{{ $s->id }}" {{ (int) $selectedSiteId === (int) $s->id ? 'selected' : '' }}>
                            {{ $s->code }} — {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Project') }}</label>
                <select name="project_id" onchange="this.form.submit()" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    <option value="">{{ __('All Projects') }}</option>
                    @foreach($projects as $p)
                        <option value="{{ $p->id }}" {{ (int) request('project_id') === (int) $p->id ? 'selected' : '' }}>
                            {{ $p->code }} — {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Priority') }}</label>
                <select name="priority" onchange="this.form.submit()" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    <option value="">{{ __('All Priorities') }}</option>
                    @foreach(\App\Models\Task::PRIORITIES as $pKey => $pLabel)
                        <option value="{{ $pKey }}" {{ request('priority') === $pKey ? 'selected' : '' }}>{{ __($pLabel) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Status') }}</label>
                <select name="status" onchange="this.form.submit()" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white">
                    <option value="">{{ __('All Statuses') }}</option>
                    @foreach(\App\Models\Task::STATUSES as $stKey => $stLabel)
                        <option value="{{ $stKey }}" {{ request('status') === $stKey ? 'selected' : '' }}>{{ __($stLabel) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="flex-1 py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-amber-600 dark:text-amber-400 text-xs font-bold border border-slate-200 dark:border-slate-700">
                    {{ __('Filter') }}
                </button>
                @if(request()->hasAny(['site_id', 'project_id', 'priority', 'status', 'overdue', 'my_tasks', 'note_category']))
                    <a href="{{ route('tasks.index', ['view' => $viewMode]) }}" class="py-2 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-xs font-bold border border-slate-200 dark:border-slate-700">
                        {{ __('Reset') }}
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Collapsible Create Task Form -->
    @can('create', \App\Models\Task::class)
    <div x-show="showNewTask" style="{{ $reopenTaskDrawer ? '' : 'display: none;' }}" class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-amber-500/40 shadow-lg space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Create New Site Task') }}</h2>
            <button type="button" @click="showNewTask = false" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white">{{ __('Close') }}</button>
        </div>
        <form method="POST" action="{{ route('tasks.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @csrf
            <input type="hidden" name="form_context" value="create_task">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Site') }} *</label>
                <select name="site_id" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach($accessibleSites as $s)
                        <option value="{{ $s->id }}" {{ (int) old('site_id', $selectedSiteId) === (int) $s->id ? 'selected' : '' }}>
                            {{ $s->code }} — {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Task Title') }} *</label>
                <input type="text" name="title" value="{{ old('title') }}" required placeholder="{{ __('e.g. Inspect pier reinforcement cage at Chainage 14+200') }}"
                    class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Priority') }} *</label>
                <select name="priority" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach(\App\Models\Task::PRIORITIES as $pKey => $pLabel)
                        <option value="{{ $pKey }}" {{ old('priority', 'medium') === $pKey ? 'selected' : '' }}>{{ __($pLabel) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Assign Staff (Optional)') }}</label>
                <select name="assigned_to" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach($companyUsers as $u)
                        <option value="{{ $u->id }}" {{ (int) old('assigned_to') === (int) $u->id ? 'selected' : '' }}>{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Assign Field Worker (Optional)') }}</label>
                <select name="assigned_worker_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    <option value="">{{ __('No Field Worker Assigned') }}</option>
                    @foreach($workers as $w)
                        <option value="{{ $w->id }}" {{ (int) old('assigned_worker_id') === (int) $w->id ? 'selected' : '' }}>
                            {{ $w->code }} — {{ $w->full_name }} ({{ $w->skill_category ?? $w->category }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Due Date') }}</label>
                <input type="date" name="due_date" value="{{ old('due_date', now()->addDays(3)->format('Y-m-d')) }}"
                    class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Description / Technical Scope') }}</label>
                <textarea name="description" rows="2" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">{{ old('description') }}</textarea>
            </div>
            <div class="sm:col-span-3 flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs transition">
                    {{ __('Create Task') }}
                </button>
            </div>
        </form>
    </div>

    <!-- Collapsible Log Site Note Form -->
    <div x-show="showNewNote" style="{{ $reopenNoteDrawer ? '' : 'display: none;' }}" class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-sky-500/40 shadow-lg space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Log Site Field Note') }}</h2>
            <button type="button" @click="showNewNote = false" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white">{{ __('Close') }}</button>
        </div>
        <form method="POST" action="{{ route('tasks.notes.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @csrf
            <input type="hidden" name="form_context" value="create_note">
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Site') }} *</label>
                <select name="site_id" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach($accessibleSites as $s)
                        <option value="{{ $s->id }}" {{ (int) old('site_id', $selectedSiteId) === (int) $s->id ? 'selected' : '' }}>
                            {{ $s->code }} — {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Note Category') }} *</label>
                <select name="category" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach(\App\Models\SiteNote::CATEGORIES as $cKey => $cLabel)
                        <option value="{{ $cKey }}" {{ old('category') === $cKey ? 'selected' : '' }}>{{ __($cLabel) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Note Title') }} *</label>
                <input type="text" name="title" value="{{ old('title') }}" required placeholder="{{ __('e.g. HSE Toolbox Talk Completed') }}"
                    class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
            </div>
            <div class="sm:col-span-3">
                <textarea name="body" rows="2" required placeholder="{{ __('Enter detailed site observation, client instruction, or shift handover note...') }}"
                    class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">{{ old('body') }}</textarea>
            </div>
            <div class="sm:col-span-3 flex items-center justify-between">
                <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_pinned" value="1" {{ old('is_pinned') ? 'checked' : '' }} class="rounded text-amber-500">
                    <span>{{ __('Pin to top of site notes') }}</span>
                </label>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-extrabold text-xs transition">
                    {{ __('Save Site Note') }}
                </button>
            </div>
        </form>
    </div>
    @endcan

    <!-- Kanban Board View (All 5 Columns including Blocked) -->
    @if($viewMode === 'board')
        @php
            $kanbanColumns = [
                'todo' => ['title' => 'To Do', 'badge' => 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'],
                'in_progress' => ['title' => 'In Progress', 'badge' => 'bg-sky-500/15 text-sky-600 dark:text-sky-400'],
                'review' => ['title' => 'In Review', 'badge' => 'bg-amber-500/15 text-amber-600 dark:text-amber-400'],
                'blocked' => ['title' => 'Blocked', 'badge' => 'bg-rose-500/15 text-rose-600 dark:text-rose-400'],
                'completed' => ['title' => 'Completed', 'badge' => 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400'],
            ];
        @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-5 gap-4">
            @foreach($kanbanColumns as $colKey => $colMeta)
                @php
                    $colTasks = $tasks->where('status', $colKey);
                @endphp
                <div class="rounded-2xl bg-white dark:bg-slate-900 border {{ $colKey === 'blocked' && $colTasks->count() > 0 ? 'border-rose-500/40' : 'border-slate-200 dark:border-slate-800' }} p-4 space-y-3 shadow-sm">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __($colMeta['title']) }}</h3>
                        <span class="px-2 py-0.5 rounded-full {{ $colMeta['badge'] }} text-[11px] font-bold">
                            {{ $colTasks->count() }}
                        </span>
                    </div>

                    <div class="space-y-2.5">
                        @forelse($colTasks as $task)
                            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border {{ $task->isOverdue() ? 'border-rose-500/50' : 'border-slate-200 dark:border-slate-800' }} space-y-2 hover:border-amber-500/40 transition">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="font-mono text-[11px] font-bold text-amber-600 dark:text-amber-400">{{ $task->code }}</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $task->priority === 'critical' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : ($task->priority === 'high' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400') }}">
                                        {{ $task->priorityLabel() }}
                                    </span>
                                </div>
                                <a href="{{ route('tasks.show', $task) }}" class="block font-bold text-sm text-slate-900 dark:text-white hover:text-amber-500 transition">
                                    {{ $task->title }}
                                </a>
                                <div class="text-[11px] text-slate-500 flex flex-wrap items-center justify-between gap-1">
                                    <span>{{ $task->site?->code }}</span>
                                    @if($task->due_date)
                                        <span class="{{ $task->isOverdue() ? 'text-rose-600 dark:text-rose-400 font-bold' : '' }}">
                                            {{ $task->isOverdue() ? __('OVERDUE') . ': ' : __('Due') . ': ' }}{{ $task->due_date->format('d M') }}
                                        </span>
                                    @endif
                                </div>
                                @if($task->assignee || $task->assignedWorker)
                                    <div class="text-[11px] text-slate-600 dark:text-slate-400 pt-1.5 border-t border-slate-200/60 dark:border-slate-800/60 space-y-0.5">
                                        @if($task->assignee)
                                            <div>{{ __('Staff') }}: <strong>{{ $task->assignee->name }}</strong></div>
                                        @endif
                                        @if($task->assignedWorker)
                                            <div>{{ __('Worker') }}: <strong>{{ $task->assignedWorker->code }} — {{ $task->assignedWorker->full_name }}</strong></div>
                                        @endif
                                    </div>
                                @endif

                                <!-- Authorized One-Click Workflow Transitions -->
                                @can('update', $task)
                                    @if(!empty($task->allowedNextStatuses()))
                                        <div class="pt-2 border-t border-slate-200/60 dark:border-slate-800/60 flex flex-wrap gap-1.5">
                                            @if($task->status === 'todo')
                                                <form method="POST" action="{{ route('tasks.update_status', $task) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="in_progress">
                                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-sky-500/15 hover:bg-sky-500/25 text-sky-600 dark:text-sky-400 text-[10px] font-extrabold uppercase transition">
                                                        &rarr; {{ __('Start Work') }}
                                                    </button>
                                                </form>
                                            @elseif($task->status === 'in_progress')
                                                <form method="POST" action="{{ route('tasks.update_status', $task) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="review">
                                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-amber-500/15 hover:bg-amber-500/25 text-amber-600 dark:text-amber-400 text-[10px] font-extrabold uppercase transition">
                                                        &rarr; {{ __('Send to Review') }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('tasks.update_status', $task) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="blocked">
                                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-rose-500/15 hover:bg-rose-500/25 text-rose-600 dark:text-rose-400 text-[10px] font-extrabold uppercase transition">
                                                        {{ __('Block') }}
                                                    </button>
                                                </form>
                                            @elseif($task->status === 'blocked')
                                                <form method="POST" action="{{ route('tasks.update_status', $task) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="in_progress">
                                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-sky-500/15 hover:bg-sky-500/25 text-sky-600 dark:text-sky-400 text-[10px] font-extrabold uppercase transition">
                                                        &rarr; {{ __('Resume Work') }}
                                                    </button>
                                                </form>
                                            @elseif($task->status === 'review')
                                                <form method="POST" action="{{ route('tasks.update_status', $task) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="completed">
                                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-600 dark:text-emerald-400 text-[10px] font-extrabold uppercase transition">
                                                        &#10003; {{ __('Complete') }}
                                                    </button>
                                                </form>
                                                <form method="POST" action="{{ route('tasks.update_status', $task) }}">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="status" value="in_progress">
                                                    <button type="submit" class="px-2.5 py-1 rounded-lg bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 text-slate-700 dark:text-slate-300 text-[10px] font-extrabold uppercase transition">
                                                        &larr; {{ __('Rework') }}
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endif
                                @endcan
                            </div>
                        @empty
                            <div class="py-6 text-center text-xs text-slate-400">{{ __('No tasks') }}</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @elseif($viewMode === 'list')
        <!-- Table List View -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                    <thead class="text-xs uppercase text-slate-500 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3 px-4">{{ __('Code') }}</th>
                            <th class="py-3 px-4">{{ __('Task Title') }}</th>
                            <th class="py-3 px-4">{{ __('Site & Project') }}</th>
                            <th class="py-3 px-4">{{ __('Staff / Field Worker') }}</th>
                            <th class="py-3 px-4">{{ __('Priority') }}</th>
                            <th class="py-3 px-4">{{ __('Status') }}</th>
                            <th class="py-3 px-4">{{ __('Due Date') }}</th>
                            <th class="py-3 px-4 text-right">{{ __('Quick Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @forelse($tasks as $task)
                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                                <td class="py-3 px-4 font-mono text-xs text-amber-600 dark:text-amber-400 font-bold">
                                    <a href="{{ route('tasks.show', $task) }}">{{ $task->code }}</a>
                                </td>
                                <td class="py-3 px-4 font-bold text-slate-900 dark:text-white">
                                    <a href="{{ route('tasks.show', $task) }}" class="hover:underline">{{ $task->title }}</a>
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    <div>{{ $task->site?->code }} — {{ $task->site?->name }}</div>
                                    <div class="text-slate-500">{{ $task->project?->code }}</div>
                                </td>
                                <td class="py-3 px-4 text-xs">
                                    <div>{{ $task->assignee?->name ?? __('Unassigned Staff') }}</div>
                                    @if($task->assignedWorker)
                                        <div class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold">
                                            {{ $task->assignedWorker->code }} — {{ $task->assignedWorker->full_name }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-xs font-bold uppercase">{{ $task->priorityLabel() }}</td>
                                <td class="py-3 px-4 text-xs font-bold">{{ $task->statusLabel() }}</td>
                                <td class="py-3 px-4 text-xs font-mono {{ $task->isOverdue() ? 'text-rose-600 dark:text-rose-400 font-bold' : '' }}">
                                    {{ $task->due_date?->format('d M Y') ?? '—' }}
                                    @if($task->isOverdue())
                                        <span class="block text-[10px] uppercase">{{ __('Overdue') }}</span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 text-right text-xs">
                                    <a href="{{ route('tasks.show', $task) }}" class="px-2.5 py-1 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold hover:bg-amber-500 hover:text-slate-950 transition">
                                        {{ __('Open') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="py-8 text-center text-xs text-slate-500">{{ __('No site tasks match your filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Site Notes & Append-Only Activity Log -->
    <div class="grid grid-cols-1 {{ $viewMode === 'notes' ? 'lg:grid-cols-1' : 'lg:grid-cols-2' }} gap-6">
        <!-- Site Field Notes -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Project & Site Field Notes') }}</h2>
                <!-- Category Filter Pills -->
                <div class="flex flex-wrap items-center gap-1.5">
                    <a href="{{ route('tasks.index', array_merge(request()->except('note_category'), ['view' => $viewMode])) }}"
                       class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase {{ empty($noteCategory) ? 'bg-sky-500 text-slate-950' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">
                        {{ __('All') }}
                    </a>
                    @foreach(\App\Models\SiteNote::CATEGORIES as $cKey => $cLabel)
                        <a href="{{ route('tasks.index', array_merge(request()->query(), ['note_category' => $cKey, 'view' => $viewMode])) }}"
                           class="px-2.5 py-1 rounded-lg text-[10px] font-bold uppercase {{ ($noteCategory ?? '') === $cKey ? 'bg-sky-500 text-slate-950' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300' }}">
                            {{ __($cLabel) }}
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($siteNotes as $note)
                    @php
                        $convertedInfo = $note->convertedTaskInfo();
                        $canManageNote = auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'supervisor'])
                            && ($note->site_id && auth()->user()->isAssignedToSite((int) $note->site_id));
                        $canDeleteNote = $canManageNote && (auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']) || (int) $note->user_id === (int) auth()->id());
                    @endphp
                    <div class="py-3.5 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="font-bold text-slate-900 dark:text-white flex flex-wrap items-center gap-2">
                                @if($note->is_pinned)
                                    <span class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-600 dark:text-amber-400 text-[10px] font-bold uppercase">{{ __('Pinned') }}</span>
                                @endif
                                <span>{{ $note->title }}</span>
                                @if($convertedInfo)
                                    <a href="{{ route('tasks.show', $convertedInfo['task_id']) }}"
                                       class="px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 text-[10px] font-mono font-bold hover:underline">
                                        {{ __('Converted') }} &rarr; {{ $convertedInfo['task_code'] }}
                                    </a>
                                @endif
                            </div>
                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px]">
                                {{ $note->categoryLabel() }}
                            </span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300 whitespace-pre-line">{{ $note->body }}</p>
                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <div class="text-[11px] text-slate-400">
                                {{ $note->site?->code }} &bull; {{ __('By') }} {{ $note->author?->name }} &bull; {{ $note->created_at->format('d M Y H:i') }}
                            </div>
                            @if($canManageNote)
                                <div class="flex items-center gap-2">
                                    <form method="POST" action="{{ route('tasks.notes.toggle_pin', $note) }}">
                                        @csrf
                                        <button type="submit" class="px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 hover:bg-amber-500/20 text-slate-600 dark:text-slate-300 text-[10px] font-bold">
                                            {{ $note->is_pinned ? __('Unpin') : __('Pin') }}
                                        </button>
                                    </form>
                                    @if(!$convertedInfo)
                                        <form method="POST" action="{{ route('tasks.notes.convert_to_task', $note) }}">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1 rounded bg-amber-500/15 hover:bg-amber-500/25 text-amber-600 dark:text-amber-400 text-[10px] font-extrabold uppercase">
                                                + {{ __('Convert to Task') }}
                                            </button>
                                        </form>
                                    @endif
                                    @if($canDeleteNote)
                                        <form method="POST" action="{{ route('tasks.notes.destroy', $note) }}" onsubmit="return confirm('Delete this field note?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2 py-1 rounded bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 text-[10px] font-bold">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-slate-500">{{ __('No field notes logged yet.') }}</div>
                @endforelse
            </div>
        </div>

        @if($viewMode !== 'notes')
        <!-- Append-Only Activity Log -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Append-Only Site Activity Log') }}</h2>
            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($recentActivity as $act)
                    <div class="py-2.5 flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold text-slate-900 dark:text-white">{{ $act->description ?? $act->action }}</div>
                            <div class="text-[11px] text-slate-500">
                                <span class="font-mono text-amber-600 dark:text-amber-400">{{ $act->action }}</span>
                                @if($act->user)
                                    &bull; {{ $act->user->name }}
                                @endif
                            </div>
                        </div>
                        <div class="font-mono text-[11px] text-slate-400 whitespace-nowrap">
                            {{ \Carbon\Carbon::parse($act->created_at)->format('d M H:i') }}
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-slate-500">{{ __('No activity logs recorded yet.') }}</div>
                @endforelse
            </div>
        </div>
        @endif
    </div>
</div>
@endsection

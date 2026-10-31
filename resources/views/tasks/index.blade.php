@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ showNewTask: false, showNewNote: false }">
    <!-- Header & View Mode Toggle -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                    {{ __('Site Tasks, Field Notes & Activity Log') }}
                </h1>
                <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                    {{ __('Every task and note carries a site and project scope. Track progress via Kanban Board or Table List.') }}
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
                @can('create', \App\Models\Task::class)
                    <button type="button" @click="showNewNote = !showNewNote" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-sky-500/10 hover:bg-sky-500/20 text-sky-600 dark:text-sky-400 border border-sky-500/30 transition">
                        + {{ __('Log Site Note') }}
                    </button>
                    <button type="button" @click="showNewTask = !showNewTask" class="px-4 py-2 rounded-xl text-xs font-extrabold bg-amber-500 hover:bg-amber-400 text-slate-950 shadow transition">
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
                <button type="submit" class="w-full py-2 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-amber-600 dark:text-amber-400 text-xs font-bold border border-slate-200 dark:border-slate-700">
                    {{ __('Filter') }}
                </button>
            </div>
        </form>
    </div>

    <!-- Collapsible Create Task Form -->
    @can('create', \App\Models\Task::class)
    <div x-show="showNewTask" style="display: none;" class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-amber-500/40 shadow-lg space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Create New Site Task') }}</h2>
            <button type="button" @click="showNewTask = false" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white">{{ __('Close') }}</button>
        </div>
        <form method="POST" action="{{ route('tasks.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Site') }} *</label>
                <select name="site_id" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach($accessibleSites as $s)
                        <option value="{{ $s->id }}" {{ (int) $selectedSiteId === (int) $s->id ? 'selected' : '' }}>
                            {{ $s->code }} — {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Task Title') }} *</label>
                <input type="text" name="title" required placeholder="{{ __('e.g. Inspect pier reinforcement cage at Chainage 14+200') }}"
                    class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Priority') }} *</label>
                <select name="priority" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach(\App\Models\Task::PRIORITIES as $pKey => $pLabel)
                        <option value="{{ $pKey }}" {{ $pKey === 'medium' ? 'selected' : '' }}>{{ __($pLabel) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Assign Staff (Optional)') }}</label>
                <select name="assigned_to" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    <option value="">{{ __('Unassigned') }}</option>
                    @foreach($companyUsers as $u)
                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Due Date') }}</label>
                <input type="date" name="due_date" value="{{ now()->addDays(3)->format('Y-m-d') }}"
                    class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
            </div>
            <div class="sm:col-span-3">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Description / Technical Scope') }}</label>
                <textarea name="description" rows="2" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white"></textarea>
            </div>
            <div class="sm:col-span-3 flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs transition">
                    {{ __('Create Task') }}
                </button>
            </div>
        </form>
    </div>

    <!-- Collapsible Log Site Note Form -->
    <div x-show="showNewNote" style="display: none;" class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-sky-500/40 shadow-lg space-y-4">
        <div class="flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Log Site Field Note') }}</h2>
            <button type="button" @click="showNewNote = false" class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white">{{ __('Close') }}</button>
        </div>
        <form method="POST" action="{{ route('tasks.notes.store') }}" class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            @csrf
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Site') }} *</label>
                <select name="site_id" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach($accessibleSites as $s)
                        <option value="{{ $s->id }}" {{ (int) $selectedSiteId === (int) $s->id ? 'selected' : '' }}>
                            {{ $s->code }} — {{ $s->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Note Category') }} *</label>
                <select name="category" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach(\App\Models\SiteNote::CATEGORIES as $cKey => $cLabel)
                        <option value="{{ $cKey }}">{{ __($cLabel) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Note Title') }} *</label>
                <input type="text" name="title" required placeholder="{{ __('e.g. HSE Toolbox Talk Completed') }}"
                    class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
            </div>
            <div class="sm:col-span-3">
                <textarea name="body" rows="2" required placeholder="{{ __('Enter detailed site observation, client instruction, or shift handover note...') }}"
                    class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white"></textarea>
            </div>
            <div class="sm:col-span-3 flex items-center justify-between">
                <label class="inline-flex items-center gap-2 text-xs text-slate-700 dark:text-slate-300">
                    <input type="checkbox" name="is_pinned" value="1" class="rounded text-amber-500">
                    <span>{{ __('Pin to top of site notes') }}</span>
                </label>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-extrabold text-xs transition">
                    {{ __('Save Site Note') }}
                </button>
            </div>
        </form>
    </div>
    @endcan

    <!-- Kanban Board View -->
    @if($viewMode === 'board')
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            @foreach(['todo' => 'To Do', 'in_progress' => 'In Progress', 'review' => 'In Review', 'completed' => 'Completed'] as $colKey => $colTitle)
                @php
                    $colTasks = $tasks->where('status', $colKey);
                @endphp
                <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-4 space-y-3 shadow-sm">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800">
                        <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __($colTitle) }}</h3>
                        <span class="px-2 py-0.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[11px] font-bold">
                            {{ $colTasks->count() }}
                        </span>
                    </div>

                    <div class="space-y-2.5">
                        @forelse($colTasks as $task)
                            <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-2 hover:border-amber-500/40 transition">
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
                                            {{ __('Due') }}: {{ $task->due_date->format('d M') }}
                                        </span>
                                    @endif
                                </div>
                                @if($task->assignee)
                                    <div class="text-[11px] text-slate-600 dark:text-slate-400 pt-1 border-t border-slate-200/60 dark:border-slate-800/60">
                                        {{ __('Assignee') }}: <strong>{{ $task->assignee->name }}</strong>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="py-6 text-center text-xs text-slate-400">{{ __('No tasks') }}</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- Table List View -->
        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                    <thead class="text-xs uppercase text-slate-500 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-3 px-4">{{ __('Code') }}</th>
                            <th class="py-3 px-4">{{ __('Task Title') }}</th>
                            <th class="py-3 px-4">{{ __('Site & Project') }}</th>
                            <th class="py-3 px-4">{{ __('Assignee') }}</th>
                            <th class="py-3 px-4">{{ __('Priority') }}</th>
                            <th class="py-3 px-4">{{ __('Status') }}</th>
                            <th class="py-3 px-4">{{ __('Due Date') }}</th>
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
                                <td class="py-3 px-4 text-xs">{{ $task->assignee?->name ?? __('Unassigned') }}</td>
                                <td class="py-3 px-4 text-xs font-bold uppercase">{{ $task->priorityLabel() }}</td>
                                <td class="py-3 px-4 text-xs font-bold">{{ $task->statusLabel() }}</td>
                                <td class="py-3 px-4 text-xs font-mono {{ $task->isOverdue() ? 'text-rose-600 dark:text-rose-400 font-bold' : '' }}">
                                    {{ $task->due_date?->format('d M Y') ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-8 text-center text-xs text-slate-500">{{ __('No site tasks match your filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Site Notes & Append-Only Activity Log -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Site Field Notes -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Project & Site Field Notes') }}</h2>
            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($siteNotes as $note)
                    <div class="py-3 space-y-1">
                        <div class="flex items-center justify-between">
                            <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                @if($note->is_pinned)
                                    <span class="px-1.5 py-0.5 rounded bg-amber-500/20 text-amber-600 dark:text-amber-400 text-[10px] font-bold uppercase">{{ __('Pinned') }}</span>
                                @endif
                                <span>{{ $note->title }}</span>
                            </div>
                            <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 text-[10px]">
                                {{ $note->categoryLabel() }}
                            </span>
                        </div>
                        <p class="text-slate-600 dark:text-slate-300">{{ $note->body }}</p>
                        <div class="text-[11px] text-slate-400">
                            {{ $note->site?->code }} &bull; {{ __('By') }} {{ $note->author?->name }} &bull; {{ $note->created_at->format('d M Y H:i') }}
                        </div>
                    </div>
                @empty
                    <div class="py-6 text-center text-slate-500">{{ __('No field notes logged yet.') }}</div>
                @endforelse
            </div>
        </div>

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
    </div>
</div>
@endsection


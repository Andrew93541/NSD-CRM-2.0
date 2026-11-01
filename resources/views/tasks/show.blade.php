@extends('layouts.app')

@section('content')
<div class="space-y-6">
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

    <!-- Header -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="font-mono text-xs font-bold text-amber-600 dark:text-amber-400">{{ $task->code }}</span>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase {{ $task->status === 'blocked' ? 'bg-rose-500/15 text-rose-600 dark:text-rose-400' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300' }}">
                    {{ $task->statusLabel() }}
                </span>
                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase {{ $task->priority === 'critical' ? 'bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-amber-500/10 text-amber-600 dark:text-amber-400' }}">
                    {{ $task->priorityLabel() }}
                </span>
                @if($task->isOverdue())
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase bg-rose-500 text-white">
                        {{ __('Overdue') }}
                    </span>
                @endif
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $task->title }}</h1>
            <p class="text-xs text-slate-500 mt-0.5">
                {{ __('Site') }}: <strong>{{ $task->site?->code }} — {{ $task->site?->name }}</strong>
                &bull; {{ __('Project') }}: <strong>{{ $task->project?->code }}</strong>
                @if($task->assignee)
                    &bull; {{ __('Staff Assignee') }}: <strong>{{ $task->assignee->name }}</strong>
                @endif
                @if($task->assignedWorker)
                    &bull; {{ __('Field Worker') }}: <strong>{{ $task->assignedWorker->code }} — {{ $task->assignedWorker->full_name }}</strong>
                @endif
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @can('delete', $task)
                <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Delete this task permanently?')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-bold transition">
                        {{ __('Delete Task') }}
                    </button>
                </form>
            @endcan
            <a href="{{ route('tasks.index', ['site_id' => $task->site_id]) }}" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 transition">
                &larr; {{ __('Back to Site Tasks') }}
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Scope & Comments -->
        <div class="lg:col-span-2 space-y-6">
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-sm">
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Task Description & Scope') }}</h2>
                <p class="text-sm text-slate-700 dark:text-slate-300 whitespace-pre-line">{{ $task->description ?: __('No additional description provided.') }}</p>
            </div>

            <!-- Task Comments Thread -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">
                    {{ __('Field Comments & Updates') }} ({{ $task->comments->count() }})
                </h2>

                @can('update', $task)
                <form method="POST" action="{{ route('tasks.comments.store', $task) }}" class="space-y-3">
                    @csrf
                    <textarea name="comment" rows="2" required placeholder="{{ __('Post a progress update or field observation...') }}"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-3 text-xs text-slate-900 dark:text-white">{{ old('comment') }}</textarea>
                    <div class="flex justify-end">
                        <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                            {{ __('Post Comment') }}
                        </button>
                    </div>
                </form>
                @endcan

                <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                    @forelse($task->comments as $c)
                        <div class="py-3 space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $c->user?->name }}</span>
                                <span class="font-mono text-slate-400">{{ $c->created_at->format('d M Y H:i') }}</span>
                            </div>
                            <p class="text-slate-700 dark:text-slate-300">{{ $c->comment }}</p>
                        </div>
                    @empty
                        <div class="py-4 text-center text-slate-500">{{ __('No comments posted yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right Col: Edit Task & Audit Trail -->
        <div class="space-y-6">
            @can('update', $task)
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Update Task & Assignments') }}</h2>
                <form method="POST" action="{{ route('tasks.update_status', $task) }}" class="space-y-3">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">{{ __('Task Title') }}</label>
                        <input type="text" name="title" value="{{ old('title', $task->title) }}" required
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">{{ __('Description / Technical Scope') }}</label>
                        <textarea name="description" rows="2"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">{{ old('description', $task->description) }}</textarea>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">{{ __('Status') }}</label>
                        @php
                            $validStatuses = array_unique(array_merge([$task->status], $task->allowedNextStatuses()));
                        @endphp
                        <select name="status" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                            @foreach(\App\Models\Task::STATUSES as $stKey => $stLabel)
                                @if(in_array($stKey, $validStatuses, true))
                                    <option value="{{ $stKey }}" {{ old('status', $task->status) === $stKey ? 'selected' : '' }}>{{ __($stLabel) }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">{{ __('Priority') }}</label>
                        <select name="priority" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                            @foreach(\App\Models\Task::PRIORITIES as $pKey => $pLabel)
                                <option value="{{ $pKey }}" {{ old('priority', $task->priority) === $pKey ? 'selected' : '' }}>{{ __($pLabel) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">{{ __('Assigned Staff') }}</label>
                        <select name="assigned_to" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Unassigned') }}</option>
                            @foreach($companyUsers as $u)
                                <option value="{{ $u->id }}" {{ (int) old('assigned_to', $task->assigned_to) === (int) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">{{ __('Assigned Field Worker') }}</label>
                        <select name="assigned_worker_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('No Field Worker Assigned') }}</option>
                            @foreach($workers as $w)
                                <option value="{{ $w->id }}" {{ (int) old('assigned_worker_id', $task->assigned_worker_id) === (int) $w->id ? 'selected' : '' }}>
                                    {{ $w->code }} — {{ $w->full_name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">{{ __('Due Date') }}</label>
                        <input type="date" name="due_date" value="{{ old('due_date', $task->due_date?->format('Y-m-d')) }}"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    </div>
                    <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs transition">
                        {{ __('Save Task Changes') }}
                    </button>
                </form>
            </div>
            @endcan

            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-sm">
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Task Audit Trail') }}</h2>
                <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                    @forelse($activityLogs as $log)
                        <div class="py-2">
                            <div class="font-semibold text-slate-800 dark:text-slate-200">{{ $log->description }}</div>
                            <div class="text-[11px] text-slate-400">{{ $log->user?->name }} &bull; {{ \Carbon\Carbon::parse($log->created_at)->format('d M H:i') }}</div>
                        </div>
                    @empty
                        <div class="py-4 text-center text-slate-500">{{ __('No audit history.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

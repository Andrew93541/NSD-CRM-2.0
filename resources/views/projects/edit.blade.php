@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="pb-4 border-b border-slate-200 dark:border-slate-800">
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Edit Project') }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Update') }} <span class="font-mono font-bold text-amber-600 dark:text-amber-400">{{ $project->code }}</span> — {{ $project->name }}</p>
    </div>

    <div class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl">
        <form method="POST" action="{{ route('projects.update', $project) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Project Code') }} *</label>
                    <input type="text" name="code" required maxlength="30" value="{{ old('code', $project->code) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm font-mono text-slate-900 dark:text-white outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Status') }} *</label>
                    <select name="status" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white outline-none focus:border-amber-500">
                        <option value="planning" {{ old('status', $project->status) === 'planning' ? 'selected' : '' }}>{{ __('Planning') }}</option>
                        <option value="active" {{ old('status', $project->status) === 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                        <option value="on_hold" {{ old('status', $project->status) === 'on_hold' ? 'selected' : '' }}>{{ __('On Hold') }}</option>
                        <option value="completed" {{ old('status', $project->status) === 'completed' ? 'selected' : '' }}>{{ __('Completed') }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Project Name') }} *</label>
                <input type="text" name="name" required maxlength="180" value="{{ old('name', $project->name) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white outline-none focus:border-amber-500">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Client / Authority Name') }}</label>
                    <input type="text" name="client_name" maxlength="160" value="{{ old('client_name', $project->client_name) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Project Manager') }}</label>
                    <select name="manager_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white outline-none focus:border-amber-500">
                        <option value="">{{ __('Unassigned') }}</option>
                        @foreach($managers as $m)
                            <option value="{{ $m->id }}" {{ (string) old('manager_id', $project->manager_id) === (string) $m->id ? 'selected' : '' }}>
                                {{ $m->name }} ({{ $m->email }})
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Start Date') }}</label>
                    <input type="date" name="start_date" value="{{ old('start_date', $project->start_date?->format('Y-m-d')) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm font-mono text-slate-900 dark:text-white outline-none focus:border-amber-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Target Completion Date') }}</label>
                    <input type="date" name="end_date" value="{{ old('end_date', $project->end_date?->format('Y-m-d')) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm font-mono text-slate-900 dark:text-white outline-none focus:border-amber-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-2">{{ __('Linked Construction Sites (Multi-Site Support)') }}</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 max-h-52 overflow-y-auto">
                    @foreach($sites as $site)
                        <label class="flex items-center gap-2.5 text-xs text-slate-800 dark:text-slate-200 cursor-pointer">
                            <input type="checkbox" name="site_ids[]" value="{{ $site->id }}" {{ in_array($site->id, old('site_ids', $selectedSiteIds ?? [])) ? 'checked' : '' }} class="rounded border-slate-300 text-amber-500 focus:ring-amber-500">
                            <span class="font-mono font-bold text-amber-600 dark:text-amber-400">{{ $site->code }}</span>
                            <span class="truncate">{{ $site->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Project Scope & Description') }}</label>
                <textarea name="description" rows="3" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white outline-none focus:border-amber-500">{{ old('description', $project->description) }}</textarea>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <a href="{{ route('projects.show', $project) }}" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300 transition">{{ __('Cancel') }}</a>
                <button type="submit" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition">{{ __('Save Changes') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection


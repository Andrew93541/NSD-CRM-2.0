@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                <a href="{{ route('admin.roles.index') }}" class="hover:underline">{{ __('Roles') }}</a>
                <span>/</span>
                <span>{{ $role->name }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ __('Edit Role & Permissions') }}: {{ $role->name }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ $role->isSystemRole() ? __('Predefined System Role — slug is locked, module permissions can be customized.') : __('Custom Role — name, description, and module permissions can be customized.') }}
            </p>
        </div>

        <a href="{{ route('admin.roles.index') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition self-start">
            ← {{ __('Back to Roles') }}
        </a>
    </div>

    <form method="POST" action="{{ route('admin.roles.update', $role) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Role Name') }} <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $role->name) }}" required
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Role Identifier (Slug)') }}</label>
                    <input type="text" value="{{ $role->slug }}" disabled
                        class="w-full rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 dark:bg-slate-950/60 px-3.5 py-2.5 text-xs font-mono text-slate-500">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Description') }}</label>
                <input type="text" name="description" value="{{ old('description', $role->description) }}"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white">
            </div>
        </div>

        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('Module Permissions') }}</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($modules as $moduleKey => $moduleDef)
                    @if($moduleKey !== 'users')
                        <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 space-y-2.5">
                            <div>
                                <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ $moduleDef['label'] }}</h3>
                                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $moduleDef['description'] }}</p>
                            </div>
                            <div class="space-y-1.5 pt-1">
                                @foreach($moduleDef['permissions'] as $permKey => $permLabel)
                                    <label class="flex items-center gap-2.5 text-xs cursor-pointer">
                                        <input type="checkbox" name="permissions[]" value="{{ $permKey }}"
                                            class="rounded text-amber-500 focus:ring-amber-500"
                                            {{ in_array($permKey, $effectivePermissions, true) ? 'checked' : '' }}>
                                        <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $permLabel }}</span>
                                        <span class="text-[10px] font-mono text-slate-400">({{ $permKey }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.roles.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition">
                {{ __('Save Role Permissions') }}
            </button>
        </div>
    </form>

    @if(!$role->isSystemRole() && Auth::user()->canPerform('roles.delete', ['super_admin', 'company_admin']))
        <div class="p-6 rounded-2xl bg-rose-500/5 border border-rose-500/30 space-y-3">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-rose-600 dark:text-rose-400">{{ __('Delete Custom Role') }}</h2>
            <p class="text-xs text-slate-600 dark:text-slate-400">
                {{ __('Assigned users:') }} <strong>{{ $assignedCount }}</strong>.
                {{ __('If users are currently assigned to this custom role, choose a replacement role before deleting.') }}
            </p>
            <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                @csrf
                @method('DELETE')
                @if($assignedCount > 0)
                    <select name="reassign_role_id" required class="rounded-xl border border-rose-500/40 bg-white dark:bg-slate-900 px-3 py-2 text-xs">
                        <option value="">{{ __('— Select Replacement Role for Assigned Users —') }}</option>
                        @foreach($reassignRoles as $rOption)
                            <option value="{{ $rOption->id }}">{{ $rOption->name }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-xs font-extrabold transition">
                    {{ __('Delete Custom Role') }}
                </button>
            </form>
        </div>
    @endif
</div>
@endsection


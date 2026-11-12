@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                <a href="{{ route('admin.users.index') }}" class="hover:underline">{{ __('Users & Access') }}</a>
                <span>•</span>
                <span>{{ __('Roles & Access Templates') }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ __('Role Management & Custom Roles') }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Configure predefined operational roles or create custom roles (Accountant, HR Manager, Site Engineer, Procurement Officer).') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.users.index') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                ← {{ __('Users List') }}
            </a>
            <a href="{{ route('admin.roles.matrix') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                {{ __('Full Permission Matrix') }}
            </a>
            @if(Auth::user()->canPerform('roles.create', ['super_admin', 'company_admin']))
            <a href="{{ route('admin.roles.create') }}" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition">
                + {{ __('Create Custom Role') }}
            </a>
            @endif
        </div>
    </div>

    <!-- Roles Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($roles as $role)
            @php
                $isSystem = $role->isSystemRole();
                $isProtected = $role->isProtectedAdminRole();
                $effectivePerms = $role->effectivePermissions();
                $userCount = $userCountsByRole[$role->id] ?? 0;
            @endphp
            <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border {{ $isProtected ? 'border-amber-500/40' : 'border-slate-200 dark:border-slate-800' }} shadow-sm flex flex-col justify-between space-y-4">
                <div class="space-y-2.5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="text-base font-extrabold text-slate-900 dark:text-white">{{ $role->name }}</h2>
                            <div class="text-[11px] font-mono text-slate-400">{{ $role->slug }}</div>
                        </div>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase tracking-wider {{ $isSystem ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30' : 'bg-sky-500/15 text-sky-700 dark:text-sky-300 border border-sky-500/30' }}">
                            {{ $isSystem ? __('System') : __('Custom') }}
                        </span>
                    </div>

                    <p class="text-xs text-slate-600 dark:text-slate-400 min-h-[2.25rem]">
                        {{ $role->description ?: __('Operational role with module-level CRM permissions.') }}
                    </p>

                    <div class="flex items-center justify-between pt-2 border-t border-slate-100 dark:border-slate-800 text-xs">
                        <span class="text-slate-500">{{ __('Assigned Company Users') }}</span>
                        <span class="font-extrabold text-slate-900 dark:text-white">{{ $userCount }}</span>
                    </div>

                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-500">{{ __('Granted Permissions') }}</span>
                        <span class="font-bold text-emerald-600 dark:text-emerald-400">
                            {{ in_array('*', $effectivePerms, true) ? __('All Modules (*)') : count($effectivePerms) . ' ' . __('permissions') }}
                        </span>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-2">
                    @if(!$isProtected && Auth::user()->canPerform('roles.update', ['super_admin', 'company_admin']))
                        <a href="{{ route('admin.roles.edit', $role) }}" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-amber-500/20 text-slate-800 hover:text-amber-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:text-amber-300 text-xs font-bold transition">
                            {{ __('Configure Permissions') }}
                        </a>
                    @else
                        <span class="text-[11px] font-bold text-amber-600 dark:text-amber-400">{{ __('Full Administrative Access') }}</span>
                    @endif

                    @if(Auth::user()->canPerform('roles.create', ['super_admin', 'company_admin']))
                        <form method="POST" action="{{ route('admin.roles.duplicate', $role) }}">
                            @csrf
                            <button type="submit" class="px-2.5 py-1.5 rounded-xl border border-slate-200 dark:border-slate-700 text-[11px] font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                                {{ __('Duplicate') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection


@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                <a href="{{ route('admin.users.index') }}" class="hover:underline">{{ __('Users & Access') }}</a>
                <span>•</span>
                <a href="{{ route('admin.roles.index') }}" class="hover:underline">{{ __('Roles') }}</a>
                <span>•</span>
                <span>{{ __('Permission Matrix') }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ __('Role & Module Permission Matrix') }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Configure module-level permissions across all operational roles in one view. Company Admin retains full administrative access.') }}
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('admin.roles.index') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                ← {{ __('Back to Roles') }}
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.roles.matrix.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950">
                            <th class="py-3.5 px-4 font-extrabold text-slate-700 dark:text-slate-200 min-w-[240px]">{{ __('Module / Permission Action') }}</th>
                            @foreach($roles as $role)
                                <th class="py-3.5 px-3 text-center font-extrabold text-slate-800 dark:text-slate-200 min-w-[120px]">
                                    <div>{{ $role->name }}</div>
                                    <div class="text-[10px] font-normal text-slate-400">{{ $role->isSystemRole() ? 'System' : 'Custom' }}</div>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                        @foreach($modules as $moduleKey => $moduleDef)
                            <tr class="bg-slate-100/70 dark:bg-slate-800/50">
                                <td colspan="{{ $roles->count() + 1 }}" class="py-2 px-4 font-extrabold text-[11px] uppercase tracking-wider text-amber-700 dark:text-amber-300">
                                    {{ $moduleDef['label'] }} — <span class="font-normal text-slate-500 dark:text-slate-400">{{ $moduleDef['description'] }}</span>
                                </td>
                            </tr>
                            @foreach($moduleDef['permissions'] as $permKey => $permLabel)
                                <tr class="hover:bg-slate-50/70 dark:hover:bg-slate-800/30">
                                    <td class="py-2.5 px-4">
                                        <div class="font-bold text-slate-800 dark:text-slate-200">{{ $permLabel }}</div>
                                        <div class="font-mono text-[10px] text-slate-400">{{ $permKey }}</div>
                                    </td>
                                    @foreach($roles as $role)
                                        @php
                                            $isProtected = $role->isProtectedAdminRole();
                                            $isAdminReserved = in_array($permKey, \App\Services\Security\PermissionCatalog::ADMIN_RESERVED_PERMISSIONS, true);
                                            $hasPerm = $role->hasPermission($permKey);
                                        @endphp
                                        <td class="py-2.5 px-3 text-center">
                                            @if($isProtected)
                                                <span class="inline-flex items-center justify-center w-5 h-5 rounded bg-amber-500/20 text-amber-600 dark:text-amber-400 font-black" title="Protected Admin Role">✓</span>
                                            @elseif($isAdminReserved)
                                                <span class="text-slate-300 dark:text-slate-700" title="Reserved for Company Admin">—</span>
                                            @else
                                                <input type="checkbox" name="matrix[{{ $role->id }}][]" value="{{ $permKey }}"
                                                    class="rounded text-amber-500 focus:ring-amber-500"
                                                    {{ $hasPerm ? 'checked' : '' }}>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @if(Auth::user()->canPerform('roles.update', ['super_admin', 'company_admin']))
            <div class="flex justify-end">
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition">
                    {{ __('Save Permission Matrix') }}
                </button>
            </div>
        @endif
    </form>
</div>
@endsection


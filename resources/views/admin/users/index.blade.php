@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Top Sub-Navigation & Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                <span>{{ __('Administration') }}</span>
                <span>•</span>
                <span>{{ __('Users & Access Control') }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ __('Employee Login Accounts & Access') }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Create employee logins, assign roles, restrict site access, customize permissions, and manage 2FA security.') }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(Auth::user()->hasRole(['super_admin', 'company_admin']) && !Auth::user()->isExplicitlyDenied('settings.manage'))
            <a href="{{ route('admin.company.edit') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                {{ __('Company Profile') }}
            </a>
            @endif
            <a href="{{ route('admin.roles.index') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                {{ __('Manage Roles') }}
            </a>
            <a href="{{ route('admin.roles.matrix') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 bg-white dark:bg-slate-900 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                {{ __('Permission Matrix') }}
            </a>
            @if(Auth::user()->canPerform('users.create', ['super_admin', 'company_admin']))
            <a href="{{ route('admin.users.create') }}" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition flex items-center gap-1.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
                <span>{{ __('Create New User') }}</span>
            </a>
            @endif
        </div>
    </div>

    @if(session('generated_temp_password'))
        <div class="p-4 rounded-2xl bg-amber-500/10 border border-amber-500/40 text-slate-900 dark:text-amber-100 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                        {{ __('One-Time Temporary Credentials (Copy Now)') }}
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-0.5">
                        {{ __('Account:') }} <span class="font-mono font-bold">{{ session('generated_temp_user_email') }}</span> —
                        {{ __('This temporary password is shown only once and is never stored in plain text.') }}
                    </p>
                </div>
                <div class="px-3.5 py-2 rounded-xl bg-slate-900 text-amber-300 font-mono text-sm font-bold tracking-wider select-all border border-amber-500/30">
                    {{ session('generated_temp_password') }}
                </div>
            </div>
        </div>
    @endif

    <!-- Summary KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Total Company Users') }}</div>
            <div class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $stats['total'] }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">{{ __('Active Accounts') }}</div>
            <div class="text-2xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-1">{{ $stats['active'] }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-wider text-rose-600 dark:text-rose-400">{{ __('Deactivated') }}</div>
            <div class="text-2xl font-extrabold text-rose-600 dark:text-rose-400 mt-1">{{ $stats['inactive'] }}</div>
        </div>
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-[11px] font-bold uppercase tracking-wider text-sky-600 dark:text-sky-400">{{ __('2FA Enabled') }}</div>
            <div class="text-2xl font-extrabold text-sky-600 dark:text-sky-400 mt-1">{{ $stats['two_factor'] }}</div>
        </div>
    </div>

    <!-- Search & Filter Bar -->
    <form method="GET" action="{{ route('admin.users.index') }}" class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div class="lg:col-span-2">
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">{{ __('Search Name, Email or Phone') }}</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="e.g. Rajesh, email@company.com..."
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">{{ __('Role') }}</label>
                <select name="role_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    <option value="">{{ __('All Roles') }}</option>
                    @foreach($roles as $roleOption)
                        <option value="{{ $roleOption->id }}" {{ (int) $roleId === (int) $roleOption->id ? 'selected' : '' }}>
                            {{ $roleOption->name }} {{ $roleOption->isSystemRole() ? '(System)' : '(Custom)' }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">{{ __('Assigned Site') }}</label>
                <select name="site_id" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    <option value="">{{ __('All Sites') }}</option>
                    @foreach($sites as $siteOption)
                        <option value="{{ $siteOption->id }}" {{ (int) $siteId === (int) $siteOption->id ? 'selected' : '' }}>
                            {{ $siteOption->code }} — {{ $siteOption->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase mb-1">{{ __('Account Status') }}</label>
                <div class="flex items-center gap-2">
                    <select name="status" class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="">{{ __('All') }}</option>
                        <option value="active" {{ $status === 'active' ? 'selected' : '' }}>{{ __('Active Only') }}</option>
                        <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>{{ __('Deactivated Only') }}</option>
                    </select>
                    <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-900 dark:bg-amber-500 text-white dark:text-slate-950 text-xs font-bold hover:opacity-90 transition shrink-0">
                        {{ __('Filter') }}
                    </button>
                </div>
            </div>
        </div>
    </form>

    <!-- Users Table -->
    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 bg-slate-50/70 dark:bg-slate-950/60 text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        <th class="py-3.5 px-4">{{ __('Employee / Login Email') }}</th>
                        <th class="py-3.5 px-4">{{ __('Role & Overrides') }}</th>
                        <th class="py-3.5 px-4">{{ __('Assigned Sites') }}</th>
                        <th class="py-3.5 px-4">{{ __('Security & 2FA') }}</th>
                        <th class="py-3.5 px-4">{{ __('Status') }}</th>
                        <th class="py-3.5 px-4 text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                    @forelse($users as $u)
                        @php
                            $uRole = $u->roleInCompany($companyId);
                            $uOverrides = $u->permissionOverrides($companyId);
                            $allowCount = count(array_filter($uOverrides, fn ($v) => $v === 'allow'));
                            $denyCount = count(array_filter($uOverrides, fn ($v) => $v === 'deny'));
                            $uSites = $u->sites->where('company_id', $companyId);
                            $restricted = $u->isRestrictedToAssignedSites($companyId);
                        @endphp
                        <tr class="hover:bg-slate-50/80 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">
                                    <a href="{{ route('admin.users.show', $u) }}" class="hover:text-amber-600 dark:hover:text-amber-400">
                                        {{ $u->name }}
                                    </a>
                                </div>
                                <div class="font-mono text-[11px] text-slate-500 dark:text-slate-400">{{ $u->email }}</div>
                                @if($u->phone)
                                    <div class="text-[11px] text-slate-400">{{ $u->phone }}</div>
                                @endif
                            </td>

                            <td class="py-3.5 px-4">
                                @if($uRole)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg text-[11px] font-bold {{ $uRole->isProtectedAdminRole() ? 'bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-slate-700' }}">
                                        {{ $uRole->name }}
                                    </span>
                                @else
                                    <span class="text-slate-400 italic">{{ __('No Role') }}</span>
                                @endif

                                @if($allowCount > 0 || $denyCount > 0)
                                    <div class="mt-1 flex items-center gap-1.5 text-[10px]">
                                        @if($allowCount > 0)
                                            <span class="px-1.5 py-0.5 rounded bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 font-bold">+{{ $allowCount }} {{ __('Allow') }}</span>
                                        @endif
                                        @if($denyCount > 0)
                                            <span class="px-1.5 py-0.5 rounded bg-rose-500/15 text-rose-600 dark:text-rose-400 font-bold">-{{ $denyCount }} {{ __('Deny') }}</span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <td class="py-3.5 px-4">
                                @if($uRole && in_array($uRole->slug, ['super_admin', 'company_admin'], true))
                                    <span class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400">{{ __('All Company Sites') }}</span>
                                @elseif($uSites->isEmpty() && !$restricted)
                                    <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Company-Wide (Unrestricted)') }}</span>
                                @elseif($uSites->isEmpty() && $restricted)
                                    <span class="text-[11px] font-semibold text-rose-600 dark:text-rose-400">{{ __('Restricted (0 Sites Assigned)') }}</span>
                                @else
                                    <div class="flex flex-wrap gap-1 max-w-xs">
                                        @foreach($uSites->take(3) as $s)
                                            <span class="px-2 py-0.5 rounded-md bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 text-[10px] font-semibold">
                                                {{ $s->code }}
                                            </span>
                                        @endforeach
                                        @if($uSites->count() > 3)
                                            <span class="px-1.5 py-0.5 rounded-md bg-slate-200 dark:bg-slate-700 text-[10px] font-bold">
                                                +{{ $uSites->count() - 3 }}
                                            </span>
                                        @endif
                                    </div>
                                @endif
                            </td>

                            <td class="py-3.5 px-4">
                                <div class="flex flex-col gap-1">
                                    @if($u->requiresTwoFactor())
                                        <span class="inline-flex items-center gap-1 text-[11px] font-bold text-sky-600 dark:text-sky-400">
                                            <span>2FA ({{ strtoupper($u->two_factor_channel ?: 'TOTP') }})</span>
                                        </span>
                                    @else
                                        <span class="text-[11px] text-slate-400">{{ __('Standard Password') }}</span>
                                    @endif

                                    @if($u->must_change_password ?? false)
                                        <span class="text-[10px] font-bold text-amber-600 dark:text-amber-400">{{ __('Must change password on login') }}</span>
                                    @endif
                                </div>
                            </td>

                            <td class="py-3.5 px-4">
                                @if($u->isActiveInCompany($companyId))
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-emerald-500/15 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                        {{ __('Active') }}
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-bold bg-rose-500/15 text-rose-700 dark:text-rose-300 border border-rose-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                        {{ __('Inactive') }}
                                    </span>
                                @endif
                            </td>

                            <td class="py-3.5 px-4 text-right">
                                <div class="inline-flex items-center gap-1.5">
                                    <a href="{{ route('admin.users.show', $u) }}" class="px-2.5 py-1.5 rounded-lg bg-slate-100 hover:bg-amber-500/20 text-slate-700 hover:text-amber-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:text-amber-300 font-bold text-[11px] transition">
                                        {{ __('Access & Security') }}
                                    </a>
                                    @if(Auth::user()->canPerform('users.update', ['super_admin', 'company_admin']))
                                    <a href="{{ route('admin.users.edit', $u) }}" class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold text-[11px] transition">
                                        {{ __('Edit') }}
                                    </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500 dark:text-slate-400 text-xs">
                                {{ __('No employee user accounts match your filter criteria.') }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($users->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $users->links() }}
            </div>
        @endif
    </div>
</div>
@endsection


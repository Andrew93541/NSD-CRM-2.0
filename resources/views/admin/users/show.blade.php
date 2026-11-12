@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ resetMode: 'auto' }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                <a href="{{ route('admin.users.index') }}" class="hover:underline">{{ __('Users & Access') }}</a>
                <span>/</span>
                <span>{{ $targetUser->name }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1 flex items-center gap-2.5">
                <span>{{ $targetUser->name }}</span>
                @if($isActive)
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">{{ __('Active') }}</span>
                @else
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30">{{ __('Deactivated') }}</span>
                @endif
            </h1>
            <p class="text-xs font-mono text-slate-500 dark:text-slate-400 mt-0.5">{{ $targetUser->email }} {{ $targetUser->phone ? '• ' . $targetUser->phone : '' }}</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('admin.users.index') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                ← {{ __('All Users') }}
            </a>
            @if(Auth::user()->canPerform('users.update', ['super_admin', 'company_admin']))
                <a href="{{ route('admin.users.edit', $targetUser) }}" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition">
                    {{ __('Edit Profile & Role') }}
                </a>
            @endif
        </div>
    </div>

    <!-- One-Time Generated Password or Recovery Codes Banner -->
    @if(session('generated_temp_password'))
        <div class="p-5 rounded-2xl bg-amber-500/10 border-2 border-amber-500/40 text-slate-900 dark:text-amber-100 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                        {{ __('One-Time Temporary Password — Copy & Share Securely Now') }}
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300 mt-1">
                        {{ __('Login Email:') }} <span class="font-mono font-bold">{{ session('generated_temp_user_email') ?? $targetUser->email }}</span> —
                        {{ __('This password is shown only once and hashed with bcrypt in the database.') }}
                    </p>
                </div>
                <div class="px-4 py-2.5 rounded-xl bg-slate-900 text-amber-300 font-mono text-sm font-bold tracking-wider select-all border border-amber-500/40">
                    {{ session('generated_temp_password') }}
                </div>
            </div>
        </div>
    @endif

    @if(session('two_factor_recovery_codes'))
        <div class="p-5 rounded-2xl bg-sky-500/10 border border-sky-500/40 text-slate-900 dark:text-sky-100 space-y-3">
            <div class="text-xs font-extrabold uppercase tracking-wider text-sky-600 dark:text-sky-400">
                {{ __('One-Time 2FA Enrollment & Recovery Credentials') }}
            </div>
            @if(session('two_factor_provisioning_qr_svg'))
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                    <div class="p-2 bg-white rounded-xl border border-slate-200 shrink-0">
                        {!! session('two_factor_provisioning_qr_svg') !!}
                    </div>
                    <div class="space-y-1 text-xs">
                        <div><span class="font-bold">{{ __('Manual Base32 Secret:') }}</span> <code class="px-2 py-0.5 rounded bg-slate-900 text-amber-300">{{ session('two_factor_secret_plain') }}</code></div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-300">{{ __('Recovery Codes (save offline):') }}</div>
                        <div class="flex flex-wrap gap-1.5 pt-1">
                            @foreach((array) session('two_factor_recovery_codes') as $rCode)
                                <code class="px-2 py-0.5 rounded bg-slate-900 text-emerald-300 text-[11px]">{{ $rCode }}</code>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    <!-- Top Grid: Role/Account Overview + Password Reset & 2FA Controls -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Account & Role Summary -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Role & Account Summary') }}</h2>

            <div class="space-y-2.5 text-xs">
                <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-500">{{ __('Assigned Role') }}</span>
                    <span class="font-extrabold text-slate-900 dark:text-white">{{ $currentRole->name ?? __('Unassigned') }}</span>
                </div>
                <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-500">{{ __('Role Type') }}</span>
                    <span class="font-semibold">{{ ($currentRole && $currentRole->isSystemRole()) ? __('Predefined System Role') : __('Custom Role') }}</span>
                </div>
                <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-500">{{ __('Site Restriction') }}</span>
                    <span class="font-bold {{ $restrictSites ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                        {{ $restrictSites ? __('Assigned Sites Only') : __('Company-Wide Access') }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-1.5 border-b border-slate-100 dark:border-slate-800">
                    <span class="text-slate-500">{{ __('2FA Status') }}</span>
                    <span class="font-bold">{{ $targetUser->requiresTwoFactor() ? __('Enabled (' . strtoupper($targetUser->two_factor_channel ?: 'TOTP') . ')') : __('Not Required') }}</span>
                </div>
                <div class="flex items-center justify-between py-1.5">
                    <span class="text-slate-500">{{ __('Last Login') }}</span>
                    <span class="font-mono text-[11px]">{{ $targetUser->last_login_at ? $targetUser->last_login_at->format('d M Y, H:i') : __('Never') }}</span>
                </div>
            </div>

            <!-- Activate / Deactivate Account + 2FA Controls -->
            @if(Auth::user()->canPerform('users.deactivate', ['super_admin', 'company_admin']) && (int) Auth::id() !== (int) $targetUser->id && !$isLastAdmin)
                <form method="POST" action="{{ route('users.toggle_active', $targetUser) }}" class="pt-2">
                    @csrf
                    <button type="submit" class="w-full py-2 px-3 rounded-xl text-xs font-extrabold transition {{ $isActive ? 'bg-rose-500/15 hover:bg-rose-500/25 text-rose-600 dark:text-rose-400 border border-rose-500/30' : 'bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' }}">
                        {{ $isActive ? __('Deactivate User Account') : __('Reactivate User Account') }}
                    </button>
                </form>
            @elseif($isLastAdmin)
                <div class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/30 text-[11px] text-amber-700 dark:text-amber-300 font-semibold">
                    {{ __('Protected: Last active Company Admin account cannot be deactivated.') }}
                </div>
            @endif

            @if(Auth::user()->hasRole(['super_admin', 'company_admin']))
                <div class="flex items-center gap-2 pt-1">
                    <form method="POST" action="{{ route('users.toggle_2fa', $targetUser) }}" class="flex-1">
                        @csrf
                        <input type="hidden" name="channel" value="totp">
                        <button type="submit" class="w-full py-2 px-3 rounded-xl border border-slate-300 dark:border-slate-700 text-[11px] font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                            {{ $targetUser->two_factor_enabled ? __('Disable Mandatory 2FA') : __('Enable TOTP 2FA') }}
                        </button>
                    </form>
                    <form method="POST" action="{{ route('users.reset_2fa', $targetUser) }}">
                        @csrf
                        <input type="hidden" name="channel" value="totp">
                        <button type="submit" class="py-2 px-3 rounded-xl border border-sky-500/40 bg-sky-500/10 text-[11px] font-bold text-sky-600 dark:text-sky-300 hover:bg-sky-500/20 transition">
                            {{ __('Reset 2FA QR') }}
                        </button>
                    </form>
                </div>
            @endif
        </div>

        <!-- Password Reset Card -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Password Reset & Session Revocation') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('Resetting an employee password immediately invalidates active sessions on other devices.') }}
            </p>

            @if(Auth::user()->canPerform('users.reset_password', ['super_admin', 'company_admin']))
                <form method="POST" action="{{ route('admin.users.password.reset', $targetUser) }}" class="space-y-3">
                    @csrf
                    <div class="flex items-center gap-3 text-xs">
                        <label class="inline-flex items-center gap-1.5 font-semibold cursor-pointer">
                            <input type="radio" name="password_mode" value="auto" x-model="resetMode" class="text-amber-500">
                            <span>{{ __('Auto-Generate Temp Password') }}</span>
                        </label>
                        <label class="inline-flex items-center gap-1.5 font-semibold cursor-pointer">
                            <input type="radio" name="password_mode" value="manual" x-model="resetMode" class="text-amber-500">
                            <span>{{ __('Set Manually') }}</span>
                        </label>
                    </div>

                    <div x-show="resetMode === 'manual'" class="space-y-2">
                        <input type="password" name="password" placeholder="{{ __('New Password (min 8 chars)') }}"
                            class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-900 dark:text-white">
                        <input type="password" name="password_confirmation" placeholder="{{ __('Confirm New Password') }}"
                            class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>

                    <label class="flex items-center gap-2 text-xs cursor-pointer">
                        <input type="checkbox" name="must_change_password" value="1" checked class="rounded text-amber-500">
                        <span class="font-semibold">{{ __('Force password change on next login') }}</span>
                    </label>

                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-slate-900 dark:bg-amber-500 text-white dark:text-slate-950 text-xs font-extrabold hover:opacity-90 transition">
                        {{ __('Reset Employee Password') }}
                    </button>
                </form>
            @endif
        </div>

        <!-- Site Assignments Card -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-500 dark:text-slate-400">{{ __('Assigned Company Sites') }}</h2>

            <form method="POST" action="{{ route('admin.users.sites.update', $targetUser) }}" class="space-y-3">
                @csrf
                @method('PUT')

                <label class="flex items-center gap-2 text-xs font-bold text-amber-700 dark:text-amber-300 bg-amber-500/10 px-3 py-2 rounded-xl border border-amber-500/30 cursor-pointer">
                    <input type="checkbox" name="restrict_to_assigned_sites" value="1" {{ $restrictSites ? 'checked' : '' }} class="rounded text-amber-500">
                    <span>{{ __('Restrict Strictly to Assigned Sites') }}</span>
                </label>

                <div class="max-h-44 overflow-y-auto space-y-1.5 pr-1">
                    @foreach($sites as $site)
                        <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 dark:border-slate-800 text-xs cursor-pointer hover:bg-slate-50 dark:hover:bg-slate-800/50">
                            <input type="checkbox" name="site_ids[]" value="{{ $site->id }}"
                                {{ in_array((int) $site->id, $assignedSiteIds, true) ? 'checked' : '' }}
                                class="rounded text-amber-500">
                            <span class="font-bold">{{ $site->code }}</span>
                            <span class="text-slate-500 truncate">{{ $site->name }}</span>
                        </label>
                    @endforeach
                </div>

                @if(Auth::user()->canPerform('users.assign_sites', ['super_admin', 'company_admin']))
                    <button type="submit" class="w-full py-2 px-4 rounded-xl border border-amber-500/40 bg-amber-500/15 hover:bg-amber-500/25 text-amber-700 dark:text-amber-300 text-xs font-extrabold transition">
                        {{ __('Update Site Assignments') }}
                    </button>
                @endif
            </form>
        </div>
    </div>

    <!-- Individual User Permission Overrides Matrix -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-b border-slate-200 dark:border-slate-800 pb-4">
            <div>
                <h2 class="text-base font-extrabold text-slate-900 dark:text-white">{{ __('Individual User Permission Customizations (Allow / Deny / Inherit)') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Precedence Rule: Explicit User DENY overrides Explicit User ALLOW, which overrides Inherited Role Permissions.') }}
                </p>
            </div>

            @if(Auth::user()->canPerform('users.manage_permissions', ['super_admin', 'company_admin']) && !empty($overrides))
                <form method="POST" action="{{ route('admin.users.permissions.reset', $targetUser) }}">
                    @csrf
                    <button type="submit" class="px-3.5 py-2 rounded-xl border border-rose-500/40 bg-rose-500/10 hover:bg-rose-500/20 text-rose-600 dark:text-rose-300 text-xs font-bold transition">
                        {{ __('Reset All Overrides to Role Defaults') }}
                    </button>
                </form>
            @endif
        </div>

        <form method="POST" action="{{ route('admin.users.permissions.update', $targetUser) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($modules as $moduleKey => $moduleDef)
                    <div class="p-4 rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-950/40 space-y-3">
                        <div>
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ $moduleDef['label'] }}</h3>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ $moduleDef['description'] }}</p>
                        </div>

                        <div class="space-y-2">
                            @foreach($moduleDef['permissions'] as $permKey => $permLabel)
                                @php
                                    $state = $permissionStates[$permKey] ?? ['inherited' => false, 'override' => 'inherit', 'effective' => false];
                                    $isReservedAdminPerm = in_array($permKey, \App\Services\Security\PermissionCatalog::ADMIN_RESERVED_PERMISSIONS, true)
                                        && (!$currentRole || !$currentRole->isProtectedAdminRole());
                                @endphp
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 p-2.5 rounded-lg bg-white dark:bg-slate-900 border border-slate-200/80 dark:border-slate-800 text-xs">
                                    <div>
                                        <div class="font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                                            <span>{{ $permLabel }}</span>
                                            @if($state['effective'])
                                                <span class="px-1.5 py-0.2 rounded text-[10px] font-extrabold bg-emerald-500/15 text-emerald-600 dark:text-emerald-400">{{ __('Effective: Allowed') }}</span>
                                            @else
                                                <span class="px-1.5 py-0.2 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-800 text-slate-500">{{ __('Effective: Denied') }}</span>
                                            @endif
                                        </div>
                                        <div class="text-[10px] font-mono text-slate-400">
                                            {{ $permKey }} • {{ __('Role Default:') }} {{ $state['inherited'] ? __('Allowed') : __('Not Granted') }}
                                        </div>
                                    </div>

                                    <select name="overrides[{{ $permKey }}]" class="rounded-lg border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-2.5 py-1.5 text-[11px] font-bold text-slate-800 dark:text-slate-200">
                                        <option value="inherit" {{ $state['override'] === 'inherit' ? 'selected' : '' }}>
                                            {{ __('Inherit Role') }} ({{ $state['inherited'] ? '✓' : '✕' }})
                                        </option>
                                        @if(!$isReservedAdminPerm)
                                            <option value="allow" {{ $state['override'] === 'allow' ? 'selected' : '' }}>
                                                {{ __('Explicit ALLOW (+)') }}
                                            </option>
                                        @endif
                                        <option value="deny" {{ $state['override'] === 'deny' ? 'selected' : '' }}>
                                            {{ __('Explicit DENY (-)') }}
                                        </option>
                                    </select>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            @if(Auth::user()->canPerform('users.manage_permissions', ['super_admin', 'company_admin']))
                <div class="flex justify-end pt-2">
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition">
                        {{ __('Save Individual Permission Overrides') }}
                    </button>
                </div>
            @endif
        </form>
    </div>

    <!-- User Audit Trail -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-3">
        <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('Recent Access & Security Activity Log') }}</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-[11px] font-bold uppercase text-slate-500">
                        <th class="py-2 pr-4">{{ __('Timestamp') }}</th>
                        <th class="py-2 pr-4">{{ __('Event') }}</th>
                        <th class="py-2 pr-4">{{ __('Actor') }}</th>
                        <th class="py-2">{{ __('IP Address') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                    @forelse($recentAuditLogs as $log)
                        <tr>
                            <td class="py-2 pr-4 font-mono text-[11px] text-slate-500">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                            <td class="py-2 pr-4 font-bold text-slate-800 dark:text-slate-200">{{ $log->action }}</td>
                            <td class="py-2 pr-4 text-slate-600 dark:text-slate-400">{{ $log->actor->name ?? 'System' }}</td>
                            <td class="py-2 font-mono text-[11px] text-slate-400">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="py-4 text-center text-slate-400">{{ __('No audit events recorded for this user yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection


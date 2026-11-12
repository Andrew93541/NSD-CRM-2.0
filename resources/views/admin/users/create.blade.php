@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{ passwordMode: '{{ old('password_mode', 'manual') }}' }">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                <a href="{{ route('admin.users.index') }}" class="hover:underline">{{ __('Users & Access') }}</a>
                <span>/</span>
                <span>{{ __('New Employee Account') }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ __('Create Employee Login Account') }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Provision a new CRM login with role permissions, site-level boundaries, and optional temporary password enforcement.') }}
            </p>
        </div>

        <a href="{{ route('admin.users.index') }}" class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition self-start">
            ← {{ __('Back to Users') }}
        </a>
    </div>

    <form method="POST" action="{{ route('admin.users.store') }}" class="space-y-6">
        @csrf

        <!-- 1. Identity & Contact Details -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('1. Employee Identity & Login Email') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Full Name') }} <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name') }}" required placeholder="e.g. Vikram Deshmukh"
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Login Email Address') }} <span class="text-rose-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email') }}" required placeholder="vikram@nsd.local"
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs font-mono text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Mobile / Phone Number') }}</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" placeholder="+91 98XXXXXXXX"
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>
        </div>

        <!-- 2. Role & Credentials -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('2. Role & Login Credentials') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Assigned Company Role') }} <span class="text-rose-500">*</span></label>
                    <select name="role_id" required class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="">{{ __('— Select Role —') }}</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ (int) old('role_id') === (int) $role->id ? 'selected' : '' }}>
                                {{ $role->name }} {{ $role->isSystemRole() ? '(System)' : '(Custom)' }}
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                        {{ __('Need a specialized role such as Accountant or Procurement Officer?') }}
                        <a href="{{ route('admin.roles.create') }}" class="text-amber-600 dark:text-amber-400 font-bold hover:underline">{{ __('Create Custom Role') }}</a>
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Initial Password Provisioning') }}</label>
                    <div class="flex items-center gap-3 pt-1">
                        <label class="inline-flex items-center gap-2 text-xs font-semibold cursor-pointer">
                            <input type="radio" name="password_mode" value="manual" x-model="passwordMode" class="text-amber-500 focus:ring-amber-500">
                            <span>{{ __('Set Password Manually') }}</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-xs font-semibold cursor-pointer">
                            <input type="radio" name="password_mode" value="auto" x-model="passwordMode" class="text-amber-500 focus:ring-amber-500">
                            <span>{{ __('Auto-Generate One-Time Password') }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div x-show="passwordMode === 'manual'" class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Password (min 8 chars)') }} <span class="text-rose-500">*</span></label>
                    <input type="password" name="password" autocomplete="new-password"
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Confirm Password') }} <span class="text-rose-500">*</span></label>
                    <input type="password" name="password_confirmation" autocomplete="new-password"
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-200 dark:border-slate-800">
                <label class="flex items-start gap-2.5 text-xs cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" {{ old('is_active', '1') ? 'checked' : '' }} class="mt-0.5 rounded text-amber-500 focus:ring-amber-500">
                    <div>
                        <span class="font-bold text-slate-900 dark:text-white">{{ __('Account Active') }}</span>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Allow immediate sign-in.') }}</p>
                    </div>
                </label>

                <label class="flex items-start gap-2.5 text-xs cursor-pointer">
                    <input type="checkbox" name="must_change_password" value="1" {{ old('must_change_password', '1') ? 'checked' : '' }} class="mt-0.5 rounded text-amber-500 focus:ring-amber-500">
                    <div>
                        <span class="font-bold text-slate-900 dark:text-white">{{ __('Force Password Change') }}</span>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Require user to set a new password on first login.') }}</p>
                    </div>
                </label>

                <label class="flex items-start gap-2.5 text-xs cursor-pointer">
                    <input type="checkbox" name="enable_2fa" value="1" {{ old('enable_2fa') ? 'checked' : '' }} class="mt-0.5 rounded text-amber-500 focus:ring-amber-500">
                    <div>
                        <span class="font-bold text-slate-900 dark:text-white">{{ __('Require Authenticator 2FA') }}</span>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Provision TOTP secret & recovery codes.') }}</p>
                    </div>
                </label>
            </div>
        </div>

        <!-- 3. Site-Level Access Control -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('3. Site-Level Access Control') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Select which construction sites this employee is authorized to access.') }}</p>
                </div>

                <label class="inline-flex items-center gap-2 text-xs font-bold text-amber-700 dark:text-amber-300 bg-amber-500/10 px-3 py-1.5 rounded-xl border border-amber-500/30 cursor-pointer">
                    <input type="checkbox" name="restrict_to_assigned_sites" value="1" {{ old('restrict_to_assigned_sites', '1') ? 'checked' : '' }} class="rounded text-amber-500 focus:ring-amber-500">
                    <span>{{ __('Restrict Strictly to Assigned Sites') }}</span>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($sites as $site)
                    <label class="flex items-start gap-2.5 p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-amber-500/50 cursor-pointer transition">
                        <input type="checkbox" name="site_ids[]" value="{{ $site->id }}"
                            {{ in_array($site->id, array_map('intval', old('site_ids', [])), true) ? 'checked' : '' }}
                            class="mt-0.5 rounded text-amber-500 focus:ring-amber-500">
                        <div class="text-xs">
                            <div class="font-bold text-slate-900 dark:text-white">{{ $site->code }} — {{ $site->name }}</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ ucfirst($site->status) }}</div>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <!-- Submit -->
        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.users.index') }}" class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition">
                {{ __('Create Employee Account') }}
            </button>
        </div>
    </form>
</div>
@endsection


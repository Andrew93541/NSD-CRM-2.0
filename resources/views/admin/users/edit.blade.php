@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <div class="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-amber-600 dark:text-amber-400">
                <a href="{{ route('admin.users.index') }}" class="hover:underline">{{ __('Users & Access') }}</a>
                <span>/</span>
                <a href="{{ route('admin.users.show', $targetUser) }}" class="hover:underline">{{ $targetUser->name }}</a>
                <span>/</span>
                <span>{{ __('Edit Profile & Role') }}</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ __('Edit Employee Account') }}: {{ $targetUser->name }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                {{ __('Update login details, assigned company role, site scope, and account activation status.') }}
            </p>
        </div>

        <a href="{{ route('admin.users.show', $targetUser) }}" class="px-3.5 py-2 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 transition self-start">
            ← {{ __('Back to User Inspector') }}
        </a>
    </div>

    <form method="POST" action="{{ route('admin.users.update', $targetUser) }}" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- 1. Identity -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('1. Employee Profile & Login Email') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Full Name') }} <span class="text-rose-500">*</span></label>
                    <input type="text" name="name" value="{{ old('name', $targetUser->name) }}" required
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Login Email Address') }} <span class="text-rose-500">*</span></label>
                    <input type="email" name="email" value="{{ old('email', $targetUser->email) }}" required
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs font-mono text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Mobile / Phone Number') }}</label>
                    <input type="text" name="phone" value="{{ old('phone', $targetUser->phone) }}"
                        class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>
        </div>

        <!-- 2. Role & Account Status -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('2. Role & Account Status') }}</h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Assigned Company Role') }} <span class="text-rose-500">*</span></label>
                    <select name="role_id" required class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}" {{ (int) old('role_id', $currentRole->id ?? 0) === (int) $role->id ? 'selected' : '' }}>
                                {{ $role->name }} {{ $role->isSystemRole() ? '(System)' : '(Custom)' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="space-y-2 pt-2">
                    <label class="flex items-start gap-2.5 text-xs cursor-pointer">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $isActive) ? 'checked' : '' }} class="mt-0.5 rounded text-amber-500 focus:ring-amber-500">
                        <div>
                            <span class="font-bold text-slate-900 dark:text-white">{{ __('Account Active in Company') }}</span>
                            <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Unchecking immediately blocks login and revokes active sessions.') }}</p>
                        </div>
                    </label>

                    <label class="flex items-start gap-2.5 text-xs cursor-pointer">
                        <input type="checkbox" name="must_change_password" value="1" {{ old('must_change_password', $targetUser->must_change_password ?? false) ? 'checked' : '' }} class="mt-0.5 rounded text-amber-500 focus:ring-amber-500">
                        <div>
                            <span class="font-bold text-slate-900 dark:text-white">{{ __('Force Password Change on Next Login') }}</span>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        <!-- 3. Site-Level Scope -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                <div>
                    <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-900 dark:text-white">{{ __('3. Assigned Construction Sites') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Choose which sites this user can view and operate.') }}</p>
                </div>

                <label class="inline-flex items-center gap-2 text-xs font-bold text-amber-700 dark:text-amber-300 bg-amber-500/10 px-3 py-1.5 rounded-xl border border-amber-500/30 cursor-pointer">
                    <input type="checkbox" name="restrict_to_assigned_sites" value="1" {{ old('restrict_to_assigned_sites', $restrictSites) ? 'checked' : '' }} class="rounded text-amber-500 focus:ring-amber-500">
                    <span>{{ __('Restrict Strictly to Assigned Sites') }}</span>
                </label>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                @foreach($sites as $site)
                    <label class="flex items-start gap-2.5 p-3 rounded-xl border border-slate-200 dark:border-slate-800 hover:border-amber-500/50 cursor-pointer transition">
                        <input type="checkbox" name="site_ids[]" value="{{ $site->id }}"
                            {{ in_array((int) $site->id, array_map('intval', old('site_ids', $assignedSiteIds)), true) ? 'checked' : '' }}
                            class="mt-0.5 rounded text-amber-500 focus:ring-amber-500">
                        <div class="text-xs">
                            <div class="font-bold text-slate-900 dark:text-white">{{ $site->code }} — {{ $site->name }}</div>
                            <div class="text-[11px] text-slate-500 dark:text-slate-400">{{ ucfirst($site->status) }}</div>
                        </div>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <a href="{{ route('admin.users.show', $targetUser) }}" class="px-4 py-2.5 rounded-xl border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                {{ __('Cancel') }}
            </a>
            <button type="submit" class="px-5 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition">
                {{ __('Save Account Changes') }}
            </button>
        </div>
    </form>
</div>
@endsection


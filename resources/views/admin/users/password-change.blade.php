@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto py-8">
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-lg space-y-5">
        <div>
            <span class="px-2.5 py-1 rounded-full text-[11px] font-extrabold uppercase tracking-wider bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                {{ __('Account Security') }}
            </span>
            <h1 class="text-xl font-extrabold text-slate-900 dark:text-white mt-2">
                {{ ($user->must_change_password ?? false) ? __('Update Your Temporary Password') : __('Change Account Password') }}
            </h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Choose a strong password of at least 8 characters to secure your CRM account.') }}
            </p>
        </div>

        <form method="POST" action="{{ route('account.password.update') }}" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Current / Temporary Password') }} <span class="text-rose-500">*</span></label>
                <input type="password" name="current_password" required autocomplete="current-password"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('New Password (min 8 chars)') }} <span class="text-rose-500">*</span></label>
                <input type="password" name="password" required autocomplete="new-password"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Confirm New Password') }} <span class="text-rose-500">*</span></label>
                <input type="password" name="password_confirmation" required autocomplete="new-password"
                    class="w-full rounded-xl border border-slate-300 dark:border-slate-700 bg-slate-50 dark:bg-slate-950 px-3.5 py-2.5 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
            </div>

            <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition">
                {{ __('Update Password & Continue') }}
            </button>
        </form>
    </div>
</div>
@endsection


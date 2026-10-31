<!DOCTYPE html>
<html lang="en" class="h-full antialiased overflow-x-hidden dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Set New 6-Digit PIN') }} — NSD CRM 2.0</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    <div class="sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white dark:bg-slate-900 py-8 px-6 shadow-2xl rounded-2xl border border-slate-200 dark:border-slate-800 sm:px-10 space-y-5">
            <div>
                <span class="px-2.5 py-1 rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-400 text-xs font-bold uppercase">{{ __('Security Requirement') }}</span>
                <h1 class="text-xl font-extrabold text-slate-900 dark:text-white mt-2">{{ __('Change Your First-Login PIN') }}</h1>
                <p class="text-xs text-slate-600 dark:text-slate-400 mt-1">
                    {{ __('Welcome') }}, <strong>{{ $worker->full_name }}</strong> ({{ $worker->code }}). {{ __('Please choose a new personal 6-digit PIN before punching attendance.') }}
                </p>
            </div>

            @if ($errors->any())
                <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-300 text-rose-800 dark:bg-rose-950/80 dark:border-rose-700 dark:text-rose-200 text-xs font-semibold">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('worker.pin.update') }}" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('New 6-Digit PIN') }} *</label>
                    <input type="password" name="new_pin" inputmode="numeric" maxlength="6" required
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-4 py-3 text-sm font-mono text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Confirm New 6-Digit PIN') }} *</label>
                    <input type="password" name="new_pin_confirmation" inputmode="numeric" maxlength="6" required
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-4 py-3 text-sm font-mono text-slate-900 dark:text-white">
                </div>
                <button type="submit" class="w-full py-3.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-sm transition">
                    {{ __('Save New PIN & Continue') }}
                </button>
            </form>
        </div>
    </div>
</body>
</html>


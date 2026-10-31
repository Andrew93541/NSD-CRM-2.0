<!DOCTYPE html>
<html lang="en" class="h-full antialiased overflow-x-hidden dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Worker Portal Sign In') }} — NSD CRM 2.0</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-8 bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100">
    <div class="sm:mx-auto sm:w-full sm:max-w-md text-center">
        <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-emerald-500 to-emerald-400 items-center justify-center font-black text-slate-950 text-2xl shadow-xl shadow-emerald-500/30 mb-4">
            W
        </div>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            {{ __('Direct Employee Worker Portal') }}
        </h1>
        <p class="mt-2 text-xs sm:text-sm text-slate-600 dark:text-slate-400">
            {{ __('Sign in with your Dynamic Worker ID (EMP-YYYY-####) and 6-digit PIN.') }}
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
        <div class="bg-white dark:bg-slate-900 py-8 px-6 shadow-2xl rounded-2xl border border-slate-200 dark:border-slate-800 sm:px-10">
            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-300 text-rose-800 dark:bg-rose-950/80 dark:border-rose-700 dark:text-rose-200 text-xs font-semibold">
                    {{ $errors->first() }}
                </div>
            @endif

            <form class="space-y-5" action="{{ route('worker.login.post') }}" method="POST">
                @csrf
                <div>
                    <label for="worker_code" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">{{ __('Worker ID / Code') }} *</label>
                    <div class="mt-2">
                        <input id="worker_code" name="worker_code" type="text" required value="{{ old('worker_code') }}" placeholder="EMP-2026-0001"
                            class="block w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-4 py-3 text-sm font-mono text-slate-900 dark:text-white focus:border-emerald-500 outline-none">
                    </div>
                </div>

                <div>
                    <label for="pin" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">{{ __('6-Digit PIN') }} *</label>
                    <div class="mt-2">
                        <input id="pin" name="pin" type="password" inputmode="numeric" required placeholder="••••••"
                            class="block w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-4 py-3 text-sm font-mono text-slate-900 dark:text-white focus:border-emerald-500 outline-none">
                    </div>
                </div>

                <button type="submit" class="w-full flex justify-center py-3.5 px-4 rounded-xl shadow-lg text-sm font-bold text-slate-950 bg-emerald-500 hover:bg-emerald-400 transition">
                    {{ __('Sign In to Worker Punch Portal') }}
                </button>
            </form>

            <div class="mt-6 pt-5 border-t border-slate-200 dark:border-slate-800 text-center">
                <a href="{{ route('login') }}" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                    &larr; {{ __('Back to Office & Supervisor Staff Login') }}
                </a>
            </div>
        </div>
    </div>
</body>
</html>


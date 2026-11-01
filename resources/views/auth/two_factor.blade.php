<!DOCTYPE html>
<html lang="en" class="h-full antialiased overflow-x-hidden {{ ($currentTheme ?? session('theme', 'dark')) === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Two-Factor Authentication — NSD CRM 2.0</title>
    <script>
        (function() {
            var storedTheme = null;
            try { storedTheme = localStorage.getItem('theme'); } catch(e) {}
            var serverTheme = "{{ $currentTheme ?? session('theme', '') }}";
            var theme = storedTheme || serverTheme || 'dark';
            if (theme === 'system') {
                theme = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
            }
            if (theme === 'dark') {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>body { font-family: 'Plus Jakarta Sans', sans-serif; }</style>
</head>
<body class="min-h-full flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 relative overflow-x-hidden transition-colors duration-200">
    <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-sky-500/10 rounded-full blur-3xl"></div>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10 text-center">
        <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-500 to-amber-400 items-center justify-center font-black text-slate-950 text-3xl shadow-xl shadow-amber-500/30 mb-4">
            2FA
        </div>
        <h2 class="text-2xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            Manager Security Verification
        </h2>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
            Enter the 6-digit security verification code for <span class="font-semibold text-slate-800 dark:text-slate-200">{{ $user->email }}</span>
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md relative z-10 px-4 sm:px-0">
        <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl py-8 px-6 shadow-2xl rounded-2xl border border-slate-200 dark:border-slate-800 sm:px-10 transition-colors duration-200">
            @if (session('success'))
                <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-800 dark:bg-emerald-950/80 dark:border-emerald-600/50 dark:text-emerald-200 text-xs">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('two_factor_demo_code'))
                <div class="mb-6 p-4 rounded-xl bg-amber-50 border border-amber-300 text-amber-900 dark:bg-amber-950/60 dark:border-amber-500/40 dark:text-amber-200 text-xs">
                    <div class="font-bold uppercase tracking-wider text-[10px] mb-1">Local / Demo Verification Code</div>
                    <div class="font-mono text-lg font-extrabold tracking-widest">{{ session('two_factor_demo_code') }}</div>
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-300 text-rose-800 dark:bg-rose-950/80 dark:border-rose-600/50 dark:text-rose-200 text-xs">
                    {{ $errors->first() }}
                </div>
            @endif

            <form class="space-y-6" action="{{ route('two-factor.verify') }}" method="POST">
                @csrf
                <div>
                    <label for="code" class="block text-xs font-semibold uppercase tracking-wider text-slate-700 dark:text-slate-300">
                        6-Digit Verification Code
                    </label>
                    <div class="mt-2">
                        <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus
                            placeholder="000000"
                            class="block w-full rounded-xl bg-slate-50 border border-slate-300 px-4 py-3 text-center font-mono text-2xl tracking-[0.5em] text-slate-900 placeholder-slate-400 dark:bg-slate-950 dark:border-slate-700 dark:text-white dark:placeholder-slate-600 focus:border-amber-500 focus:ring-amber-500 focus:outline-none">
                    </div>
                </div>

                <div>
                    <button type="submit"
                        class="flex w-full justify-center rounded-xl bg-amber-500 px-4 py-3 text-sm font-bold text-slate-950 shadow-lg shadow-amber-500/20 hover:bg-amber-400 transition">
                        Verify & Continue to CRM
                    </button>
                </div>
            </form>

            <div class="mt-6 flex items-center justify-between border-t border-slate-200 dark:border-slate-800 pt-4 text-xs">
                <form action="{{ route('two-factor.resend') }}" method="POST">
                    @csrf
                    <button type="submit" class="font-semibold text-amber-600 dark:text-amber-400 hover:underline">
                        Resend Verification Code
                    </button>
                </form>

                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>


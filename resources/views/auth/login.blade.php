<!DOCTYPE html>
<html lang="en" class="h-full antialiased overflow-x-hidden {{ ($currentTheme ?? session('theme', 'dark')) === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In — NSD CRM 2.0</title>
    <!-- Anti-FOUC Theme Script -->
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
    <!-- Ambient glow -->
    <div class="fixed inset-0 overflow-hidden pointer-events-none" aria-hidden="true">
        <div class="absolute -top-40 -right-40 w-96 h-96 bg-amber-500/10 rounded-full blur-3xl"></div>
        <div class="absolute -bottom-40 -left-40 w-96 h-96 bg-sky-500/10 rounded-full blur-3xl"></div>
    </div>

    <!-- Theme Switcher Top Right -->
    <div class="absolute top-4 right-4 z-20" x-data="{
        isDark: document.documentElement.classList.contains('dark'),
        toggleTheme() {
            this.isDark = !this.isDark;
            if (this.isDark) {
                document.documentElement.classList.add('dark');
                try { localStorage.setItem('theme', 'dark'); } catch(e) {}
            } else {
                document.documentElement.classList.remove('dark');
                try { localStorage.setItem('theme', 'light'); } catch(e) {}
            }
            fetch('{{ route('switch_theme') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({ theme: this.isDark ? 'dark' : 'light' })
            }).catch(function(err){ console.error(err); });
        }
    }">
        <button type="button" @click="toggleTheme()"
            class="p-2.5 rounded-xl border transition flex items-center justify-center bg-white hover:bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-amber-400 dark:border-slate-700 shadow-sm focus:outline-none focus:ring-2 focus:ring-amber-500"
            :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
            :title="isDark ? 'Switch to light theme' : 'Switch to dark theme'">
            <svg x-show="isDark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
            </svg>
            <svg x-show="!isDark" class="w-4 h-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
            </svg>
        </button>
    </div>

    <div class="sm:mx-auto sm:w-full sm:max-w-md relative z-10 text-center">
        <div class="inline-flex w-14 h-14 rounded-2xl bg-gradient-to-tr from-amber-500 to-amber-400 items-center justify-center font-black text-slate-950 text-3xl shadow-xl shadow-amber-500/30 mb-4">
            N
        </div>
        <h2 class="text-3xl font-extrabold tracking-tight text-slate-900 dark:text-white">
            NSD Earth Movers & Construction
        </h2>
        <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">
            Operational CRM 2.0 — Workforce, Work Orders & Stores
        </p>
    </div>

    <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md relative z-10 px-4 sm:px-0">
        <div class="bg-white/90 dark:bg-slate-900/90 backdrop-blur-xl py-8 px-6 shadow-2xl rounded-2xl border border-slate-200 dark:border-slate-800 sm:px-10 transition-colors duration-200">
            @if ($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-300 text-rose-800 dark:bg-rose-950/80 dark:border-rose-600/50 dark:text-rose-200 text-xs">
                    {{ $errors->first() }}
                </div>
            @endif

            <form class="space-y-5" action="{{ route('login.post') }}" method="POST">
                @csrf
                <div>
                    <label for="email" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Email Address</label>
                    <div class="mt-2">
                        <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email', app()->isLocal() ? 'admin@nsd.local' : '') }}"
                            class="block w-full rounded-xl bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Password</label>
                    <div class="mt-2">
                        <input id="password" name="password" type="password" required value="{{ app()->isLocal() ? 'password' : '' }}"
                            class="block w-full rounded-xl bg-white dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 focus:ring-1 focus:ring-amber-500 outline-none transition">
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input id="remember" name="remember" type="checkbox" checked
                            class="h-4 w-4 rounded bg-white dark:bg-slate-950 border-slate-300 dark:border-slate-800 text-amber-500 focus:ring-amber-500">
                        <label for="remember" class="ml-2 block text-xs text-slate-600 dark:text-slate-400">Remember session</label>
                    </div>
                </div>

                <div>
                    <button type="submit"
                        class="w-full flex justify-center py-3.5 px-4 border border-transparent rounded-xl shadow-lg shadow-amber-500/25 text-sm font-bold text-slate-950 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 focus:outline-none transition active:scale-[0.99]">
                        Sign in to Operational CRM
                    </button>
                </div>
            </form>

            @if(app()->isLocal())
            <!-- Quick Demo Role Switcher (Development Only) -->
            <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-800 text-center" x-data>
                <p class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider mb-3">Quick Demo Credentials (Password: password)</p>
                <div class="grid grid-cols-2 gap-2 text-[11px]">
                    <button type="button" @click="document.getElementById('email').value='admin@nsd.local'" class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-800 dark:bg-slate-800/80 dark:hover:bg-slate-700 dark:border-slate-700/60 dark:text-slate-200 font-semibold transition text-left">
                        <div class="text-amber-600 dark:text-amber-400 font-bold">Admin</div>
                        <div class="text-slate-500 dark:text-slate-400 truncate">admin@nsd.local</div>
                    </button>
                    <button type="button" @click="document.getElementById('email').value='senior.manager@nsd.local'" class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-800 dark:bg-slate-800/80 dark:hover:bg-slate-700 dark:border-slate-700/60 dark:text-slate-200 font-semibold transition text-left">
                        <div class="text-sky-600 dark:text-sky-400 font-bold">Senior VP Operations</div>
                        <div class="text-slate-500 dark:text-slate-400 truncate">senior.manager@nsd.local</div>
                    </button>
                    <button type="button" @click="document.getElementById('email').value='site.manager@nsd.local'" class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-800 dark:bg-slate-800/80 dark:hover:bg-slate-700 dark:border-slate-700/60 dark:text-slate-200 font-semibold transition text-left">
                        <div class="text-emerald-600 dark:text-emerald-400 font-bold">Site Manager</div>
                        <div class="text-slate-500 dark:text-slate-400 truncate">site.manager@nsd.local</div>
                    </button>
                    <button type="button" @click="document.getElementById('email').value='supervisor1@nsd.local'" class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-800 dark:bg-slate-800/80 dark:hover:bg-slate-700 dark:border-slate-700/60 dark:text-slate-200 font-semibold transition text-left">
                        <div class="text-purple-600 dark:text-purple-400 font-bold">Site Supervisor</div>
                        <div class="text-slate-500 dark:text-slate-400 truncate">supervisor1@nsd.local</div>
                    </button>
                    <button type="button" @click="document.getElementById('email').value='warehouse@nsd.local'" class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 border border-slate-200 text-slate-800 dark:bg-slate-800/80 dark:hover:bg-slate-700 dark:border-slate-700/60 dark:text-slate-200 font-semibold transition text-left col-span-2">
                        <div class="text-orange-600 dark:text-orange-400 font-bold">Central Depot Keeper</div>
                        <div class="text-slate-500 dark:text-slate-400 truncate">warehouse@nsd.local</div>
                    </button>
                </div>
            </div>
            @endif
        </div>
    </div>
</body>
</html>

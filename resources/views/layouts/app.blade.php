<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full antialiased {{ ($currentTheme ?? session('theme', 'dark')) === 'dark' ? 'dark' : '' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'NSD CRM 2.0' }} — {{ __('Daily Overview') }}</title>
    <!-- Anti-FOUC Theme Initialization Script -->
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
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', 'Noto Sans Devanagari', sans-serif; }
        code, pre, .font-mono { font-family: 'JetBrains Mono', monospace; }
    </style>
</head>
<body class="h-full flex flex-col overflow-x-hidden bg-slate-100 text-slate-800 dark:bg-slate-950 dark:text-slate-100 transition-colors duration-200" x-data="{ mobileMenu: false }">
    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-30 bg-white/95 text-slate-800 border-b border-slate-200 dark:bg-slate-900/90 dark:text-slate-200 dark:border-slate-800 backdrop-blur-md transition-colors duration-200">
        <div class="px-2.5 sm:px-6 lg:px-8 flex h-16 items-center justify-between gap-2">
            <div class="flex items-center gap-1.5 sm:gap-3 min-w-0">
                <button type="button" @click="mobileMenu = !mobileMenu" class="lg:hidden p-1.5 sm:p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-amber-500 shrink-0" aria-label="Toggle Navigation">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
                </button>
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 sm:gap-3 min-w-0">
                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-amber-400 flex items-center justify-center font-black text-slate-950 text-lg sm:text-xl shadow-lg shadow-amber-500/20 shrink-0">
                        N
                    </div>
                    <div class="min-w-0">
                        <span class="font-extrabold text-xs sm:text-lg tracking-tight text-slate-900 dark:text-white flex items-center gap-1.5 sm:gap-2 whitespace-nowrap">
                            NSD CRM <span class="hidden sm:inline-block text-[10px] px-2 py-0.5 rounded-full bg-amber-500/20 text-amber-600 dark:text-amber-300 font-bold border border-amber-500/30">2.0</span>
                        </span>
                        <p class="text-[10px] text-slate-500 dark:text-slate-400 font-medium hidden sm:block">NSD Earth Movers & Construction</p>
                    </div>
                </a>
            </div>

            <!-- Header Controls: Theme Switcher, Language Switcher, Organization, User Profile -->
            <div class="flex items-center gap-1.5 sm:gap-4 shrink-0">
                <!-- Accessible Light / Dark Theme Switcher -->
                <div x-data="{
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
                    <button type="button" @click="toggleTheme()" id="theme-toggle-btn"
                        class="p-1.5 sm:p-2 rounded-xl border transition flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-amber-400 dark:border-slate-700 focus:outline-none focus:ring-2 focus:ring-amber-500"
                        :aria-label="isDark ? '{{ __('Switch to light theme') }}' : '{{ __('Switch to dark theme') }}'"
                        :title="isDark ? '{{ __('Switch to light theme') }}' : '{{ __('Switch to dark theme') }}'">
                        <!-- Sun icon (shown in dark mode to switch to light) -->
                        <svg x-show="isDark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                        <!-- Moon icon (shown in light mode to switch to dark) -->
                        <svg x-show="!isDark" class="w-4 h-4 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/>
                        </svg>
                    </button>
                </div>

                <!-- Multilingual Language Switcher (EN | HI | MR) -->
                <div class="flex items-center bg-slate-100 dark:bg-slate-950 p-0.5 sm:p-1 rounded-xl border border-slate-200 dark:border-slate-800 text-[11px] sm:text-xs">
                    <form method="POST" action="{{ route('switch_locale') }}" class="inline">
                        @csrf
                        <input type="hidden" name="locale" value="en">
                        <button type="submit" class="px-1.5 sm:px-2.5 py-1 rounded-lg font-bold transition {{ app()->getLocale() === 'en' ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                            EN
                        </button>
                    </form>
                    <form method="POST" action="{{ route('switch_locale') }}" class="inline">
                        @csrf
                        <input type="hidden" name="locale" value="hi">
                        <button type="submit" class="px-1.5 sm:px-2.5 py-1 rounded-lg font-bold transition {{ app()->getLocale() === 'hi' ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                            हिन्दी
                        </button>
                    </form>
                    <form method="POST" action="{{ route('switch_locale') }}" class="inline">
                        @csrf
                        <input type="hidden" name="locale" value="mr">
                        <button type="submit" class="px-1.5 sm:px-2.5 py-1 rounded-lg font-bold transition {{ app()->getLocale() === 'mr' ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
                            मराठी
                        </button>
                    </form>
                </div>

                @if(!request()->routeIs('sites.index') && isset($accessibleSites) && $accessibleSites->count() > 0)
                    <form method="POST" action="{{ route('switch_site') }}" class="hidden md:flex items-center">
                        @csrf
                        <label for="topbar-site-switcher" class="sr-only">{{ __('Switch Site') }}</label>
                        <select id="topbar-site-switcher" name="site_id" onchange="this.form.submit()" class="rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700/60 px-2.5 py-1.5 text-xs font-bold text-slate-700 dark:text-slate-200 focus:border-amber-500 outline-none max-w-[190px] truncate">
                            <option value="">{{ __('All Accessible Sites') }} ({{ $accessibleSites->count() }})</option>
                            @foreach($accessibleSites as $navSite)
                                <option value="{{ $navSite->id }}" {{ (int) ($activeSiteId ?? 0) === (int) $navSite->id ? 'selected' : '' }}>
                                    {{ $navSite->code }} — {{ $navSite->name }}
                                </option>
                            @endforeach
                        </select>
                    </form>
                @endif

                @if(isset($currentCompany))
                    <div class="hidden xl:flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 border border-slate-200 text-slate-700 dark:bg-slate-800/80 dark:border-slate-700/60 dark:text-slate-300">
                        <span class="w-2 h-2 rounded-full bg-emerald-500 dark:bg-emerald-400 animate-pulse"></span>
                        <span class="text-xs font-semibold truncate max-w-[180px]">{{ $currentCompany->name }}</span>
                    </div>
                @endif

                @php
                    $unreadNotifCount = \Illuminate\Support\Facades\Schema::hasTable('crm_notifications')
                        ? \App\Models\CrmNotification::where('user_id', Auth::id())->whereNull('read_at')->count()
                        : 0;
                @endphp
                <a href="{{ route('tasks.notifications') }}" class="relative p-1.5 sm:p-2 rounded-xl border transition flex items-center justify-center bg-slate-100 hover:bg-slate-200 text-slate-700 border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700" title="{{ __('Notifications') }}">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    @if($unreadNotifCount > 0)
                        <span class="absolute -top-1 -right-1 px-1.5 py-0.2 rounded-full bg-rose-500 text-white text-[10px] font-black leading-tight">
                            {{ $unreadNotifCount }}
                        </span>
                    @endif
                </a>

                <div class="flex items-center gap-1.5 sm:gap-3">
                    <div class="text-right hidden md:block">
                        <div class="text-xs font-bold text-slate-900 dark:text-white">{{ Auth::user()->name }}</div>
                        <div class="text-[10px] font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider">
                            {{ __(str_replace('_', ' ', $currentRole->name ?? 'Staff')) }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="px-2 sm:px-3 py-1 sm:py-1.5 text-[11px] sm:text-xs font-bold rounded-xl bg-slate-100 hover:bg-rose-50 hover:text-rose-600 hover:border-rose-300 text-slate-700 border border-slate-300 dark:bg-slate-800 dark:hover:bg-rose-950 dark:hover:text-white dark:hover:border-rose-700 dark:text-slate-300 dark:border-slate-700 transition whitespace-nowrap">
                            {{ __('Sign Out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="flex-1 flex overflow-hidden">
        <!-- Desktop Sidebar (Simplified 6 Main Sections) -->
        <aside class="hidden lg:flex lg:flex-col w-64 bg-white border-r border-slate-200 dark:bg-slate-900 dark:border-slate-800 flex-shrink-0 transition-colors duration-200">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800/60">
                <span class="text-[11px] font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Daily Operations') }}</span>
            </div>

            <nav class="flex-1 p-3 space-y-1.5 overflow-y-auto">
                <!-- 1. Home -->
                <a href="{{ route('dashboard') }}" class="group flex items-start gap-3.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('dashboard') || request()->routeIs('home') ? 'bg-amber-500/10 text-amber-600 border border-amber-500/30 font-bold dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-medium dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-amber-500 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    <div>
                        <div class="text-sm leading-tight">{{ __('Home') }}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">{{ __('Daily Overview') }}</div>
                    </div>
                </a>

                <!-- 2. Projects -->
                @can('viewAny', \App\Models\Project::class)
                <a href="{{ route('projects.index') }}" class="group flex items-start gap-3.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('projects.*') ? 'bg-amber-500/10 text-amber-600 border border-amber-500/30 font-bold dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-medium dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-sky-600 dark:text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                    <div>
                        <div class="text-sm leading-tight">{{ __('Projects') }}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">{{ __('Milestones & docs') }}</div>
                    </div>
                </a>
                @endcan

                <!-- 3. Sites -->
                @can('viewAny', \App\Models\Site::class)
                <a href="{{ route('sites.index') }}" class="group flex items-start gap-3.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('sites.*') ? 'bg-amber-500/10 text-amber-600 border border-amber-500/30 font-bold dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-medium dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    <div>
                        <div class="text-sm leading-tight">{{ __('Sites') }}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">{{ __('Locations & projects') }}</div>
                    </div>
                </a>
                @endcan

                <!-- 2. Workers -->
                <a href="{{ route('workforce.index') }}" class="group flex items-start gap-3.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('workforce.*') ? 'bg-amber-500/10 text-amber-600 border border-amber-500/30 font-bold dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-medium dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-emerald-600 dark:text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                    <div>
                        <div class="text-sm leading-tight">{{ __('Workers') }}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">{{ __('People and details') }}</div>
                    </div>
                </a>

                <!-- 3. Attendance -->
                <a href="{{ route('attendance.muster') }}" class="group flex items-start gap-3.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('attendance.*') ? 'bg-amber-500/10 text-amber-600 border border-amber-500/30 font-bold dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-medium dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    <div>
                        <div class="text-sm leading-tight">{{ __('Attendance') }}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">{{ __('Daily muster & shifts') }}</div>
                    </div>
                </a>

                <!-- 4. Work Orders -->
                <a href="{{ route('work_orders.index') }}" class="group flex items-start gap-3.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('work_orders.*') ? 'bg-amber-500/10 text-amber-600 border border-amber-500/30 font-bold dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-medium dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    <div>
                        <div class="text-sm leading-tight">{{ __('Work Orders') }}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">{{ __('Site job approvals') }}</div>
                    </div>
                </a>

                <!-- 4b. Site Tasks & Notes -->
                @can('viewAny', \App\Models\Task::class)
                <a href="{{ route('tasks.index') }}" class="group flex items-start gap-3.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('tasks.*') ? 'bg-amber-500/10 text-amber-600 border border-amber-500/30 font-bold dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-medium dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"/></svg>
                    <div>
                        <div class="text-sm leading-tight">{{ __('Site Tasks & Notes') }}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">{{ __('Kanban board & logs') }}</div>
                    </div>
                </a>
                @endcan

                <!-- 5. Materials & Deliveries -->
                <a href="{{ route('procurement.requests') }}" class="group flex items-start gap-3.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('procurement.*') || request()->routeIs('inventory.*') ? 'bg-amber-500/10 text-amber-600 border border-amber-500/30 font-bold dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-medium dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-cyan-600 dark:text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    <div>
                        <div class="text-sm leading-tight">{{ __('Materials & Deliveries') }}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">{{ __('Requests, orders & stock') }}</div>
                    </div>
                </a>

                <!-- 6. Reports -->
                <a href="{{ route('reports.workforce') }}" class="group flex items-start gap-3.5 px-3 py-2.5 rounded-xl transition {{ request()->routeIs('reports.*') || request()->routeIs('attendance.register') ? 'bg-amber-500/10 text-amber-600 border border-amber-500/30 font-bold dark:bg-amber-500/10 dark:text-amber-400 dark:border-amber-500/30' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900 font-medium dark:text-slate-300 dark:hover:bg-slate-800/60 dark:hover:text-white' }}">
                    <svg class="w-5 h-5 flex-shrink-0 mt-0.5 text-violet-600 dark:text-violet-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    <div>
                        <div class="text-sm leading-tight">{{ __('Attendance Register') }}</div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-normal mt-0.5">{{ __('Date & month present view') }}</div>
                    </div>
                </a>
            </nav>

            <div class="p-4 border-t border-slate-200 dark:border-slate-800/60 bg-slate-50 dark:bg-slate-950/50">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-full bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300 flex items-center justify-center font-bold text-xs uppercase">
                        {{ substr(Auth::user()->name, 0, 2) }}
                    </div>
                    <div class="overflow-hidden">
                        <div class="text-xs font-bold text-slate-900 dark:text-slate-200 truncate">{{ Auth::user()->name }}</div>
                        <div class="text-[10px] text-slate-500 dark:text-slate-400 truncate">{{ Auth::user()->email }}</div>
                    </div>
                </div>
            </div>
        </aside>

        <!-- Mobile Drawer Navigation -->
        <div x-show="mobileMenu" class="fixed inset-0 z-50 lg:hidden flex" style="display: none;">
            <div @click="mobileMenu = false" class="fixed inset-0 bg-slate-950/70 backdrop-blur-sm transition-opacity"></div>

            <div class="relative w-72 bg-white text-slate-800 border-r border-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:border-slate-800 p-5 flex flex-col justify-between shadow-2xl">
                <div>
                    <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-xl bg-amber-500 flex items-center justify-center font-black text-slate-950">N</div>
                            <span class="font-extrabold text-base text-slate-900 dark:text-white">NSD CRM</span>
                        </div>
                        <button type="button" @click="mobileMenu = false" class="p-1 rounded-lg text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    <nav class="mt-4 space-y-2">
                        <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl font-bold {{ request()->routeIs('dashboard') ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            <span>{{ __('Home') }}</span>
                        </a>
                        @can('viewAny', \App\Models\Project::class)
                        <a href="{{ route('projects.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl font-bold {{ request()->routeIs('projects.*') ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            <span>{{ __('Projects') }}</span>
                        </a>
                        @endcan
                        @can('viewAny', \App\Models\Site::class)
                        <a href="{{ route('sites.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl font-bold {{ request()->routeIs('sites.*') ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            <span>{{ __('Sites') }}</span>
                        </a>
                        @endcan
                        <a href="{{ route('workforce.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl font-bold {{ request()->routeIs('workforce.*') ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            <span>{{ __('Workers') }}</span>
                        </a>
                        <a href="{{ route('attendance.muster') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl font-bold {{ request()->routeIs('attendance.*') ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            <span>{{ __('Attendance') }}</span>
                        </a>
                        <a href="{{ route('work_orders.index') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl font-bold {{ request()->routeIs('work_orders.*') ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            <span>{{ __('Work Orders') }}</span>
                        </a>
                        <a href="{{ route('procurement.requests') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl font-bold {{ request()->routeIs('procurement.*') || request()->routeIs('inventory.*') ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            <span>{{ __('Materials') }}</span>
                        </a>
                        <a href="{{ route('reports.workforce') }}" class="flex items-center gap-3 px-3 py-3 rounded-xl font-bold {{ request()->routeIs('reports.*') ? 'bg-amber-500 text-slate-950 shadow' : 'text-slate-700 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            <span>{{ __('Reports') }}</span>
                        </a>
                    </nav>
                </div>

                <div class="pt-4 border-t border-slate-200 dark:border-slate-800 space-y-3">
                    <div class="text-xs text-slate-600 dark:text-slate-400">{{ Auth::user()->name }}</div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full py-2.5 text-center text-xs font-bold rounded-xl bg-slate-100 hover:bg-rose-50 hover:text-rose-600 text-slate-700 border border-slate-300 dark:bg-slate-800 dark:hover:bg-rose-950 dark:hover:text-white dark:text-slate-300 dark:border-slate-700 transition">
                            {{ __('Sign Out') }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto bg-slate-100 dark:bg-slate-950 p-4 sm:p-6 lg:p-8 pb-24 sm:pb-24 lg:pb-8 transition-colors duration-200">
            <div class="max-w-7xl mx-auto space-y-6">
                <!-- Flash Messages -->
                @if(session('success'))
                    <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-300 text-emerald-800 dark:bg-emerald-950/80 dark:border-emerald-600/50 dark:text-emerald-200 flex items-start justify-between shadow-sm">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-sm font-semibold">{{ session('success') }}</span>
                        </div>
                    </div>
                @endif

                @if(session('error'))
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-300 text-rose-800 dark:bg-rose-950/80 dark:border-rose-600/50 dark:text-rose-200 flex items-start justify-between shadow-sm">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span class="text-sm font-semibold">{{ session('error') }}</span>
                        </div>
                    </div>
                @endif

                @if($errors->any())
                    <div class="p-4 rounded-xl bg-rose-50 border border-rose-300 text-rose-800 dark:bg-rose-950/80 dark:border-rose-600/50 dark:text-rose-200 shadow-sm">
                        <div class="flex items-center gap-2 font-bold text-sm mb-2 text-rose-700 dark:text-rose-300">
                            <svg class="w-5 h-5 text-rose-600 dark:text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <span>{{ __('Please correct the following errors:') }}</span>
                        </div>
                        <ul class="list-disc list-inside text-xs space-y-1 text-rose-800 dark:text-rose-200">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </div>
        </main>
    </div>

    <!-- Mobile Bottom Navigation Bar (Thumb Friendly on Phones) -->
    <nav class="lg:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 border-t border-slate-200 text-slate-600 dark:bg-slate-900/95 dark:border-slate-800 dark:text-slate-400 backdrop-blur-md px-2 py-2 flex items-center justify-around shadow-2xl transition-colors duration-200">
        <a href="{{ route('dashboard') }}" class="flex flex-col items-center gap-1 py-1 px-1.5 rounded-xl transition {{ request()->routeIs('dashboard') ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
            <span class="text-[10px]">{{ __('Home') }}</span>
        </a>
        @can('viewAny', \App\Models\Site::class)
        <a href="{{ route('sites.index') }}" class="flex flex-col items-center gap-1 py-1 px-1.5 rounded-xl transition {{ request()->routeIs('sites.*') ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="text-[10px]">{{ __('Sites') }}</span>
        </a>
        @endcan
        <a href="{{ route('workforce.index') }}" class="flex flex-col items-center gap-1 py-1 px-1.5 rounded-xl transition {{ request()->routeIs('workforce.*') ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
            <span class="text-[10px]">{{ __('Workers') }}</span>
        </a>
        <a href="{{ route('attendance.muster') }}" class="flex flex-col items-center gap-1 py-1 px-1.5 rounded-xl transition {{ request()->routeIs('attendance.*') ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
            <span class="text-[10px]">{{ __('Attendance') }}</span>
        </a>
        <a href="{{ route('work_orders.index') }}" class="flex flex-col items-center gap-1 py-1 px-1.5 rounded-xl transition {{ request()->routeIs('work_orders.*') ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
            <span class="text-[10px]">{{ __('Work Orders') }}</span>
        </a>
        <a href="{{ route('procurement.requests') }}" class="flex flex-col items-center gap-1 py-1 px-1.5 rounded-xl transition {{ request()->routeIs('procurement.*') || request()->routeIs('inventory.*') ? 'text-amber-600 dark:text-amber-400 font-bold' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white' }}">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <span class="text-[10px]">{{ __('Materials') }}</span>
        </a>
    </nav>
</body>
</html>

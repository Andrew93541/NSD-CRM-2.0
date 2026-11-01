@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Site Attendance Kiosk Terminals') }}</h1>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                {{ __('Launch site-bound tablet/gate Kiosk terminals with dedicated rate limiting and stateless QR / Worker ID + PIN punch recording.') }}
            </p>
        </div>
        <a href="{{ route('attendance.muster') }}" class="px-4 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition">
            &larr; {{ __('Back to Muster Roll') }}
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @forelse($sites as $site)
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <span class="px-2.5 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-400 font-mono text-xs font-bold">{{ $site->code }}</span>
                        <h2 class="text-lg font-extrabold text-slate-900 dark:text-white mt-1">{{ $site->name }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $site->formattedAddress() ?: __('Active Site') }}</p>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold uppercase">
                        {{ __('Stateless Kiosk Ready') }}
                    </span>
                </div>

                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-1">
                    <div class="text-[10px] font-bold uppercase text-slate-500">{{ __('Site-Bound Kiosk Device URL') }}</div>
                    <div class="font-mono text-xs text-slate-800 dark:text-slate-200 break-all select-all">{{ route('kiosk.terminal', $site->kiosk_token) }}</div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 pt-2">
                    <a href="{{ route('kiosk.terminal', $site->kiosk_token) }}" target="_blank" class="px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-lg shadow-amber-500/20 transition">
                        {{ __('Launch Site Kiosk Terminal') }} &nearr;
                    </a>

                    @if(auth()->user()->hasRole(['super_admin', 'company_admin', 'senior_manager', 'site_manager']))
                        <form method="POST" action="{{ route('attendance.kiosk.rotate', $site) }}" onsubmit="return confirm('Rotate Kiosk token for {{ $site->code }}? Existing gate tablets will need the new URL.');">
                            @csrf
                            <button type="submit" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-rose-500/15 text-slate-600 hover:text-rose-600 dark:bg-slate-800 dark:text-slate-300 text-xs font-bold border border-slate-200 dark:border-slate-700 transition">
                                &#x21bb; {{ __('Rotate Device Token') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-2 p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center text-sm text-slate-500">
                {{ __('No active sites assigned to your account.') }}
            </div>
        @endforelse
    </div>
</div>
@endsection


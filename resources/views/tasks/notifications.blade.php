@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('In-App Notifications Center') }}</h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                {{ __('Alerts for task assignments, status updates, attendance correction reviews, and site anomalies.') }}
            </p>
        </div>
        <form method="POST" action="{{ route('tasks.notifications.read_all') }}">
            @csrf
            <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                {{ __('Mark All as Read') }}
            </button>
        </form>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 divide-y divide-slate-200 dark:divide-slate-800 shadow-sm">
        @forelse($notifications as $notif)
            <div class="p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 {{ is_null($notif->read_at) ? 'bg-amber-500/5' : '' }}">
                <div class="space-y-1">
                    <div class="flex items-center gap-2">
                        @if(is_null($notif->read_at))
                            <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        @endif
                        <span class="font-bold text-sm text-slate-900 dark:text-white">{{ $notif->title }}</span>
                        @if($notif->site)
                            <span class="text-xs font-mono text-amber-600 dark:text-amber-400">({{ $notif->site->code }})</span>
                        @endif
                    </div>
                    <p class="text-xs text-slate-600 dark:text-slate-300">{{ $notif->message }}</p>
                    <div class="text-[11px] font-mono text-slate-400">{{ $notif->created_at->format('d M Y, H:i') }}</div>
                </div>

                <form method="POST" action="{{ route('tasks.notifications.read', $notif) }}">
                    @csrf
                    <button type="submit" class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-800 dark:text-slate-200 transition">
                        {{ $notif->action_url ? __('Open & Mark Read') : __('Mark Read') }}
                    </button>
                </form>
            </div>
        @empty
            <div class="p-10 text-center text-xs text-slate-500">{{ __('You have no notifications.') }}</div>
        @endforelse
    </div>
</div>
@endsection


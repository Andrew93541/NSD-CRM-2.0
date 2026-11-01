<!DOCTYPE html>
<html lang="en" class="h-full antialiased overflow-x-hidden dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ __('Worker Self-Punch Portal') }} — NSD CRM 2.0</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-50 text-slate-800 dark:bg-slate-950 dark:text-slate-100 pb-12" x-data="{
    lat: '',
    lng: '',
    acc: '',
    geoStatus: 'Locating GPS...',
    init() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition((pos) => {
                this.lat = pos.coords.latitude.toFixed(7);
                this.lng = pos.coords.longitude.toFixed(7);
                this.acc = pos.coords.accuracy.toFixed(1);
                this.geoStatus = 'GPS Locked (' + this.lat + ', ' + this.lng + ')';
            }, () => {
                this.geoStatus = 'GPS not shared (Punch will be recorded with server time)';
            });
        } else {
            this.geoStatus = 'Geolocation unsupported';
        }
    }
}">
    <div class="max-w-lg mx-auto px-4 pt-6 space-y-6">
        <!-- Top Header -->
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex items-center justify-between shadow-sm">
            <div>
                <div class="text-xs font-mono font-bold text-emerald-600 dark:text-emerald-400">{{ $worker->code }}</div>
                <h1 class="text-lg font-extrabold text-slate-900 dark:text-white">{{ $worker->full_name }}</h1>
                <div class="text-xs text-slate-500">{{ $worker->trade_designation }} &bull; {{ $site?->name ?? __('Unassigned Site') }}</div>
            </div>
            <form method="POST" action="{{ route('worker.logout') }}">
                @csrf
                <button type="submit" class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 transition">
                    {{ __('Sign Out') }}
                </button>
            </form>
        </div>

        @if(session('success'))
            <div class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-700 dark:text-emerald-300 text-xs font-bold">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-700 dark:text-rose-300 text-xs font-bold">
                {{ $errors->first() }}
            </div>
        @endif

        <!-- Big Punch In / Out Card -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-center space-y-4 shadow-lg">
            <div class="text-xs font-bold uppercase tracking-wider text-slate-500">
                {{ __('Server Time Authority (IST)') }} &bull; {{ now()->format('d M Y, h:i:s A (H:i)') }}
            </div>

            @if($site)
                <div class="text-xs text-slate-600 dark:text-slate-400">
                    {{ __('Assigned Site') }}: <strong class="text-slate-900 dark:text-white">{{ $site->code }} — {{ $site->name }}</strong>
                    ({{ __('Geofence Radius') }}: {{ $site->geofence_radius_m ?? 200 }}m)
                </div>
            @endif

            <div class="text-[11px] font-mono px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300" x-text="geoStatus"></div>

            <form method="POST" action="{{ route('worker.punch.store') }}">
                @csrf
                <input type="hidden" name="site_id" value="{{ $site?->id }}">
                <input type="hidden" name="punch_type" value="toggle">
                <input type="hidden" name="latitude" :value="lat">
                <input type="hidden" name="longitude" :value="lng">
                <input type="hidden" name="accuracy_m" :value="acc">

                @if($openAttendance)
                    <button type="submit" class="w-full py-6 rounded-2xl bg-gradient-to-r from-rose-500 to-rose-600 hover:from-rose-400 hover:to-rose-500 text-white font-black text-xl shadow-xl shadow-rose-500/25 transition active:scale-[0.99]">
                        {{ __('PUNCH OUT') }} ({{ __('Clocked in at') }} {{ \Carbon\Carbon::parse($openAttendance->clock_in)->format('h:i A') }})
                    </button>
                @else
                    <button type="submit" class="w-full py-6 rounded-2xl bg-gradient-to-r from-emerald-400 to-emerald-500 hover:from-emerald-300 hover:to-emerald-400 text-slate-950 font-black text-xl shadow-xl shadow-emerald-500/25 transition active:scale-[0.99]">
                        {{ __('PUNCH IN NOW') }}
                    </button>
                @endif
            </form>
        </div>

        <!-- Leave Request Card -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Request Leave') }}</h2>
            <form method="POST" action="{{ route('worker.leave.store') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">{{ __('Leave Type') }}</label>
                        <select name="leave_type_name" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                            <option value="Paid Leave">{{ __('Paid Leave') }}</option>
                            <option value="Sick Leave">{{ __('Sick Leave') }}</option>
                            <option value="Casual Leave">{{ __('Casual Leave') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">{{ __('Start Date') }}</label>
                        <input type="date" name="start_date" required value="{{ now()->addDay()->format('Y-m-d') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-500 uppercase mb-1">{{ __('End Date') }}</label>
                        <input type="date" name="end_date" required value="{{ now()->addDay()->format('Y-m-d') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>
                <div>
                    <input type="text" name="reason" required placeholder="{{ __('Reason for leave request...') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs text-slate-900 dark:text-white">
                </div>
                <button type="submit" class="w-full py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                    {{ __('Submit Leave Request') }}
                </button>
            </form>
        </div>

        <!-- Recent Punches -->
        <div class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-sm">
            <h2 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Recent Server-Verified Punches') }}</h2>
            <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
                @forelse($recentPunches as $p)
                    <div class="py-2.5 flex items-center justify-between">
                        <div>
                            <span class="font-bold uppercase {{ $p->punch_type === 'in' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                {{ strtoupper($p->punch_type) }}
                            </span>
                            <span class="text-slate-600 dark:text-slate-300 ml-1">{{ $p->site?->code }}</span>
                            @if($p->within_geofence === false)
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-600 dark:text-amber-400 text-[10px]">{{ __('Out of Geofence') }}</span>
                            @elseif($p->within_geofence === true)
                                <span class="ml-1 px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px]">{{ __('In Geofence') }}</span>
                            @endif
                        </div>
                        <div class="font-mono text-slate-500">{{ $p->punched_at->format('d M, h:i:s A') }}</div>
                    </div>
                @empty
                    <div class="py-4 text-center text-slate-500">{{ __('No punches recorded yet.') }}</div>
                @endforelse
            </div>
        </div>
    </div>
</body>
</html>


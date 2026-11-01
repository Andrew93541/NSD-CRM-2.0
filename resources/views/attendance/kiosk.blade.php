<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Kiosk — {{ $site->code }} ({{ $site->name }})</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-950 text-white min-h-screen font-sans p-4 sm:p-8" x-data="{
    tab: 'qr',
    clock: '{{ now()->timezone(config('app.timezone', 'Asia/Kolkata'))->format('h:i:s A') }}',
    init() {
        setInterval(() => {
            const now = new Date();
            this.clock = now.toLocaleTimeString('en-IN', { timeZone: 'Asia/Kolkata', hour: '2-digit', minute: '2-digit', second: '2-digit', hour12: true });
        }, 1000);
    }
}">
    <div class="max-w-5xl mx-auto space-y-6">
        <!-- Top Kiosk Header -->
        <div class="p-6 rounded-2xl bg-slate-900 border border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-2xl">
            <div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 rounded bg-amber-500 text-slate-950 font-mono text-xs font-black uppercase">{{ $site->code }}</span>
                    <span class="text-xs font-bold uppercase tracking-widest text-amber-400">{{ $site->company?->name ?? 'NSD Construction' }} &bull; {{ __('GATE ATTENDANCE KIOSK') }}</span>
                </div>
                <h1 class="text-2xl font-black text-white mt-1">{{ $site->name }}</h1>
                <p class="text-xs text-slate-400">{{ __('Stateless Gate Terminal — Zero persistent worker session between scans') }}</p>
            </div>
            <div class="sm:text-right">
                <div class="text-3xl font-black font-mono text-amber-400" x-text="clock"></div>
                <div class="text-xs font-mono text-slate-400 mt-0.5">{{ now()->timezone(config('app.timezone', 'Asia/Kolkata'))->format('l, d M Y') }} (IST)</div>
            </div>
        </div>

        <!-- Flash Result Banner -->
        @if(session('kiosk_punch_result'))
            @php $res = session('kiosk_punch_result'); @endphp
            <div class="p-5 rounded-2xl border-2 {{ $res['punch_type'] === 'IN' ? 'bg-emerald-950/80 border-emerald-500 text-emerald-200' : 'bg-sky-950/80 border-sky-500 text-sky-200' }} flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-xl">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-wider {{ $res['punch_type'] === 'IN' ? 'text-emerald-400' : 'text-sky-400' }}">
                        &#x2713; {{ __('PUNCH RECORDED') }} — {{ __('CLOCK') }} {{ $res['punch_type'] }}
                    </div>
                    <div class="text-xl font-black text-white mt-1">{{ $res['worker_name'] }} <span class="font-mono text-sm text-amber-400">({{ $res['worker_code'] }})</span></div>
                    <div class="text-xs text-slate-300 mt-0.5">{{ $res['trade'] }}</div>
                </div>
                <div class="sm:text-right">
                    <div class="text-xs uppercase text-slate-400">{{ __('Verified Server Timestamp') }}</div>
                    <div class="text-lg font-mono font-black text-white">{{ $res['punched_at'] }}</div>
                </div>
            </div>
        @endif

        @if($errors->any())
            <div class="p-4 rounded-2xl bg-rose-950/80 border border-rose-500 text-rose-200 text-xs space-y-1">
                @foreach($errors->all() as $err)
                    <div class="font-bold">&bull; {{ $err }}</div>
                @endforeach
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Stateless Scan / PIN Form -->
            <div class="lg:col-span-7 p-6 rounded-2xl bg-slate-900 border border-slate-800 space-y-6 shadow-xl">
                <div class="flex rounded-xl bg-slate-950 p-1 border border-slate-800">
                    <button type="button" @click="tab = 'qr'" :class="tab === 'qr' ? 'bg-amber-500 text-slate-950 font-black' : 'text-slate-400 font-bold'" class="flex-1 py-2.5 rounded-lg text-xs uppercase transition">
                        1. {{ __('QR Badge Scan') }}
                    </button>
                    <button type="button" @click="tab = 'pin'" :class="tab === 'pin' ? 'bg-amber-500 text-slate-950 font-black' : 'text-slate-400 font-bold'" class="flex-1 py-2.5 rounded-lg text-xs uppercase transition">
                        2. {{ __('Worker ID + 6-Digit PIN') }}
                    </button>
                </div>

                <form method="POST" action="{{ route('kiosk.punch', $site->kiosk_token) }}" class="space-y-5" autocomplete="off">
                    @csrf
                    <input type="hidden" name="mode" :value="tab">

                    <div x-show="tab === 'qr'" class="space-y-3">
                        <label class="block text-xs font-extrabold text-slate-300 uppercase">{{ __('Scan Worker QR Badge or Barcode') }} *</label>
                        <input type="text" name="qr_token" autofocus placeholder="{{ __('Focus here and scan Worker QR Badge...') }}" class="w-full rounded-xl bg-slate-950 border-2 border-amber-500/60 focus:border-amber-400 p-4 text-base font-mono text-white placeholder-slate-500 outline-none">
                        <p class="text-[11px] text-slate-400">{{ __('Supports USB/Bluetooth 2D barcode scanners or pasting the worker QR token.') }}</p>
                    </div>

                    <div x-show="tab === 'pin'" x-cloak class="space-y-4">
                        <div>
                            <label class="block text-xs font-extrabold text-slate-300 uppercase mb-1">{{ __('Worker ID Code') }} *</label>
                            <input type="text" name="worker_code" placeholder="EMP-2026-0001" class="w-full rounded-xl bg-slate-950 border border-slate-700 focus:border-amber-500 p-3.5 text-base font-mono uppercase text-white outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-extrabold text-slate-300 uppercase mb-1">{{ __('6-Digit Worker PIN') }} *</label>
                            <input type="password" name="pin" maxlength="6" inputmode="numeric" placeholder="&bull;&bull;&bull;&bull;&bull;&bull;" class="w-full rounded-xl bg-slate-950 border border-slate-700 focus:border-amber-500 p-3.5 text-lg font-mono tracking-widest text-white outline-none">
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 pt-2">
                        <button type="submit" name="punch_type" value="toggle" class="col-span-3 sm:col-span-1 py-3.5 px-4 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-black text-xs uppercase shadow-lg shadow-amber-500/20 transition">
                            {{ __('Auto Punch (In/Out)') }}
                        </button>
                        <button type="submit" name="punch_type" value="in" class="py-3.5 px-4 rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs uppercase transition">
                            {{ __('Punch IN') }}
                        </button>
                        <button type="submit" name="punch_type" value="out" class="py-3.5 px-4 rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 font-black text-xs uppercase transition">
                            {{ __('Punch OUT') }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Right: Today's Live Kiosk Feed -->
            <div class="lg:col-span-5 p-6 rounded-2xl bg-slate-900 border border-slate-800 space-y-4 shadow-xl">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h2 class="text-xs font-extrabold uppercase tracking-wider text-slate-300">{{ __('Recent Site Punches Today') }}</h2>
                    <span class="text-[11px] font-mono text-amber-400">{{ $recentPunches->count() }} {{ __('shown') }}</span>
                </div>

                <div class="divide-y divide-slate-800 text-xs">
                    @forelse($recentPunches as $p)
                        <div class="py-2.5 flex items-center justify-between">
                            <div>
                                <div class="font-bold text-white">{{ $p->worker?->full_name }}</div>
                                <div class="font-mono text-[11px] text-slate-400">{{ $p->worker?->code }} &bull; {{ strtoupper($p->source) }}</div>
                            </div>
                            <div class="text-right">
                                <span class="px-2 py-0.5 rounded font-bold uppercase text-[10px] {{ $p->punch_type === 'in' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-sky-500/20 text-sky-400' }}">
                                    {{ strtoupper($p->punch_type) }}
                                </span>
                                <div class="font-mono text-[11px] text-slate-300 mt-0.5">
                                    {{ $p->punched_at->timezone(config('app.timezone', 'Asia/Kolkata'))->format('h:i:s A') }}
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="py-8 text-center text-slate-500">{{ __('No punches recorded at this site today yet.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</body>
</html>


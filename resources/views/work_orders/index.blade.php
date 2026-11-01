@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Work Orders Management') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Client-issued contract tasks and internal operations with 2-tier approval workflow.') }}</p>
        </div>
        <a href="{{ route('work_orders.create') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-sm shadow-lg shadow-amber-500/20 transition">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
            {{ __('Create Work Order') }}
        </a>
    </div>

    <!-- Filter Bar -->
    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
        <form method="GET" action="{{ route('work_orders.index') }}" class="grid grid-cols-1 sm:grid-cols-4 gap-3">
            <div>
                <select name="status" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    <option value="">{{ __('All Statuses') }}</option>
                    <option value="submitted" {{ request('status') == 'submitted' ? 'selected' : '' }}>{{ __('Pending Site Manager (Tier 1)') }}</option>
                    <option value="site_manager_approved" {{ request('status') == 'site_manager_approved' ? 'selected' : '' }}>{{ __('Pending Senior VP (Tier 2)') }}</option>
                    <option value="senior_manager_approved" {{ request('status') == 'senior_manager_approved' ? 'selected' : '' }}>{{ __('Authorized for Execution') }}</option>
                    <option value="rejected" {{ request('status') == 'rejected' ? 'selected' : '' }}>{{ __('Rejected') }}</option>
                </select>
            </div>
            <div>
                <select name="type" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    <option value="">{{ __('All Order Types') }}</option>
                    <option value="client_issued" {{ request('type') == 'client_issued' ? 'selected' : '' }}>{{ __('Client Issued') }}</option>
                    <option value="internal" {{ request('type') == 'internal' ? 'selected' : '' }}>{{ __('Internal Work') }}</option>
                </select>
            </div>
            <div>
                <select name="site_id" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    <option value="">{{ __('All Sites') }}</option>
                    @foreach($sites as $s)
                        <option value="{{ $s->id }}" {{ request('site_id') == $s->id ? 'selected' : '' }}>{{ $s->code }} — {{ $s->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-white text-xs font-bold transition">{{ __('Filter') }}</button>
                <a href="{{ route('work_orders.index') }}" class="py-2 px-3 rounded-lg bg-slate-50 hover:bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-950 dark:hover:bg-slate-800 dark:text-slate-400 dark:border-slate-800 text-xs font-bold transition">{{ __('Reset') }}</a>
            </div>
        </form>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Order # / PO #') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Type') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Title & Site') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Order Value (A)') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Order Consumption') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Workflow Stage') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/60">
                    @forelse($workOrders as $wo)
                        @php
                            $pct = $wo->consumptionPercent();
                            $warn = $wo->consumptionWarningLevel();
                            $barColor = match($warn) {
                                'cap_100' => 'bg-rose-500',
                                'warning_90' => 'bg-orange-500',
                                'warning_80' => 'bg-amber-500',
                                default => 'bg-emerald-500',
                            };
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                            <td class="py-3 px-4">
                                <div class="font-mono font-bold text-slate-900 dark:text-white">{{ $wo->order_number }}</div>
                                <div class="text-[11px] font-mono text-slate-500">
                                    @if($wo->po_number)
                                        PO #{{ $wo->po_number }} &bull;
                                    @endif
                                    Rev {{ $wo->current_revision ?? 0 }}
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase {{ $wo->order_type === 'client_issued' ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/30' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/30' }}">
                                    {{ $wo->order_type === 'client_issued' ? __('Client Issued') : __('Internal Work') }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $wo->title }}</div>
                                <div class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $wo->site->name ?? __('Project Wide') }}
                                    @if($wo->client)
                                        &bull; {{ $wo->client->name }}
                                    @endif
                                </div>
                            </td>
                            <td class="py-3 px-4 font-mono text-slate-700 dark:text-slate-200">
                                ₹{{ number_format((float) ($wo->base_order_value ?: $wo->estimated_amount), 2) }}
                            </td>
                            <td class="py-3 px-4">
                                <div class="w-32 space-y-1">
                                    <div class="flex items-center justify-between text-[11px] font-mono">
                                        <span class="font-bold">{{ number_format($pct, 1) }}%</span>
                                        <span class="text-slate-400">₹{{ number_format($wo->totalBilledAmount(), 0) }}</span>
                                    </div>
                                    <div class="w-full h-1.5 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                                        <div class="h-full {{ $barColor }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                @if($wo->status === 'submitted')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-ping"></span>
                                        {{ __('Pending Site Manager (Tier 1)') }}
                                    </span>
                                @elseif($wo->status === 'site_manager_approved')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/30">
                                        <span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>
                                        {{ __('Pending Senior VP (Tier 2)') }}
                                    </span>
                                @elseif($wo->status === 'senior_manager_approved')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                        {{ __('Authorized for Execution') }}
                                    </span>
                                @elseif($wo->status === 'rejected')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                        {{ __('Rejected') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <a href="{{ route('work_orders.show', $wo) }}" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-amber-400 dark:border-slate-700 transition">
                                    {{ __('Review') }} &rarr;
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">{{ __('No work orders match the filter.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="sm:hidden space-y-3 p-4">
            @forelse($workOrders as $wo)
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="font-mono font-bold text-slate-900 dark:text-white text-xs">{{ $wo->order_number }}</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $wo->order_type === 'client_issued' ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/30' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/30' }}">
                            {{ $wo->order_type === 'client_issued' ? __('Client Issued') : __('Internal Work') }}
                        </span>
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 dark:text-white text-sm">{{ $wo->title }}</div>
                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ $wo->site->name ?? __('Project Wide') }}</div>
                    </div>
                    <div class="flex items-center justify-between text-xs pt-1 border-t border-slate-200 dark:border-slate-800/60">
                        <span class="font-mono font-bold text-amber-600 dark:text-amber-400">₹{{ number_format($wo->estimated_amount, 2) }}</span>
                        <a href="{{ route('work_orders.show', $wo) }}" class="px-2.5 py-1 text-xs font-bold rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-amber-400 dark:border-slate-700">
                            {{ __('Review') }} &rarr;
                        </a>
                    </div>
                </div>
            @empty
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/50 border border-slate-200 dark:border-slate-800 text-center text-xs text-slate-500">
                    {{ __('No work orders found.') }}
                </div>
            @endforelse
        </div>

        @if($workOrders->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950">
                {{ $workOrders->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

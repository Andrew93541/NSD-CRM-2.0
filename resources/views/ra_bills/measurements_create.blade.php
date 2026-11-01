@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6" x-data="{
    itemStats: @js($itemStats),
    selectedItemId: '{{ $workOrder->items->first()?->id ?? '' }}',
    mode: 'dimensional',
    nos: 1,
    length: '',
    breadth: '',
    depth: '',
    directQty: '',
    holdQty: 0,
    computedQty() {
        const n = parseFloat(this.nos) || 1;
        if (this.mode === 'direct') {
            const d = parseFloat(this.directQty) || 0;
            return Math.round(n * d * 1000) / 1000;
        }
        const l = this.length !== '' ? (parseFloat(this.length) || 0) : 1;
        const b = this.breadth !== '' ? (parseFloat(this.breadth) || 0) : 1;
        const d = this.depth !== '' ? (parseFloat(this.depth) || 0) : 1;
        if (this.length === '' && this.breadth === '' && this.depth === '') {
            return 0;
        }
        return Math.round(n * l * b * d * 1000) / 1000;
    },
    effectiveQty() {
        const h = parseFloat(this.holdQty) || 0;
        return Math.max(0, Math.round((this.computedQty() - h) * 1000) / 1000);
    },
    currentStat() {
        return this.itemStats[this.selectedItemId] || { ordered_quantity: 0, billed_quantity: 0, unbilled_measured: 0, remaining_balance: 0, unit: 'M3', sr_no: '' };
    },
    projectedUsagePct() {
        const st = this.currentStat();
        if (!st.ordered_quantity || st.ordered_quantity <= 0) return 0;
        const total = st.billed_quantity + st.unbilled_measured + this.effectiveQty();
        return Math.min(100, Math.round((total / st.ordered_quantity) * 1000) / 10);
    },
    projectedExcess() {
        const st = this.currentStat();
        const totalClaimed = st.unbilled_measured + this.effectiveQty();
        return Math.max(0, Math.round((totalClaimed - st.remaining_balance) * 1000) / 1000);
    }
}">
    <!-- Header -->
    <div class="flex items-center justify-between gap-3 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded bg-amber-500/15 text-amber-600 dark:text-amber-400 border border-amber-500/30">
                {{ __('Mobile Field Measurement Sheet') }}
            </span>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white mt-1">
                {{ $workOrder->order_number }} &mdash; {{ $workOrder->title }}
            </h1>
            <p class="text-xs text-slate-500">{{ $workOrder->site?->name ?? $workOrder->job_location ?? __('Project Wide') }} &bull; PO #{{ $workOrder->po_number ?? '—' }}</p>
        </div>
        <a href="{{ route('work_orders.show', $workOrder) }}" class="px-3.5 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-200 transition">
            &larr; {{ __('Work Order') }}
        </a>
    </div>

    <!-- Mobile-First Entry Card -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-lg space-y-5">
        <form method="POST" action="{{ route('work_orders.measurements.store', $workOrder) }}" enctype="multipart/form-data" class="space-y-4">
            @csrf

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1.5">{{ __('1. Select Service Order Line Item (BOQ)') }} *</label>
                <select name="work_order_item_id" x-model="selectedItemId" required class="w-full min-h-11 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2.5 text-sm font-semibold text-slate-900 dark:text-white">
                    @foreach($workOrder->items as $item)
                        <option value="{{ $item->id }}">
                            {{ $item->sr_no }} — {{ $item->description }} ({{ $item->unit }} | Ordered: {{ number_format((float) $item->ordered_quantity, 3) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Live Item Balance & Consumption Indicator -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-2 text-xs">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <span class="text-slate-500 uppercase font-bold">{{ __('Ordered') }}:</span>
                        <span class="font-mono font-bold text-slate-900 dark:text-white" x-text="currentStat().ordered_quantity.toFixed(3) + ' ' + currentStat().unit"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 uppercase font-bold">{{ __('Approved Billed') }}:</span>
                        <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400" x-text="currentStat().billed_quantity.toFixed(3)"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 uppercase font-bold">{{ __('Remaining Balance') }}:</span>
                        <span class="font-mono font-extrabold text-amber-600 dark:text-amber-400" x-text="currentStat().remaining_balance.toFixed(3) + ' ' + currentStat().unit"></span>
                    </div>
                </div>
                <div class="w-full h-2.5 rounded-full bg-slate-200 dark:bg-slate-800 overflow-hidden">
                    <div class="h-full transition-all duration-300"
                         :class="projectedUsagePct() >= 100 ? 'bg-rose-500' : (projectedUsagePct() >= 90 ? 'bg-orange-500' : (projectedUsagePct() >= 80 ? 'bg-amber-500' : 'bg-emerald-500'))"
                         :style="`width: ${projectedUsagePct()}%`"></div>
                </div>
                <div class="flex items-center justify-between text-[11px]">
                    <span class="font-mono font-bold" x-text="'Projected Order Consumption: ' + projectedUsagePct() + '%'"></span>
                    <template x-if="projectedExcess() > 0">
                        <span class="font-mono font-extrabold text-rose-600 dark:text-rose-400" x-text="'Cap Warning: +' + projectedExcess().toFixed(3) + ' Excess Above WO Cap'"></span>
                    </template>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Measurement Date') }} *</label>
                    <input type="date" name="measured_on" value="{{ now()->format('Y-m-d') }}" required class="w-full min-h-11 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm text-slate-900 dark:text-white">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Chainage / Grid / Pit Location') }} *</label>
                    <input type="text" name="location_chainage" required placeholder="e.g. SMS-2 Grid B4-B8 / Ch 1+200" class="w-full min-h-11 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm text-slate-900 dark:text-white">
                </div>
            </div>

            <!-- Entry Mode Toggle (Dimensional L x B x D vs Direct Quantity) -->
            <div class="flex items-center gap-2 pt-1">
                <button type="button" @click="mode = 'dimensional'; directQty = ''" :class="mode === 'dimensional' ? 'bg-amber-500 text-slate-950 font-extrabold' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold'" class="flex-1 py-2.5 rounded-xl text-xs transition">
                    {{ __('Dimensional (Nos × L × B × D)') }}
                </button>
                <button type="button" @click="mode = 'direct'; length = ''; breadth = ''; depth = ''" :class="mode === 'direct' ? 'bg-amber-500 text-slate-950 font-extrabold' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 font-bold'" class="flex-1 py-2.5 rounded-xl text-xs transition">
                    {{ __('Direct Quantity Entry') }}
                </button>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" x-show="mode === 'dimensional'">
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Nos') }}</label>
                    <input type="number" step="0.001" min="0.001" name="nos" x-model="nos" class="w-full min-h-11 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Length (L)') }}</label>
                    <input type="number" step="0.001" min="0" name="length" x-model="length" placeholder="0.000" class="w-full min-h-11 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Breadth (B)') }}</label>
                    <input type="number" step="0.001" min="0" name="breadth" x-model="breadth" placeholder="0.000" class="w-full min-h-11 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-mono">
                </div>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Depth / Ht (D)') }}</label>
                    <input type="number" step="0.001" min="0" name="depth_height" x-model="depth" placeholder="0.000" class="w-full min-h-11 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" x-show="mode === 'direct'" x-cloak>
                <div>
                    <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Direct Measured Quantity') }}</label>
                    <input type="number" step="0.001" min="0" name="direct_quantity" x-model="directQty" placeholder="0.000" class="w-full min-h-11 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm font-mono">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
                <div>
                    <label class="block text-[11px] font-bold text-rose-600 dark:text-rose-400 uppercase mb-1">{{ __('Quality / Inspection Hold Qty') }}</label>
                    <input type="number" step="0.001" min="0" name="hold_quantity" x-model="holdQty" class="w-full min-h-11 rounded-xl bg-slate-50 dark:bg-slate-950 border border-rose-300 dark:border-rose-900/60 px-3 py-2 text-sm font-mono">
                </div>
                <div class="p-3 rounded-xl bg-slate-100 dark:bg-slate-800/70 text-xs">
                    <div class="text-slate-500 uppercase font-bold text-[10px]">{{ __('Computed Gross Qty') }}</div>
                    <div class="font-mono font-extrabold text-base text-slate-900 dark:text-white" x-text="computedQty().toFixed(3) + ' ' + currentStat().unit"></div>
                </div>
                <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-xs">
                    <div class="text-emerald-700 dark:text-emerald-400 uppercase font-bold text-[10px]">{{ __('Effective Claimed (Net of Hold)') }}</div>
                    <div class="font-mono font-extrabold text-base text-emerald-700 dark:text-emerald-400" x-text="effectiveQty().toFixed(3) + ' ' + currentStat().unit"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Joint Measurement Remarks') }}</label>
                    <input type="text" name="remarks" placeholder="Jointly measured with JSW Civil Engineer..." class="w-full min-h-11 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Joint Sheet / Site Photo (Private Vault)') }}</label>
                    <input type="file" name="photo" accept="image/*,.pdf" capture="environment" class="w-full min-h-11 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 p-2 text-xs">
                </div>
            </div>

            <button type="submit" class="w-full min-h-12 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-sm shadow-lg shadow-amber-500/20 transition">
                {{ __('Record Verified Site Measurement Entry') }}
            </button>
        </form>
    </div>
</div>
@endsection


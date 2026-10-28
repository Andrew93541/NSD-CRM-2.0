@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6" x-data="{
    isEmergency: false,
    items: [{ material_id: '', quantity: 1, purpose_notes: '' }],
    addItem() {
        this.items.push({ material_id: '', quantity: 1, purpose_notes: '' });
    },
    removeItem(idx) {
        if (this.items.length > 1) {
            this.items.splice(idx, 1);
        }
    }
}">
    <div class="pb-4 border-b border-slate-200 dark:border-slate-800">
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Raise Material Purchase Request') }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Requires manager approval before a purchase order can be issued.') }}</p>
    </div>

    <div class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl transition-colors duration-200">
        <form method="POST" action="{{ route('procurement.store_request') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Destination Site') }}</label>
                    <select name="site_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="">{{ __('Select Site (Direct Site Delivery)...') }}</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Or Central Warehouse') }}</label>
                    <select name="warehouse_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="">{{ __('Select Warehouse Storage...') }}</option>
                        @foreach($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->code }} — {{ $wh->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Controlled Emergency Exception -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
                <div class="flex items-center gap-3">
                    <input type="checkbox" name="is_emergency" value="1" x-model="isEmergency" id="is_emergency" class="h-4 w-4 rounded bg-white dark:bg-slate-900 border-slate-300 dark:border-slate-800 text-rose-500 focus:ring-rose-500">
                    <label for="is_emergency" class="text-xs font-bold text-rose-600 dark:text-rose-400 uppercase tracking-wider cursor-pointer">
                        {{ __('Mark as Controlled Emergency Purchase Exception') }}
                    </label>
                </div>

                <div x-show="isEmergency" class="space-y-2 pt-2 border-t border-slate-200 dark:border-slate-800">
                    <label class="block text-xs font-bold text-rose-700 dark:text-rose-300 uppercase">{{ __('Emergency Operational Justification') }} *</label>
                    <textarea name="emergency_justification" rows="2" placeholder="{{ __('Explain exact site stoppage risk or emergency condition...') }}" class="w-full rounded-xl bg-white dark:bg-slate-900 border border-rose-300 dark:border-rose-900/60 p-2.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 outline-none"></textarea>
                </div>
            </div>

            <!-- Request Items Repeater -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">{{ __('Required Materials') }}</label>
                    <button type="button" @click="addItem()" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:text-amber-500 flex items-center gap-1">
                        + {{ __('Add Material Line') }}
                    </button>
                </div>

                <template x-for="(item, idx) in items" :key="idx">
                    <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-12 gap-3 items-center">
                        <div class="sm:col-span-6">
                            <select :name="`items[${idx}][material_id]`" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                                <option value="">{{ __('Select Material Catalog Item...') }}</option>
                                @foreach($materials as $m)
                                    <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->unit_of_measure }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <input type="number" step="0.01" :name="`items[${idx}][quantity]`" required placeholder="{{ __('Quantity') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        </div>
                        <div class="sm:col-span-2">
                            <input type="text" :name="`items[${idx}][purpose_notes]`" placeholder="{{ __('Usage note...') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        </div>
                        <div class="sm:col-span-1 text-right">
                            <button type="button" @click="removeItem(idx)" x-show="items.length > 1" class="text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 p-1">
                                &times;
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <a href="{{ route('procurement.requests') }}" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700">{{ __('Cancel') }}</a>
                <button type="submit" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition">{{ __('Submit Requisition') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

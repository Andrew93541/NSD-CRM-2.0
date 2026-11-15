@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ showCreate: false, editId: null }">
    <!-- Materials & Stock Sub-Navigation -->
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
        <a href="{{ route('procurement.requests') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Purchase Requests') }}
        </a>
        <a href="{{ route('procurement.orders') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Purchase Orders & GRN') }}
        </a>
        <a href="{{ route('procurement.vendors') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Suppliers') }}
        </a>
        <a href="{{ route('inventory.stocks') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Warehouse & Yard Stock') }}
        </a>
        <a href="{{ route('inventory.transactions') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Stock Movement Ledger') }}
        </a>
        <a href="{{ route('inventory.warehouses') }}" class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 text-slate-950 shadow-sm">
            {{ __('Storage Locations') }}
        </a>
        <a href="{{ route('inventory.materials') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Material Catalog') }}
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Storage Locations') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Manage central warehouses and site storage yards where materials are received and issued.') }}</p>
        </div>
        @if($canManage)
            <button type="button" @click="showCreate = !showCreate" class="px-4 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-sm transition">
                + {{ __('Add Storage Location') }}
            </button>
        @endif
    </div>

    @if($canManage)
        <div x-show="showCreate" x-cloak class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm" x-data="{ locType: 'site_yard' }">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">{{ __('New Storage Location') }}</h2>
            <form method="POST" action="{{ route('inventory.warehouses.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Location Name') }} *</label>
                        <input type="text" name="name" required placeholder="e.g., Central Yard Panvel"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Location Code') }} *</label>
                        <input type="text" name="code" required placeholder="WH-MAIN-01"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm font-mono uppercase text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Location Type') }} *</label>
                        <select name="location_type" x-model="locType" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                            <option value="site_yard">{{ __('Site Storage Yard') }}</option>
                            <option value="central_warehouse">{{ __('Central Warehouse') }}</option>
                        </select>
                    </div>
                    <div x-show="locType === 'site_yard'">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Linked Site') }} *</label>
                        <select name="site_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                            <option value="">{{ __('Select Site...') }}</option>
                            @foreach($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->code }} — {{ $site->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Store In-Charge (Optional)') }}</label>
                        <select name="manager_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                            <option value="">{{ __('Unassigned') }}</option>
                            @foreach($managers as $mgr)
                                <option value="{{ $mgr->id }}">{{ $mgr->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Address / Gate Details') }}</label>
                        <input type="text" name="address"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm text-slate-900 dark:text-white">
                    </div>
                </div>
                <input type="hidden" name="is_active" value="1">
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" @click="showCreate = false" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300">{{ __('Cancel') }}</button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 hover:bg-amber-400 text-slate-950">{{ __('Save Location') }}</button>
                </div>
            </form>
        </div>
    @endif

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Location & Code') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Type') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Site') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Store In-Charge') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Tracked Items') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Status') }}</th>
                        @if($canManage)
                            <th class="py-3 px-4 font-semibold text-right">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($warehouses as $wh)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $wh->name }}</div>
                                <div class="text-xs font-mono text-slate-500">{{ $wh->code }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-semibold">
                                {{ $wh->location_type === 'central_warehouse' ? __('Central Warehouse') : __('Site Yard') }}
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                {{ $wh->site ? $wh->site->code . ' — ' . $wh->site->name : '—' }}
                            </td>
                            <td class="py-3.5 px-4 text-xs">{{ $wh->manager?->name ?? '—' }}</td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold">{{ $wh->stocks_count }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $wh->is_active ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                                    {{ $wh->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            @if($canManage)
                                <td class="py-3.5 px-4 text-right space-x-2 whitespace-nowrap">
                                    <button type="button" @click="editId = (editId === {{ $wh->id }} ? null : {{ $wh->id }})" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                                        {{ __('Edit') }}
                                    </button>
                                    @if($wh->stocks_count === 0)
                                        <form method="POST" action="{{ route('inventory.warehouses.destroy', $wh) }}" class="inline" onsubmit="return confirm('Delete empty storage location {{ addslashes($wh->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-bold text-rose-600 dark:text-rose-400 hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                        @if($canManage)
                            <tr x-show="editId === {{ $wh->id }}" x-cloak class="bg-slate-50/70 dark:bg-slate-950/70">
                                <td colspan="7" class="p-4">
                                    <form method="POST" action="{{ route('inventory.warehouses.update', $wh) }}" class="grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Name') }}</label>
                                            <input type="text" name="name" value="{{ $wh->name }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-1.5 text-xs">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Code') }}</label>
                                            <input type="text" name="code" value="{{ $wh->code }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-1.5 text-xs font-mono uppercase">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Type') }}</label>
                                            <select name="location_type" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-1.5 text-xs">
                                                <option value="site_yard" {{ $wh->location_type === 'site_yard' ? 'selected' : '' }}>{{ __('Site Yard') }}</option>
                                                <option value="central_warehouse" {{ $wh->location_type === 'central_warehouse' ? 'selected' : '' }}>{{ __('Central Warehouse') }}</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Site') }}</label>
                                            <select name="site_id" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-1.5 text-xs">
                                                <option value="">{{ __('None (Central)') }}</option>
                                                @foreach($sites as $site)
                                                    <option value="{{ $site->id }}" {{ (int) $wh->site_id === (int) $site->id ? 'selected' : '' }}>{{ $site->code }} — {{ $site->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="inline-flex items-center gap-2 text-xs font-bold py-2">
                                                <input type="checkbox" name="is_active" value="1" {{ $wh->is_active ? 'checked' : '' }} class="rounded border-slate-300 text-amber-500">
                                                <span>{{ __('Active') }}</span>
                                            </label>
                                        </div>
                                        <div class="flex items-center justify-end gap-2">
                                            <input type="hidden" name="manager_id" value="{{ $wh->manager_id }}">
                                            <input type="hidden" name="address" value="{{ $wh->address }}">
                                            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-amber-500 text-slate-950 text-xs font-extrabold">{{ __('Update') }}</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 7 : 6 }}" class="py-12 px-4 text-center">
                                <div class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ __('No storage locations added yet') }}</div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Add a central warehouse or site yard before receiving delivered materials.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

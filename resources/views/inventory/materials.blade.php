@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ showAddMaterial: false, showCategories: false, editMaterialId: null }">
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
        <a href="{{ route('inventory.warehouses') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Storage Locations') }}
        </a>
        <a href="{{ route('inventory.materials') }}" class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 text-slate-950 shadow-sm">
            {{ __('Material Catalog') }}
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Material Catalog') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Standardized construction materials, measurement units, and low-stock alert levels.') }}</p>
        </div>
        @if($canManage)
            <div class="flex items-center gap-2">
                <button type="button" @click="showCategories = !showCategories" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition">
                    {{ __('Categories') }} ({{ $categories->count() }})
                </button>
                <button type="button" @click="showAddMaterial = !showAddMaterial" class="px-4 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-sm transition">
                    + {{ __('Add Material') }}
                </button>
            </div>
        @endif
    </div>

    @if($canManage)
        <!-- Expandable Categories Panel -->
        <div x-show="showCategories" x-cloak class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('Material Categories') }}</h2>
                <button type="button" @click="showCategories = false" class="text-xs font-semibold text-slate-500 hover:text-slate-800 dark:hover:text-white">{{ __('Close') }}</button>
            </div>
            <form method="POST" action="{{ route('inventory.categories.store') }}" class="flex flex-col sm:flex-row gap-3">
                @csrf
                <input type="text" name="name" required placeholder="{{ __('Category Name (e.g., Cement & Concrete)') }}"
                    class="flex-1 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs">
                <input type="text" name="code" required placeholder="CAT-CEM"
                    class="sm:w-44 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs font-mono uppercase">
                <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 text-slate-950 text-xs font-extrabold">{{ __('Add Category') }}</button>
            </form>
            @if($categories->isNotEmpty())
                <div class="flex flex-wrap gap-2 pt-2">
                    @foreach($categories as $cat)
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-100 dark:bg-slate-800 text-xs">
                            <span class="font-bold text-slate-800 dark:text-slate-200">{{ $cat->name }}</span>
                            <span class="font-mono text-slate-500">({{ $cat->code }} · {{ $cat->materials_count }})</span>
                            @if($cat->materials_count === 0)
                                <form method="POST" action="{{ route('inventory.categories.destroy', $cat) }}" class="inline" onsubmit="return confirm('Delete category {{ addslashes($cat->name) }}?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-rose-600 dark:text-rose-400 font-bold hover:underline">×</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Expandable Add Material Form -->
        <div x-show="showAddMaterial" x-cloak class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">{{ __('Add Material to Catalog') }}</h2>
            @if($categories->isEmpty())
                <p class="text-xs text-amber-700 dark:text-amber-300 font-semibold">{{ __('Please create at least one Material Category first using the Categories button above.') }}</p>
            @else
                <form method="POST" action="{{ route('inventory.materials.store') }}" class="space-y-4">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-bold mb-1">{{ __('Category') }} *</label>
                            <select name="category_id" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->code }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1">{{ __('Material Name') }} *</label>
                            <input type="text" name="name" required placeholder="OPC 53 Grade Cement (50kg Bag)"
                                class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1">{{ __('Item Code') }} *</label>
                            <input type="text" name="code" required placeholder="MAT-CEM-53"
                                class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm font-mono uppercase">
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1">{{ __('Unit of Measure (UOM)') }} *</label>
                            <input type="text" name="unit_of_measure" required placeholder="Bags / MT / Cubic Meter / Nos"
                                class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1">{{ __('Minimum Alert Qty') }} *</label>
                            <input type="number" step="0.01" min="0" name="minimum_stock_level" value="0" required
                                class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm font-mono">
                        </div>
                        <div>
                            <label class="block text-xs font-bold mb-1">{{ __('Reference Unit Cost (₹)') }} *</label>
                            <input type="number" step="0.01" min="0" name="standard_unit_cost" value="0" required
                                class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm font-mono">
                        </div>
                    </div>
                    <input type="hidden" name="is_active" value="1">
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" @click="showAddMaterial = false" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800">{{ __('Cancel') }}</button>
                        <button type="submit" class="px-5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 text-slate-950">{{ __('Save Material') }}</button>
                    </div>
                </form>
            @endif
        </div>
    @endif

    <!-- Search & Category Filter -->
    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
        <form method="GET" action="{{ route('inventory.materials') }}" class="flex flex-col sm:flex-row gap-3">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="{{ __('Search by material name or code...') }}"
                class="flex-1 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs">
            <select name="category_id" class="sm:w-60 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs">
                <option value="">{{ __('All Categories') }}</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat->id }}" {{ (string) request('category_id') === (string) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                @endforeach
            </select>
            <button type="submit" class="px-4 py-2 rounded-lg bg-slate-100 dark:bg-slate-800 text-xs font-bold">{{ __('Filter') }}</button>
        </form>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Material & Code') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Category') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('UOM') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Min Alert Qty') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Std Cost (₹)') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Status') }}</th>
                        @if($canManage)
                            <th class="py-3 px-4 font-semibold text-right">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($materials as $mat)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $mat->name }}</div>
                                <div class="text-xs font-mono text-slate-500">{{ $mat->code }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs">{{ $mat->category?->name ?? '—' }}</td>
                            <td class="py-3.5 px-4 text-xs font-mono">{{ $mat->unit_of_measure }}</td>
                            <td class="py-3.5 px-4 text-right font-mono">{{ number_format((float) $mat->minimum_stock_level, 2) }}</td>
                            <td class="py-3.5 px-4 text-right font-mono">₹{{ number_format((float) $mat->standard_unit_cost, 2) }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $mat->is_active ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                                    {{ $mat->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            @if($canManage)
                                <td class="py-3.5 px-4 text-right space-x-2 whitespace-nowrap">
                                    <button type="button" @click="editMaterialId = (editMaterialId === {{ $mat->id }} ? null : {{ $mat->id }})" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                                        {{ __('Edit') }}
                                    </button>
                                    @if($mat->stocks_count === 0)
                                        <form method="POST" action="{{ route('inventory.materials.destroy', $mat) }}" class="inline" onsubmit="return confirm('Delete material {{ addslashes($mat->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-bold text-rose-600 dark:text-rose-400 hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                        @if($canManage)
                            <tr x-show="editMaterialId === {{ $mat->id }}" x-cloak class="bg-slate-50/70 dark:bg-slate-950/70">
                                <td colspan="7" class="p-4">
                                    <form method="POST" action="{{ route('inventory.materials.update', $mat) }}" class="grid grid-cols-1 md:grid-cols-7 gap-2.5 items-end">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Category') }}</label>
                                            <select name="category_id" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs">
                                                @foreach($categories as $cat)
                                                    <option value="{{ $cat->id }}" {{ (int) $mat->category_id === (int) $cat->id ? 'selected' : '' }}>{{ $cat->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Name') }}</label>
                                            <input type="text" name="name" value="{{ $mat->name }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Code') }}</label>
                                            <input type="text" name="code" value="{{ $mat->code }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs font-mono uppercase">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('UOM') }}</label>
                                            <input type="text" name="unit_of_measure" value="{{ $mat->unit_of_measure }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Min Alert') }}</label>
                                            <input type="number" step="0.01" min="0" name="minimum_stock_level" value="{{ $mat->minimum_stock_level }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs font-mono">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Std Cost') }}</label>
                                            <input type="number" step="0.01" min="0" name="standard_unit_cost" value="{{ $mat->standard_unit_cost }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs font-mono">
                                        </div>
                                        <div class="flex items-center justify-end gap-2">
                                            <label class="inline-flex items-center gap-1 text-xs font-bold">
                                                <input type="checkbox" name="is_active" value="1" {{ $mat->is_active ? 'checked' : '' }} class="rounded border-slate-300 text-amber-500">
                                                <span>{{ __('Active') }}</span>
                                            </label>
                                            <button type="submit" class="px-3 py-1.5 rounded-lg bg-amber-500 text-slate-950 text-xs font-extrabold">{{ __('Save') }}</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 7 : 6 }}" class="py-12 px-4 text-center">
                                <div class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ __('No catalog materials found') }}</div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Create a material category and add standard construction items to start raising purchase requests.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($materials->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $materials->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

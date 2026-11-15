@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ showMovementPanel: {{ $errors->any() ? 'true' : 'false' }}, movementTab: 'issue' }">
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
        <a href="{{ route('inventory.stocks') }}" class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 text-slate-950 shadow-sm">
            {{ __('Warehouse & Yard Stock') }}
        </a>
        <a href="{{ route('inventory.transactions') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Stock Movement Ledger') }}
        </a>
        <a href="{{ route('inventory.warehouses') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Storage Locations') }}
        </a>
        <a href="{{ route('inventory.materials') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Material Catalog') }}
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Warehouse & Yard Stock') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Current material quantities across central warehouses and site storage yards.') }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            @if(!empty($canRequestPr))
                <a href="{{ route('procurement.create_request') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-white dark:bg-slate-900 text-slate-700 dark:text-slate-200 border border-slate-300 dark:border-slate-700 hover:border-amber-500 transition">
                    + {{ __('Request Materials') }}
                </a>
            @endif
            @if(!empty($canIssue))
                <button type="button" @click="showMovementPanel = !showMovementPanel" class="px-4 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-sm transition">
                    <span x-show="!showMovementPanel">+ {{ __('Record Stock Movement') }}</span>
                    <span x-show="showMovementPanel" x-cloak>{{ __('Close Movement Form') }}</span>
                </button>
            @endif
        </div>
    </div>

    @if(!empty($pendingAdjustmentsCount) && $pendingAdjustmentsCount > 0)
        <div class="p-3.5 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 flex items-center justify-between gap-3">
            <div class="text-xs font-semibold text-amber-900 dark:text-amber-200">
                {{ $pendingAdjustmentsCount }} {{ __('stock adjustment request(s) awaiting manager verification.') }}
            </div>
            <a href="{{ route('inventory.transactions', ['type' => 'stock_adjustment', 'status' => 'pending_confirmation']) }}" class="px-3 py-1.5 rounded-lg text-xs font-extrabold bg-amber-500 text-slate-950 hover:bg-amber-400 transition">
                {{ __('Review Adjustments') }}
            </a>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-800 text-xs text-rose-700 dark:text-rose-300 space-y-1">
            @foreach($errors->all() as $err)
                <div class="font-semibold">&bull; {{ $err }}</div>
            @endforeach
        </div>
    @endif

    @if(!empty($canIssue))
        <!-- Expandable Stock Movement Panel (Progressive Disclosure) -->
        <div x-show="showMovementPanel" x-cloak class="p-5 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
            <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
                <button type="button" @click="movementTab = 'issue'" :class="movementTab === 'issue' ? 'bg-slate-900 text-white dark:bg-amber-500 dark:text-slate-950' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition">
                    1. {{ __('Issue to Site') }}
                </button>
                <button type="button" @click="movementTab = 'transfer'" :class="movementTab === 'transfer' ? 'bg-slate-900 text-white dark:bg-amber-500 dark:text-slate-950' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition">
                    2. {{ __('Transfer Between Yards') }}
                </button>
                <button type="button" @click="movementTab = 'return'" :class="movementTab === 'return' ? 'bg-slate-900 text-white dark:bg-amber-500 dark:text-slate-950' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition">
                    3. {{ __('Return from Site') }}
                </button>
                <button type="button" @click="movementTab = 'adjustment'" :class="movementTab === 'adjustment' ? 'bg-slate-900 text-white dark:bg-amber-500 dark:text-slate-950' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300'" class="px-3.5 py-1.5 rounded-lg text-xs font-bold transition">
                    4. {{ __('Stock Adjustment') }}
                </button>
            </div>

            <!-- 1. Issue to Site -->
            <form x-show="movementTab === 'issue'" method="POST" action="{{ route('inventory.movements.issue') }}" x-data="{ submitting: false }" @submit="submitting = true" class="space-y-4">
                @csrf
                <input type="hidden" name="submission_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('From Storage Location') }} *</label>
                        <select name="source_warehouse_id" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select source location') }}</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('To Construction Site') }} *</label>
                        <select name="site_id" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select destination site') }}</option>
                            @foreach($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->name }} ({{ $site->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Work Order (Optional)') }}</label>
                        <select name="work_order_id" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('General site consumption') }}</option>
                            @foreach($workOrders as $wo)
                                <option value="{{ $wo->id }}">{{ $wo->order_number }} — {{ $wo->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Issue Slip / Reference #') }}</label>
                        <input type="text" name="challan_or_invoice_no" placeholder="e.g. ISS-SLIP-101" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Material Item') }} *</label>
                        <select name="items[0][material_id]" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select material') }}</option>
                            @foreach($materials as $mat)
                                <option value="{{ $mat->id }}">{{ $mat->name }} ({{ $mat->unit_of_measure }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Quantity to Issue') }} *</label>
                        <input type="number" step="0.01" min="0.01" name="items[0][quantity]" required placeholder="0.00" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Purpose / Notes') }}</label>
                        <input type="text" name="notes" placeholder="e.g. Pier cap concreting" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" :disabled="submitting" class="px-4 py-2 rounded-xl text-xs font-extrabold bg-amber-500 hover:bg-amber-400 text-slate-950 disabled:opacity-50 transition">
                        {{ __('Confirm Stock Issue') }}
                    </button>
                </div>
            </form>

            <!-- 2. Transfer Between Yards -->
            <form x-show="movementTab === 'transfer'" x-cloak method="POST" action="{{ route('inventory.movements.transfer') }}" x-data="{ submitting: false }" @submit="submitting = true" class="space-y-4">
                @csrf
                <input type="hidden" name="submission_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('From Storage Location') }} *</label>
                        <select name="source_warehouse_id" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select source location') }}</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('To Destination Location') }} *</label>
                        <select name="destination_warehouse_id" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select destination location') }}</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Gate Pass / Challan #') }}</label>
                        <input type="text" name="challan_or_invoice_no" placeholder="e.g. GP-204" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Material Item') }} *</label>
                        <select name="items[0][material_id]" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select material') }}</option>
                            @foreach($materials as $mat)
                                <option value="{{ $mat->id }}">{{ $mat->name }} ({{ $mat->unit_of_measure }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Quantity to Transfer') }} *</label>
                        <input type="number" step="0.01" min="0.01" name="items[0][quantity]" required placeholder="0.00" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Transfer Notes') }}</label>
                        <input type="text" name="notes" placeholder="Vehicle number / remarks" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" :disabled="submitting" class="px-4 py-2 rounded-xl text-xs font-extrabold bg-amber-500 hover:bg-amber-400 text-slate-950 disabled:opacity-50 transition">
                        {{ __('Confirm Stock Transfer') }}
                    </button>
                </div>
            </form>

            <!-- 3. Return from Site -->
            <form x-show="movementTab === 'return'" x-cloak method="POST" action="{{ route('inventory.movements.return') }}" x-data="{ submitting: false }" @submit="submitting = true" class="space-y-4">
                @csrf
                <input type="hidden" name="submission_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Returning Site') }} *</label>
                        <select name="site_id" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select site') }}</option>
                            @foreach($sites as $site)
                                <option value="{{ $site->id }}">{{ $site->name }} ({{ $site->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Into Storage Location') }} *</label>
                        <select name="destination_warehouse_id" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select receiving location') }}</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Work Order (Optional)') }}</label>
                        <select name="work_order_id" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('None') }}</option>
                            @foreach($workOrders as $wo)
                                <option value="{{ $wo->id }}">{{ $wo->order_number }} — {{ $wo->title }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Return Slip #') }}</label>
                        <input type="text" name="challan_or_invoice_no" placeholder="e.g. RET-SLIP-09" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Material Item') }} *</label>
                        <select name="items[0][material_id]" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select material') }}</option>
                            @foreach($materials as $mat)
                                <option value="{{ $mat->id }}">{{ $mat->name }} ({{ $mat->unit_of_measure }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Returned Quantity') }} *</label>
                        <input type="number" step="0.01" min="0.01" name="items[0][quantity]" required placeholder="0.00" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Condition / Notes') }}</label>
                        <input type="text" name="notes" placeholder="Unused surplus material" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" :disabled="submitting" class="px-4 py-2 rounded-xl text-xs font-extrabold bg-amber-500 hover:bg-amber-400 text-slate-950 disabled:opacity-50 transition">
                        {{ __('Record Site Return') }}
                    </button>
                </div>
            </form>

            <!-- 4. Stock Adjustment (Requires Manager Approval) -->
            <form x-show="movementTab === 'adjustment'" x-cloak method="POST" action="{{ route('inventory.movements.adjustment') }}" x-data="{ submitting: false }" @submit="submitting = true" class="space-y-4">
                @csrf
                <input type="hidden" name="submission_token" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
                <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs text-slate-600 dark:text-slate-400">
                    {{ __('Stock adjustments require independent manager approval before on-hand quantities change. Enter a positive number (+10) for surplus found or a negative number (-5) for damaged/short stock.') }}
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Storage Location') }} *</label>
                        <select name="warehouse_id" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select storage location') }}</option>
                            @foreach($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Mandatory Adjustment Reason') }} *</label>
                        <input type="text" name="adjustment_reason" required minlength="5" placeholder="e.g. Monthly physical stock audit variance / rain-damaged bags" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-100 dark:border-slate-800">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Material Item') }} *</label>
                        <select name="items[0][material_id]" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select material') }}</option>
                            @foreach($materials as $mat)
                                <option value="{{ $mat->id }}">{{ $mat->name }} ({{ $mat->unit_of_measure }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Quantity Change (+/-)') }} *</label>
                        <input type="number" step="0.01" name="items[0][quantity_delta]" required placeholder="e.g. -5 or 10" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Audit Sheet Reference #') }}</label>
                        <input type="text" name="challan_or_invoice_no" placeholder="e.g. AUDIT-NOV-01" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" :disabled="submitting" class="px-4 py-2 rounded-xl text-xs font-extrabold bg-amber-500 hover:bg-amber-400 text-slate-950 disabled:opacity-50 transition">
                        {{ __('Submit Adjustment for Approval') }}
                    </button>
                </div>
            </form>
        </div>
    @endif

    <!-- Filter by warehouse -->
    <div class="p-4 rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm transition-colors duration-200">
        <form method="GET" action="{{ route('inventory.stocks') }}" class="flex items-center gap-3">
            <select name="warehouse_id" onchange="this.form.submit()" class="rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white focus:border-amber-500 outline-none w-full sm:w-64">
                <option value="">{{ __('All Storage Locations') }}</option>
                @foreach($warehouses as $wh)
                    <option value="{{ $wh->id }}" {{ $selectedWarehouseId == $wh->id ? 'selected' : '' }}>{{ $wh->name }} ({{ $wh->code }})</option>
                @endforeach
            </select>
        </form>
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Location') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Material Item') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Category') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('On Hand Qty') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Min Threshold') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($stocks as $s)
                        @php
                            $isLow = $s->quantity <= $s->material->minimum_stock_level;
                        @endphp
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $s->warehouse->name }}</div>
                                <div class="text-xs text-slate-500 font-mono">{{ $s->warehouse->code }}</div>
                            </td>
                            <td class="py-3.5 px-4">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $s->material->name }}</div>
                                <div class="text-xs text-amber-600 dark:text-amber-400 font-mono">{{ $s->material->code }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs text-slate-500 dark:text-slate-400">{{ $s->material->category->name }}</td>
                            <td class="py-3.5 px-4 font-mono font-bold text-right {{ $isLow ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                                {{ number_format($s->quantity, 2) }} {{ $s->material->unit_of_measure }}
                            </td>
                            <td class="py-3.5 px-4 font-mono text-slate-500 dark:text-slate-400 text-right text-xs">
                                {{ number_format($s->material->minimum_stock_level, 2) }} {{ $s->material->unit_of_measure }}
                            </td>
                            <td class="py-3.5 px-4 text-right">
                                @if($isLow)
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                        {{ __('LOW STOCK') }}
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                        {{ __('HEALTHY') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-slate-500">{{ __('No inventory stock records found.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="sm:hidden space-y-3 p-4">
            @forelse($stocks as $s)
                @php
                    $isLow = $s->quantity <= $s->material->minimum_stock_level;
                @endphp
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $s->material->name }}</span>
                        @if($isLow)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                {{ __('LOW STOCK') }}
                            </span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">
                                {{ __('HEALTHY') }}
                            </span>
                        @endif
                    </div>
                    <div class="text-xs text-slate-500 dark:text-slate-400">
                        {{ $s->warehouse->name }} &bull; {{ $s->material->category->name }}
                    </div>
                    <div class="flex items-center justify-between text-xs pt-2 border-t border-slate-200 dark:border-slate-800/80">
                        <span class="text-slate-500">{{ __('On Hand') }}:</span>
                        <span class="font-mono font-bold {{ $isLow ? 'text-rose-600 dark:text-rose-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ number_format($s->quantity, 2) }} {{ $s->material->unit_of_measure }}
                        </span>
                    </div>
                </div>
            @empty
                <div class="p-6 text-center text-slate-500 text-xs">
                    {{ __('No inventory stock records found.') }}
                </div>
            @endforelse
        </div>
    </div>

    @if($stocks->hasPages())
        <div>
            {{ $stocks->links() }}
        </div>
    @endif
</div>
@endsection

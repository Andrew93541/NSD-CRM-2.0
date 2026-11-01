@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6" x-data="{
    estimatedAmount: {{ (float) old('estimated_amount', 0) }},
    cgstRate: {{ (float) old('cgst_rate', 9) }},
    sgstRate: {{ (float) old('sgst_rate', 9) }},
    showClientModal: false,
    items: [
        { sr_no: '1.10', service_code: '6003235', sac_code: '995419', description: 'Earthwork in Excavation for Foundations & Trenches', specification: 'In all types of soil including soft rock, dewatering & shoring', unit: 'M3', ordered_quantity: 100, rate: 280 },
        { sr_no: '1.20', service_code: '6003238', sac_code: '995419', description: 'Providing & Laying Plain Cement Concrete M-15', specification: 'Using 20mm graded crushed stone aggregate', unit: 'M3', ordered_quantity: 50, rate: 5200 }
    ],
    addRow() {
        const nextIdx = (this.items.length + 1) * 10;
        this.items.push({ sr_no: `1.${nextIdx}`, service_code: '', sac_code: '995419', description: '', specification: '', unit: 'M3', ordered_quantity: 1, rate: 0 });
    },
    removeRow(idx) {
        this.items.splice(idx, 1);
        this.syncEstimated();
    },
    baseTotal() {
        return this.items.reduce((sum, item) => {
            const q = parseFloat(item.ordered_quantity) || 0;
            const r = parseFloat(item.rate) || 0;
            return sum + Math.round(q * r * 100) / 100;
        }, 0);
    },
    cgstAmount() {
        return Math.round(this.baseTotal() * ((parseFloat(this.cgstRate) || 0) / 100) * 100) / 100;
    },
    sgstAmount() {
        return Math.round(this.baseTotal() * ((parseFloat(this.sgstRate) || 0) / 100) * 100) / 100;
    },
    grossTotal() {
        return this.baseTotal() + this.cgstAmount() + this.sgstAmount();
    },
    syncEstimated() {
        const total = this.baseTotal();
        if (total > 0) {
            this.estimatedAmount = total.toFixed(2);
        }
    }
}" x-init="syncEstimated()">
    <div class="pb-4 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Register Received Service Order / NSD Work Order') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('JSW Steel Service Order / NSD Earth Movers BOQ schedule with 2-tier approval and locked revision tracking.') }}</p>
        </div>
        <button type="button" @click="showClientModal = true" class="px-3.5 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-xs font-bold text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700 transition">
            + {{ __('New Client (e.g. JSW Steel)') }}
        </button>
    </div>

    <!-- Quick Add Client Modal -->
    <div x-show="showClientModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/60 p-4">
        <div @click.outside="showClientModal = false" class="w-full max-w-lg rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 shadow-2xl space-y-4">
            <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-3">
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Register Client Organization') }}</h2>
                <button type="button" @click="showClientModal = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold">&times;</button>
            </div>
            <form method="POST" action="{{ route('work_orders.clients.store') }}" class="space-y-3 text-xs">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Client Name') }} *</label>
                        <input type="text" name="name" required placeholder="JSW STEEL LIMITED" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Client Code') }}</label>
                        <input type="text" name="code" placeholder="JSW-DOLVI" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 font-mono text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('GSTIN') }}</label>
                        <input type="text" name="gstin" placeholder="27AAACJ4323N1Z5" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 font-mono text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('PAN') }}</label>
                        <input type="text" name="pan" placeholder="AAACJ4323N" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 font-mono text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('NSD Vendor Code') }}</label>
                        <input type="text" name="vendor_code" placeholder="V-100984" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 font-mono text-slate-900 dark:text-white">
                    </div>
                </div>
                <div>
                    <label class="block font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Registered / Plant Address') }}</label>
                    <textarea name="address" rows="2" placeholder="Geetapuram, Dolvi, Taluka Pen, Dist. Raigad - 402107" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 text-slate-900 dark:text-white"></textarea>
                </div>
                <div class="flex justify-end gap-2 pt-2">
                    <button type="button" @click="showClientModal = false" class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 font-bold">{{ __('Cancel') }}</button>
                    <button type="submit" class="px-4 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold">{{ __('Save Client') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl transition-colors duration-200">
        <form method="POST" action="{{ route('work_orders.store') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <!-- Section 1: Project, Site & Client / Service Order Header -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Work Order Type') }} *</label>
                    <select name="order_type" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="client_issued">{{ __('Client-Issued Service Order (JSW)') }}</option>
                        <option value="internal">{{ __('Internal / Subcontractor Work Order') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Project') }} *</label>
                    <select name="project_id" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        @foreach($projects as $p)
                            <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Construction Site') }}</label>
                    <select name="site_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="">{{ __('General Project Wide') }}</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Section 2: JSW Service Order / Client PO Metadata -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-4">
                <h2 class="text-xs font-extrabold text-slate-800 dark:text-slate-200 uppercase tracking-wider">{{ __('JSW Steel Service Order / Client PO Metadata') }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Client Organization') }}</label>
                        <select name="client_id" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('-- Select Client --') }}</option>
                            @foreach($clients ?? [] as $client)
                                <option value="{{ $client->id }}" @selected(old('client_id') == $client->id)>{{ $client->name }} ({{ $client->gstin ?? $client->code }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('PO / Service Order Number') }}</label>
                        <input type="text" name="po_number" placeholder="e.g. 4700226845" value="{{ old('po_number') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('PO Date') }}</label>
                        <input type="date" name="po_date" value="{{ old('po_date', now()->format('Y-m-d')) }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Client Ref / Amendment') }}</label>
                        <input type="text" name="client_reference" placeholder="e.g. 4700226845 / Rev 0" value="{{ old('client_reference') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Job Location (Plant / Area)') }}</label>
                        <input type="text" name="job_location" placeholder="e.g. JSW Steel Ltd, Dolvi Works - SMS-2" value="{{ old('job_location') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('User Department / Person') }}</label>
                        <input type="text" name="user_department_person" placeholder="e.g. Civil Projects / Mr. S. Kulkarni" value="{{ old('user_department_person') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Effective Date') }}</label>
                        <input type="date" name="effective_date" value="{{ old('effective_date', now()->format('Y-m-d')) }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Completion Date') }}</label>
                        <input type="date" name="completion_date" value="{{ old('completion_date', now()->addMonths(6)->format('Y-m-d')) }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 pt-2 border-t border-slate-200 dark:border-slate-800">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Executing Contractor / Subcontractor Name') }}</label>
                        <input type="text" name="contractor_name" placeholder="e.g. NSD Earth Movers & Construction" value="{{ old('contractor_name') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Contractor GSTIN') }}</label>
                        <input type="text" name="contractor_gst" placeholder="e.g. 27AABCS1429B1Z1" value="{{ old('contractor_gst') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Contact Person & Phone') }}</label>
                        <input type="text" name="contractor_contact" placeholder="e.g. Rajesh Patil (+91 98201...)" value="{{ old('contractor_contact') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div class="sm:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Payment & Billing Terms') }}</label>
                        <input type="text" name="payment_terms" value="{{ old('payment_terms', \App\Models\WorkOrder::DEFAULT_PAYMENT_TERMS) }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('CGST / SGST Rate (%)') }}</label>
                        <div class="grid grid-cols-2 gap-2">
                            <input type="number" step="0.01" name="cgst_rate" x-model="cgstRate" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white" title="CGST %">
                            <input type="number" step="0.01" name="sgst_rate" x-model="sgstRate" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white" title="SGST %">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Retention (%)') }}</label>
                        <input type="number" step="0.01" name="retention_percent" value="{{ old('retention_percent', 5.00) }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white">
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Work Order Title / Subject') }} *</label>
                <input type="text" name="title" required placeholder="e.g. Civil & Structural Foundations at SMS-2 Dolvi Plant" value="{{ old('title') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Description') }}</label>
                    <textarea name="description" rows="2" placeholder="{{ __('Summary of earthmoving or civil structural requirements...') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">{{ old('description') }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Scope of Work') }}</label>
                    <textarea name="scope_of_work" rows="2" placeholder="1. Trench excavation... 2. PCC & RCC works..." class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">{{ old('scope_of_work') }}</textarea>
                </div>
            </div>

            <!-- Section 3: JSW Service Order Line Items / BOQ Schedule -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Service Order Line Items (BOQ Schedule)') }}</h2>
                        <p class="text-[11px] text-slate-500">{{ __('Sr. No. is preserved as exact text (e.g. 1.10, 1.20) for JSW Service Orders and RA Billing.') }}</p>
                    </div>
                    <button type="button" @click="addRow()" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-amber-500 hover:text-slate-950 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-amber-500 dark:hover:text-slate-950 text-xs font-bold transition">
                        + {{ __('Add Service Line Item') }}
                    </button>
                </div>

                <div class="space-y-2">
                    <template x-for="(item, index) in items" :key="index">
                        <div class="p-3 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-2">
                            <div class="grid grid-cols-1 sm:grid-cols-12 gap-2 items-end">
                                <div class="sm:col-span-1">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Sr. No.') }}</label>
                                    <input type="text" :name="`items[${index}][sr_no]`" x-model="item.sr_no" placeholder="1.10" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white">
                                </div>
                                <div class="sm:col-span-2">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Service Code') }}</label>
                                    <input type="text" :name="`items[${index}][service_code]`" x-model="item.service_code" placeholder="6003235" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white">
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('SAC') }}</label>
                                    <input type="text" :name="`items[${index}][sac_code]`" x-model="item.sac_code" placeholder="995419" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white">
                                </div>
                                <div class="sm:col-span-4">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Item Description') }} *</label>
                                    <input type="text" :name="`items[${index}][description]`" x-model="item.description" placeholder="Work item description..." class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs text-slate-900 dark:text-white">
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('UOM') }}</label>
                                    <input type="text" :name="`items[${index}][unit]`" x-model="item.unit" placeholder="M3 / MT" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white">
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Ordered Qty') }}</label>
                                    <input type="number" step="0.001" min="0" :name="`items[${index}][ordered_quantity]`" x-model="item.ordered_quantity" @input="syncEstimated()" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white">
                                </div>
                                <div class="sm:col-span-1">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Rate (₹)') }}</label>
                                    <input type="number" step="0.01" min="0" :name="`items[${index}][rate]`" x-model="item.rate" @input="syncEstimated()" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 p-2 text-xs font-mono text-slate-900 dark:text-white">
                                </div>
                                <div class="sm:col-span-1 flex justify-end">
                                    <button type="button" @click="removeRow(index)" class="px-2.5 py-2 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white text-xs font-bold transition">&times;</button>
                                </div>
                            </div>
                            <div>
                                <input type="text" :name="`items[${index}][specification]`" x-model="item.specification" placeholder="{{ __('Detailed Technical Specification / Long Text (optional)...') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-1.5 text-[11px] text-slate-700 dark:text-slate-300">
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Live Base + GST Order Summary -->
                <div class="p-4 rounded-xl bg-amber-500/5 border border-amber-500/20 flex flex-wrap items-center justify-between gap-4 text-xs">
                    <div>
                        <span class="text-slate-500 uppercase font-bold">{{ __('Base Order Value (A)') }}:</span>
                        <span class="font-mono font-extrabold text-slate-900 dark:text-white ml-1" x-text="'₹' + baseTotal().toFixed(2)"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 uppercase font-bold">{{ __('CGST') }} (<span x-text="cgstRate"></span>%):</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200 ml-1" x-text="'₹' + cgstAmount().toFixed(2)"></span>
                    </div>
                    <div>
                        <span class="text-slate-500 uppercase font-bold">{{ __('SGST') }} (<span x-text="sgstRate"></span>%):</span>
                        <span class="font-mono font-bold text-slate-800 dark:text-slate-200 ml-1" x-text="'₹' + sgstAmount().toFixed(2)"></span>
                    </div>
                    <div>
                        <span class="text-amber-700 dark:text-amber-400 uppercase font-extrabold">{{ __('Total Gross Order Value') }}:</span>
                        <span class="font-mono font-extrabold text-amber-600 dark:text-amber-400 text-sm ml-1" x-text="'₹' + grossTotal().toFixed(2)"></span>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Base Order Value') }} (₹)</label>
                    <input type="number" step="0.01" name="estimated_amount" x-model="estimatedAmount" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm font-mono font-bold text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Target Start') }}</label>
                    <input type="date" name="target_start_date" value="{{ old('target_start_date', now()->format('Y-m-d')) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Target Completion') }}</label>
                    <input type="date" name="target_completion_date" value="{{ old('target_completion_date', now()->addDays(30)->format('Y-m-d')) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Standard Terms & Conditions (NSD / JSW Template)') }}</label>
                <textarea name="terms_and_conditions" rows="4" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs font-mono text-slate-800 dark:text-slate-200 focus:border-amber-500 outline-none">{{ old('terms_and_conditions', \App\Models\WorkOrder::DEFAULT_TERMS) }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Supporting Attachment (PDF, DOC, DOCX, Image — Stored in Private Vault)') }}</label>
                <input type="file" name="attachment" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-600 dark:text-slate-400 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-white file:text-slate-800">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <a href="{{ route('work_orders.index') }}" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:border-slate-700 transition">{{ __('Cancel') }}</a>
                <button type="submit" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition">{{ __('Save Service Order & Submit for Tier 1 Approval') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

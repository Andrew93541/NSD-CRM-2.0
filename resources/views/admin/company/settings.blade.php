@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Administration Sub-Navigation -->
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
        <a href="{{ route('admin.users.index') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Users & Access') }}
        </a>
        <a href="{{ route('admin.roles.index') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Roles & Matrix') }}
        </a>
        <a href="{{ route('admin.company.edit') }}" class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 text-slate-950 shadow-sm">
            {{ __('Company Profile & Billing') }}
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Company Profile & Billing Settings') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">
                {{ __('Configure legal business details, GSTIN/PAN, bank details for tax invoices, and standard document numbering.') }}
            </p>
        </div>
        <div class="px-3.5 py-2 rounded-xl bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-xs font-mono text-slate-600 dark:text-slate-300">
            {{ __('Organization Code:') }} <span class="font-extrabold text-slate-900 dark:text-white">{{ $company->code }}</span>
        </div>
    </div>

    <form method="POST" action="{{ route('admin.company.update') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Section 1: Legal Identity & Contact -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('1. Organization Identity & Tax Registration') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Printed on official Work Orders, Purchase Orders, and GST Tax Invoices.') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Legal Company Name') }} *</label>
                    <input type="text" name="name" value="{{ old('name', $company->name) }}" required
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Client Vendor Code (Optional)') }}</label>
                    <input type="text" name="vendor_code" value="{{ old('vendor_code', $company->vendor_code) }}" placeholder="e.g., VENDOR-100452"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm font-mono text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('GSTIN (15 Characters)') }}</label>
                    <input type="text" name="gstin" value="{{ old('gstin', $company->gstin) }}" maxlength="15" placeholder="27AABCN1234F1Z5"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm font-mono uppercase text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('PAN (10 Characters)') }}</label>
                    <input type="text" name="pan" value="{{ old('pan', $company->pan) }}" maxlength="10" placeholder="AABCN1234F"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm font-mono uppercase text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Official Email') }}</label>
                    <input type="email" name="email" value="{{ old('email', $company->email) }}"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Official Phone') }}</label>
                    <input type="text" name="phone" value="{{ old('phone', $company->phone) }}"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Registered Billing Address') }}</label>
                    <textarea name="registered_address" rows="3"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">{{ old('registered_address', $company->registered_address) }}</textarea>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Operational Office Address') }}</label>
                    <textarea name="address" rows="3"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">{{ old('address', $company->address) }}</textarea>
                </div>
            </div>
        </div>

        <!-- Section 2: Bank Remittance Details -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('2. Bank Account for Client Remittances') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Displayed on RA Bill Tax Invoices so clients remit to the right account.') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Bank Name') }}</label>
                    <input type="text" name="bank_name" value="{{ old('bank_name', $company->bank_name) }}"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Branch Name') }}</label>
                    <input type="text" name="bank_branch" value="{{ old('bank_branch', $company->bank_branch) }}"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Account Number') }}</label>
                    <input type="text" name="bank_account_no" value="{{ old('bank_account_no', $company->bank_account_no) }}"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm font-mono text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('IFSC Code') }}</label>
                    <input type="text" name="bank_ifsc" value="{{ old('bank_ifsc', $company->bank_ifsc) }}" maxlength="11" placeholder="HDFC0000123"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm font-mono uppercase text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>
        </div>

        <!-- Section 3: Invoicing, Terms & Authorized Signatory -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
            <div>
                <h2 class="text-base font-bold text-slate-900 dark:text-white">{{ __('3. Invoice Numbering, Terms & Signatory Stamp') }}</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Controls financial-year invoice series, rounding precision, and private signature image.') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Invoice Prefix') }} *</label>
                    <input type="text" name="invoice_prefix" value="{{ old('invoice_prefix', $company->invoice_prefix ?? 'CIVIL') }}" required
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm font-mono uppercase text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Starting Sequence Number') }} *</label>
                    <input type="number" name="invoice_starting_number" min="1" value="{{ old('invoice_starting_number', $company->invoice_starting_number ?? 21) }}" required
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm font-mono text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Invoice Rounding Mode') }} *</label>
                    <select name="invoice_rounding_mode" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="paisa" {{ old('invoice_rounding_mode', $company->invoice_rounding_mode ?? 'paisa') === 'paisa' ? 'selected' : '' }}>{{ __('Exact Paisa (2 Decimals)') }}</option>
                        <option value="rupee" {{ old('invoice_rounding_mode', $company->invoice_rounding_mode ?? 'paisa') === 'rupee' ? 'selected' : '' }}>{{ __('Nearest Rupee') }}</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Default Work Order Terms & Conditions Template') }}</label>
                <textarea name="work_order_terms_template" rows="4" placeholder="{{ __('Standard safety, billing, and retention clauses...') }}"
                    class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">{{ old('work_order_terms_template', $company->work_order_terms_template) }}</textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-start pt-2 border-t border-slate-200 dark:border-slate-800">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">{{ __('Authorized Signatory Image (Private PNG/JPG, Max 2 MB)') }}</label>
                    <input type="file" name="signature" accept=".png,.jpg,.jpeg,image/png,image/jpeg"
                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3.5 py-2 text-xs text-slate-700 dark:text-slate-300">
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">{{ __('Stored in private vault storage; never exposed via public URLs.') }}</p>
                </div>

                @if(!empty($company->signature_path))
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
                        <div class="text-xs">
                            <div class="font-bold text-slate-800 dark:text-slate-200">{{ __('Current Signatory Stamp Configured') }}</div>
                            <a href="{{ route('admin.company.signature') }}" target="_blank" class="text-amber-600 dark:text-amber-400 font-semibold hover:underline">
                                {{ __('View Authorized Stamp') }}
                            </a>
                        </div>
                        <label class="inline-flex items-center gap-2 text-xs font-semibold text-rose-600 dark:text-rose-400 cursor-pointer">
                            <input type="checkbox" name="remove_signature" value="1" class="rounded border-slate-300 text-rose-600 focus:ring-rose-500">
                            <span>{{ __('Remove') }}</span>
                        </label>
                    </div>
                @endif
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <button type="submit" class="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-sm font-extrabold shadow-lg shadow-amber-500/20 transition">
                {{ __('Save Company Settings') }}
            </button>
        </div>
    </form>
</div>
@endsection

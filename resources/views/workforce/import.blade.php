@extends('layouts.app')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Top Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-3">
                <span class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-600 dark:text-amber-400 flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                </span>
                <span>{{ __('Bulk Worker CSV Upload') }}</span>
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-slate-400 mt-1">
                {{ __('Import dozens or hundreds of workers at once. Worker IDs (EMP-YYYY-#### and SUB-YYYY-####) and QR tokens are generated automatically.') }}
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('workforce.import.template') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-700 dark:text-emerald-300 border border-emerald-500/30 font-extrabold text-xs sm:text-sm transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                <span>{{ __('Download Sample CSV Template') }}</span>
            </a>
            <a href="{{ route('workforce.index') }}" class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 font-bold text-xs sm:text-sm transition">
                &larr; {{ __('Back to Workforce') }}
            </a>
        </div>
    </div>

    <!-- Duplicate Prevention Info Banner -->
    <div class="p-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 flex items-start gap-3">
        <span class="w-7 h-7 rounded-lg bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 mt-0.5">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
        </span>
        <div class="text-xs text-slate-700 dark:text-slate-300 space-y-1">
            <div class="font-extrabold text-emerald-700 dark:text-emerald-300">{{ __('Smart Duplicate Prevention Enabled') }}</div>
            <p class="text-slate-600 dark:text-slate-400">
                {{ __('Unique personal identifiers are automatically checked for duplicates both inside your CSV file and against existing workers:') }}
                <span class="font-semibold text-slate-800 dark:text-slate-200">{{ __('10-digit Mobile Number, Email Address, Bank Account Number, and UPI ID.') }}</span>
                {{ __('Workers sharing the same Full Name, Date of Birth, or Address are allowed as long as their unique identifiers do not conflict.') }}
            </p>
        </div>
    </div>

    <!-- Upload Form Card -->
    <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5">
        <form method="POST" action="{{ route('workforce.import.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                        {{ __('Select CSV File') }} <span class="text-rose-500">*</span>
                    </label>
                    <input type="file" name="csv_file" accept=".csv,.txt" required
                           class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-xs sm:text-sm text-slate-900 dark:text-white file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-extrabold file:bg-amber-500 file:text-slate-950 hover:file:bg-amber-400">
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
                        {{ __('Maximum file size: 5 MB (.csv). UTF-8 encoded.') }}
                    </p>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">
                        {{ __('Default Site (Optional Fallback)') }}
                    </label>
                    <select name="default_site_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-3 text-xs sm:text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="">{{ __('Use site_code column from CSV (or leave unassigned)') }}</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}" {{ old('default_site_id') == $s->id ? 'selected' : '' }}>
                                {{ $s->code }} — {{ $s->name }} ({{ $s->location }})
                            </option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1.5">
                        {{ __('Applied automatically to rows where the site_code column is left blank.') }}
                    </p>
                </div>
            </div>

            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 flex items-center justify-end gap-3">
                <button type="submit" class="px-6 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs sm:text-sm shadow-lg shadow-amber-500/20 transition flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span>{{ __('Validate & Import Workers Now') }}</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Column Guide & Available Site Codes -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <h2 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
                {{ __('Supported CSV Columns') }}
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400">
                {{ __('Columns marked with * are required. All other columns are optional and can be left blank.') }}
            </p>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700 dark:text-slate-300">
                    <thead class="text-[11px] uppercase text-slate-500 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                        <tr>
                            <th class="py-2 px-3 font-bold">{{ __('Column Header') }}</th>
                            <th class="py-2 px-3 font-bold">{{ __('Required') }}</th>
                            <th class="py-2 px-3 font-bold">{{ __('Allowed Values / Example') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-slate-800 font-mono text-[11px]">
                        <tr>
                            <td class="py-2 px-3 font-bold text-amber-600 dark:text-amber-400">worker_type *</td>
                            <td class="py-2 px-3 text-rose-500 font-sans font-bold">Yes</td>
                            <td class="py-2 px-3 font-sans"><code>employee</code>, <code>direct_labour</code>, or <code>subcontractor_worker</code></td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-bold text-amber-600 dark:text-amber-400">first_name *</td>
                            <td class="py-2 px-3 text-rose-500 font-sans font-bold">Yes</td>
                            <td class="py-2 px-3 font-sans">e.g. <code>Rajesh</code></td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3">last_name</td>
                            <td class="py-2 px-3 font-sans text-slate-400">Optional</td>
                            <td class="py-2 px-3 font-sans">e.g. <code>Deshmukh</code></td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-bold text-amber-600 dark:text-amber-400">trade_designation *</td>
                            <td class="py-2 px-3 text-rose-500 font-sans font-bold">Yes</td>
                            <td class="py-2 px-3 font-sans">e.g. <code>Mason</code>, <code>Excavator Operator</code>, <code>Site Engineer</code></td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 font-bold text-amber-600 dark:text-amber-400">daily_rate *</td>
                            <td class="py-2 px-3 text-rose-500 font-sans font-bold">Yes</td>
                            <td class="py-2 px-3 font-sans">Daily wage in INR, e.g. <code>850</code></td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3">overtime_rate</td>
                            <td class="py-2 px-3 font-sans text-slate-400">Optional</td>
                            <td class="py-2 px-3 font-sans">Hourly OT rate in INR, e.g. <code>125</code></td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3">site_code</td>
                            <td class="py-2 px-3 font-sans text-slate-400">Optional</td>
                            <td class="py-2 px-3 font-sans">Exact site code from list on the right</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3">subcontractor_name</td>
                            <td class="py-2 px-3 font-sans text-slate-400">If Subcontractor</td>
                            <td class="py-2 px-3 font-sans">Vendor name (auto-created if new)</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3">skill_level</td>
                            <td class="py-2 px-3 font-sans text-slate-400">Optional</td>
                            <td class="py-2 px-3 font-sans"><code>unskilled</code>, <code>semi_skilled</code>, <code>skilled</code>, <code>highly_skilled</code>, <code>supervisor</code></td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3">phone, email, gender, date_of_birth, aadhaar_last_four, joining_date, bank_account_number, bank_ifsc, upi_id</td>
                            <td class="py-2 px-3 font-sans text-slate-400">Optional</td>
                            <td class="py-2 px-3 font-sans">Full personal, emergency contact, and bank payout details</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Reference Site Codes & Subcontractors -->
        <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div>
                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">{{ __('Active Site Codes') }}</h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Use any of these in the site_code column:') }}</p>
                <div class="mt-2 space-y-1.5 max-h-48 overflow-y-auto">
                    @foreach($sites as $s)
                        <div class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs">
                            <span class="font-mono font-bold text-amber-600 dark:text-amber-400">{{ $s->code }}</span>
                            <span class="text-slate-600 dark:text-slate-400 truncate max-w-[140px]">{{ $s->name }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">{{ __('Registered Subcontractors') }}</h3>
                <div class="mt-2 space-y-1.5 max-h-40 overflow-y-auto">
                    @forelse($subcontractors as $sub)
                        <div class="px-3 py-1.5 rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 text-xs font-semibold text-slate-700 dark:text-slate-300">
                            {{ $sub->name }}
                        </div>
                    @empty
                        <div class="text-xs text-slate-400">{{ __('New subcontractor names in CSV will be created automatically.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection


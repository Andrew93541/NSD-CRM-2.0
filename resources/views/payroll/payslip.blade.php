<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip — {{ $worker?->code }} ({{ $period->period_code }})</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
        }
    </style>
</head>
<body class="bg-slate-100 text-slate-900 p-4 sm:p-8 font-sans">
    <div class="max-w-3xl mx-auto space-y-4">
        <div class="no-print flex items-center justify-between bg-slate-900 text-white px-5 py-3 rounded-xl">
            <div class="text-xs">
                <span class="font-bold text-amber-400">{{ __('Official Worker Payslip') }}</span> &bull; {{ $worker?->full_name }} ({{ $worker?->code }})
            </div>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="px-4 py-1.5 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold transition">
                    {{ __('Print / Save as PDF') }}
                </button>
            </div>
        </div>

        <div class="bg-white border-2 border-slate-900 rounded-xl p-6 sm:p-8 shadow-sm space-y-6">
            <!-- Company Letterhead -->
            <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4 pb-5 border-b-2 border-slate-900">
                <div>
                    <div class="text-xs font-extrabold uppercase tracking-widest text-amber-600">{{ __('NSD Earth Movers & Construction') }}</div>
                    <h1 class="text-xl font-black text-slate-900 uppercase mt-0.5">{{ $company?->name ?? 'NSD Construction Pvt. Ltd.' }}</h1>
                    <p class="text-xs text-slate-600 mt-1">{{ __('Site') }}: {{ $entry->site?->name ?? $worker?->assignedSite?->name ?? __('General Operations') }}</p>
                </div>
                <div class="sm:text-right">
                    <div class="inline-block px-3 py-1 bg-slate-900 text-amber-400 font-mono text-xs font-extrabold uppercase rounded">
                        {{ __('PAYSLIP') }} #{{ $period->period_code }}-{{ $entry->id }}
                    </div>
                    <div class="text-xs text-slate-600 mt-1.5 font-mono">
                        {{ $period->start_date->format('d M Y') }} — {{ $period->end_date->format('d M Y') }}
                    </div>
                </div>
            </div>

            <!-- Worker Details Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-xs bg-slate-50 p-4 rounded-lg border border-slate-200">
                <div>
                    <div class="text-slate-500 font-semibold uppercase text-[10px]">{{ __('Worker Code') }}</div>
                    <div class="font-mono font-extrabold text-slate-900 mt-0.5">{{ $worker?->code }}</div>
                </div>
                <div>
                    <div class="text-slate-500 font-semibold uppercase text-[10px]">{{ __('Worker Name') }}</div>
                    <div class="font-bold text-slate-900 mt-0.5">{{ $worker?->full_name }}</div>
                </div>
                <div>
                    <div class="text-slate-500 font-semibold uppercase text-[10px]">{{ __('Trade / Designation') }}</div>
                    <div class="font-bold text-slate-900 mt-0.5">{{ $worker?->trade_designation }}</div>
                </div>
                <div>
                    <div class="text-slate-500 font-semibold uppercase text-[10px]">{{ __('Compensation Category') }}</div>
                    <div class="font-bold text-amber-700 uppercase mt-0.5">{{ $worker?->payTypeLabel() }} (₹{{ number_format((float) $entry->base_rate, 2) }})</div>
                </div>
                <div>
                    <div class="text-slate-500 font-semibold uppercase text-[10px]">{{ __('Present / Half / Leave Days') }}</div>
                    <div class="font-mono font-bold text-slate-900 mt-0.5">{{ $entry->present_days }}P / {{ $entry->half_days }}HD / {{ $entry->paid_leave_days }}PL</div>
                </div>
                <div>
                    <div class="text-slate-500 font-semibold uppercase text-[10px]">{{ __('Effective Paid Days') }}</div>
                    <div class="font-mono font-bold text-slate-900 mt-0.5">{{ number_format((float) $entry->effective_days, 2) }} {{ __('Days') }}</div>
                </div>
                <div>
                    <div class="text-slate-500 font-semibold uppercase text-[10px]">{{ __('Worked / Overtime Hours') }}</div>
                    <div class="font-mono font-bold text-slate-900 mt-0.5">{{ number_format((float) $entry->worked_hours, 2) }}h / +{{ number_format((float) $entry->overtime_hours, 2) }}h OT</div>
                </div>
                <div>
                    <div class="text-slate-500 font-semibold uppercase text-[10px]">{{ __('Bank / UPI Details') }}</div>
                    <div class="font-mono text-slate-900 mt-0.5">{{ $worker?->bank_account_number ?: ($worker?->upi_id ?: 'Cash / Site Muster') }}</div>
                </div>
            </div>

            <!-- Earnings & Deductions Split Table -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div class="border border-slate-300 rounded-lg overflow-hidden">
                    <div class="bg-slate-900 text-white px-4 py-2 text-xs font-extrabold uppercase">{{ __('Earnings') }}</div>
                    <div class="divide-y divide-slate-200 text-xs">
                        <div class="px-4 py-2.5 flex justify-between">
                            <span>{{ __('Base Compensation') }} ({{ strtoupper($entry->pay_type) }})</span>
                            <span class="font-mono font-bold">₹{{ number_format((float) $entry->base_pay, 2) }}</span>
                        </div>
                        <div class="px-4 py-2.5 flex justify-between">
                            <span>{{ __('Overtime Pay') }} ({{ number_format((float) $entry->overtime_hours, 2) }} hrs @ ₹{{ number_format((float) $entry->overtime_rate, 2) }})</span>
                            <span class="font-mono font-bold">₹{{ number_format((float) $entry->overtime_pay, 2) }}</span>
                        </div>
                        <div class="px-4 py-2.5 flex justify-between">
                            <span>{{ __('Site Bonus / Incentive') }}</span>
                            <span class="font-mono font-bold">₹{{ number_format((float) $entry->bonus_amount, 2) }}</span>
                        </div>
                        <div class="px-4 py-3 flex justify-between bg-slate-50 font-extrabold">
                            <span>{{ __('Gross Earnings') }}</span>
                            <span class="font-mono">₹{{ number_format((float) $entry->gross_pay, 2) }}</span>
                        </div>
                    </div>
                </div>

                <div class="border border-slate-300 rounded-lg overflow-hidden">
                    <div class="bg-slate-900 text-white px-4 py-2 text-xs font-extrabold uppercase">{{ __('Deductions') }}</div>
                    <div class="divide-y divide-slate-200 text-xs">
                        <div class="px-4 py-2.5 flex justify-between">
                            <span>{{ __('Salary Advance Recovery') }}</span>
                            <span class="font-mono font-bold text-rose-600">₹{{ number_format((float) $entry->advance_deduction, 2) }}</span>
                        </div>
                        <div class="px-4 py-2.5 flex justify-between">
                            <span>{{ __('Other Deductions') }} {{ $entry->deduction_notes ? '(' . $entry->deduction_notes . ')' : '' }}</span>
                            <span class="font-mono font-bold text-rose-600">₹{{ number_format((float) $entry->other_deduction, 2) }}</span>
                        </div>
                        <div class="px-4 py-2.5 flex justify-between text-slate-400">
                            <span>&nbsp;</span>
                            <span>&nbsp;</span>
                        </div>
                        <div class="px-4 py-3 flex justify-between bg-slate-50 font-extrabold">
                            <span>{{ __('Total Deductions') }}</span>
                            <span class="font-mono text-rose-600">₹{{ number_format((float) $entry->advance_deduction + (float) $entry->other_deduction, 2) }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Net Payable Banner -->
            <div class="p-4 rounded-xl bg-slate-900 text-white flex items-center justify-between">
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-widest text-amber-400">{{ __('Net Payable Amount') }}</div>
                    <div class="text-xs text-slate-300 mt-0.5">{{ __('Status') }}: {{ strtoupper($period->status) }}</div>
                </div>
                <div class="text-2xl font-black font-mono text-emerald-400">
                    ₹{{ number_format((float) $entry->net_pay, 2) }}
                </div>
            </div>

            <!-- Signatures -->
            <div class="grid grid-cols-3 gap-6 pt-8 text-xs text-center text-slate-600">
                <div class="border-t border-slate-400 pt-2">{{ __('Worker Signature / Thumb') }}</div>
                <div class="border-t border-slate-400 pt-2">{{ __('Site Manager / Timekeeper') }}</div>
                <div class="border-t border-slate-400 pt-2">{{ __('Authorized Signatory (NSD)') }}</div>
            </div>
        </div>
    </div>
</body>
</html>


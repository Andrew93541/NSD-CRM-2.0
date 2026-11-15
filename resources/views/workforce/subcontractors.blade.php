@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ showCreate: false, editSubId: null }">
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
        <a href="{{ route('workforce.index') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Employees & Workers') }}
        </a>
        <a href="{{ route('workforce.subcontractors') }}" class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 text-slate-950 shadow-sm">
            {{ __('Subcontractors') }}
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Subcontractor Companies') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Partner labour contractors and specialized trade vendors supplying named site workers.') }}</p>
        </div>
        @if($canManage ?? false)
            <button type="button" @click="showCreate = !showCreate" class="px-4 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-sm transition">
                + {{ __('Add Subcontractor') }}
            </button>
        @endif
    </div>

    @if($canManage ?? false)
        <div x-show="showCreate" x-cloak class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">{{ __('Register Subcontractor Firm') }}</h2>
            <form method="POST" action="{{ route('workforce.subcontractors.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('Firm Name') }} *</label>
                        <input type="text" name="name" required placeholder="Apex Civil Contractors"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('Code (Auto if blank)') }}</label>
                        <input type="text" name="code" placeholder="SUB-001"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm font-mono uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('Contact Person') }}</label>
                        <input type="text" name="contact_person"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('Phone') }}</label>
                        <input type="text" name="phone"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('GSTIN') }}</label>
                        <input type="text" name="gst_number" maxlength="15"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm font-mono uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('PAN') }}</label>
                        <input type="text" name="pan_number" maxlength="10"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm font-mono uppercase">
                    </div>
                </div>
                <input type="hidden" name="is_active" value="1">
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="showCreate = false" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800">{{ __('Cancel') }}</button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 text-slate-950">{{ __('Save Subcontractor') }}</button>
                </div>
            </form>
        </div>
    @endif

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Subcontractor & Code') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Contact Person') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Phone') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('GSTIN') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Workers') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Status') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($subcontractors as $sub)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $sub->name }}</div>
                                <div class="text-xs font-mono text-slate-500">{{ $sub->code }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs">{{ $sub->contact_person ?? '—' }}</td>
                            <td class="py-3.5 px-4 text-xs font-mono">{{ $sub->phone ?? '—' }}</td>
                            <td class="py-3.5 px-4 text-xs font-mono">{{ $sub->gst_number ?? '—' }}</td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold">{{ $sub->workers_count }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $sub->is_active ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                                    {{ $sub->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-right space-x-2 whitespace-nowrap">
                                <a href="{{ route('workforce.index', ['type' => 'subcontractor_worker', 'search' => $sub->name]) }}" class="text-xs font-bold text-sky-600 dark:text-sky-400 hover:underline">
                                    {{ __('Workers') }}
                                </a>
                                @if($canManage ?? false)
                                    <button type="button" @click="editSubId = (editSubId === {{ $sub->id }} ? null : {{ $sub->id }})" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                                        {{ __('Edit') }}
                                    </button>
                                    @if($sub->workers_count === 0)
                                        <form method="POST" action="{{ route('workforce.subcontractors.destroy', $sub) }}" class="inline" onsubmit="return confirm('Delete subcontractor {{ addslashes($sub->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-bold text-rose-600 dark:text-rose-400 hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    @endif
                                @endif
                            </td>
                        </tr>
                        @if($canManage ?? false)
                            <tr x-show="editSubId === {{ $sub->id }}" x-cloak class="bg-slate-50/70 dark:bg-slate-950/70">
                                <td colspan="7" class="p-4">
                                    <form method="POST" action="{{ route('workforce.subcontractors.update', $sub) }}" class="grid grid-cols-1 md:grid-cols-6 gap-2.5 items-end">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Name') }}</label>
                                            <input type="text" name="name" value="{{ $sub->name }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Code') }}</label>
                                            <input type="text" name="code" value="{{ $sub->code }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs font-mono uppercase">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Contact') }}</label>
                                            <input type="text" name="contact_person" value="{{ $sub->contact_person }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Phone') }}</label>
                                            <input type="text" name="phone" value="{{ $sub->phone }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs">
                                        </div>
                                        <div>
                                            <label class="inline-flex items-center gap-1.5 text-xs font-bold py-2">
                                                <input type="checkbox" name="is_active" value="1" {{ $sub->is_active ? 'checked' : '' }} class="rounded border-slate-300 text-amber-500">
                                                <span>{{ __('Active') }}</span>
                                            </label>
                                        </div>
                                        <div class="flex justify-end">
                                            <input type="hidden" name="gst_number" value="{{ $sub->gst_number }}">
                                            <input type="hidden" name="pan_number" value="{{ $sub->pan_number }}">
                                            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-amber-500 text-slate-950 text-xs font-extrabold">{{ __('Save') }}</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="7" class="py-12 px-4 text-center">
                                <div class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ __('No subcontractor firms registered yet') }}</div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Register labour contractors here before onboarding subcontractor workers.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

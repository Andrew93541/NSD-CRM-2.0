@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ showCreate: false, editClientId: null }">
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
        <a href="{{ route('work_orders.index') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Work Orders') }}
        </a>
        <a href="{{ route('clients.index') }}" class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 text-slate-950 shadow-sm">
            {{ __('Clients Directory') }}
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Clients Directory') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Corporate and project clients, billing addresses, GSTINs, and vendor codes for RA Billing.') }}</p>
        </div>
        @if($canManage)
            <button type="button" @click="showCreate = !showCreate" class="px-4 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-sm transition">
                + {{ __('Add Client') }}
            </button>
        @endif
    </div>

    @if($canManage)
        <div x-show="showCreate" x-cloak class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">{{ __('Register Client Organization') }}</h2>
            <form method="POST" action="{{ route('clients.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('Client Legal Name') }} *</label>
                        <input type="text" name="name" required placeholder="JSW Steel Limited"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('Client Code (Auto if blank)') }}</label>
                        <input type="text" name="code" placeholder="CLI-001"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm font-mono uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('Our Vendor Code with Client') }}</label>
                        <input type="text" name="vendor_code" placeholder="e.g., 10045291"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm font-mono">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('Client GSTIN') }}</label>
                        <input type="text" name="gstin" maxlength="15" placeholder="27AAACJ4323N1ZG"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm font-mono uppercase">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('Client PAN') }}</label>
                        <input type="text" name="pan" maxlength="10" placeholder="AAACJ4323N"
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
                        <label class="block text-xs font-bold mb-1">{{ __('Email') }}</label>
                        <input type="email" name="email"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-bold mb-1">{{ __('Billing Address') }}</label>
                        <input type="text" name="address"
                            class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-sm">
                    </div>
                </div>
                <input type="hidden" name="is_active" value="1">
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="showCreate = false" class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-100 dark:bg-slate-800">{{ __('Cancel') }}</button>
                    <button type="submit" class="px-5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 text-slate-950">{{ __('Save Client') }}</button>
                </div>
            </form>
        </div>
    @endif

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Client & Code') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('GSTIN / Vendor Code') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Contact') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Work Orders') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Status') }}</th>
                        @if($canManage)
                            <th class="py-3 px-4 font-semibold text-right">{{ __('Actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($clients as $client)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-3.5 px-4">
                                <div class="font-bold text-slate-900 dark:text-white">{{ $client->name }}</div>
                                <div class="text-xs font-mono text-slate-500">{{ $client->code ?? '—' }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-xs font-mono">
                                <div>{{ $client->gstin ?? '—' }}</div>
                                @if($client->vendor_code)
                                    <div class="text-slate-500">{{ __('Vendor:') }} {{ $client->vendor_code }}</div>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-xs">
                                <div>{{ $client->contact_person ?? '—' }}</div>
                                <div class="text-slate-500">{{ $client->phone }}</div>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold">{{ $client->work_orders_count }}</td>
                            <td class="py-3.5 px-4">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold {{ $client->is_active ? 'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' : 'bg-slate-200 dark:bg-slate-800 text-slate-600 dark:text-slate-400' }}">
                                    {{ $client->is_active ? __('Active') : __('Inactive') }}
                                </span>
                            </td>
                            @if($canManage)
                                <td class="py-3.5 px-4 text-right space-x-2 whitespace-nowrap">
                                    <button type="button" @click="editClientId = (editClientId === {{ $client->id }} ? null : {{ $client->id }})" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                                        {{ __('Edit') }}
                                    </button>
                                    @if($client->work_orders_count === 0)
                                        <form method="POST" action="{{ route('clients.destroy', $client) }}" class="inline" onsubmit="return confirm('Delete client {{ addslashes($client->name) }}?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-bold text-rose-600 dark:text-rose-400 hover:underline">{{ __('Delete') }}</button>
                                        </form>
                                    @endif
                                </td>
                            @endif
                        </tr>
                        @if($canManage)
                            <tr x-show="editClientId === {{ $client->id }}" x-cloak class="bg-slate-50/70 dark:bg-slate-950/70">
                                <td colspan="6" class="p-4">
                                    <form method="POST" action="{{ route('clients.update', $client) }}" class="grid grid-cols-1 md:grid-cols-6 gap-2.5 items-end">
                                        @csrf
                                        @method('PUT')
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Name') }}</label>
                                            <input type="text" name="name" value="{{ $client->name }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Code') }}</label>
                                            <input type="text" name="code" value="{{ $client->code }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs font-mono uppercase">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('GSTIN') }}</label>
                                            <input type="text" name="gstin" value="{{ $client->gstin }}" maxlength="15" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs font-mono uppercase">
                                        </div>
                                        <div>
                                            <label class="block text-[11px] font-bold mb-1">{{ __('Contact') }}</label>
                                            <input type="text" name="contact_person" value="{{ $client->contact_person }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2 py-1.5 text-xs">
                                        </div>
                                        <div>
                                            <label class="inline-flex items-center gap-1.5 text-xs font-bold py-2">
                                                <input type="checkbox" name="is_active" value="1" {{ $client->is_active ? 'checked' : '' }} class="rounded border-slate-300 text-amber-500">
                                                <span>{{ __('Active') }}</span>
                                            </label>
                                        </div>
                                        <div class="flex justify-end">
                                            <input type="hidden" name="pan" value="{{ $client->pan }}">
                                            <input type="hidden" name="vendor_code" value="{{ $client->vendor_code }}">
                                            <input type="hidden" name="phone" value="{{ $client->phone }}">
                                            <input type="hidden" name="email" value="{{ $client->email }}">
                                            <input type="hidden" name="address" value="{{ $client->address }}">
                                            <button type="submit" class="px-3.5 py-1.5 rounded-lg bg-amber-500 text-slate-950 text-xs font-extrabold">{{ __('Save') }}</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 6 : 5 }}" class="py-12 px-4 text-center">
                                <div class="text-sm font-bold text-slate-700 dark:text-slate-300">{{ __('No clients registered yet') }}</div>
                                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">{{ __('Add project clients here so their legal GSTIN and address appear automatically on Work Orders and RA Bills.') }}</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($clients->hasPages())
            <div class="p-4 border-t border-slate-200 dark:border-slate-800">
                {{ $clients->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

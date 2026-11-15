@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <!-- Materials & Stock Sub-Navigation -->
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 dark:border-slate-800 pb-3">
        <a href="{{ route('procurement.requests') }}" class="px-3.5 py-2 rounded-xl text-xs font-extrabold bg-amber-500 text-slate-950 shadow-sm">
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
        <a href="{{ route('inventory.materials') }}" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-white dark:bg-slate-900 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-800 hover:border-amber-500/40 transition">
            {{ __('Material Catalog') }}
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Purchase Requests') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Request materials for your site or warehouse and track manager approvals.') }}</p>
        </div>
        @can('create', \App\Models\PurchaseRequest::class)
            <a href="{{ route('procurement.create_request') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs shadow-sm transition">
                + {{ __('New Purchase Request') }}
            </a>
        @endcan
    </div>

    <div class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm transition-colors duration-200">
        <!-- Desktop Table View -->
        <div class="hidden sm:block overflow-x-auto">
            <table class="w-full text-left text-sm text-slate-700 dark:text-slate-300">
                <thead class="text-xs uppercase text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-950 border-b border-slate-200 dark:border-slate-800">
                    <tr>
                        <th class="py-3 px-4 font-semibold">{{ __('Request #') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Location') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Urgency') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Material Item') }}</th>
                        <th class="py-3 px-4 font-semibold">{{ __('Status') }}</th>
                        <th class="py-3 px-4 font-semibold text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800/80">
                    @forelse($requests as $r)
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-4 px-4 font-mono font-bold text-slate-900 dark:text-white text-xs">{{ $r->request_number }}</td>
                            <td class="py-4 px-4">
                                <div class="font-semibold text-slate-900 dark:text-white">{{ $r->site->name ?? $r->warehouse->name ?? __('Head Office') }}</div>
                                <div class="text-xs text-slate-500">{{ __('By') }} {{ $r->requester->name }}</div>
                            </td>
                            <td class="py-4 px-4">
                                @if($r->is_emergency)
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30">
                                        {{ __('Emergency Purchase') }}
                                    </span>
                                    <div class="text-[11px] text-rose-600 dark:text-rose-300 mt-1 max-w-xs">{{ $r->emergency_justification }}</div>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase {{ $r->urgency === 'urgent' ? 'bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300' }}">
                                        {{ __($r->urgency === 'urgent' ? 'Urgent' : 'Normal') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-xs space-y-1">
                                @foreach($r->items as $item)
                                    <div><span class="font-bold text-slate-900 dark:text-white">{{ $item->quantity_requested }} {{ $item->material->unit_of_measure }}</span> &mdash; {{ $item->material->name }}</div>
                                @endforeach
                            </td>
                            <td class="py-4 px-4">
                                @php
                                    $statusBadge = match($r->status) {
                                        'pending' => ['bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30', __('Pending')],
                                        'approved' => ['bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', __('Approved')],
                                        'rejected' => ['bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30', __('Rejected')],
                                        'cancelled' => ['bg-slate-200/70 text-slate-600 dark:bg-slate-800 dark:text-slate-400', __('Cancelled')],
                                        default => ['bg-sky-500/10 text-sky-600 dark:text-sky-400', __('Issued')],
                                    };
                                @endphp
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold uppercase {{ $statusBadge[0] }}">
                                    {{ $statusBadge[1] }}
                                </span>
                                @if($r->status === 'rejected' && $r->rejection_reason)
                                    <div class="text-[11px] text-rose-600 dark:text-rose-400 mt-1 max-w-xs">{{ __('Reason') }}: {{ $r->rejection_reason }}</div>
                                @endif
                            </td>
                            <td class="py-4 px-4 text-right" x-data="{ showReject: false }">
                                <div class="flex flex-wrap items-center justify-end gap-1.5">
                                    @can('approve', $r)
                                        <form method="POST" action="{{ route('procurement.approve_request', $r) }}" class="inline-block">
                                            @csrf
                                            <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-emerald-500 text-slate-950 hover:bg-emerald-400 transition">
                                                {{ __('Approve Request') }}
                                            </button>
                                        </form>
                                    @endcan

                                    @can('reject', $r)
                                        <button type="button" @click="showReject = !showReject" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-rose-500/15 text-rose-600 dark:text-rose-400 hover:bg-rose-500 hover:text-white transition">
                                            {{ __('Reject') }}
                                        </button>
                                    @endcan

                                    @can('cancel', $r)
                                        <form method="POST" action="{{ route('procurement.cancel_request', $r) }}" class="inline-block" onsubmit="return confirm('Cancel this pending purchase request?')">
                                            @csrf
                                            <button type="submit" class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-200 transition">
                                                {{ __('Cancel') }}
                                            </button>
                                        </form>
                                    @endcan

                                    @can('createOrder', $r)
                                        <a href="{{ route('procurement.create_po', $r) }}" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-sky-500 text-slate-950 hover:bg-sky-400 transition">
                                            {{ __('Create Purchase Order') }} &rarr;
                                        </a>
                                    @endcan
                                </div>

                                @can('reject', $r)
                                    <form x-show="showReject" x-cloak method="POST" action="{{ route('procurement.reject_request', $r) }}" class="mt-2 p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-rose-500/30 text-left space-y-2 max-w-xs ml-auto">
                                        @csrf
                                        <label class="block text-[11px] font-bold text-rose-600 dark:text-rose-400">{{ __('Rejection Reason') }} *</label>
                                        <input type="text" name="rejection_reason" required minlength="5" maxlength="500" placeholder="{{ __('Explain why this request is declined...') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-1.5 text-xs text-slate-900 dark:text-white">
                                        <div class="flex justify-end gap-1.5">
                                            <button type="button" @click="showReject = false" class="px-2.5 py-1 text-[11px] rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">{{ __('Close') }}</button>
                                            <button type="submit" class="px-3 py-1 text-[11px] font-bold rounded-lg bg-rose-600 text-white hover:bg-rose-500">{{ __('Confirm Reject') }}</button>
                                        </div>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 px-4 text-center space-y-2">
                                <div class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ __('No pending purchase requests.') }}</div>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('When a site needs materials or tools, raise a purchase request here for approval.') }}</p>
                                @can('create', \App\Models\PurchaseRequest::class)
                                    <div class="pt-1">
                                        <a href="{{ route('procurement.create_request') }}" class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-extrabold shadow-sm transition">
                                            + {{ __('Raise Your First Purchase Request') }}
                                        </a>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Mobile Card List View -->
        <div class="sm:hidden space-y-3 p-4">
            @forelse($requests as $r)
                @php
                    $mobileBadge = match($r->status) {
                        'pending' => ['bg-amber-500/10 text-amber-600 dark:text-amber-400 border border-amber-500/30', __('Pending')],
                        'approved' => ['bg-emerald-500/10 text-emerald-600 dark:text-emerald-400', __('Approved')],
                        'rejected' => ['bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30', __('Rejected')],
                        'cancelled' => ['bg-slate-200/70 text-slate-600 dark:bg-slate-800 dark:text-slate-400', __('Cancelled')],
                        default => ['bg-sky-500/10 text-sky-600 dark:text-sky-400', __('Issued')],
                    };
                @endphp
                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950/70 border border-slate-200 dark:border-slate-800 space-y-2.5" x-data="{ showMobileReject: false }">
                    <div class="flex items-center justify-between">
                        <span class="font-mono font-bold text-slate-900 dark:text-white text-xs">{{ $r->request_number }}</span>
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ $mobileBadge[0] }}">
                            {{ $mobileBadge[1] }}
                        </span>
                    </div>
                    <div>
                        <div class="font-bold text-slate-900 dark:text-white text-sm">{{ $r->site->name ?? $r->warehouse->name ?? __('Head Office') }}</div>
                        <div class="text-xs text-slate-500">{{ __('By') }} {{ $r->requester->name }}</div>
                    </div>
                    @if($r->status === 'rejected' && $r->rejection_reason)
                        <div class="text-[11px] text-rose-600 dark:text-rose-400"><strong>{{ __('Reason') }}:</strong> {{ $r->rejection_reason }}</div>
                    @endif
                    @if($r->is_emergency)
                        <div class="text-[11px] text-rose-700 dark:text-rose-300 p-2 rounded-lg bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-900/40">
                            <strong>{{ __('Emergency Purchase') }}:</strong> {{ $r->emergency_justification }}
                        </div>
                    @endif
                    <div class="space-y-1 text-xs pt-1 border-t border-slate-200 dark:border-slate-800/80">
                        @foreach($r->items as $item)
                            <div class="text-slate-700 dark:text-slate-300"><span class="font-bold text-slate-900 dark:text-white">{{ $item->quantity_requested }} {{ $item->material->unit_of_measure }}</span> &bull; {{ $item->material->name }}</div>
                        @endforeach
                    </div>
                    <div class="flex flex-wrap items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-slate-800/80">
                        @can('approve', $r)
                            <form method="POST" action="{{ route('procurement.approve_request', $r) }}">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-emerald-500 text-slate-950 hover:bg-emerald-400 transition">
                                    {{ __('Approve Request') }}
                                </button>
                            </form>
                        @endcan
                        @can('reject', $r)
                            <button type="button" @click="showMobileReject = !showMobileReject" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-rose-500/15 text-rose-600 dark:text-rose-400 hover:bg-rose-500 hover:text-white transition">
                                {{ __('Reject') }}
                            </button>
                        @endcan
                        @can('cancel', $r)
                            <form method="POST" action="{{ route('procurement.cancel_request', $r) }}" onsubmit="return confirm('Cancel this pending purchase request?')">
                                @csrf
                                <button type="submit" class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-slate-200 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                                    {{ __('Cancel') }}
                                </button>
                            </form>
                        @endcan
                        @can('createOrder', $r)
                            <a href="{{ route('procurement.create_po', $r) }}" class="px-3 py-1.5 text-xs font-bold rounded-lg bg-sky-500 text-slate-950 hover:bg-sky-400 transition">
                                {{ __('Create Purchase Order') }} &rarr;
                            </a>
                        @endcan
                    </div>
                    @can('reject', $r)
                        <form x-show="showMobileReject" x-cloak method="POST" action="{{ route('procurement.reject_request', $r) }}" class="pt-2 border-t border-rose-500/30 space-y-2">
                            @csrf
                            <input type="text" name="rejection_reason" required minlength="5" maxlength="500" placeholder="{{ __('Rejection reason...') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-1.5 text-xs text-slate-900 dark:text-white">
                            <button type="submit" class="w-full py-1.5 text-xs font-bold rounded-lg bg-rose-600 text-white">{{ __('Confirm Reject') }}</button>
                        </form>
                    @endcan
                </div>
            @empty
                <div class="p-6 text-center text-slate-500 text-xs">
                    {{ __('No pending purchase requests.') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

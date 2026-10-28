@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div>
            <div class="flex items-center gap-3">
                <span class="font-mono text-xs font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-amber-600 dark:text-amber-400 border border-slate-200 dark:border-slate-700">{{ $workOrder->order_number }}</span>
                <span class="px-2 py-0.5 rounded text-[11px] font-bold uppercase {{ $workOrder->order_type === 'client_issued' ? 'bg-sky-500/10 text-sky-600 dark:text-sky-400' : 'bg-purple-500/10 text-purple-600 dark:text-purple-400' }}">
                    {{ $workOrder->order_type === 'client_issued' ? __('Client Issued') : __('Internal Work') }}
                </span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $workOrder->title }}</h1>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $workOrder->project->name }} &bull; {{ $workOrder->site->name ?? __('Project Wide') }}</p>
        </div>

        <div class="flex items-center gap-2">
            <a href="{{ route('work_orders.index') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:border-slate-700 transition">
                &larr; {{ __('Back to Orders') }}
            </a>
        </div>
    </div>

    <!-- 2-Tier Approval Stepper -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm transition-colors duration-200">
        <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Multi-Tier Approval Lifecycle') }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
            <!-- Stage 1: Submission -->
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-bold mb-1">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    1. {{ __('Registered') }}
                </div>
                <div class="text-slate-800 dark:text-slate-300 font-semibold">{{ $workOrder->creator->name }}</div>
                <div class="text-[11px] text-slate-500">{{ $workOrder->created_at->format('d M Y, H:i') }}</div>
            </div>

            <!-- Stage 2: Site Manager Approval -->
            <div class="p-4 rounded-xl {{ $workOrder->site_manager_approved_at ? 'bg-slate-50 dark:bg-slate-950 border-emerald-500/40' : ($workOrder->status === 'submitted' ? 'bg-amber-500/10 border-amber-500/40' : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800') }} border">
                <div class="flex items-center gap-2 {{ $workOrder->site_manager_approved_at ? 'text-emerald-600 dark:text-emerald-400' : ($workOrder->status === 'submitted' ? 'text-amber-600 dark:text-amber-400' : 'text-slate-500') }} font-bold mb-1">
                    @if($workOrder->site_manager_approved_at)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @endif
                    2. {{ __('Site Manager') }} ({{ __('Tier 1') }})
                </div>
                @if($workOrder->site_manager_approved_at)
                    <div class="text-slate-800 dark:text-slate-300 font-semibold">{{ $workOrder->siteManager->name }}</div>
                    <div class="text-[11px] text-slate-500">{{ $workOrder->site_manager_approved_at->format('d M Y, H:i') }}</div>
                    @if($workOrder->site_manager_notes)
                        <div class="text-[11px] text-slate-600 dark:text-slate-400 mt-1 italic">"{{ $workOrder->site_manager_notes }}"</div>
                    @endif
                @else
                    <div class="text-slate-500 dark:text-slate-400">{{ __('Awaiting Site Verification') }}</div>
                @endif
            </div>

            <!-- Stage 3: Senior Manager Approval -->
            <div class="p-4 rounded-xl {{ $workOrder->senior_manager_approved_at ? 'bg-slate-50 dark:bg-slate-950 border-emerald-500/40' : ($workOrder->status === 'site_manager_approved' ? 'bg-sky-500/10 border-sky-500/40' : 'bg-slate-50 dark:bg-slate-950 border-slate-200 dark:border-slate-800') }} border">
                <div class="flex items-center gap-2 {{ $workOrder->senior_manager_approved_at ? 'text-emerald-600 dark:text-emerald-400' : ($workOrder->status === 'site_manager_approved' ? 'text-sky-600 dark:text-sky-400' : 'text-slate-500') }} font-bold mb-1">
                    @if($workOrder->senior_manager_approved_at)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    @endif
                    3. {{ __('Senior VP') }} ({{ __('Tier 2') }})
                </div>
                @if($workOrder->senior_manager_approved_at)
                    <div class="text-slate-800 dark:text-slate-300 font-semibold">{{ $workOrder->seniorManager->name }}</div>
                    <div class="text-[11px] text-slate-500">{{ $workOrder->senior_manager_approved_at->format('d M Y, H:i') }}</div>
                    @if($workOrder->senior_manager_notes)
                        <div class="text-[11px] text-slate-600 dark:text-slate-400 mt-1 italic">"{{ $workOrder->senior_manager_notes }}"</div>
                    @endif
                @else
                    <div class="text-slate-500 dark:text-slate-400">{{ __('Awaiting Executive Sign-off') }}</div>
                @endif
            </div>
        </div>

        <!-- Approval Action Buttons (Role Protected) -->
        <div class="pt-4 border-t border-slate-200 dark:border-slate-800 flex flex-wrap items-center gap-3">
            @can('approveSite', $workOrder)
                <form method="POST" action="{{ route('work_orders.approve_site', $workOrder) }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    @csrf
                    <input type="text" name="notes" placeholder="{{ __('Site manager verification notes...') }}" class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white outline-none w-full sm:w-64">
                    <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 transition">
                        {{ __('Tier 1: Approve as Site Manager') }}
                    </button>
                </form>
            @endcan

            @can('approveSenior', $workOrder)
                <form method="POST" action="{{ route('work_orders.approve_senior', $workOrder) }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    @csrf
                    <input type="text" name="notes" placeholder="{{ __('Senior manager authorization notes...') }}" class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs text-slate-900 dark:text-white outline-none w-full sm:w-64">
                    <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-sky-500 hover:bg-sky-400 text-slate-950 transition">
                        {{ __('Tier 2: Final Executive Authorization') }}
                    </button>
                </form>
            @endcan

            @can('reject', $workOrder)
                <form method="POST" action="{{ route('work_orders.reject', $workOrder) }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
                    @csrf
                    <input type="text" name="rejection_reason" required placeholder="{{ __('Mandatory rejection reason...') }}" class="rounded-xl bg-slate-50 dark:bg-slate-950 border border-rose-300 dark:border-rose-900/60 px-3 py-2 text-xs text-slate-900 dark:text-white outline-none w-full sm:w-64">
                    <button type="submit" class="px-4 py-2 text-xs font-bold rounded-xl bg-rose-100 hover:bg-rose-200 text-rose-800 border border-rose-300 dark:bg-rose-900/80 dark:hover:bg-rose-800 dark:text-rose-200 dark:border-rose-700 transition">
                        {{ __('Reject Work Order') }}
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <!-- Details Card -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm transition-colors duration-200">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('Specifications & Scope') }}</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
            <div>
                <span class="text-slate-500">{{ __('Client Reference') }}:</span>
                <span class="font-mono text-slate-900 dark:text-white ml-2">{{ $workOrder->client_reference ?? __('Internal Order') }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ __('Estimated Value') }}:</span>
                <span class="font-mono text-amber-600 dark:text-amber-400 font-bold ml-2">₹{{ number_format($workOrder->estimated_amount, 2) }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ __('Target Start') }}:</span>
                <span class="text-slate-800 dark:text-white ml-2">{{ $workOrder->target_start_date?->format('d M Y') ?? __('Not specified') }}</span>
            </div>
            <div>
                <span class="text-slate-500">{{ __('Target Completion') }}:</span>
                <span class="text-slate-800 dark:text-white ml-2">{{ $workOrder->target_completion_date?->format('d M Y') ?? __('Not specified') }}</span>
            </div>
        </div>

        @if($workOrder->description)
            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-xs">
                <span class="text-slate-500 block mb-1">{{ __('Description') }}:</span>
                <p class="text-slate-700 dark:text-slate-300 leading-relaxed">{{ $workOrder->description }}</p>
            </div>
        @endif

        @if($workOrder->scope_of_work)
            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-xs">
                <span class="text-slate-500 block mb-1">{{ __('Scope of Work') }}:</span>
                <div class="text-slate-800 dark:text-slate-300 font-mono whitespace-pre-line bg-slate-50 dark:bg-slate-950 p-3 rounded-xl border border-slate-200 dark:border-slate-800">{{ $workOrder->scope_of_work }}</div>
            </div>
        @endif

        <!-- Document Attachments -->
        @if($workOrder->attachments->isNotEmpty())
            <div class="pt-3 border-t border-slate-200 dark:border-slate-800 text-xs">
                <span class="text-slate-500 block mb-2 font-bold uppercase">{{ __('Attached Files') }}:</span>
                <div class="space-y-2">
                    @foreach($workOrder->attachments as $att)
                        <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800">
                            <span class="text-slate-800 dark:text-slate-200 font-medium">{{ $att->file_name }} ({{ round($att->file_size / 1024, 1) }} KB)</span>
                            <a href="{{ asset('storage/' . $att->file_path) }}" target="_blank" class="text-amber-600 dark:text-amber-400 font-bold hover:underline">{{ __('Download') }}</a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    <!-- Audited Approval History -->
    <div class="p-5 sm:p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-3 shadow-sm transition-colors duration-200">
        <h3 class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Immutable Audit Trail') }}</h3>
        <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
            @forelse($workOrder->approvals as $audit)
                <div class="py-2.5 flex items-start justify-between">
                    <div>
                        <div class="font-bold text-slate-900 dark:text-white">{{ $audit->actor->name }} ({{ str_replace('_', ' ', $audit->stage) }})</div>
                        <div class="text-slate-500 dark:text-slate-400 italic mt-0.5">{{ $audit->comments ?? __('No comment provided') }}</div>
                    </div>
                    <div class="text-right">
                        <span class="font-bold uppercase {{ $audit->action === 'approved' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">{{ __($audit->action === 'approved' ? 'Approved' : 'Rejected') }}</span>
                        <div class="text-[11px] text-slate-500">{{ $audit->created_at->format('d M Y, H:i') }}</div>
                    </div>
                </div>
            @empty
                <div class="text-slate-500 py-2">{{ __('No audit events recorded yet.') }}</div>
            @endforelse
        </div>
    </div>
</div>
@endsection

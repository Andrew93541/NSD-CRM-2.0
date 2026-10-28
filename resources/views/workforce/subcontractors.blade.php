@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="pb-4 border-b border-slate-200 dark:border-slate-800">
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Subcontractor Companies') }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Vendor partners supplying equipment, plant, and named specialized labour.') }}</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @foreach($subcontractors as $sub)
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 space-y-4 shadow-sm hover:border-amber-500/40 transition transition-colors duration-200">
                <div class="flex items-start justify-between">
                    <div>
                        <span class="font-mono text-xs font-bold text-amber-600 dark:text-amber-400">{{ $sub->code }}</span>
                        <h3 class="text-lg font-bold text-slate-900 dark:text-white mt-1">{{ $sub->name }}</h3>
                    </div>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/30">
                        {{ $sub->workers_count }} {{ __('Workers') }}
                    </span>
                </div>

                <div class="space-y-2 text-xs text-slate-600 dark:text-slate-300">
                    <div class="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800/80">
                        <span class="text-slate-500">{{ __('Contact Person') }}:</span>
                        <span class="font-medium text-slate-900 dark:text-white">{{ $sub->contact_person ?? 'N/A' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800/80">
                        <span class="text-slate-500">{{ __('Phone') }}:</span>
                        <span class="font-mono text-slate-800 dark:text-slate-200">{{ $sub->phone ?? 'N/A' }}</span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-slate-100 dark:border-slate-800/80">
                        <span class="text-slate-500">{{ __('GST') }}:</span>
                        <span class="font-mono text-slate-800 dark:text-slate-200">{{ $sub->gst_number ?? 'N/A' }}</span>
                    </div>
                </div>

                <a href="{{ route('workforce.index', ['type' => 'subcontractor_worker']) }}" class="block w-full text-center py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 dark:text-slate-200 dark:border-slate-700 text-xs font-bold transition">
                    {{ __('View Named Workers') }} &rarr;
                </a>
            </div>
        @endforeach
    </div>
</div>
@endsection

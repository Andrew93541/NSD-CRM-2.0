@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="pb-4 border-b border-slate-200 dark:border-slate-800">
        <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Create Work Order') }}</h1>
        <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Initiates the 2-tier approval lifecycle (Site Manager &rarr; Senior Manager).') }}</p>
    </div>

    <div class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl transition-colors duration-200">
        <form method="POST" action="{{ route('work_orders.store') }}" enctype="multipart/form-data" class="space-y-5">
            @csrf

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Work Order Type') }} *</label>
                    <select name="order_type" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="client_issued">{{ __('Client-Issued Order') }}</option>
                        <option value="internal">{{ __('Internal Operational Order') }}</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Client Reference / Contract #') }}</label>
                    <input type="text" name="client_reference" placeholder="e.g. NHAI-PKG-48-WO-901" value="{{ old('client_reference') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
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

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Title') }} *</label>
                <input type="text" name="title" required placeholder="e.g. Culvert Box Excavation & Precast Placement km 28" value="{{ old('title') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Description') }}</label>
                <textarea name="description" rows="3" placeholder="{{ __('Summary of earthmoving or structural requirements...') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Scope of Work') }}</label>
                <textarea name="scope_of_work" rows="3" placeholder="1. Trench excavation... 2. Embankment grading..." class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">{{ old('scope_of_work') }}</textarea>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Estimated Value') }} (₹)</label>
                    <input type="number" step="0.01" name="estimated_amount" value="{{ old('estimated_amount', 0) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Target Start') }}</label>
                    <input type="date" name="target_start_date" value="{{ old('target_start_date') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Target Completion') }}</label>
                    <input type="date" name="target_completion_date" value="{{ old('target_completion_date') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Document Attachment (PDF, DWG, DOCX)') }}</label>
                <input type="file" name="attachment" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2 text-xs text-slate-600 dark:text-slate-400 file:mr-4 file:py-1 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-100 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-white file:text-slate-800">
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <a href="{{ route('work_orders.index') }}" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:border-slate-700 transition">{{ __('Cancel') }}</a>
                <button type="submit" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition">{{ __('Submit For Tier 1 Approval') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

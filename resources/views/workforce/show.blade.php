@extends('layouts.app')

@section('content')
@php
    $photoDoc = isset($documents) ? $documents->firstWhere('document_type', 'photograph') : null;
@endphp
<div class="space-y-6">
    <!-- Worker Profile Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
        <div class="flex items-start gap-4">
            <div class="w-14 h-14 rounded-2xl bg-amber-500/15 border border-amber-500/30 flex items-center justify-center text-lg font-extrabold text-amber-600 dark:text-amber-400 shrink-0">
                {{ mb_strtoupper(mb_substr($worker->first_name, 0, 1) . mb_substr($worker->last_name ?? '', 0, 1)) }}
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-mono text-xs font-bold px-2.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-amber-600 dark:text-amber-400 border border-slate-200 dark:border-slate-700">{{ $worker->code }}</span>
                    @if($worker->worker_type === 'employee')
                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">{{ __('Direct Employee') }}</span>
                    @elseif($worker->worker_type === 'direct_labour')
                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-sky-500/10 text-sky-600 dark:text-sky-400 border border-sky-500/30">{{ __('Direct Labour') }}</span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-purple-500/10 text-purple-600 dark:text-purple-400 border border-purple-500/30">
                            {{ __('Subcontractor Worker') }}: {{ $worker->subcontractor->name ?? __('External') }}
                        </span>
                    @endif
                    <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold uppercase {{ $worker->status === 'active' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30' }}">
                        {{ __($worker->status === 'active' ? 'Active' : ($worker->status === 'inactive' ? 'Inactive' : 'Terminated')) }}
                    </span>
                </div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white mt-1">{{ $worker->full_name }}</h1>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    {{ __('Trade / Skill') }}: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ $worker->trade_designation ?: __('Not specified') }}</span>
                    @if($worker->department)
                        &bull; {{ __('Department') }}: <span class="font-semibold text-slate-700 dark:text-slate-300">{{ __($worker->department) }}</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('workforce.index') }}" class="px-3.5 py-2 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:border-slate-700 transition">
                &larr; {{ __('Back to Workforce') }}
            </a>
            @can('update', $worker)
                <a href="{{ route('workforce.edit', $worker) }}" class="px-4 py-2 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/20 transition">
                    {{ __('Edit Worker Profile') }}
                </a>
            @endcan
        </div>
    </div>

    @if ($errors->any())
        <div class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-600 dark:text-rose-300 text-xs space-y-1">
            <div class="font-bold">{{ __('Please resolve the following validation issues:') }}</div>
            <ul class="list-disc list-inside space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- Top KPI Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Assigned Primary Site') }}</div>
            <div class="text-sm font-bold text-slate-900 dark:text-white mt-1.5">
                {{ $worker->assignedSite ? ($worker->assignedSite->code . ' — ' . $worker->assignedSite->name) : __('Unassigned') }}
            </div>
            @if($worker->assignedSite && auth()->user()->can('view', $worker->assignedSite))
                <a href="{{ route('sites.show', $worker->assignedSite) }}" class="inline-block mt-2 text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                    {{ __('Inspect Site') }} &rarr;
                </a>
            @endif
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="flex items-center justify-between">
                <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Compensation') }}</div>
                <span class="px-2 py-0.5 rounded bg-amber-500/15 text-amber-700 dark:text-amber-400 text-[10px] font-bold uppercase">{{ $worker->payTypeLabel() }}</span>
            </div>
            <div class="text-2xl font-extrabold font-mono text-slate-900 dark:text-white mt-1">
                ₹{{ number_format($worker->primaryCompensationRate(), 2) }} <span class="text-xs font-normal text-slate-400">{{ $worker->primaryCompensationUnit() }}</span>
            </div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">{{ __('Daily Base') }}: ₹{{ number_format($worker->daily_rate, 2) }} &bull; {{ __('Joining') }}: {{ $worker->joining_date?->format('Y-m-d') ?? __('Not specified') }}</div>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Contact & Identity') }}</div>
            <div class="text-sm font-bold font-mono text-slate-900 dark:text-white mt-1.5">{{ $worker->phone ?: __('No phone') }}</div>
            @if($worker->aadhaar_last_four)
                <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono mt-1">Aadhaar: XXXX-XXXX-{{ $worker->aadhaar_last_four }}</div>
            @else
                <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">{{ __('Aadhaar not recorded') }}</div>
            @endif
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm">
            <div class="text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Verified Documents') }}</div>
            <div class="text-2xl font-extrabold text-amber-600 dark:text-amber-400 mt-1">{{ $documents->count() }}</div>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">{{ __('Private compliance storage') }}</div>
        </div>
    </div>

    <!-- Structured Worker Profile Sections (1–5) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- 1. Personal Information -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="pb-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('1. Personal Information') }}</h2>
                @if($photoDoc && auth()->user()->can('downloadDocument', [$worker, $photoDoc]))
                    <a href="{{ route('workforce.documents.download', [$worker, $photoDoc->id]) }}" class="text-xs font-bold text-amber-600 dark:text-amber-400 hover:underline">
                        {{ __('Download Photo') }}
                    </a>
                @endif
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Full Name') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white mt-0.5">{{ $worker->full_name }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Date of Birth') }}</dt>
                    <dd class="font-bold font-mono text-slate-900 dark:text-white mt-0.5">
                        {{ $worker->date_of_birth ? $worker->date_of_birth->format('Y-m-d') : __('Not specified') }}
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Gender') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white mt-0.5">{{ $worker->genderLabel() ?? __('Not specified') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Aadhaar Identity (Last 4 Digits)') }}</dt>
                    <dd class="font-bold font-mono text-slate-900 dark:text-white mt-0.5">
                        {{ $worker->aadhaar_last_four ? ('XXXX-XXXX-' . $worker->aadhaar_last_four) : __('Not specified') }}
                    </dd>
                </div>
            </dl>
        </div>

        <!-- 2. Contact Information -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="pb-3 border-b border-slate-200 dark:border-slate-800">
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('2. Contact Information') }}</h2>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Mobile Number') }}</dt>
                    <dd class="font-bold font-mono text-slate-900 dark:text-white mt-0.5">{{ $worker->phone ?: __('Not specified') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Alternate Number') }}</dt>
                    <dd class="font-bold font-mono text-slate-900 dark:text-white mt-0.5">{{ $worker->alternate_phone ?: __('Not specified') }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Email Address') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white mt-0.5 break-all">{{ $worker->email ?: __('Not specified') }}</dd>
                </div>
                <div class="sm:col-span-2">
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Residential / Camp Address') }}</dt>
                    <dd class="font-semibold text-slate-800 dark:text-slate-200 mt-0.5 whitespace-pre-line">{{ $worker->address ?: __('Not specified') }}</dd>
                </div>
            </dl>
        </div>

        <!-- 3. Employment Information -->
        <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
            <div class="pb-3 border-b border-slate-200 dark:border-slate-800">
                <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('3. Employment Information') }}</h2>
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Worker Code') }}</dt>
                    <dd class="font-bold font-mono text-amber-600 dark:text-amber-400 mt-0.5">{{ $worker->code }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Worker Category') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white mt-0.5">
                        @if($worker->worker_type === 'employee')
                            {{ __('Direct Employee') }}
                        @elseif($worker->worker_type === 'direct_labour')
                            {{ __('Direct Labour') }}
                        @else
                            {{ __('Subcontractor Worker') }} ({{ $worker->subcontractor->name ?? __('External') }})
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Trade / Skill Designation') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white mt-0.5">{{ $worker->trade_designation ?: __('Not specified') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Department / Work Category') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white mt-0.5">{{ $worker->department ? __($worker->department) : __('Not specified') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Joining Date') }}</dt>
                    <dd class="font-bold font-mono text-slate-900 dark:text-white mt-0.5">{{ $worker->joining_date?->format('Y-m-d') ?? __('Not specified') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Daily Base Rate') }}</dt>
                    <dd class="font-bold font-mono text-slate-900 dark:text-white mt-0.5">₹{{ number_format($worker->daily_rate, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Skill Level') }}</dt>
                    <dd class="font-bold text-slate-900 dark:text-white mt-0.5">{{ $worker->skillLevelLabel() ?? __('Not specified') }}</dd>
                </div>
                <div>
                    <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Overtime Rate / Hr') }}</dt>
                    <dd class="font-bold font-mono text-slate-900 dark:text-white mt-0.5">₹{{ number_format((float) ($worker->overtime_rate ?? 0), 2) }}</dd>
                </div>
            </dl>
        </div>

        <!-- 4. Site Assignment & 5. Emergency Contact -->
        <div class="space-y-6">
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('4. Site Assignment') }}</h2>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="sm:col-span-2">
                        <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Assigned Primary Site') }}</dt>
                        <dd class="font-bold text-slate-900 dark:text-white mt-0.5">
                            @if($worker->assignedSite)
                                <span class="font-mono text-amber-600 dark:text-amber-400">{{ $worker->assignedSite->code }}</span> &mdash; {{ $worker->assignedSite->name }}
                            @else
                                {{ __('Unassigned') }}
                            @endif
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="pb-3 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('5. Emergency Contact') }}</h2>
                </div>
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Emergency Contact Name') }}</dt>
                        <dd class="font-bold text-slate-900 dark:text-white mt-0.5">{{ $worker->emergency_contact_name ?: __('Not specified') }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Relationship') }}</dt>
                        <dd class="font-bold text-slate-900 dark:text-white mt-0.5">{{ $worker->emergency_contact_relation ?: __('Not specified') }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500 dark:text-slate-400 font-semibold">{{ __('Emergency Contact Phone') }}</dt>
                        <dd class="font-bold font-mono text-slate-900 dark:text-white mt-0.5">{{ $worker->emergency_contact_phone ?: __('Not specified') }}</dd>
                    </div>
                </dl>
            </div>

            <!-- QR Attendance Badge & Worker Portal PIN -->
            <div class="p-6 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-4">
                <div class="pb-3 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                    <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('QR Attendance Badge & Portal Credentials') }}</h2>
                    <span class="px-2 py-0.5 rounded bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 text-[10px] font-bold uppercase">{{ __('Verified Token') }}</span>
                </div>
                <div class="text-xs space-y-3">
                    <div>
                        <div class="text-slate-500 dark:text-slate-400 font-semibold mb-1">{{ __('Cryptographic QR Badge Token') }}</div>
                        <div class="p-2.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 font-mono text-xs text-amber-600 dark:text-amber-400 break-all">
                            {{ $worker->qr_token ?? $worker->code }}
                        </div>
                    </div>

                    @can('update', $worker)
                        @if($worker->worker_type === 'employee')
                            <form method="POST" action="{{ route('workforce.reset_pin', $worker) }}" class="pt-2 border-t border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-stretch sm:items-end gap-2">
                                @csrf
                                <div class="flex-1">
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">{{ __('Issue / Reset 6-Digit Worker Portal PIN') }}</label>
                                    <input type="text" name="pin" inputmode="numeric" maxlength="6" pattern="\d{6}" required placeholder="e.g. 123456"
                                        class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 px-3 py-2 text-xs font-mono text-slate-900 dark:text-white">
                                </div>
                                <button type="submit" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs transition">
                                    {{ __('Set Temporary PIN') }}
                                </button>
                            </form>
                        @endif
                    @endcan
                </div>
            </div>
        </div>
    </div>

    <!-- 6. Worker Documents Management Section (Point 3 Integrated) -->
    <div id="worker-documents" class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-6 transition-colors duration-200">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h2 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Worker Documents & Identity Records') }} ({{ $documents->count() }})</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Stored in encrypted/isolated private storage. Never exposed via public links.') }}</p>
            </div>
        </div>

        @can('uploadDocument', $worker)
            <form method="POST" action="{{ route('workforce.documents.store', $worker) }}" enctype="multipart/form-data"
                  x-data="{
                      rows: [{ id: 1, type: 'aadhaar_identity', title: '' }],
                      uploading: false,
                      addRow() {
                          if (this.rows.length < 10) {
                              this.rows.push({ id: Date.now() + Math.random(), type: 'aadhaar_identity', title: '' });
                          }
                      },
                      removeRow(idx) {
                          if (this.rows.length > 1) {
                              this.rows.splice(idx, 1);
                          }
                      }
                  }"
                  @submit="uploading = true"
                  class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
                @csrf
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider">{{ __('Upload Worker Documents') }}</div>
                    <button type="button" @click="addRow()" class="self-start sm:self-auto px-2.5 py-1 rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-xs font-bold text-slate-700 dark:text-slate-300 hover:border-amber-500 transition">
                        + {{ __('Add Another File') }}
                    </button>
                </div>

                <div class="space-y-3">
                    <template x-for="(row, index) in rows" :key="row.id">
                        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end p-3 rounded-lg bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                            <div class="sm:col-span-3">
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Document Category') }} *</label>
                                <select name="document_types[]" x-model="row.type" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                                    @foreach($documentTypes as $typeKey => $typeLabel)
                                        <option value="{{ $typeKey }}">{{ __($typeLabel) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Document Title / Reference') }}</label>
                                <input type="text" name="document_titles[]" x-model="row.title" placeholder="{{ __('Optional note...') }}" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Expiry Date') }}</label>
                                <input type="date" name="document_expiry_dates[]" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-2 py-2 text-xs font-mono text-slate-900 dark:text-white">
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Select File') }} *</label>
                                <input type="file" name="documents[]" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" class="w-full text-xs text-slate-600 dark:text-slate-300 file:mr-2 file:py-1.5 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-amber-500/15 file:text-amber-700 dark:file:text-amber-400 hover:file:bg-amber-500/25">
                            </div>
                            <div class="sm:col-span-1 flex justify-end">
                                <button type="button" x-show="rows.length > 1" @click="removeRow(index)" class="px-2.5 py-2 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white text-xs font-bold transition">
                                    &times;
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <span class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('Allowed: PDF, JPG, PNG, WEBP, DOC, DOCX (Max 5 MB each)') }}</span>
                    <button type="submit" :disabled="uploading" class="px-4 py-2 rounded-xl bg-amber-500 hover:bg-amber-400 disabled:opacity-60 text-slate-950 text-xs font-extrabold shadow-sm transition">
                        <span x-show="!uploading">{{ __('Upload Documents') }}</span>
                        <span x-show="uploading" x-cloak>{{ __('Uploading...') }}</span>
                    </button>
                </div>
            </form>
        @endcan

        <!-- Existing Documents List -->
        <div class="divide-y divide-slate-200 dark:divide-slate-800 text-xs">
            @forelse($documents as $doc)
                <div class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-amber-500/10 text-amber-700 dark:text-amber-400 border border-amber-500/30">
                                {{ $doc->typeLabel() }}
                            </span>
                            <span class="font-bold text-slate-900 dark:text-white break-all">{{ $doc->original_name }}</span>
                            @if($doc->title)
                                <span class="text-slate-500 dark:text-slate-400">&mdash; {{ $doc->title }}</span>
                            @endif
                            @if($doc->expiryStatus() === 'expired')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-rose-500/15 text-rose-600 dark:text-rose-400 border border-rose-500/30">{{ __('Expired') }} ({{ $doc->expiry_date?->format('Y-m-d') }})</span>
                            @elseif($doc->expiryStatus() === 'expiring_soon')
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-amber-500/15 text-amber-700 dark:text-amber-300 border border-amber-500/30">{{ __('Expiring Soon') }} ({{ $doc->expiry_date?->format('Y-m-d') }})</span>
                            @elseif($doc->expiry_date)
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">{{ __('Valid until') }} {{ $doc->expiry_date->format('Y-m-d') }}</span>
                            @endif
                        </div>
                        <div class="text-[11px] text-slate-500 dark:text-slate-400 font-mono">
                            {{ $doc->formattedSize() }} &bull; {{ $doc->mime_type }} &bull; {{ __('Uploaded by') }} {{ $doc->uploader->name ?? __('System') }} ({{ $doc->created_at?->format('Y-m-d H:i') }})
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        @can('downloadDocument', [$worker, $doc])
                            <a href="{{ route('workforce.documents.download', [$worker, $doc->id]) }}" class="px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-amber-500 hover:text-slate-950 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-amber-500 dark:hover:text-slate-950 font-bold transition">
                                {{ __('Download') }}
                            </a>
                        @endcan
                        @can('deleteDocument', [$worker, $doc])
                            <form method="POST" action="{{ route('workforce.documents.destroy', [$worker, $doc->id]) }}" onsubmit="return confirm('{{ __('Delete this worker document permanently?') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="px-3 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white border border-rose-500/30 font-bold transition">
                                    {{ __('Delete') }}
                                </button>
                            </form>
                        @endcan
                    </div>
                </div>
            @empty
                <div class="py-6 text-center text-slate-500 dark:text-slate-400">
                    {{ __('No documents uploaded for this worker yet.') }}
                </div>
            @endforelse
        </div>
    </div>

    <!-- 7. Dated Site Assignment & Transfer History -->
    <div id="worker-site-assignments" class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-sm space-y-5 transition-colors duration-200">
        <div>
            <h2 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Dated Site Assignments & Transfer History') }}</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Full historical ledger of every site this worker has been deployed to. Transferring to a new site automatically closes the previous active assignment.') }}</p>
        </div>

        @can('update', $worker)
            <form method="POST" action="{{ route('workforce.transfer', $worker) }}" class="p-4 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 space-y-3">
                @csrf
                <div class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase">{{ __('Transfer Worker to Another Site') }}</div>
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Destination Site') }} *</label>
                        <select name="site_id" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                            <option value="">{{ __('Select Site...') }}</option>
                            @foreach($sites ?? [] as $s)
                                <option value="{{ $s->id }}">{{ $s->code }} — {{ $s->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Role on Site') }}</label>
                        <input type="text" name="role_on_site" value="{{ $worker->trade_designation }}" maxlength="80" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Effective Start Date') }} *</label>
                        <input type="date" name="start_date" value="{{ now()->toDateString() }}" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs font-mono text-slate-900 dark:text-white">
                    </div>
                    <div class="flex items-end">
                        <button type="submit" class="w-full px-4 py-2 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-extrabold text-xs transition">
                            {{ __('Transfer Worker') }}
                        </button>
                    </div>
                </div>
                <div>
                    <input type="text" name="notes" placeholder="{{ __('Optional transfer reason or deployment note...') }}" maxlength="255" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-3 py-2 text-xs text-slate-900 dark:text-white">
                </div>
            </form>
        @endcan

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 dark:border-slate-800 text-slate-500 dark:text-slate-400 uppercase text-[11px]">
                        <th class="py-2.5 pr-4">{{ __('Site') }}</th>
                        <th class="py-2.5 px-4">{{ __('Role on Site') }}</th>
                        <th class="py-2.5 px-4">{{ __('Start Date') }}</th>
                        <th class="py-2.5 px-4">{{ __('End Date') }}</th>
                        <th class="py-2.5 px-4">{{ __('Status') }}</th>
                        <th class="py-2.5 pl-4">{{ __('Notes') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-slate-800">
                    @forelse($siteAssignments ?? [] as $sa)
                        <tr>
                            <td class="py-2.5 pr-4 font-bold text-slate-900 dark:text-white">
                                @if($sa->site)
                                    <span class="font-mono text-amber-600 dark:text-amber-400 mr-1">{{ $sa->site->code }}</span> {{ $sa->site->name }}
                                @else
                                    {{ __('Unknown Site') }}
                                @endif
                            </td>
                            <td class="py-2.5 px-4 text-slate-700 dark:text-slate-300">{{ $sa->role_on_site }}</td>
                            <td class="py-2.5 px-4 font-mono text-slate-700 dark:text-slate-300">{{ $sa->start_date?->format('Y-m-d') }}</td>
                            <td class="py-2.5 px-4 font-mono text-slate-500 dark:text-slate-400">{{ $sa->end_date ? $sa->end_date->format('Y-m-d') : '—' }}</td>
                            <td class="py-2.5 px-4">
                                @if($sa->is_active)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30">{{ __('Active') }}</span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase bg-slate-500/10 text-slate-600 dark:text-slate-400 border border-slate-500/30">{{ __('Historical') }}</span>
                                @endif
                            </td>
                            <td class="py-2.5 pl-4 text-slate-500 dark:text-slate-400">{{ $sa->notes ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-4 text-slate-500 dark:text-slate-400">{{ __('No dated site assignment records logged yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <div class="pb-4 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Edit Worker Profile') }}</h1>
            <p class="text-sm text-slate-600 dark:text-slate-400 mt-1">{{ __('Update details for') }} <span class="font-mono text-amber-600 dark:text-amber-400 font-bold">{{ $worker->code }}</span> &mdash; {{ $worker->full_name }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('workforce.show', $worker) }}" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-800 border border-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700 dark:border-slate-700 transition">
                {{ __('View Profile') }}
            </a>
            <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider {{ $worker->status === 'active' ? 'bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/10 text-rose-600 dark:text-rose-400 border border-rose-500/30' }}">
                {{ __($worker->status === 'active' ? 'Active' : ($worker->status === 'inactive' ? 'Inactive' : 'Terminated')) }}
            </span>
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

    <div class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl transition-colors duration-200"
         x-data="{
            workerType: '{{ old('worker_type', $worker->worker_type) }}',
            docRows: [],
            submitting: false,
            addDocRow() {
                if (this.docRows.length < 10) {
                    this.docRows.push({ id: Date.now() + Math.random(), type: 'aadhaar_identity', title: '' });
                }
            },
            removeDocRow(idx) {
                this.docRows.splice(idx, 1);
            }
         }">
        <form method="POST" action="{{ route('workforce.update', $worker) }}" enctype="multipart/form-data" class="space-y-5" @submit="submitting = true">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">{{ __('Worker Category') }} *</label>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <label class="cursor-pointer border rounded-xl p-3 text-center transition" :class="workerType === 'employee' ? 'border-amber-500 bg-amber-500/10 text-amber-700 dark:text-white font-bold' : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400'">
                        <input type="radio" name="worker_type" value="employee" x-model="workerType" class="hidden">
                        <div class="text-xs">{{ __('Direct Employee') }}</div>
                    </label>
                    <label class="cursor-pointer border rounded-xl p-3 text-center transition" :class="workerType === 'direct_labour' ? 'border-amber-500 bg-amber-500/10 text-amber-700 dark:text-white font-bold' : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400'">
                        <input type="radio" name="worker_type" value="direct_labour" x-model="workerType" class="hidden">
                        <div class="text-xs">{{ __('Direct Labour') }}</div>
                    </label>
                    <label class="cursor-pointer border rounded-xl p-3 text-center transition" :class="workerType === 'subcontractor_worker' ? 'border-amber-500 bg-amber-500/10 text-amber-700 dark:text-white font-bold' : 'border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-950 text-slate-600 dark:text-slate-400'">
                        <input type="radio" name="worker_type" value="subcontractor_worker" x-model="workerType" class="hidden">
                        <div class="text-xs">{{ __('Subcontractor Worker') }}</div>
                    </label>
                </div>
            </div>

            <div x-show="workerType === 'subcontractor_worker'" class="p-4 rounded-xl bg-purple-50 dark:bg-purple-950/40 border border-purple-200 dark:border-purple-800/60 space-y-2">
                <label class="block text-xs font-bold text-purple-700 dark:text-purple-300 uppercase tracking-wider">{{ __('Subcontractor Company') }} *</label>
                <select name="subcontractor_id" class="w-full rounded-xl bg-white dark:bg-slate-950 border border-purple-300 dark:border-purple-800/80 p-2.5 text-sm text-slate-900 dark:text-white">
                    <option value="">{{ __('Select Subcontractor Firm...') }}</option>
                    @foreach($subcontractors as $sub)
                        <option value="{{ $sub->id }}" {{ old('subcontractor_id', $worker->subcontractor_id) == $sub->id ? 'selected' : '' }}>{{ $sub->code }} — {{ $sub->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('First Name') }} *</label>
                    <input type="text" name="first_name" required value="{{ old('first_name', $worker->first_name) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Last Name') }}</label>
                    <input type="text" name="last_name" value="{{ old('last_name', $worker->last_name) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Trade / Skill Designation') }} *</label>
                    <input type="text" name="trade_designation" required value="{{ old('trade_designation', $worker->trade_designation) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Phone Number') }}</label>
                    <input type="text" name="phone" value="{{ old('phone', $worker->phone) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Assigned Primary Site') }}</label>
                    <select name="assigned_site_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="">{{ __('Unassigned') }}</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}" {{ old('assigned_site_id', $worker->assigned_site_id) == $s->id ? 'selected' : '' }}>{{ $s->code }} — {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Daily Base Rate') }} (₹) *</label>
                    <input type="number" step="0.01" name="daily_rate" required value="{{ old('daily_rate', $worker->daily_rate) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Status') }} *</label>
                    <select name="status" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="active" {{ old('status', $worker->status) == 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                        <option value="inactive" {{ old('status', $worker->status) == 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                        <option value="terminated" {{ old('status', $worker->status) == 'terminated' ? 'selected' : '' }}>{{ __('Terminated') }}</option>
                    </select>
                </div>
            </div>

            <!-- Optional Document Upload During Edit -->
            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div>
                        <h2 class="text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">{{ __('Attach New Documents With Profile Save (Optional)') }}</h2>
                        <p class="text-[11px] text-slate-500 dark:text-slate-400">{{ __('PDF, JPG, PNG, WEBP, DOC, DOCX — max 5 MB per file.') }}</p>
                    </div>
                    <button type="button" @click="addDocRow()" class="self-start sm:self-auto inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-amber-500 hover:text-slate-950 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-amber-500 dark:hover:text-slate-950 text-xs font-bold transition">
                        + {{ __('Add Document Row') }}
                    </button>
                </div>

                <div class="space-y-3" x-show="docRows.length > 0">
                    <template x-for="(row, index) in docRows" :key="row.id">
                        <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-200 dark:border-slate-800 grid grid-cols-1 sm:grid-cols-12 gap-3 items-end">
                            <div class="sm:col-span-4">
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Document Category') }} *</label>
                                <select name="document_types[]" x-model="row.type" required class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                                    @foreach($documentTypes ?? \App\Models\WorkerDocument::documentTypes() as $typeKey => $typeLabel)
                                        <option value="{{ $typeKey }}">{{ __($typeLabel) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Document Title / Reference') }}</label>
                                <input type="text" name="document_titles[]" x-model="row.title" placeholder="{{ __('Optional note...') }}" class="w-full rounded-lg bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                            </div>
                            <div class="sm:col-span-4">
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Select File') }} *</label>
                                <input type="file" name="documents[]" required accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" class="w-full text-xs text-slate-600 dark:text-slate-300 file:mr-2 file:py-1.5 file:px-2.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-amber-500/15 file:text-amber-700 dark:file:text-amber-400 hover:file:bg-amber-500/25">
                            </div>
                            <div class="sm:col-span-1 flex justify-end">
                                <button type="button" @click="removeDocRow(index)" class="px-2.5 py-2 rounded-lg bg-rose-500/10 hover:bg-rose-500 text-rose-600 hover:text-white text-xs font-bold transition" title="{{ __('Remove') }}">
                                    &times;
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <a href="{{ route('workforce.index') }}" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:border-slate-700 transition">{{ __('Cancel') }}</a>
                <button type="submit" :disabled="submitting" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 disabled:opacity-60 text-slate-950 shadow-lg shadow-amber-500/20 transition">
                    <span x-show="!submitting">{{ __('Save Changes') }}</span>
                    <span x-show="submitting" x-cloak>{{ __('Uploading & Saving...') }}</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Existing Worker Documents & Direct Upload Card -->
    <div id="worker-documents" class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl space-y-6 transition-colors duration-200">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h2 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Worker Documents & Identity Records') }} ({{ isset($documents) ? $documents->count() : 0 }})</h2>
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
                            <div class="sm:col-span-4">
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Document Category') }} *</label>
                                <select name="document_types[]" x-model="row.type" required class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                                    @foreach($documentTypes ?? \App\Models\WorkerDocument::documentTypes() as $typeKey => $typeLabel)
                                        <option value="{{ $typeKey }}">{{ __($typeLabel) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="sm:col-span-3">
                                <label class="block text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase mb-1">{{ __('Document Title / Reference') }}</label>
                                <input type="text" name="document_titles[]" x-model="row.title" placeholder="{{ __('Optional note...') }}" class="w-full rounded-lg bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-700 px-2.5 py-2 text-xs text-slate-900 dark:text-white">
                            </div>
                            <div class="sm:col-span-4">
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
            @forelse($documents ?? [] as $doc)
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
</div>
@endsection

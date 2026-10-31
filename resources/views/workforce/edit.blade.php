@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
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
            addDocRow(defaultType = 'aadhaar_identity', defaultTitle = '') {
                if (this.docRows.length < 10) {
                    this.docRows.push({ id: Date.now() + Math.random(), type: defaultType, title: defaultTitle });
                }
            },
            removeDocRow(idx) {
                this.docRows.splice(idx, 1);
            }
         }">
        <form method="POST" action="{{ route('workforce.update', $worker) }}" enctype="multipart/form-data" class="space-y-8" @submit="submitting = true">
            @csrf
            @method('PUT')

            <!-- SECTION 1: PERSONAL INFORMATION -->
            <section class="space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('1. Personal Information') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Full name, demographic details, and masked identity verification.') }}</p>
                    </div>
                    <button type="button" @click="addDocRow('photograph', 'Passport Photograph')" class="self-start sm:self-auto px-3 py-1.5 rounded-lg bg-amber-500/10 hover:bg-amber-500/20 text-amber-700 dark:text-amber-400 border border-amber-500/30 text-xs font-bold transition">
                        + {{ __('Attach Passport Photograph') }}
                    </button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="first_name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('First Name') }} *</label>
                        <input id="first_name" type="text" name="first_name" required maxlength="100" value="{{ old('first_name', $worker->first_name) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    </div>
                    <div>
                        <label for="last_name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Last Name') }}</label>
                        <input id="last_name" type="text" name="last_name" maxlength="100" value="{{ old('last_name', $worker->last_name) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="date_of_birth" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Date of Birth') }}</label>
                        <input id="date_of_birth" type="date" name="date_of_birth" value="{{ old('date_of_birth', $worker->date_of_birth?->format('Y-m-d')) }}" max="{{ now()->subDay()->format('Y-m-d') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    </div>
                    <div>
                        <label for="gender" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Gender') }}</label>
                        <select id="gender" name="gender" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                            <option value="">{{ __('Select Gender...') }}</option>
                            @foreach($genders ?? \App\Models\Worker::GENDERS as $genderKey => $genderLabel)
                                <option value="{{ $genderKey }}" {{ old('gender', $worker->gender) === $genderKey ? 'selected' : '' }}>{{ __($genderLabel) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="aadhaar_last_four" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Aadhaar Last 4 Digits') }}</label>
                        <input id="aadhaar_last_four" type="text" name="aadhaar_last_four" inputmode="numeric" pattern="\d{4}" maxlength="4" placeholder="1234" value="{{ old('aadhaar_last_four', $worker->aadhaar_last_four) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm font-mono text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
                        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">{{ __('Privacy safe: store only last 4 digits.') }}</p>
                    </div>
                </div>
            </section>

            <!-- SECTION 2: CONTACT INFORMATION -->
            <section class="space-y-4">
                <div class="pb-2 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('2. Contact Information') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Primary mobile number, alternate number, email, and residential/camp address.') }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="phone" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Mobile Number') }}</label>
                        <input id="phone" type="text" name="phone" maxlength="30" placeholder="+91 9876543210" value="{{ old('phone', $worker->phone) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
                    </div>
                    <div>
                        <label for="alternate_phone" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Alternate Number') }}</label>
                        <input id="alternate_phone" type="text" name="alternate_phone" maxlength="30" placeholder="+91 ..." value="{{ old('alternate_phone', $worker->alternate_phone) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
                    </div>
                    <div>
                        <label for="email" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Email Address') }}</label>
                        <input id="email" type="email" name="email" maxlength="255" placeholder="worker@example.com" value="{{ old('email', $worker->email) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
                    </div>
                </div>

                <div>
                    <label for="address" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Residential / Camp Address') }}</label>
                    <textarea id="address" name="address" rows="2" maxlength="1000" placeholder="{{ __('House/Room No, Labour Camp or Village, District, State, PIN...') }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">{{ old('address', $worker->address) }}</textarea>
                </div>
            </section>

            <!-- SECTION 3: EMPLOYMENT INFORMATION -->
            <section class="space-y-4">
                <div class="pb-2 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('3. Employment Information') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Worker type, trade designation, department, joining date, status, and daily base rate.') }}</p>
                </div>

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
                    <label for="subcontractor_id" class="block text-xs font-bold text-purple-700 dark:text-purple-300 uppercase tracking-wider">{{ __('Subcontractor Company') }} *</label>
                    <select id="subcontractor_id" name="subcontractor_id" class="w-full rounded-xl bg-white dark:bg-slate-950 border border-purple-300 dark:border-purple-800/80 p-2.5 text-sm text-slate-900 dark:text-white">
                        <option value="">{{ __('Select Subcontractor Firm...') }}</option>
                        @foreach($subcontractors as $sub)
                            <option value="{{ $sub->id }}" {{ old('subcontractor_id', $worker->subcontractor_id) == $sub->id ? 'selected' : '' }}>{{ $sub->code }} — {{ $sub->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="trade_designation" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Trade / Skill Designation') }} *</label>
                        <input id="trade_designation" type="text" name="trade_designation" required maxlength="100" value="{{ old('trade_designation', $worker->trade_designation) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    </div>
                    <div>
                        <label for="skill_level" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Skill Level') }}</label>
                        <select id="skill_level" name="skill_level" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                            <option value="">{{ __('Select Skill Level...') }}</option>
                            @foreach($skillLevels ?? \App\Models\Worker::SKILL_LEVELS as $skKey => $skLabel)
                                <option value="{{ $skKey }}" {{ old('skill_level', $worker->skill_level) === $skKey ? 'selected' : '' }}>{{ __($skLabel) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="department" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Department / Work Category') }}</label>
                        <input id="department" type="text" name="department" list="worker-departments-list" maxlength="100" placeholder="{{ __('e.g. Civil & Earthworks, Electrical & MEP') }}" value="{{ old('department', $worker->department) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
                        <datalist id="worker-departments-list">
                            @foreach($departments ?? \App\Models\Worker::DEPARTMENTS as $dept)
                                <option value="{{ $dept }}">{{ __($dept) }}</option>
                            @endforeach
                        </datalist>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label for="joining_date" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Joining Date') }}</label>
                        <input id="joining_date" type="date" name="joining_date" value="{{ old('joining_date', $worker->joining_date?->format('Y-m-d')) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    </div>
                    <div>
                        <label for="status" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Employment Status') }} *</label>
                        <select id="status" name="status" required class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                            <option value="active" {{ old('status', $worker->status) == 'active' ? 'selected' : '' }}>{{ __('Active') }}</option>
                            <option value="inactive" {{ old('status', $worker->status) == 'inactive' ? 'selected' : '' }}>{{ __('Inactive') }}</option>
                            <option value="terminated" {{ old('status', $worker->status) == 'terminated' ? 'selected' : '' }}>{{ __('Terminated') }}</option>
                        </select>
                    </div>
                    <div>
                        <label for="daily_rate" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Daily Base Rate') }} (₹) *</label>
                        <input id="daily_rate" type="number" step="0.01" min="0" name="daily_rate" required value="{{ old('daily_rate', $worker->daily_rate) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm font-mono text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    </div>
                    <div>
                        <label for="overtime_rate" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Overtime Rate / Hr') }} (₹)</label>
                        <input id="overtime_rate" type="number" step="0.01" min="0" name="overtime_rate" value="{{ old('overtime_rate', $worker->overtime_rate ?? 0) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm font-mono text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                    </div>
                </div>
            </section>

            <!-- SECTION 4: SITE ASSIGNMENT -->
            <section class="space-y-4">
                <div class="pb-2 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('4. Site Assignment') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Assign worker to an active company site within your authorized site scope.') }}</p>
                </div>

                <div>
                    <label for="assigned_site_id" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Assigned Primary Site') }}</label>
                    <select id="assigned_site_id" name="assigned_site_id" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white focus:border-amber-500 outline-none">
                        <option value="">{{ __('Unassigned') }}</option>
                        @foreach($sites as $s)
                            <option value="{{ $s->id }}" {{ old('assigned_site_id', $worker->assigned_site_id) == $s->id ? 'selected' : '' }}>{{ $s->code }} — {{ $s->name }}</option>
                        @endforeach
                    </select>
                </div>
            </section>

            <!-- SECTION 5: EMERGENCY CONTACT -->
            <section class="space-y-4">
                <div class="pb-2 border-b border-slate-200 dark:border-slate-800">
                    <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('5. Emergency Contact') }}</h2>
                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Next-of-kin or emergency responder details for site safety compliance.') }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label for="emergency_contact_name" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Emergency Contact Name') }}</label>
                        <input id="emergency_contact_name" type="text" name="emergency_contact_name" maxlength="150" placeholder="{{ __('Full Name') }}" value="{{ old('emergency_contact_name', $worker->emergency_contact_name) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
                    </div>
                    <div>
                        <label for="emergency_contact_relation" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Relationship') }}</label>
                        <input id="emergency_contact_relation" type="text" name="emergency_contact_relation" maxlength="60" placeholder="{{ __('e.g. Spouse, Father, Brother') }}" value="{{ old('emergency_contact_relation', $worker->emergency_contact_relation) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
                    </div>
                    <div>
                        <label for="emergency_contact_phone" class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase mb-1">{{ __('Emergency Contact Phone') }}</label>
                        <input id="emergency_contact_phone" type="text" name="emergency_contact_phone" maxlength="30" placeholder="+91 ..." value="{{ old('emergency_contact_phone', $worker->emergency_contact_phone) }}" class="w-full rounded-xl bg-slate-50 dark:bg-slate-950 border border-slate-300 dark:border-slate-800 p-2.5 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:border-amber-500 outline-none">
                    </div>
                </div>
            </section>

            <!-- SECTION 6: ATTACH NEW DOCUMENTS WITH PROFILE SAVE (OPTIONAL) -->
            <section class="space-y-3">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-2 border-b border-slate-200 dark:border-slate-800">
                    <div>
                        <h2 class="text-sm font-extrabold text-slate-900 dark:text-white uppercase tracking-wider">{{ __('6. Attach New Documents With Profile Save (Optional)') }}</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('PDF, JPG, PNG, WEBP, DOC, DOCX — max 5 MB per file.') }}</p>
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
            </section>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-800">
                <a href="{{ route('workforce.index') }}" class="px-4 py-2.5 text-xs font-bold rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700 dark:border-slate-700 transition">{{ __('Cancel') }}</a>
                <button type="submit" :disabled="submitting" class="px-6 py-2.5 text-xs font-extrabold rounded-xl bg-amber-500 hover:bg-amber-400 disabled:opacity-60 text-slate-950 shadow-lg shadow-amber-500/20 transition">
                    <span x-show="!submitting">{{ __('Save Changes') }}</span>
                    <span x-show="submitting" x-cloak>{{ __('Uploading & Saving...') }}</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Existing Worker Documents List Card -->
    <div id="worker-documents" class="p-6 sm:p-8 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xl space-y-4 transition-colors duration-200">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h2 class="text-base font-extrabold text-slate-900 dark:text-white tracking-tight">{{ __('Worker Documents & Identity Records') }} ({{ isset($documents) ? $documents->count() : 0 }})</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ __('Stored in encrypted/isolated private storage. Never exposed via public links.') }}</p>
            </div>
        </div>

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

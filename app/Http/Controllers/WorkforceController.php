<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Site;
use App\Models\SiteAssignment;
use App\Models\Subcontractor;
use App\Models\Worker;
use App\Models\WorkerDocument;
use App\Services\Security\TenantContext;
use App\Services\Workforce\WorkerCodeGenerator;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkforceController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Worker::class);

        $user = Auth::user();
        $query = Worker::with(['subcontractor', 'assignedSite']);

        if (Schema::hasTable('worker_documents')) {
            $query->withCount('documents');
        }

        if (!$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])
            && !$user->hasPermission('workforce.manage')
        ) {
            $companyId = TenantContext::getCompanyId();
            $assignedSiteIds = Site::where('company_id', $companyId)
                ->get()
                ->filter(fn (Site $site) => $user->isAssignedToSite($site))
                ->pluck('id')
                ->all();

            $query->where(function ($q) use ($assignedSiteIds) {
                $q->whereNull('assigned_site_id')
                  ->orWhereIn('assigned_site_id', $assignedSiteIds);
            });
        }

        if ($request->filled('type')) {
            $query->where('worker_type', $request->query('type'));
        }

        if ($request->filled('site_id')) {
            $query->where('assigned_site_id', $request->query('site_id'));
        } elseif (!$request->has('site_id') && session()->has('active_site_id') && session('active_site_id')) {
            $query->where('assigned_site_id', session('active_site_id'));
        }

        if ($request->filled('compliance_filter') && Schema::hasColumn('worker_documents', 'expiry_date')) {
            $todayStr = Carbon::today()->toDateString();
            $soonStr = Carbon::today()->addDays(30)->toDateString();
            $cf = $request->query('compliance_filter');

            if ($cf === 'expired') {
                $query->whereHas('documents', fn ($dq) => $dq->whereNotNull('expiry_date')->whereDate('expiry_date', '<', $todayStr));
            } elseif ($cf === 'expiring_soon') {
                $query->whereHas('documents', fn ($dq) => $dq->whereNotNull('expiry_date')->whereDate('expiry_date', '>=', $todayStr)->whereDate('expiry_date', '<=', $soonStr));
            }
        }

        if ($request->filled('search')) {
            $search = '%' . $request->query('search') . '%';
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', $search)
                  ->orWhere('last_name', 'like', $search)
                  ->orWhere('code', 'like', $search)
                  ->orWhere('phone', 'like', $search)
                  ->orWhere('trade_designation', 'like', $search);
            });
        }

        $expiredDocsCount = 0;
        $expiringSoonDocsCount = 0;
        if (Schema::hasTable('worker_documents') && Schema::hasColumn('worker_documents', 'expiry_date')) {
            $todayStr = Carbon::today()->toDateString();
            $soonStr = Carbon::today()->addDays(30)->toDateString();
            $expiredDocsCount = WorkerDocument::whereNotNull('expiry_date')
                ->whereDate('expiry_date', '<', $todayStr)
                ->count();
            $expiringSoonDocsCount = WorkerDocument::whereNotNull('expiry_date')
                ->whereDate('expiry_date', '>=', $todayStr)
                ->whereDate('expiry_date', '<=', $soonStr)
                ->count();
        }

        $workers = $query->latest('id')->paginate(25)->withQueryString();
        $sites = $this->authorizedActiveSites();
        $subcontractors = Subcontractor::where('is_active', true)->get();

        return view('workforce.index', compact(
            'workers',
            'sites',
            'subcontractors',
            'expiredDocsCount',
            'expiringSoonDocsCount'
        ));
    }

    public function create(): View
    {
        Gate::authorize('create', Worker::class);

        $companyId = (int) TenantContext::getCompanyId();
        $sites = $this->authorizedActiveSites();
        $subcontractors = Subcontractor::where('is_active', true)->get();
        $documentTypes = WorkerDocument::documentTypes();
        $genders = Worker::GENDERS;
        $departments = Worker::DEPARTMENTS;
        $skillLevels = Worker::SKILL_LEVELS;
        $payTypes = Worker::PAY_TYPES;
        $previewCodes = [
            'employee' => WorkerCodeGenerator::previewNext($companyId, 'employee'),
            'subcontractor_worker' => WorkerCodeGenerator::previewNext($companyId, 'subcontractor_worker'),
            'direct_labour' => WorkerCodeGenerator::previewNext($companyId, 'direct_labour'),
        ];

        return view('workforce.create', compact(
            'sites',
            'subcontractors',
            'documentTypes',
            'genders',
            'departments',
            'skillLevels',
            'payTypes',
            'previewCodes'
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Worker::class);

        $companyId = TenantContext::getCompanyId();

        $validated = $request->validate(array_merge(
            $this->profileValidationRules($companyId, false),
            $this->documentValidationRules()
        ));

        if ($validated['worker_type'] === 'subcontractor_worker' && empty($validated['subcontractor_id'])) {
            return back()->withErrors(['subcontractor_id' => 'Subcontractor company is mandatory for subcontractor workers.'])->withInput();
        }

        if (!empty($validated['assigned_site_id']) && !Auth::user()->isAssignedToSite((int) $validated['assigned_site_id'])) {
            abort(403, 'You are not authorized to assign workers to this site.');
        }

        $uploadedFiles = $request->file('documents', []);
        if (!is_array($uploadedFiles)) {
            throw ValidationException::withMessages(['documents' => ['Invalid documents payload.']]);
        }
        $this->validateDocumentBatch($uploadedFiles, (array) $request->input('document_types', []));

        $hasExtendedColumns = Schema::hasColumn('workers', 'date_of_birth');
        $hasPhase2Columns = Schema::hasColumn('workers', 'skill_level');

        $worker = DB::transaction(function () use ($validated, $companyId, $uploadedFiles, $request, $hasExtendedColumns, $hasPhase2Columns) {
            $code = WorkerCodeGenerator::generate(
                (int) $companyId,
                $validated['worker_type'],
                $validated['joining_date'] ?? null
            );

            $attributes = [
                'worker_type' => $validated['worker_type'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'aadhaar_last_four' => $validated['aadhaar_last_four'] ?? null,
                'trade_designation' => $validated['trade_designation'],
                'assigned_site_id' => $validated['assigned_site_id'] ?? null,
                'subcontractor_id' => $validated['worker_type'] === 'subcontractor_worker' ? ($validated['subcontractor_id'] ?? null) : null,
                'pay_type' => $validated['pay_type'] ?? 'daily',
                'daily_rate' => $validated['daily_rate'] ?? 0.00,
                'monthly_salary' => $validated['monthly_salary'] ?? 0.00,
                'hourly_rate' => $validated['hourly_rate'] ?? 0.00,
                'contract_rate' => $validated['contract_rate'] ?? 0.00,
            ];

            if ($hasExtendedColumns) {
                $attributes['date_of_birth'] = $validated['date_of_birth'] ?? null;
                $attributes['gender'] = $validated['gender'] ?? null;
                $attributes['alternate_phone'] = $validated['alternate_phone'] ?? null;
                $attributes['email'] = $validated['email'] ?? null;
                $attributes['address'] = $validated['address'] ?? null;
                $attributes['department'] = $validated['department'] ?? null;
                $attributes['emergency_contact_name'] = $validated['emergency_contact_name'] ?? null;
                $attributes['emergency_contact_relation'] = $validated['emergency_contact_relation'] ?? null;
                $attributes['emergency_contact_phone'] = $validated['emergency_contact_phone'] ?? null;
            }

            if ($hasPhase2Columns) {
                $attributes['skill_level'] = $validated['skill_level'] ?? null;
                $attributes['overtime_rate'] = $validated['overtime_rate'] ?? 0.00;
                $attributes['exit_date'] = $validated['exit_date'] ?? null;
                $attributes['bank_account_name'] = $validated['bank_account_name'] ?? null;
                $attributes['bank_account_number'] = $validated['bank_account_number'] ?? null;
                $attributes['bank_ifsc'] = $validated['bank_ifsc'] ?? null;
                $attributes['upi_id'] = $validated['upi_id'] ?? null;
            }

            $worker = new Worker($attributes);
            $worker->company_id = $companyId;
            $worker->code = $code;
            $worker->status = $validated['status'] ?? 'active';
            $worker->joining_date = !empty($validated['joining_date'])
                ? Carbon::parse($validated['joining_date'])
                : Carbon::today();
            $worker->save();

            SiteAssignment::recordWorkerAssignment(
                $worker,
                $worker->assigned_site_id ? (int) $worker->assigned_site_id : null,
                $worker->joining_date?->format('Y-m-d'),
                Auth::id(),
                'Initial site assignment'
            );

            if (!empty($uploadedFiles) && Schema::hasTable('worker_documents')) {
                $this->persistUploadedDocuments(
                    $worker,
                    $uploadedFiles,
                    (array) $request->input('document_types', []),
                    (array) $request->input('document_titles', []),
                    (array) $request->input('document_numbers', []),
                    (array) $request->input('document_issue_dates', []),
                    (array) $request->input('document_expiry_dates', [])
                );
            }

            return $worker;
        });

        return redirect()->route('workforce.index')->with('success', "Worker {$worker->full_name} ({$worker->code}) created successfully.");
    }

    public function show(Worker $worker): View
    {
        Gate::authorize('view', $worker);

        if (Schema::hasColumn('workers', 'qr_token')) {
            $worker->ensureQrToken();
        }

        $worker->load(['subcontractor', 'assignedSite']);
        $documents = Schema::hasTable('worker_documents')
            ? $worker->documents()->with('uploader')->get()
            : collect();
        $recentAttendances = $worker->attendances()->with('site')->latest('attendance_date')->limit(10)->get();
        $siteAssignments = Schema::hasTable('site_assignments')
            ? $worker->siteAssignments()->with(['site', 'assigner'])->get()
            : collect();
        $sites = $this->authorizedActiveSites();
        $documentTypes = WorkerDocument::documentTypes();

        return view('workforce.show', compact(
            'worker',
            'documents',
            'recentAttendances',
            'siteAssignments',
            'sites',
            'documentTypes'
        ));
    }

    public function transferSite(Request $request, Worker $worker): RedirectResponse
    {
        Gate::authorize('update', $worker);

        $companyId = TenantContext::getCompanyId();
        $validated = $request->validate([
            'site_id' => [
                'required',
                'integer',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'role_on_site' => ['nullable', 'string', 'max:80'],
            'start_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if (!Auth::user()->isAssignedToSite((int) $validated['site_id'])) {
            abort(403, 'You are not authorized to transfer workers to this site.');
        }

        DB::transaction(function () use ($worker, $validated) {
            $worker->assigned_site_id = (int) $validated['site_id'];
            $worker->save();

            $assignment = SiteAssignment::recordWorkerAssignment(
                $worker,
                (int) $validated['site_id'],
                $validated['start_date'],
                Auth::id(),
                $validated['notes'] ?? 'Site transfer'
            );

            if ($assignment && !empty($validated['role_on_site'])) {
                $assignment->role_on_site = $validated['role_on_site'];
                $assignment->save();
            }
        });

        return back()->with('success', "Worker {$worker->full_name} transferred and dated assignment history updated.");
    }

    public function resetPin(Request $request, Worker $worker): RedirectResponse
    {
        Gate::authorize('update', $worker);

        $validated = $request->validate([
            'pin' => ['required', 'digits:6'],
        ]);

        $worker->update([
            'pin' => \Illuminate\Support\Facades\Hash::make($validated['pin']),
            'must_change_pin' => true,
        ]);

        \App\Models\AuditLog::record(
            'worker.pin_reset',
            $worker,
            null,
            ['must_change_pin' => true],
            "Temporary 6-digit PIN set for {$worker->full_name} ({$worker->code})"
        );

        return back()->with('success', "Temporary 6-digit PIN set for {$worker->full_name}. Worker will be prompted to change PIN on first login.");
    }

    public function subcontractors(): View
    {
        Gate::authorize('viewAny', Worker::class);

        $subcontractors = Subcontractor::withCount('workers')->get();
        return view('workforce.subcontractors', compact('subcontractors'));
    }

    public function edit(Worker $worker): View
    {
        Gate::authorize('update', $worker);

        $worker->load(['subcontractor', 'assignedSite']);
        $documents = Schema::hasTable('worker_documents')
            ? $worker->documents()->with('uploader')->get()
            : collect();
        $sites = $this->authorizedActiveSites();
        $subcontractors = Subcontractor::where('is_active', true)->get();
        $documentTypes = WorkerDocument::documentTypes();
        $genders = Worker::GENDERS;
        $departments = Worker::DEPARTMENTS;
        $skillLevels = Worker::SKILL_LEVELS;
        $payTypes = Worker::PAY_TYPES;

        return view('workforce.edit', compact(
            'worker',
            'sites',
            'subcontractors',
            'documents',
            'documentTypes',
            'genders',
            'departments',
            'skillLevels',
            'payTypes'
        ));
    }

    public function update(Request $request, Worker $worker): RedirectResponse
    {
        Gate::authorize('update', $worker);

        $companyId = TenantContext::getCompanyId();

        $validated = $request->validate(array_merge(
            $this->profileValidationRules($companyId, true),
            $this->documentValidationRules()
        ));

        if ($validated['worker_type'] === 'subcontractor_worker' && empty($validated['subcontractor_id'])) {
            return back()->withErrors(['subcontractor_id' => 'Subcontractor company is mandatory for subcontractor workers.'])->withInput();
        }

        if ($validated['worker_type'] !== 'subcontractor_worker') {
            $validated['subcontractor_id'] = null;
        }

        if (!empty($validated['assigned_site_id']) && !Auth::user()->isAssignedToSite((int) $validated['assigned_site_id'])) {
            abort(403, 'You are not authorized to assign workers to this site.');
        }

        $uploadedFiles = $request->file('documents', []);
        if (!is_array($uploadedFiles)) {
            throw ValidationException::withMessages(['documents' => ['Invalid documents payload.']]);
        }
        $this->validateDocumentBatch($uploadedFiles, (array) $request->input('document_types', []));

        $hasExtendedColumns = Schema::hasColumn('workers', 'date_of_birth');
        $hasPhase2Columns = Schema::hasColumn('workers', 'skill_level');
        $rawInput = $request->all();

        DB::transaction(function () use ($worker, $validated, $uploadedFiles, $request, $hasExtendedColumns, $hasPhase2Columns, $rawInput) {
            $attributes = [
                'worker_type' => $validated['worker_type'],
                'first_name' => $validated['first_name'],
                'last_name' => array_key_exists('last_name', $rawInput) ? ($validated['last_name'] ?? null) : $worker->last_name,
                'phone' => array_key_exists('phone', $rawInput) ? ($validated['phone'] ?? null) : $worker->phone,
                'aadhaar_last_four' => array_key_exists('aadhaar_last_four', $rawInput) ? ($validated['aadhaar_last_four'] ?? null) : $worker->aadhaar_last_four,
                'trade_designation' => $validated['trade_designation'],
                'assigned_site_id' => array_key_exists('assigned_site_id', $rawInput) ? ($validated['assigned_site_id'] ?? null) : $worker->assigned_site_id,
                'subcontractor_id' => $validated['subcontractor_id'] ?? null,
                'pay_type' => array_key_exists('pay_type', $rawInput) ? ($validated['pay_type'] ?? 'daily') : ($worker->pay_type ?? 'daily'),
                'daily_rate' => $validated['daily_rate'] ?? $worker->daily_rate ?? 0.00,
                'monthly_salary' => array_key_exists('monthly_salary', $rawInput) ? ($validated['monthly_salary'] ?? 0.00) : ($worker->monthly_salary ?? 0.00),
                'hourly_rate' => array_key_exists('hourly_rate', $rawInput) ? ($validated['hourly_rate'] ?? 0.00) : ($worker->hourly_rate ?? 0.00),
                'contract_rate' => array_key_exists('contract_rate', $rawInput) ? ($validated['contract_rate'] ?? 0.00) : ($worker->contract_rate ?? 0.00),
                'status' => $validated['status'],
                'joining_date' => array_key_exists('joining_date', $rawInput)
                    ? (!empty($validated['joining_date']) ? Carbon::parse($validated['joining_date']) : null)
                    : $worker->joining_date,
            ];

            if ($hasExtendedColumns) {
                $extendedFields = [
                    'date_of_birth',
                    'gender',
                    'alternate_phone',
                    'email',
                    'address',
                    'department',
                    'emergency_contact_name',
                    'emergency_contact_relation',
                    'emergency_contact_phone',
                ];

                foreach ($extendedFields as $field) {
                    if (array_key_exists($field, $rawInput)) {
                        $attributes[$field] = $validated[$field] ?? null;
                    }
                }
            }

            if ($hasPhase2Columns) {
                foreach ([
                    'skill_level',
                    'overtime_rate',
                    'exit_date',
                    'bank_account_name',
                    'bank_account_number',
                    'bank_ifsc',
                    'upi_id',
                ] as $p2Field) {
                    if (array_key_exists($p2Field, $rawInput)) {
                        $attributes[$p2Field] = $validated[$p2Field] ?? null;
                    }
                }
            }

            $worker->fill($attributes);
            $worker->save();

            SiteAssignment::recordWorkerAssignment(
                $worker,
                $worker->assigned_site_id ? (int) $worker->assigned_site_id : null,
                $worker->joining_date?->format('Y-m-d'),
                Auth::id(),
                'Profile site assignment update'
            );

            if (!empty($uploadedFiles) && Schema::hasTable('worker_documents')) {
                $this->persistUploadedDocuments(
                    $worker,
                    $uploadedFiles,
                    (array) $request->input('document_types', []),
                    (array) $request->input('document_titles', []),
                    (array) $request->input('document_numbers', []),
                    (array) $request->input('document_issue_dates', []),
                    (array) $request->input('document_expiry_dates', [])
                );
            }
        });

        return redirect()->route('workforce.index')->with('success', "Worker {$worker->full_name} ({$worker->code}) updated successfully.");
    }

    public function storeDocuments(Request $request, Worker $worker): RedirectResponse
    {
        Gate::authorize('uploadDocument', $worker);

        $request->validate(array_merge(
            [
                'documents' => ['required', 'array', 'min:1', 'max:10'],
            ],
            $this->documentValidationRules()
        ));

        $uploadedFiles = $request->file('documents', []);
        if (!is_array($uploadedFiles)) {
            throw ValidationException::withMessages(['documents' => ['Invalid documents payload.']]);
        }
        $this->validateDocumentBatch($uploadedFiles, (array) $request->input('document_types', []), true);

        $count = $this->persistUploadedDocuments(
            $worker,
            $uploadedFiles,
            (array) $request->input('document_types', []),
            (array) $request->input('document_titles', []),
            (array) $request->input('document_numbers', []),
            (array) $request->input('document_issue_dates', []),
            (array) $request->input('document_expiry_dates', [])
        );

        return back()->with('success', "{$count} document(s) uploaded securely for {$worker->full_name}.");
    }

    public function downloadDocument(Worker $worker, int $document): StreamedResponse
    {
        $doc = WorkerDocument::where('worker_id', $worker->id)->findOrFail($document);

        Gate::authorize('downloadDocument', [$worker, $doc]);

        if (!$this->isValidWorkerDocumentPath($worker, $doc)) {
            abort(403, 'Invalid document storage path.');
        }

        $disk = Storage::disk('local');
        if (!$disk->exists($doc->file_path)) {
            abort(404, 'The requested document file could not be found in private storage.');
        }

        $safeDownloadName = preg_replace('/[^A-Za-z0-9._\- ]/', '_', basename($doc->original_name)) ?: 'worker-document';

        return $disk->download($doc->file_path, $safeDownloadName, [
            'Content-Type' => $doc->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroyDocument(Worker $worker, int $document): RedirectResponse
    {
        $doc = WorkerDocument::where('worker_id', $worker->id)->findOrFail($document);

        Gate::authorize('deleteDocument', [$worker, $doc]);

        if (!$this->isValidWorkerDocumentPath($worker, $doc)) {
            abort(403, 'Invalid document storage path.');
        }

        $disk = Storage::disk('local');
        if ($disk->exists($doc->file_path)) {
            $disk->delete($doc->file_path);
        }

        $doc->delete();

        return back()->with('success', 'Worker document removed from private storage.');
    }

    protected function authorizedActiveSites(): Collection
    {
        $user = Auth::user();
        $sites = Site::where('status', 'active')->orderBy('name')->get();

        if ($user && !$user->hasRole(['super_admin', 'company_admin', 'senior_manager', 'office_staff'])) {
            return $sites->filter(fn (Site $site) => $user->isAssignedToSite($site))->values();
        }

        return $sites;
    }

    protected function profileValidationRules(int $companyId, bool $isUpdate): array
    {
        return [
            // Personal Information
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1920-01-01'],
            'gender' => ['nullable', 'string', Rule::in(array_keys(Worker::GENDERS))],
            'aadhaar_last_four' => ['nullable', 'string', 'regex:/^\d{4}$/'],

            // Contact Information
            'phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'alternate_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:1000'],

            // Employment Information
            'worker_type' => ['required', 'in:employee,direct_labour,subcontractor_worker'],
            'subcontractor_id' => [
                'nullable',
                Rule::exists('subcontractors', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'trade_designation' => ['required', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'skill_level' => ['nullable', 'string', Rule::in(array_keys(Worker::SKILL_LEVELS))],
            'joining_date' => ['nullable', 'date', 'after:1970-01-01', 'before_or_equal:2100-12-31'],
            'exit_date' => ['nullable', 'date', 'after_or_equal:joining_date'],
            'status' => $isUpdate
                ? ['required', 'in:active,inactive,terminated']
                : ['nullable', 'in:active,inactive,terminated'],
            'pay_type' => ['nullable', 'string', Rule::in(array_keys(Worker::PAY_TYPES))],
            'daily_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],
            'monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],
            'contract_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'overtime_rate' => ['nullable', 'numeric', 'min:0', 'max:99999.99'],

            // Site Assignment
            'assigned_site_id' => [
                'nullable',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],

            // Emergency Contact & Payroll Bank Details
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:60'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
            'bank_account_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'upi_id' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function isValidWorkerDocumentPath(Worker $worker, WorkerDocument $doc): bool
    {
        if ($doc->storage_disk !== 'local') {
            return false;
        }

        if (str_contains($doc->file_path, '..') || str_contains($doc->file_path, "\0")) {
            return false;
        }

        $allowedExtsPattern = implode('|', array_map('preg_quote', WorkerDocument::ALLOWED_EXTENSIONS));
        $pattern = sprintf(
            '#^worker-documents/%d/%d/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(%s)$#',
            (int) $worker->company_id,
            (int) $worker->id,
            $allowedExtsPattern
        );

        return preg_match($pattern, $doc->file_path) === 1;
    }

    protected function documentValidationRules(): array
    {
        $allowedTypes = implode(',', array_keys(WorkerDocument::DOCUMENT_TYPES));
        $allowedExts = implode(',', WorkerDocument::ALLOWED_EXTENSIONS);
        $allowedMimes = implode(',', WorkerDocument::ALLOWED_MIME_TYPES);
        $maxKb = WorkerDocument::MAX_FILE_SIZE_KB;

        return [
            'documents' => ['nullable', 'array', 'max:10'],
            'documents.*' => [
                'required',
                'file',
                'min:1',
                "max:{$maxKb}",
                "extensions:{$allowedExts}",
                "mimetypes:{$allowedMimes}",
            ],
            'document_types' => ['nullable', 'array', 'max:10'],
            'document_types.*' => ['nullable', 'string', "in:{$allowedTypes}"],
            'document_titles' => ['nullable', 'array', 'max:10'],
            'document_titles.*' => ['nullable', 'string', 'max:150'],
            'document_numbers' => ['nullable', 'array', 'max:10'],
            'document_numbers.*' => ['nullable', 'string', 'max:80'],
            'document_issue_dates' => ['nullable', 'array', 'max:10'],
            'document_issue_dates.*' => ['nullable', 'date'],
            'document_expiry_dates' => ['nullable', 'array', 'max:10'],
            'document_expiry_dates.*' => ['nullable', 'date'],
        ];
    }

    protected function validateDocumentBatch(array $uploadedFiles, array $documentTypes, bool $requireFiles = false): void
    {
        if ($requireFiles && empty($uploadedFiles)) {
            throw ValidationException::withMessages([
                'documents' => ['Please select at least one document file to upload.'],
            ]);
        }

        $forbiddenTokens = [
            'php', 'phtml', 'phar', 'sh', 'bash', 'exe', 'bat', 'cmd',
            'com', 'scr', 'msi', 'vbs', 'js', 'jsp', 'asp', 'aspx',
            'cgi', 'pl', 'py', 'rb', 'jar', 'war', 'svg', 'html', 'htm',
        ];

        $allowedTypeKeys = array_keys(WorkerDocument::DOCUMENT_TYPES);
        $maxBytes = WorkerDocument::MAX_FILE_SIZE_KB * 1024;

        foreach ($uploadedFiles as $idx => $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                throw ValidationException::withMessages([
                    "documents.{$idx}" => ['The uploaded file is invalid or corrupted.'],
                ]);
            }

            $size = (int) $file->getSize();
            if ($size <= 0 || $size > $maxBytes) {
                throw ValidationException::withMessages([
                    "documents.{$idx}" => ['The file size must be between 1 byte and 5 MB.'],
                ]);
            }

            $originalName = basename($file->getClientOriginalName());
            $parts = explode('.', strtolower($originalName));
            if (count($parts) < 2) {
                throw ValidationException::withMessages([
                    "documents.{$idx}" => ['The file must have a valid extension.'],
                ]);
            }

            $ext = end($parts);
            if (!in_array($ext, WorkerDocument::ALLOWED_EXTENSIONS, true)) {
                throw ValidationException::withMessages([
                    "documents.{$idx}" => ['Unsupported or unsafe file extension.'],
                ]);
            }

            // Reject double extensions containing executable tokens (e.g., payload.php.pdf)
            foreach (array_slice($parts, 1, -1) as $middlePart) {
                if (in_array($middlePart, $forbiddenTokens, true)) {
                    throw ValidationException::withMessages([
                        "documents.{$idx}" => ['Files with executable double extensions are not permitted.'],
                    ]);
                }
            }

            // Server-side finfo MIME check
            $detectedMime = $file->getMimeType();
            if (!is_string($detectedMime) || !in_array($detectedMime, WorkerDocument::ALLOWED_MIME_TYPES, true)) {
                throw ValidationException::withMessages([
                    "documents.{$idx}" => ['The file content MIME type is not permitted.'],
                ]);
            }

            $docType = $documentTypes[$idx] ?? null;
            if (!is_string($docType) || !in_array($docType, $allowedTypeKeys, true)) {
                throw ValidationException::withMessages([
                    "document_types.{$idx}" => ['Please select a valid document category for each uploaded file.'],
                ]);
            }
        }
    }

    protected function persistUploadedDocuments(
        Worker $worker,
        array $uploadedFiles,
        array $documentTypes,
        array $documentTitles = [],
        array $documentNumbers = [],
        array $documentIssueDates = [],
        array $documentExpiryDates = []
    ): int {
        $companyId = (int) $worker->company_id;
        $userId = Auth::id();
        $storedPaths = [];
        $count = 0;
        $hasDocExpiryColumns = Schema::hasColumn('worker_documents', 'expiry_date');

        try {
            DB::beginTransaction();

            foreach ($uploadedFiles as $idx => $file) {
                if (!$file instanceof UploadedFile) {
                    continue;
                }

                $rawOriginalName = basename($file->getClientOriginalName());
                $cleanOriginalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $rawOriginalName) ?: 'document';
                $cleanOriginalName = mb_substr($cleanOriginalName, 0, 255);

                $ext = strtolower($file->getClientOriginalExtension());
                if (!in_array($ext, WorkerDocument::ALLOWED_EXTENSIONS, true)) {
                    throw new \RuntimeException('Invalid document extension.');
                }

                $generatedFilename = Str::uuid()->toString() . '.' . $ext;
                $directory = "worker-documents/{$companyId}/{$worker->id}";
                $storedPath = $file->storeAs($directory, $generatedFilename, 'local');

                if (!$storedPath) {
                    throw new \RuntimeException('Failed to write worker document to private storage.');
                }

                $storedPaths[] = $storedPath;

                $docType = $documentTypes[$idx] ?? null;
                if (!is_string($docType) || !array_key_exists($docType, WorkerDocument::DOCUMENT_TYPES)) {
                    throw new \RuntimeException('Invalid document category.');
                }

                $title = isset($documentTitles[$idx]) && trim((string) $documentTitles[$idx]) !== ''
                    ? mb_substr(trim((string) $documentTitles[$idx]), 0, 150)
                    : null;

                $docPayload = [
                    'company_id' => $companyId,
                    'worker_id' => $worker->id,
                    'document_type' => $docType,
                    'title' => $title,
                    'original_name' => $cleanOriginalName,
                    'storage_disk' => 'local',
                    'file_path' => $storedPath,
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'file_size' => (int) $file->getSize(),
                    'uploaded_by' => $userId,
                ];

                if ($hasDocExpiryColumns) {
                    $docPayload['document_number'] = !empty($documentNumbers[$idx]) ? mb_substr(trim((string) $documentNumbers[$idx]), 0, 80) : null;
                    $docPayload['issue_date'] = !empty($documentIssueDates[$idx]) ? Carbon::parse($documentIssueDates[$idx])->format('Y-m-d') : null;
                    $docPayload['expiry_date'] = !empty($documentExpiryDates[$idx]) ? Carbon::parse($documentExpiryDates[$idx])->format('Y-m-d') : null;
                }

                WorkerDocument::create($docPayload);

                $count++;
            }

            DB::commit();
            return $count;
        } catch (\Throwable $e) {
            DB::rollBack();
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }
            throw $e;
        }
    }

    /**
     * Shows the Bulk Worker CSV Import page with column mapping guide and reference codes.
     */
    public function showImportForm(): View
    {
        Gate::authorize('create', Worker::class);

        $sites = $this->authorizedActiveSites();
        $subcontractors = Subcontractor::where('is_active', true)->orderBy('name')->get();

        return view('workforce.import', compact('sites', 'subcontractors'));
    }

    /**
     * Downloads a pre-formatted CSV template for mass worker onboarding.
     */
    public function downloadCsvTemplate(): StreamedResponse
    {
        Gate::authorize('create', Worker::class);

        $firstSite = $this->authorizedActiveSites()->first();
        $firstSub = Subcontractor::where('is_active', true)->first();

        $headers = [
            'worker_type',
            'first_name',
            'last_name',
            'trade_designation',
            'skill_level',
            'department',
            'daily_rate',
            'overtime_rate',
            'site_code',
            'subcontractor_name',
            'phone',
            'alternate_phone',
            'email',
            'date_of_birth',
            'gender',
            'aadhaar_last_four',
            'joining_date',
            'status',
            'address',
            'emergency_contact_name',
            'emergency_contact_relation',
            'emergency_contact_phone',
            'bank_account_name',
            'bank_account_number',
            'bank_ifsc',
            'upi_id',
        ];

        $sampleSiteCode = $firstSite?->code ?? 'SITE-01';
        $sampleSubName = $firstSub?->name ?? 'Apex Civil Contractors';

        return response()->streamDownload(function () use ($headers, $sampleSiteCode, $sampleSubName) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);

            // Sample Row 1: Direct Employee
            fputcsv($out, [
                'employee',
                'Rajesh',
                'Deshmukh',
                'Senior Site Engineer',
                'highly_skilled',
                'Civil & Earthworks',
                '1400',
                '220',
                $sampleSiteCode,
                '',
                '9876543210',
                '',
                'rajesh.d@nsd.local',
                '1990-05-14',
                'male',
                '4821',
                Carbon::today()->format('Y-m-d'),
                'active',
                'Plot 12, Kothrud, Pune',
                'Sunita Deshmukh',
                'Spouse',
                '9876543211',
                'Rajesh Deshmukh',
                '123456789012',
                'SBIN0001234',
                'rajesh@upi',
            ]);

            // Sample Row 2: Direct Labour
            fputcsv($out, [
                'direct_labour',
                'Mahesh',
                'Jadhav',
                'Excavator Operator',
                'skilled',
                'Plant & Heavy Machinery',
                '950',
                '150',
                $sampleSiteCode,
                '',
                '9823011223',
                '',
                '',
                '1994-08-20',
                'male',
                '7390',
                Carbon::today()->format('Y-m-d'),
                'active',
                'Navi Mumbai Sector 11',
                'Prakash Jadhav',
                'Brother',
                '9823011224',
                'Mahesh Jadhav',
                '987654321098',
                'HDFC0000123',
                'mahesh@upi',
            ]);

            // Sample Row 3: Subcontractor Worker
            fputcsv($out, [
                'subcontractor_worker',
                'Santosh',
                'Kamble',
                'Bar Bender',
                'semi_skilled',
                'Structural & Masonry',
                '750',
                '110',
                $sampleSiteCode,
                $sampleSubName,
                '9765432109',
                '',
                '',
                '1996-11-02',
                'male',
                '5512',
                Carbon::today()->format('Y-m-d'),
                'active',
                'Thane West',
                'Anita Kamble',
                'Mother',
                '9765432100',
                '',
                '',
                '',
                '',
            ]);

            fclose($out);
        }, 'nsd_workers_bulk_import_template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Parses a flexible date string from a CSV cell (supports YYYY-MM-DD, DD-MM-YYYY, DD/MM/YYYY, etc.).
     */
    protected function parseCsvDate(?string $value, ?string $default = null): ?string
    {
        $val = trim((string) $value);
        if ($val === '') {
            return $default;
        }

        foreach (['Y-m-d', 'd-m-Y', 'd/m/Y', 'Y/m/d', 'd.m.Y', 'm/d/Y'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $val);
                if ($dt !== false) {
                    return $dt->format('Y-m-d');
                }
            } catch (\Throwable) {
                // Continue trying next format
            }
        }

        try {
            return Carbon::parse($val)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Processes a bulk CSV upload of workers on a mass level.
     */
    public function importCsv(Request $request): RedirectResponse
    {
        Gate::authorize('create', Worker::class);

        $request->validate([
            'csv_file' => ['required', 'file', 'max:5120'],
            'default_site_id' => ['nullable', 'integer'],
        ]);

        $companyId = (int) TenantContext::getCompanyId();
        $user = Auth::user();

        $defaultSiteId = $request->filled('default_site_id')
            ? (int) $request->input('default_site_id')
            : (session()->has('active_site_id') && session('active_site_id') ? (int) session('active_site_id') : null);

        if ($defaultSiteId && !$user->isAssignedToSite($defaultSiteId)) {
            abort(403, 'You are not authorized to assign workers to the selected default site.');
        }

        $file = $request->file('csv_file');
        $rawContent = file_get_contents($file->getRealPath());
        if ($rawContent === false || trim($rawContent) === '') {
            return back()->withErrors(['csv_file' => 'The uploaded CSV file is empty or unreadable.']);
        }

        // Normalize Windows (\r\n) and classic Mac (\r) line endings so fgetcsv works across all OS/Excel exports
        $normalizedContent = str_replace(["\r\n", "\r"], "\n", $rawContent);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $normalizedContent);
        rewind($handle);

        // Auto-detect delimiter (comma, semicolon, or tab) from the first line
        $firstLine = fgets($handle);
        rewind($handle);
        $delimiter = ',';
        if ($firstLine !== false) {
            $commaCount = substr_count($firstLine, ',');
            $semiCount = substr_count($firstLine, ';');
            $tabCount = substr_count($firstLine, "\t");
            if ($semiCount > $commaCount && $semiCount >= $tabCount) {
                $delimiter = ';';
            } elseif ($tabCount > $commaCount && $tabCount > $semiCount) {
                $delimiter = "\t";
            }
        }

        // Read header row and strip UTF-8 BOM if present
        $rawHeaders = fgetcsv($handle, 0, $delimiter);
        if (!$rawHeaders || count($rawHeaders) < 2) {
            fclose($handle);
            return back()->withErrors(['csv_file' => 'The CSV file is empty or missing a valid header row.']);
        }

        $headerAliases = [
            'type' => 'worker_type',
            'category' => 'worker_type',
            'name' => 'first_name',
            'full_name' => 'first_name',
            'worker_name' => 'first_name',
            'surname' => 'last_name',
            'trade' => 'trade_designation',
            'designation' => 'trade_designation',
            'role' => 'trade_designation',
            'skill' => 'skill_category',
            'rate' => 'daily_rate',
            'wage' => 'daily_rate',
            'daily_wage' => 'daily_rate',
            'ot_rate' => 'overtime_hourly_rate',
            'site' => 'site_code',
            'subcontractor' => 'subcontractor_name',
            'mobile' => 'phone',
            'mobile_number' => 'phone',
            'phone_number' => 'phone',
            'contact' => 'phone',
            'dob' => 'date_of_birth',
            'aadhaar' => 'aadhaar_last_four',
            'aadhar' => 'aadhaar_last_four',
            'aadhaar_last_4' => 'aadhaar_last_four',
        ];

        $headers = array_map(function ($h) use ($headerAliases) {
            $clean = preg_replace('/^\xEF\xBB\xBF/', '', (string) $h);
            $clean = strtolower(trim(str_replace([' ', '-'], '_', $clean)));
            $clean = preg_replace('/[^a-z0-9_]/', '', $clean);
            return $headerAliases[$clean] ?? $clean;
        }, $rawHeaders);

        $requiredColumns = ['worker_type', 'first_name', 'trade_designation', 'daily_rate'];
        $missingColumns = array_diff($requiredColumns, $headers);
        if (!empty($missingColumns)) {
            fclose($handle);
            return back()->withErrors([
                'csv_file' => 'Missing required CSV columns: ' . implode(', ', $missingColumns),
            ]);
        }

        // Cache company sites and subcontractors for fast lookup
        $sitesByCode = Site::where('company_id', $companyId)
            ->get()
            ->keyBy(fn (Site $s) => strtolower(trim($s->code)));

        $sitesByName = Site::where('company_id', $companyId)
            ->get()
            ->keyBy(fn (Site $s) => strtolower(trim($s->name)));

        $subsByName = Subcontractor::where('company_id', $companyId)
            ->get()
            ->keyBy(fn (Subcontractor $sub) => strtolower(trim($sub->name)));

        // Existing workers for duplicate detection (strictly by unique identifiers: phone, email, bank_account_number, upi_id)
        // Note: Full name, date of birth, and address are intentionally allowed to be shared by multiple workers.
        $existingWorkers = Worker::where('company_id', $companyId)
            ->get(['id', 'code', 'first_name', 'last_name', 'phone', 'email', 'bank_account_number', 'upi_id']);

        $existingPhones = [];
        $existingEmails = [];
        $existingBankAccounts = [];
        $existingUpiIds = [];

        foreach ($existingWorkers as $ew) {
            $normPhone = preg_replace('/\D+/', '', (string) $ew->phone);
            if ($normPhone !== '' && strlen($normPhone) >= 10) {
                $existingPhones[substr($normPhone, -10)] = "{$ew->full_name} ({$ew->code})";
            }

            $normEmail = strtolower(trim((string) $ew->email));
            if ($normEmail !== '') {
                $existingEmails[$normEmail] = "{$ew->full_name} ({$ew->code})";
            }

            $normBank = preg_replace('/\s+/', '', strtoupper((string) $ew->bank_account_number));
            if ($normBank !== '') {
                $existingBankAccounts[$normBank] = "{$ew->full_name} ({$ew->code})";
            }

            $normUpi = strtolower(trim((string) $ew->upi_id));
            if ($normUpi !== '') {
                $existingUpiIds[$normUpi] = "{$ew->full_name} ({$ew->code})";
            }
        }

        $batchPhones = [];
        $batchEmails = [];
        $batchBankAccounts = [];
        $batchUpiIds = [];

        $rowsToInsert = [];
        $rowErrors = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $lineNumber++;

            // Skip completely blank lines
            $nonEmpty = array_filter($row, fn ($v) => trim((string) $v) !== '');
            if (empty($nonEmpty)) {
                continue;
            }

            $data = [];
            foreach ($headers as $idx => $colName) {
                if ($colName !== '') {
                    $data[$colName] = isset($row[$idx]) ? trim((string) $row[$idx]) : '';
                }
            }

            // Normalize worker_type aliases
            $rawType = strtolower($data['worker_type'] ?? '');
            $typeMap = [
                'employee' => 'employee',
                'direct_employee' => 'employee',
                'direct employee' => 'employee',
                'staff' => 'employee',
                'direct_labour' => 'direct_labour',
                'direct labour' => 'direct_labour',
                'labour' => 'direct_labour',
                'labor' => 'direct_labour',
                'subcontractor_worker' => 'subcontractor_worker',
                'subcontractor worker' => 'subcontractor_worker',
                'subcontractor' => 'subcontractor_worker',
                'sub' => 'subcontractor_worker',
            ];
            $workerType = $typeMap[$rawType] ?? $rawType;

            if (!in_array($workerType, ['employee', 'direct_labour', 'subcontractor_worker'], true)) {
                $rowErrors[] = "Row {$lineNumber}: Invalid worker_type '{$data['worker_type']}' (allowed: employee, direct_labour, subcontractor_worker).";
                continue;
            }

            if (empty($data['first_name'])) {
                $rowErrors[] = "Row {$lineNumber}: first_name is required.";
                continue;
            }

            if (empty($data['trade_designation'])) {
                $rowErrors[] = "Row {$lineNumber}: trade_designation is required.";
                continue;
            }

            if (!isset($data['daily_rate']) || !is_numeric($data['daily_rate']) || (float) $data['daily_rate'] < 0) {
                $rowErrors[] = "Row {$lineNumber}: daily_rate must be a valid non-negative number.";
                continue;
            }

            // Validate optional Aadhaar last 4
            $aadhaar = $data['aadhaar_last_four'] ?? '';
            if ($aadhaar !== '' && !preg_match('/^\d{4}$/', $aadhaar)) {
                $rowErrors[] = "Row {$lineNumber}: aadhaar_last_four '{$aadhaar}' must be exactly 4 digits.";
                continue;
            }

            $parsedDob = $this->parseCsvDate($data['date_of_birth'] ?? null, null);
            if (!empty($data['date_of_birth']) && $parsedDob === null) {
                $rowErrors[] = "Row {$lineNumber}: Invalid date_of_birth '{$data['date_of_birth']}'. Use YYYY-MM-DD or DD/MM/YYYY.";
                continue;
            }

            $parsedJoiningDate = $this->parseCsvDate($data['joining_date'] ?? null, Carbon::today()->format('Y-m-d'));
            if (!empty($data['joining_date']) && $parsedJoiningDate === null) {
                $rowErrors[] = "Row {$lineNumber}: Invalid joining_date '{$data['joining_date']}'. Use YYYY-MM-DD or DD/MM/YYYY.";
                continue;
            }

            // --- DUPLICATE VALIDATION (Against Existing Database & Current CSV Batch) ---
            // Full Name, Date of Birth, and Address CAN be duplicated across workers.
            // Only unique identifiers (Phone, Email, Bank Account Number, UPI ID) are blocked when duplicated.
            $rawPhone = $data['phone'] ?? '';
            $normPhone = preg_replace('/\D+/', '', $rawPhone);
            $phoneKey = strlen($normPhone) >= 10 ? substr($normPhone, -10) : '';

            if ($phoneKey !== '') {
                if (isset($existingPhones[$phoneKey])) {
                    $rowErrors[] = "Row {$lineNumber}: Duplicate mobile number '{$rawPhone}' — already registered to {$existingPhones[$phoneKey]}.";
                    continue;
                }
                if (isset($batchPhones[$phoneKey])) {
                    $rowErrors[] = "Row {$lineNumber}: Duplicate mobile number '{$rawPhone}' appears multiple times in this CSV (first seen on Row {$batchPhones[$phoneKey]}).";
                    continue;
                }
            }

            $normEmail = strtolower(trim($data['email'] ?? ''));
            if ($normEmail !== '') {
                if (isset($existingEmails[$normEmail])) {
                    $rowErrors[] = "Row {$lineNumber}: Duplicate email '{$normEmail}' — already registered to {$existingEmails[$normEmail]}.";
                    continue;
                }
                if (isset($batchEmails[$normEmail])) {
                    $rowErrors[] = "Row {$lineNumber}: Duplicate email '{$normEmail}' appears multiple times in this CSV (first seen on Row {$batchEmails[$normEmail]}).";
                    continue;
                }
            }

            $normBank = preg_replace('/\s+/', '', strtoupper($data['bank_account_number'] ?? ''));
            if ($normBank !== '') {
                if (isset($existingBankAccounts[$normBank])) {
                    $rowErrors[] = "Row {$lineNumber}: Duplicate bank account number '{$data['bank_account_number']}' — already registered to {$existingBankAccounts[$normBank]}.";
                    continue;
                }
                if (isset($batchBankAccounts[$normBank])) {
                    $rowErrors[] = "Row {$lineNumber}: Duplicate bank account number '{$data['bank_account_number']}' appears multiple times in this CSV (first seen on Row {$batchBankAccounts[$normBank]}).";
                    continue;
                }
            }

            $normUpi = strtolower(trim($data['upi_id'] ?? ''));
            if ($normUpi !== '') {
                if (isset($existingUpiIds[$normUpi])) {
                    $rowErrors[] = "Row {$lineNumber}: Duplicate UPI ID '{$normUpi}' — already registered to {$existingUpiIds[$normUpi]}.";
                    continue;
                }
                if (isset($batchUpiIds[$normUpi])) {
                    $rowErrors[] = "Row {$lineNumber}: Duplicate UPI ID '{$normUpi}' appears multiple times in this CSV (first seen on Row {$batchUpiIds[$normUpi]}).";
                    continue;
                }
            }

            // Record unique keys in current CSV batch tracker
            if ($phoneKey !== '') {
                $batchPhones[$phoneKey] = $lineNumber;
            }
            if ($normEmail !== '') {
                $batchEmails[$normEmail] = $lineNumber;
            }
            if ($normBank !== '') {
                $batchBankAccounts[$normBank] = $lineNumber;
            }
            if ($normUpi !== '') {
                $batchUpiIds[$normUpi] = $lineNumber;
            }

            // Resolve Site
            $resolvedSiteId = $defaultSiteId;
            $rawSiteCode = strtolower($data['site_code'] ?? $data['site'] ?? '');
            if ($rawSiteCode !== '') {
                $matchedSite = $sitesByCode->get($rawSiteCode) ?? $sitesByName->get($rawSiteCode);
                if (!$matchedSite) {
                    $rowErrors[] = "Row {$lineNumber}: Site '{$data['site_code']}' was not found in your company.";
                    continue;
                }
                if (!$user->isAssignedToSite($matchedSite)) {
                    $rowErrors[] = "Row {$lineNumber}: You are not authorized to assign workers to site '{$matchedSite->code}'.";
                    continue;
                }
                $resolvedSiteId = $matchedSite->id;
            }

            // Resolve Subcontractor (mandatory for subcontractor_worker)
            $resolvedSubId = null;
            $rawSubName = strtolower($data['subcontractor_name'] ?? $data['subcontractor'] ?? '');
            if ($workerType === 'subcontractor_worker') {
                if ($rawSubName === '') {
                    $firstActiveSub = $subsByName->first();
                    if (!$firstActiveSub) {
                        $rowErrors[] = "Row {$lineNumber}: subcontractor_name is required for subcontractor_worker.";
                        continue;
                    }
                    $resolvedSubId = $firstActiveSub->id;
                } else {
                    $matchedSub = $subsByName->get($rawSubName);
                    if (!$matchedSub) {
                        $matchedSub = Subcontractor::create([
                            'company_id' => $companyId,
                            'code' => 'SUBV-' . strtoupper(Str::random(5)),
                            'name' => trim($data['subcontractor_name'] ?? $data['subcontractor']),
                            'is_active' => true,
                        ]);
                        $subsByName->put($rawSubName, $matchedSub);
                    }
                    $resolvedSubId = $matchedSub->id;
                }
            }

            // Validate optional skill_level
            $skillLevel = strtolower(str_replace([' ', '-'], '_', $data['skill_level'] ?? ''));
            if ($skillLevel !== '' && !array_key_exists($skillLevel, Worker::SKILL_LEVELS)) {
                $rowErrors[] = "Row {$lineNumber}: Invalid skill_level '{$data['skill_level']}'.";
                continue;
            }

            // Validate optional gender
            $gender = strtolower($data['gender'] ?? '');
            if ($gender !== '' && !array_key_exists($gender, Worker::GENDERS)) {
                $rowErrors[] = "Row {$lineNumber}: Invalid gender '{$gender}'.";
                continue;
            }

            // Validate optional pay_type
            $payType = strtolower(trim($data['pay_type'] ?? 'daily')) ?: 'daily';
            if (!array_key_exists($payType, Worker::PAY_TYPES)) {
                $payType = 'daily';
            }

            // Validate optional status
            $status = strtolower($data['status'] ?? 'active') ?: 'active';
            if (!in_array($status, ['active', 'inactive', 'on_leave'], true)) {
                $status = 'active';
            }

            $rowsToInsert[] = [
                'line' => $lineNumber,
                'worker_type' => $workerType,
                'subcontractor_id' => $resolvedSubId,
                'assigned_site_id' => $resolvedSiteId,
                'first_name' => mb_substr($data['first_name'], 0, 100),
                'last_name' => !empty($data['last_name']) ? mb_substr($data['last_name'], 0, 100) : null,
                'trade_designation' => mb_substr($data['trade_designation'], 0, 100),
                'skill_level' => $skillLevel ?: null,
                'department' => !empty($data['department']) ? mb_substr($data['department'], 0, 100) : null,
                'pay_type' => $payType,
                'daily_rate' => round((float) $data['daily_rate'], 2),
                'monthly_salary' => isset($data['monthly_salary']) && is_numeric($data['monthly_salary'])
                    ? round((float) $data['monthly_salary'], 2)
                    : 0.00,
                'hourly_rate' => isset($data['hourly_rate']) && is_numeric($data['hourly_rate'])
                    ? round((float) $data['hourly_rate'], 2)
                    : 0.00,
                'contract_rate' => isset($data['contract_rate']) && is_numeric($data['contract_rate'])
                    ? round((float) $data['contract_rate'], 2)
                    : 0.00,
                'overtime_rate' => isset($data['overtime_rate']) && is_numeric($data['overtime_rate'])
                    ? round((float) $data['overtime_rate'], 2)
                    : 0.00,
                'phone' => !empty($data['phone']) ? mb_substr($data['phone'], 0, 25) : null,
                'alternate_phone' => !empty($data['alternate_phone']) ? mb_substr($data['alternate_phone'], 0, 25) : null,
                'email' => !empty($data['email']) ? mb_substr($data['email'], 0, 150) : null,
                'date_of_birth' => $parsedDob,
                'gender' => $gender ?: null,
                'aadhaar_last_four' => $aadhaar ?: null,
                'joining_date' => $parsedJoiningDate,
                'status' => $status,
                'address' => !empty($data['address']) ? mb_substr($data['address'], 0, 500) : null,
                'emergency_contact_name' => !empty($data['emergency_contact_name']) ? mb_substr($data['emergency_contact_name'], 0, 120) : null,
                'emergency_contact_relation' => !empty($data['emergency_contact_relation']) ? mb_substr($data['emergency_contact_relation'], 0, 60) : null,
                'emergency_contact_phone' => !empty($data['emergency_contact_phone']) ? mb_substr($data['emergency_contact_phone'], 0, 25) : null,
                'bank_account_name' => !empty($data['bank_account_name']) ? mb_substr($data['bank_account_name'], 0, 120) : null,
                'bank_account_number' => !empty($data['bank_account_number']) ? mb_substr($data['bank_account_number'], 0, 50) : null,
                'bank_ifsc' => !empty($data['bank_ifsc']) ? strtoupper(mb_substr($data['bank_ifsc'], 0, 20)) : null,
                'upi_id' => !empty($data['upi_id']) ? mb_substr($data['upi_id'], 0, 100) : null,
            ];
        }

        fclose($handle);

        if (!empty($rowErrors)) {
            return back()->withErrors($rowErrors)->withInput();
        }

        if (empty($rowsToInsert)) {
            return back()->withErrors(['csv_file' => 'The CSV file did not contain any valid worker rows to import.']);
        }

        $importedWorkers = [];

        DB::transaction(function () use ($rowsToInsert, $companyId, &$importedWorkers) {
            foreach ($rowsToInsert as $item) {
                $code = WorkerCodeGenerator::generate(
                    $companyId,
                    $item['worker_type'],
                    $item['joining_date']
                );

                $worker = new Worker([
                    'worker_type' => $item['worker_type'],
                    'subcontractor_id' => $item['subcontractor_id'],
                    'assigned_site_id' => $item['assigned_site_id'],
                    'first_name' => $item['first_name'],
                    'last_name' => $item['last_name'],
                    'trade_designation' => $item['trade_designation'],
                    'skill_level' => $item['skill_level'],
                    'department' => $item['department'],
                    'pay_type' => $item['pay_type'],
                    'daily_rate' => $item['daily_rate'],
                    'monthly_salary' => $item['monthly_salary'],
                    'hourly_rate' => $item['hourly_rate'],
                    'contract_rate' => $item['contract_rate'],
                    'overtime_rate' => $item['overtime_rate'],
                    'phone' => $item['phone'],
                    'alternate_phone' => $item['alternate_phone'],
                    'email' => $item['email'],
                    'date_of_birth' => $item['date_of_birth'],
                    'gender' => $item['gender'],
                    'aadhaar_last_four' => $item['aadhaar_last_four'],
                    'address' => $item['address'],
                    'emergency_contact_name' => $item['emergency_contact_name'],
                    'emergency_contact_relation' => $item['emergency_contact_relation'],
                    'emergency_contact_phone' => $item['emergency_contact_phone'],
                    'bank_account_name' => $item['bank_account_name'],
                    'bank_account_number' => $item['bank_account_number'],
                    'bank_ifsc' => $item['bank_ifsc'],
                    'upi_id' => $item['upi_id'],
                ]);

                $worker->company_id = $companyId;
                $worker->code = $code;
                $worker->status = $item['status'];
                $worker->joining_date = Carbon::parse($item['joining_date']);

                if ($item['worker_type'] === 'employee' && Schema::hasColumn('workers', 'pin')) {
                    $worker->pin = Hash::make('123456');
                    $worker->must_change_pin = true;
                }

                $worker->save();

                SiteAssignment::recordWorkerAssignment(
                    $worker,
                    $worker->assigned_site_id ? (int) $worker->assigned_site_id : null,
                    $worker->joining_date?->format('Y-m-d'),
                    Auth::id(),
                    'Bulk CSV Import'
                );

                $importedWorkers[] = $worker;
            }

            AuditLog::record(
                'worker.bulk_csv_imported',
                null,
                null,
                [
                    'imported_count' => count($importedWorkers),
                    'sample_codes' => collect($importedWorkers)->take(10)->pluck('code')->all(),
                ],
                'Mass worker CSV upload completed'
            );
        });

        $count = count($importedWorkers);
        $firstCode = $importedWorkers[0]->code ?? '';
        $lastCode = $importedWorkers[$count - 1]->code ?? '';

        // If the user has an active_site_id filter in session that doesn't match the imported workers' site, clear it so all imported workers are immediately visible
        if (session()->has('active_site_id') && session('active_site_id')) {
            $activeSiteId = (int) session('active_site_id');
            $allMatchActiveSite = collect($importedWorkers)->every(fn ($w) => (int) $w->assigned_site_id === $activeSiteId);
            if (!$allMatchActiveSite) {
                session()->forget('active_site_id');
            }
        }

        return redirect()
            ->route('workforce.index')
            ->with('success', "Successfully imported {$count} workers via CSV ({$firstCode} to {$lastCode}).");
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\Worker;
use App\Models\WorkerDocument;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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

        $workers = $query->orderBy('first_name')->paginate(20)->withQueryString();
        $sites = $this->authorizedActiveSites();
        $subcontractors = Subcontractor::where('is_active', true)->get();

        return view('workforce.index', compact('workers', 'sites', 'subcontractors'));
    }

    public function create(): View
    {
        Gate::authorize('create', Worker::class);

        $sites = $this->authorizedActiveSites();
        $subcontractors = Subcontractor::where('is_active', true)->get();
        $documentTypes = WorkerDocument::documentTypes();
        $genders = Worker::GENDERS;
        $departments = Worker::DEPARTMENTS;

        return view('workforce.create', compact('sites', 'subcontractors', 'documentTypes', 'genders', 'departments'));
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

        $prefix = match ($validated['worker_type']) {
            'employee' => 'W-EMP',
            'direct_labour' => 'W-LAB',
            'subcontractor_worker' => 'W-SUB',
        };

        $hasExtendedColumns = Schema::hasColumn('workers', 'date_of_birth');

        $worker = DB::transaction(function () use ($validated, $companyId, $prefix, $uploadedFiles, $request, $hasExtendedColumns) {
            $count = Worker::withoutGlobalScopes()
                ->where('company_id', $companyId)
                ->where('worker_type', $validated['worker_type'])
                ->count() + 1;
            $code = sprintf('%s-%03d', $prefix, $count);

            $attributes = [
                'worker_type' => $validated['worker_type'],
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'aadhaar_last_four' => $validated['aadhaar_last_four'] ?? null,
                'trade_designation' => $validated['trade_designation'],
                'assigned_site_id' => $validated['assigned_site_id'] ?? null,
                'subcontractor_id' => $validated['worker_type'] === 'subcontractor_worker' ? ($validated['subcontractor_id'] ?? null) : null,
                'daily_rate' => $validated['daily_rate'],
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

            $worker = new Worker($attributes);
            $worker->company_id = $companyId;
            $worker->code = $code;
            $worker->status = $validated['status'] ?? 'active';
            $worker->joining_date = !empty($validated['joining_date'])
                ? Carbon::parse($validated['joining_date'])
                : Carbon::today();
            $worker->save();

            if (!empty($uploadedFiles) && Schema::hasTable('worker_documents')) {
                $this->persistUploadedDocuments(
                    $worker,
                    $uploadedFiles,
                    (array) $request->input('document_types', []),
                    (array) $request->input('document_titles', [])
                );
            }

            return $worker;
        });

        return redirect()->route('workforce.index')->with('success', "Worker {$worker->full_name} ({$worker->code}) created successfully.");
    }

    public function show(Worker $worker): View
    {
        Gate::authorize('view', $worker);

        $worker->load(['subcontractor', 'assignedSite']);
        $documents = Schema::hasTable('worker_documents')
            ? $worker->documents()->with('uploader')->get()
            : collect();
        $recentAttendances = $worker->attendances()->with('site')->latest('attendance_date')->limit(10)->get();
        $documentTypes = WorkerDocument::documentTypes();

        return view('workforce.show', compact('worker', 'documents', 'recentAttendances', 'documentTypes'));
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

        return view('workforce.edit', compact('worker', 'sites', 'subcontractors', 'documents', 'documentTypes', 'genders', 'departments'));
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
        $rawInput = $request->all();

        DB::transaction(function () use ($worker, $validated, $uploadedFiles, $request, $hasExtendedColumns, $rawInput) {
            $attributes = [
                'worker_type' => $validated['worker_type'],
                'first_name' => $validated['first_name'],
                'last_name' => array_key_exists('last_name', $rawInput) ? ($validated['last_name'] ?? null) : $worker->last_name,
                'phone' => array_key_exists('phone', $rawInput) ? ($validated['phone'] ?? null) : $worker->phone,
                'aadhaar_last_four' => array_key_exists('aadhaar_last_four', $rawInput) ? ($validated['aadhaar_last_four'] ?? null) : $worker->aadhaar_last_four,
                'trade_designation' => $validated['trade_designation'],
                'assigned_site_id' => array_key_exists('assigned_site_id', $rawInput) ? ($validated['assigned_site_id'] ?? null) : $worker->assigned_site_id,
                'subcontractor_id' => $validated['subcontractor_id'] ?? null,
                'daily_rate' => $validated['daily_rate'],
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

            $worker->fill($attributes);
            $worker->save();

            if (!empty($uploadedFiles) && Schema::hasTable('worker_documents')) {
                $this->persistUploadedDocuments(
                    $worker,
                    $uploadedFiles,
                    (array) $request->input('document_types', []),
                    (array) $request->input('document_titles', [])
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
            (array) $request->input('document_titles', [])
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
            'joining_date' => ['nullable', 'date', 'after:1970-01-01', 'before_or_equal:2100-12-31'],
            'status' => $isUpdate
                ? ['required', 'in:active,inactive,terminated']
                : ['nullable', 'in:active,inactive,terminated'],
            'daily_rate' => ['required', 'numeric', 'min:0', 'max:999999.99'],

            // Site Assignment
            'assigned_site_id' => [
                'nullable',
                Rule::exists('sites', 'id')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],

            // Emergency Contact
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:60'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{7,30}$/'],
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
        array $documentTitles = []
    ): int {
        $companyId = (int) $worker->company_id;
        $userId = Auth::id();
        $storedPaths = [];
        $count = 0;

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

                WorkerDocument::create([
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
                ]);

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
}

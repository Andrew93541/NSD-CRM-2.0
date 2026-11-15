<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Project;
use App\Models\WorkOrder;
use App\Services\Security\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    protected function canViewClients(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->can('viewAny', WorkOrder::class) || $user->can('viewAny', Project::class);
    }

    protected function canManageClients(): bool
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }

        return $user->can('create', WorkOrder::class) || $user->can('create', Project::class);
    }

    public function index(Request $request): View
    {
        if (! $this->canViewClients()) {
            abort(403, 'Unauthorized to view client directory.');
        }

        $query = Client::withCount('workOrders');

        if ($request->filled('search')) {
            $search = '%'.trim((string) $request->query('search')).'%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                    ->orWhere('code', 'like', $search)
                    ->orWhere('gstin', 'like', $search);
            });
        }

        $clients = $query->orderBy('name')->paginate(25)->withQueryString();
        $canManage = $this->canManageClients();

        return view('clients.index', compact('clients', 'canManage'));
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->canManageClients()) {
            abort(403, 'Unauthorized to register clients.');
        }

        $companyId = (int) TenantContext::getCompanyId();

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }
        if ($request->filled('gstin')) {
            $request->merge(['gstin' => strtoupper(trim((string) $request->input('gstin')))]);
        }
        if ($request->filled('pan')) {
            $request->merge(['pan' => strtoupper(trim((string) $request->input('pan')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('clients', 'code')->where(fn ($q) => $q->where('company_id', $companyId)),
            ],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z0-9]{13}$/'],
            'pan' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'vendor_code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $client = DB::transaction(function () use ($companyId, $validated, $request) {
            $code = $validated['code'] ?? null;
            if (empty($code)) {
                DB::table('companies')->where('id', $companyId)->lockForUpdate()->first();
                $code = $this->nextAtomicClientCode($companyId);
            }

            return Client::create([
                'company_id' => $companyId,
                'code' => $code,
                'name' => $validated['name'],
                'contact_person' => $validated['contact_person'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'gstin' => $validated['gstin'] ?? null,
                'pan' => $validated['pan'] ?? null,
                'vendor_code' => $validated['vendor_code'] ?? null,
                'address' => $validated['address'] ?? null,
                'is_active' => $request->boolean('is_active', true),
            ]);
        });

        AuditLog::record(
            'client.created',
            $client,
            null,
            ['code' => $client->code, 'name' => $client->name, 'gstin' => $client->gstin],
            "Added client {$client->name} ({$client->code})"
        );

        return redirect()->route('clients.index')->with('success', "Client {$client->name} ({$client->code}) added.");
    }

    protected function nextAtomicClientCode(int $companyId): string
    {
        $maxExisting = 0;
        $codes = Client::withoutGlobalScopes()
            ->where('company_id', $companyId)
            ->where('code', 'like', 'CLI-%')
            ->pluck('code');

        foreach ($codes as $existingCode) {
            if (preg_match('/^CLI-(\d+)$/', (string) $existingCode, $m)) {
                $maxExisting = max($maxExisting, (int) $m[1]);
            }
        }

        $nextNumber = $maxExisting + 1;

        if (Schema::hasTable('worker_sequences')) {
            $seqRow = DB::table('worker_sequences')
                ->where('company_id', $companyId)
                ->where('prefix', 'CLI')
                ->where('year', 0)
                ->lockForUpdate()
                ->first();

            if ($seqRow) {
                $nextNumber = max((int) $seqRow->last_number, $maxExisting) + 1;
                DB::table('worker_sequences')
                    ->where('id', $seqRow->id)
                    ->update(['last_number' => $nextNumber, 'updated_at' => now()]);
            } else {
                DB::table('worker_sequences')->insert([
                    'company_id' => $companyId,
                    'prefix' => 'CLI',
                    'year' => 0,
                    'last_number' => $nextNumber,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        while (Client::withoutGlobalScopes()->where('company_id', $companyId)->where('code', sprintf('CLI-%03d', $nextNumber))->exists()) {
            $nextNumber++;
        }

        return sprintf('CLI-%03d', $nextNumber);
    }

    public function update(Request $request, Client $client): RedirectResponse
    {
        if (! $this->canManageClients()) {
            abort(403, 'Unauthorized to update client records.');
        }

        $companyId = (int) TenantContext::getCompanyId();

        if ($request->filled('code')) {
            $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        }
        if ($request->filled('gstin')) {
            $request->merge(['gstin' => strtoupper(trim((string) $request->input('gstin')))]);
        }
        if ($request->filled('pan')) {
            $request->merge(['pan' => strtoupper(trim((string) $request->input('pan')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'code' => [
                'nullable',
                'string',
                'max:40',
                'regex:/^[A-Z0-9\-_]+$/',
                Rule::unique('clients', 'code')->where(fn ($q) => $q->where('company_id', $companyId))->ignore($client->id),
            ],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:150'],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z0-9]{13}$/'],
            'pan' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'vendor_code' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $client->update([
            'code' => $validated['code'] ?? $client->code,
            'name' => $validated['name'],
            'contact_person' => $validated['contact_person'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'gstin' => $validated['gstin'] ?? null,
            'pan' => $validated['pan'] ?? null,
            'vendor_code' => $validated['vendor_code'] ?? null,
            'address' => $validated['address'] ?? null,
            'is_active' => $request->boolean('is_active', false),
        ]);

        AuditLog::record(
            'client.updated',
            $client,
            null,
            ['code' => $client->code, 'name' => $client->name, 'is_active' => $client->is_active],
            "Updated client {$client->name} ({$client->code})"
        );

        return redirect()->route('clients.index')->with('success', "Client {$client->name} updated.");
    }

    public function destroy(Client $client): RedirectResponse
    {
        if (! $this->canManageClients()) {
            abort(403, 'Unauthorized to delete client records.');
        }

        if ($client->workOrders()->exists()) {
            return back()->withErrors([
                'client' => "Cannot delete client {$client->name} because work orders reference it. Mark it Inactive instead.",
            ]);
        }

        $code = $client->code;
        $name = $client->name;
        $client->delete();

        AuditLog::record(
            'client.deleted',
            null,
            ['code' => $code, 'name' => $name],
            null,
            "Deleted unreferenced client {$name}"
        );

        return redirect()->route('clients.index')->with('success', "Client {$name} deleted.");
    }
}

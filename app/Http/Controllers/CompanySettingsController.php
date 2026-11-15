<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Company;
use App\Services\Security\TenantContext;
use App\Services\Storage\VaultStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanySettingsController extends Controller
{
    protected function authorizeSettingsAccess(): Company
    {
        $user = Auth::user();
        if (! $user || ! $user->hasRole(['super_admin', 'company_admin']) || $user->isExplicitlyDenied('settings.manage')) {
            abort(403, 'Only Company Administrators can view or modify organization settings.');
        }

        $companyId = (int) TenantContext::getCompanyId();
        if ($companyId <= 0) {
            abort(403, 'No active company context.');
        }

        return Company::findOrFail($companyId);
    }

    public function edit(): View
    {
        $company = $this->authorizeSettingsAccess();

        return view('admin.company.settings', compact('company'));
    }

    public function update(Request $request): RedirectResponse
    {
        $company = $this->authorizeSettingsAccess();

        if ($request->filled('gstin')) {
            $request->merge(['gstin' => strtoupper(trim((string) $request->input('gstin')))]);
        }
        if ($request->filled('pan')) {
            $request->merge(['pan' => strtoupper(trim((string) $request->input('pan')))]);
        }
        if ($request->filled('bank_ifsc')) {
            $request->merge(['bank_ifsc' => strtoupper(trim((string) $request->input('bank_ifsc')))]);
        }
        if ($request->filled('invoice_prefix')) {
            $request->merge(['invoice_prefix' => strtoupper(trim((string) $request->input('invoice_prefix')))]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'registered_address' => ['nullable', 'string', 'max:1000'],
            'gstin' => ['nullable', 'string', 'regex:/^[0-9]{2}[A-Z0-9]{13}$/'],
            'pan' => ['nullable', 'string', 'regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/'],
            'vendor_code' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'bank_ifsc' => ['nullable', 'string', 'regex:/^[A-Z]{4}0[A-Z0-9]{6}$/'],
            'bank_branch' => ['nullable', 'string', 'max:120'],
            'invoice_prefix' => ['required', 'string', 'max:30', 'regex:/^[A-Z0-9\-_]+$/'],
            'invoice_starting_number' => ['required', 'integer', 'min:1', 'max:9999999'],
            'invoice_rounding_mode' => ['required', 'in:paisa,rupee'],
            'work_order_terms_template' => ['nullable', 'string', 'max:5000'],
            'signature' => [
                'nullable',
                'file',
                'max:2048',
                'extensions:png,jpg,jpeg',
                'mimetypes:image/png,image/jpeg',
            ],
            'remove_signature' => ['nullable', 'boolean'],
        ], [
            'gstin.regex' => 'GSTIN must be a valid 15-character GST Identification Number (e.g., 27AABCN1234F1Z5).',
            'pan.regex' => 'PAN must be a valid 10-character Permanent Account Number (e.g., AABCN1234F).',
            'bank_ifsc.regex' => 'IFSC code must be 11 characters in standard bank format (e.g., HDFC0000123).',
            'invoice_prefix.regex' => 'Invoice prefix may only contain uppercase letters, numbers, hyphens, or underscores.',
        ]);

        $newSignaturePath = null;
        $newSignatureDisk = null;
        $oldSignaturePath = $company->signature_path;
        $oldSignatureDisk = Schema::hasColumn('companies', 'signature_disk') ? ($company->signature_disk ?? null) : null;

        if ($request->hasFile('signature')) {
            $file = $request->file('signature');
            if ((int) $file->getSize() <= 0) {
                return back()->withInput()->withErrors(['signature' => 'Uploaded signature file cannot be empty.']);
            }
            $ext = strtolower($file->getClientOriginalExtension()) ?: 'png';
            if (! in_array($ext, ['png', 'jpg', 'jpeg'], true)) {
                return back()->withInput()->withErrors(['signature' => 'Signature image must be a PNG or JPG file.']);
            }
            $filename = (string) Str::uuid().'.'.$ext;
            $stored = VaultStorage::storeUploaded($file, "company-signatures/{$company->id}", $filename);
            $newSignaturePath = $stored['path'];
            $newSignatureDisk = $stored['disk'];
        }

        $trackedFields = [
            'name',
            'email',
            'phone',
            'address',
            'registered_address',
            'gstin',
            'pan',
            'vendor_code',
            'bank_name',
            'bank_account_no',
            'bank_ifsc',
            'bank_branch',
            'invoice_prefix',
            'invoice_starting_number',
            'invoice_rounding_mode',
            'work_order_terms_template',
        ];

        $oldValues = [];
        foreach ($trackedFields as $field) {
            if (Schema::hasColumn('companies', $field)) {
                $oldValues[$field] = $company->{$field};
            }
        }

        try {
            DB::transaction(function () use ($company, $validated, $trackedFields, $newSignaturePath, $newSignatureDisk, $oldValues, $request) {
                $payload = [];
                foreach ($trackedFields as $field) {
                    if (Schema::hasColumn('companies', $field) && array_key_exists($field, $validated)) {
                        $payload[$field] = $validated[$field];
                    }
                }

                if (Schema::hasColumn('companies', 'signature_path')) {
                    if ($newSignaturePath !== null) {
                        $payload['signature_path'] = $newSignaturePath;
                        if (Schema::hasColumn('companies', 'signature_disk')) {
                            $payload['signature_disk'] = $newSignatureDisk;
                        }
                    } elseif ($request->boolean('remove_signature')) {
                        $payload['signature_path'] = null;
                        if (Schema::hasColumn('companies', 'signature_disk')) {
                            $payload['signature_disk'] = null;
                        }
                    }
                }

                $company->update($payload);

                $newValues = [];
                foreach ($trackedFields as $field) {
                    if (Schema::hasColumn('companies', $field)) {
                        $newValues[$field] = $company->{$field};
                    }
                }
                $newValues['company_code'] = $company->code;
                $newValues['signature_updated'] = $newSignaturePath !== null || $request->boolean('remove_signature');

                AuditLog::record(
                    'company.settings_updated',
                    $company,
                    $oldValues,
                    $newValues,
                    "Updated organization profile and billing settings for {$company->name}"
                );
            });
        } catch (\Throwable $e) {
            if ($newSignaturePath) {
                VaultStorage::delete($newSignaturePath, $newSignatureDisk);
            }

            throw $e;
        }

        if (($newSignaturePath !== null || $request->boolean('remove_signature')) && $oldSignaturePath) {
            VaultStorage::delete($oldSignaturePath, $oldSignatureDisk);
        }

        return redirect()->route('admin.company.edit')->with('success', 'Organization profile, tax details, and invoice settings saved.');
    }

    public function downloadSignature(): StreamedResponse
    {
        $company = $this->authorizeSettingsAccess();

        if (empty($company->signature_path) || str_contains($company->signature_path, '..')) {
            abort(404, 'No authorized signatory stamp uploaded.');
        }

        $recordDisk = Schema::hasColumn('companies', 'signature_disk') ? ($company->signature_disk ?? null) : null;
        if (! VaultStorage::exists($company->signature_path, $recordDisk)) {
            abort(404, 'Signature file not found in private storage.');
        }

        $ext = strtolower(pathinfo($company->signature_path, PATHINFO_EXTENSION));
        $mime = $ext === 'png' ? 'image/png' : 'image/jpeg';

        return VaultStorage::response($company->signature_path, 'company-signature.'.$ext, [
            'Content-Type' => $mime,
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ], $recordDisk);
    }
}

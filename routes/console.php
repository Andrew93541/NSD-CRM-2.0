<?php

use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('nsd:reset-2fa {email : Email address of the user whose 2FA enrollment should be reset}', function (string $email) {
    $user = User::where('email', trim($email))->first();

    if (!$user) {
        $this->error("No user found with email [{$email}].");
        return self::FAILURE;
    }

    $user->resetTwoFactorEnrollment();

    AuditLog::record(
        'auth.2fa_cli_reset',
        $user,
        null,
        ['target_email' => $user->email, 'source' => 'artisan_cli'],
        null,
        $user->id,
        $user->companies()->first()?->id
    );

    $this->info("Two-Factor Authentication enrollment and secrets have been reset for [{$user->email}].");
    return self::SUCCESS;
})->purpose('Reset Two-Factor Authentication enrollment and secrets for a user account');

Artisan::command('nsd:create-admin {--email= : Administrator email} {--name= : Administrator full name} {--password= : Initial password (generated if omitted)} {--company-name=NSD Construction Pvt Ltd : Primary company name} {--company-code=NSD : Primary company code}', function () {
    $email = trim((string) ($this->option('email') ?: $this->ask('Administrator Email', 'admin@nsdconstruction.in')));
    $name = trim((string) ($this->option('name') ?: $this->ask('Administrator Name', 'System Administrator')));
    $password = (string) ($this->option('password') ?: Str::password(16));
    $companyName = trim((string) $this->option('company-name'));
    $companyCode = strtoupper(trim((string) $this->option('company-code')));

    $company = Company::firstOrCreate(
        ['code' => $companyCode],
        ['name' => $companyName, 'is_active' => true]
    );

    $role = Role::firstOrCreate(
        ['company_id' => $company->id, 'slug' => 'company_admin'],
        ['name' => 'Company Admin', 'permissions' => ['*']]
    );

    $user = User::updateOrCreate(
        ['email' => $email],
        [
            'name' => $name,
            'password' => Hash::make($password),
            'is_active' => true,
        ]
    );

    $user->companies()->syncWithoutDetaching([
        $company->id => [
            'role_id' => $role->id,
            'status' => 'active',
            'is_primary' => true,
        ],
    ]);

    $this->info("Administrator [{$user->email}] provisioned for company [{$company->code}].");
    if (!$this->option('password')) {
        $this->line("Generated initial password: {$password}");
    }

    return self::SUCCESS;
})->purpose('Securely provision an initial Company Administrator account without hardcoded demo credentials');

Artisan::command('nsd:seed-demo', function () {
    $currentDb = (string) \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
    if ($currentDb === 'nsd_crm_2') {
        $this->error('SAFETY ABORT: nsd:seed-demo is blocked on the protected [nsd_crm_2] database.');
        return self::FAILURE;
    }

    $seeder = new \Database\Seeders\RenderDemoSeeder();
    $seeder->run();

    $this->info("Synthetic demo environment seeded in database [{$currentDb}].");
    foreach ($seeder->provisionedAccounts as $acct) {
        $msg = "  - [{$acct['role']}] {$acct['email']} (password: {$acct['password_source']})";
        if (!empty($acct['generated_password'])) {
            $msg .= " -> Generated: {$acct['generated_password']}";
        }
        $this->line($msg);
    }

    return self::SUCCESS;
})->purpose('Idempotently seed synthetic demo records into a separate demo database (never nsd_crm_2)');



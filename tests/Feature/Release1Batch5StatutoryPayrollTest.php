<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\PayrollEntry;
use App\Models\PayrollPeriod;
use App\Models\Project;
use App\Models\Role;
use App\Models\Site;
use App\Models\User;
use App\Models\Worker;
use App\Services\Security\PermissionCatalog;
use App\Services\Security\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class Release1Batch5StatutoryPayrollTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private Site $siteA;

    private Site $siteB;

    private User $admin;

    private User $siteManagerA;

    private User $siteManagerB;

    private User $supervisorA;

    private User $deniedOfficeStaff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Kaveri Heavy Civil Ltd',
            'code' => 'KHCL',
            'status' => 'active',
            'is_active' => true,
            'onboarding_completed_at' => now(),
        ]);

        TenantContext::setCompanyId($this->company->id);

        $defaults = PermissionCatalog::defaultRolePermissions();
        $roles = [];
        foreach (['company_admin', 'site_manager', 'site_supervisor', 'office_staff'] as $slug) {
            $roles[$slug] = Role::create([
                'company_id' => $this->company->id,
                'name' => ucwords(str_replace('_', ' ', $slug)),
                'slug' => $slug,
                'permissions' => $defaults[$slug],
                'is_system' => true,
                'is_active' => true,
            ]);
        }

        $project = Project::create([
            'company_id' => $this->company->id,
            'code' => 'PRJ-KHC-01',
            'name' => 'Barrage Spillway Works',
            'status' => 'active',
        ]);

        $this->siteA = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-KHC-A',
            'name' => 'Spillway Left Bank',
            'status' => 'active',
        ]);

        $this->siteB = Site::create([
            'company_id' => $this->company->id,
            'project_id' => $project->id,
            'code' => 'SITE-KHC-B',
            'name' => 'Spillway Right Bank',
            'status' => 'active',
        ]);

        $this->admin = $this->makeUser('Admin KHC', 'admin@khcl.test', $roles['company_admin'], false);
        $this->siteManagerA = $this->makeUser('Site Manager A', 'sma@khcl.test', $roles['site_manager'], true, [$this->siteA->id]);
        $this->siteManagerB = $this->makeUser('Site Manager B', 'smb@khcl.test', $roles['site_manager'], true, [$this->siteB->id]);
        $this->supervisorA = $this->makeUser('Supervisor A', 'supa@khcl.test', $roles['site_supervisor'], true, [$this->siteA->id]);
        $this->deniedOfficeStaff = $this->makeUser('Denied Office Staff', 'denied@khcl.test', $roles['office_staff'], false, [], [
            'payroll.edit' => 'deny',
        ]);
    }

    private function makeUser(
        string $name,
        string $email,
        Role $role,
        bool $restricted,
        array $siteIds = [],
        ?array $overrides = null
    ): User {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('StrongPass#2026'),
            'company_id' => $this->company->id,
            'is_active' => true,
            'restrict_to_assigned_sites' => $restricted,
        ]);

        $user->companies()->attach($this->company->id, [
            'role_id' => $role->id,
            'is_primary' => true,
            'status' => 'active',
            'restrict_to_assigned_sites' => $restricted,
            'permission_overrides' => $overrides ? json_encode($overrides) : null,
        ]);

        if (! empty($siteIds)) {
            $user->sites()->sync($siteIds);
        }

        return $user;
    }

    private function makeWorker(string $code, string $firstName, float $dailyRate, array $extra = []): Worker
    {
        return Worker::create(array_merge([
            'company_id' => $this->company->id,
            'worker_type' => 'employee',
            'code' => $code,
            'first_name' => $firstName,
            'last_name' => 'Gowda',
            'trade_designation' => 'Fitter',
            'assigned_site_id' => $this->siteA->id,
            'pay_type' => 'daily',
            'daily_rate' => $dailyRate,
            'status' => 'active',
            'joining_date' => '2026-01-01',
        ], $extra));
    }

    private function seedPresentDays(Worker $worker, string $yearMonth, int $days): void
    {
        for ($d = 1; $d <= $days; $d++) {
            DailyAttendance::create([
                'company_id' => $this->company->id,
                'site_id' => $this->siteA->id,
                'worker_id' => $worker->id,
                'attendance_date' => sprintf('%s-%02d', $yearMonth, $d),
                'status' => 'present',
                'worked_hours' => 8.0,
                'overtime_hours' => 0.0,
                'marked_by' => $this->supervisorA->id,
            ]);
        }
    }

    public function test_individual_combinations_pf_esic_pt_not_applicable_and_unconfigured_states(): void
    {
        // 1. Worker with PF + ESIC + PT applicable (Gross = 20 * 1000 = 20,000; PF capped at 15,000 ceiling)
        $wAll = $this->makeWorker('EMP-001', 'Manjunath', 1000.00);
        $this->actingAs($this->siteManagerA)->put(route('workforce.statutory.update', $wAll), [
            'pf_status' => 'applicable',
            'pf_employee_rate' => 12.00,
            'pf_employer_rate' => 13.00,
            'pf_wage_ceiling' => 15000.00,
            'uan_number' => '100998877665',
            'esic_status' => 'applicable',
            'esic_employee_rate' => 0.75,
            'esic_employer_rate' => 3.25,
            'esic_wage_ceiling' => 21000.00,
            'esic_ip_number' => 'ESIC998877',
            'pt_status' => 'applicable',
            'pt_monthly_amount' => 200.00,
        ])->assertRedirect(route('workforce.show', $wAll));

        // 2. Worker with PF only (Gross = 10 * 1000 = 10,000; PF 12% = 1,200)
        $wPfOnly = $this->makeWorker('EMP-002', 'Prakash', 1000.00);
        $this->actingAs($this->siteManagerA)->put(route('workforce.statutory.update', $wPfOnly), [
            'pf_status' => 'applicable',
            'pf_employee_rate' => 12.00,
            'pf_employer_rate' => 12.00,
            'esic_status' => 'not_applicable',
            'pt_status' => 'not_applicable',
        ])->assertRedirect();

        // 3a. Worker with ESIC only below ceiling (Gross = 15 * 1000 = 15,000 <= 21,000; ESIC 0.75% = 112.50)
        $wEsicBelow = $this->makeWorker('EMP-003', 'Kiran', 1000.00);
        $this->actingAs($this->siteManagerA)->put(route('workforce.statutory.update', $wEsicBelow), [
            'pf_status' => 'not_applicable',
            'esic_status' => 'applicable',
            'esic_employee_rate' => 0.75,
            'esic_employer_rate' => 3.25,
            'esic_wage_ceiling' => 21000.00,
            'pt_status' => 'not_applicable',
        ])->assertRedirect();

        // 3b. Worker with ESIC only exceeding ceiling (Gross = 25 * 1000 = 25,000 > 21,000 -> ESIC = 0.00)
        $wEsicAbove = $this->makeWorker('EMP-004', 'Harish', 1000.00);
        $this->actingAs($this->siteManagerA)->put(route('workforce.statutory.update', $wEsicAbove), [
            'pf_status' => 'not_applicable',
            'esic_status' => 'applicable',
            'esic_employee_rate' => 0.75,
            'esic_employer_rate' => 3.25,
            'esic_wage_ceiling' => 21000.00,
            'pt_status' => 'not_applicable',
        ])->assertRedirect();

        // 4. Worker with PT only (Gross = 12 * 1000 = 12,000; PT = 200.00)
        $wPtOnly = $this->makeWorker('EMP-005', 'Naveen', 1000.00);
        $this->actingAs($this->siteManagerA)->put(route('workforce.statutory.update', $wPtOnly), [
            'pf_status' => 'not_applicable',
            'esic_status' => 'not_applicable',
            'pt_status' => 'applicable',
            'pt_monthly_amount' => 200.00,
        ])->assertRedirect();

        // 5. Worker with all Not Applicable
        $wNone = $this->makeWorker('EMP-006', 'Raghav', 800.00);
        $this->actingAs($this->siteManagerA)->put(route('workforce.statutory.update', $wNone), [
            'pf_status' => 'not_applicable',
            'esic_status' => 'not_applicable',
            'pt_status' => 'not_applicable',
        ])->assertRedirect();

        // 6. Worker left Unconfigured (default)
        $wUnconfigured = $this->makeWorker('EMP-007', 'Dinesh', 800.00);

        $this->seedPresentDays($wAll, '2026-10', 20);
        $this->seedPresentDays($wPfOnly, '2026-10', 10);
        $this->seedPresentDays($wEsicBelow, '2026-10', 15);
        $this->seedPresentDays($wEsicAbove, '2026-10', 25);
        $this->seedPresentDays($wPtOnly, '2026-10', 12);
        $this->seedPresentDays($wNone, '2026-10', 10);
        $this->seedPresentDays($wUnconfigured, '2026-10', 10);

        $this->actingAs($this->siteManagerA)->post(route('payroll.store'), [
            'title' => 'October 2026 Site A Payroll',
            'site_id' => $this->siteA->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ])->assertRedirect();

        $period = PayrollPeriod::firstOrFail();

        // Verify Case 1: PF (12% of 15,000 = 1,800; Employer 13% = 1,950) + ESIC (0.75% of 20,000 = 150; Employer 3.25% = 650) + PT (200)
        // Employee Statutory Total = 1,800 + 150 + 200 = 2,150 -> Net Pay = 20,000 - 2,150 = 17,850
        $eAll = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $wAll->id)->firstOrFail();
        $this->assertSame('applicable', $eAll->statutory_config_state);
        $this->assertEquals(1800.00, (float) $eAll->pf_employee_amount);
        $this->assertEquals(1950.00, (float) $eAll->pf_employer_amount);
        $this->assertEquals(150.00, (float) $eAll->esic_employee_amount);
        $this->assertEquals(650.00, (float) $eAll->esic_employer_amount);
        $this->assertEquals(200.00, (float) $eAll->pt_amount);
        $this->assertEquals(17850.00, (float) $eAll->net_pay);

        // Verify Case 2: PF only -> 1,200 deducted, Net = 8,800
        $ePf = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $wPfOnly->id)->firstOrFail();
        $this->assertSame('applicable', $ePf->statutory_config_state);
        $this->assertEquals(1200.00, (float) $ePf->pf_employee_amount);
        $this->assertEquals(0.00, (float) $ePf->esic_employee_amount);
        $this->assertEquals(0.00, (float) $ePf->pt_amount);
        $this->assertEquals(8800.00, (float) $ePf->net_pay);

        // Verify Case 3a & 3b: ESIC below ceiling vs exceeding ceiling
        $eEsicBelow = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $wEsicBelow->id)->firstOrFail();
        $this->assertEquals(112.50, (float) $eEsicBelow->esic_employee_amount);
        $this->assertEquals(487.50, (float) $eEsicBelow->esic_employer_amount);
        $this->assertEquals(14887.50, (float) $eEsicBelow->net_pay);

        $eEsicAbove = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $wEsicAbove->id)->firstOrFail();
        $this->assertEquals(0.00, (float) $eEsicAbove->esic_employee_amount);
        $this->assertSame('exceeds_wage_ceiling', $eEsicAbove->statutory_snapshot['esic']['reason']);
        $this->assertEquals(25000.00, (float) $eEsicAbove->net_pay);

        // Verify Case 4: PT only -> 200 deducted, Net = 11,800
        $ePt = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $wPtOnly->id)->firstOrFail();
        $this->assertSame('applicable', $ePt->statutory_config_state);
        $this->assertEquals(200.00, (float) $ePt->pt_amount);
        $this->assertEquals(11800.00, (float) $ePt->net_pay);

        // Verify Case 5 vs Case 6: Not Applicable vs Unconfigured are explicitly distinct!
        $eNone = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $wNone->id)->firstOrFail();
        $this->assertSame('not_applicable', $eNone->statutory_config_state);
        $this->assertEquals(8000.00, (float) $eNone->net_pay);

        $eUnconf = PayrollEntry::where('payroll_period_id', $period->id)->where('worker_id', $wUnconfigured->id)->firstOrFail();
        $this->assertSame('unconfigured', $eUnconf->statutory_config_state);
        $this->assertEquals(8000.00, (float) $eUnconf->net_pay);

        // UI explicitly flags unconfigured worker and renders web + PDF payslips with statutory breakdown
        $this->actingAs($this->siteManagerA)
            ->get(route('payroll.show', $period))
            ->assertOk()
            ->assertSee('Statutory Configuration Incomplete')
            ->assertSee('Unconfigured');

        $this->actingAs($this->siteManagerA)
            ->get(route('payroll.payslip', [$period, $eAll]))
            ->assertOk()
            ->assertSee('1,800.00')
            ->assertSee('1,950.00');

        $pdf = $this->actingAs($this->siteManagerA)->get(route('payroll.payslip_pdf', [$period, $eAll]));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->streamedContent());
    }

    public function test_statutory_validation_authorization_and_effective_from_date_guardrails(): void
    {
        $worker = $this->makeWorker('EMP-VAL-01', 'Santosh', 1000.00);

        // Invalid rates (> 100 or negative) and missing required rate when applicable are rejected
        $this->actingAs($this->admin)
            ->from(route('workforce.show', $worker))
            ->put(route('workforce.statutory.update', $worker), [
                'pf_status' => 'applicable',
                'pf_employee_rate' => 145,
                'esic_status' => 'applicable',
                'esic_employee_rate' => -1,
                'pt_status' => 'applicable',
            ])
            ->assertSessionHasErrors(['pf_employee_rate', 'esic_employee_rate', 'pt_monthly_amount']);

        // Unauthorized roles / site-restricted / explicit deny blocked with 403
        $payload = [
            'pf_status' => 'not_applicable',
            'esic_status' => 'not_applicable',
            'pt_status' => 'not_applicable',
        ];
        $this->actingAs($this->supervisorA)->put(route('workforce.statutory.update', $worker), $payload)->assertForbidden();
        $this->actingAs($this->siteManagerB)->put(route('workforce.statutory.update', $worker), $payload)->assertForbidden();
        $this->actingAs($this->deniedOfficeStaff)->put(route('workforce.statutory.update', $worker), $payload)->assertForbidden();

        // Future effective_from date does NOT deduct in an earlier payroll period
        $this->actingAs($this->admin)->put(route('workforce.statutory.update', $worker), [
            'pf_status' => 'applicable',
            'pf_employee_rate' => 12.00,
            'esic_status' => 'not_applicable',
            'pt_status' => 'applicable',
            'pt_monthly_amount' => 200.00,
            'statutory_effective_from' => '2026-11-01',
        ])->assertSessionHasNoErrors();

        $this->seedPresentDays($worker, '2026-10', 10);
        $this->actingAs($this->admin)->post(route('payroll.store'), [
            'title' => 'October Pre-Effective Payroll',
            'site_id' => $this->siteA->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ])->assertRedirect();

        $octEntry = PayrollEntry::where('worker_id', $worker->id)->firstOrFail();
        $this->assertSame('not_yet_effective', $octEntry->statutory_config_state);
        $this->assertEquals(0.00, (float) $octEntry->pf_employee_amount);
        $this->assertEquals(0.00, (float) $octEntry->pt_amount);
        $this->assertEquals(10000.00, (float) $octEntry->net_pay);
    }

    public function test_historical_locked_payroll_entries_remain_immutable_when_statutory_settings_change(): void
    {
        $worker = $this->makeWorker('EMP-HIST-01', 'Venkatesh', 1000.00);
        $this->actingAs($this->admin)->put(route('workforce.statutory.update', $worker), [
            'pf_status' => 'applicable',
            'pf_employee_rate' => 12.00,
            'pf_employer_rate' => 12.00,
            'esic_status' => 'not_applicable',
            'pt_status' => 'applicable',
            'pt_monthly_amount' => 200.00,
        ])->assertRedirect();

        $this->seedPresentDays($worker, '2026-09', 20);
        // Prepared by Site Manager A; independently approved & locked by Company Admin (maker-checker)
        $this->actingAs($this->siteManagerA)->post(route('payroll.store'), [
            'title' => 'September 2026 Payroll',
            'site_id' => $this->siteA->id,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ])->assertRedirect();

        $sepPeriod = PayrollPeriod::firstOrFail();
        // Approve & Lock September period (Gross = 20,000; PF 12% = 2,400; PT = 200 -> Net = 17,400)
        $this->actingAs($this->admin)->post(route('payroll.lock', $sepPeriod))->assertRedirect();

        $sepEntryBefore = PayrollEntry::where('payroll_period_id', $sepPeriod->id)->firstOrFail();
        $this->assertEquals(2400.00, (float) $sepEntryBefore->pf_employee_amount);
        $this->assertEquals(200.00, (float) $sepEntryBefore->pt_amount);
        $this->assertEquals(17400.00, (float) $sepEntryBefore->net_pay);
        $snapshotBefore = $sepEntryBefore->statutory_snapshot;

        // Now change the worker's statutory settings in October (PF disabled, PT changed to 300)
        $this->actingAs($this->admin)->put(route('workforce.statutory.update', $worker), [
            'pf_status' => 'not_applicable',
            'esic_status' => 'not_applicable',
            'pt_status' => 'applicable',
            'pt_monthly_amount' => 300.00,
        ])->assertRedirect();

        // Locked September period cannot be recalculated and its entry + snapshot remain 100% unchanged!
        $this->actingAs($this->admin)
            ->from(route('payroll.show', $sepPeriod))
            ->post(route('payroll.recalculate', $sepPeriod))
            ->assertSessionHasErrors('status');

        $sepEntryAfter = $sepEntryBefore->fresh();
        $this->assertEquals(2400.00, (float) $sepEntryAfter->pf_employee_amount);
        $this->assertEquals(200.00, (float) $sepEntryAfter->pt_amount);
        $this->assertEquals(17400.00, (float) $sepEntryAfter->net_pay);
        $this->assertEquals($snapshotBefore, $sepEntryAfter->statutory_snapshot);

        // New October period uses the updated statutory configuration (PF = 0, PT = 300 -> Net = 19,700)
        $this->seedPresentDays($worker, '2026-10', 20);
        $this->actingAs($this->admin)->post(route('payroll.store'), [
            'title' => 'October 2026 Payroll',
            'site_id' => $this->siteA->id,
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ])->assertRedirect();

        $octPeriod = PayrollPeriod::where('title', 'October 2026 Payroll')->firstOrFail();
        $octEntry = PayrollEntry::where('payroll_period_id', $octPeriod->id)->firstOrFail();
        $this->assertEquals(0.00, (float) $octEntry->pf_employee_amount);
        $this->assertEquals(300.00, (float) $octEntry->pt_amount);
        $this->assertEquals(19700.00, (float) $octEntry->net_pay);
    }
}

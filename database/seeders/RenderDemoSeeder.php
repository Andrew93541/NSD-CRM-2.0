<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\InventoryStock;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\MeasurementEntry;
use App\Models\Project;
use App\Models\RaBill;
use App\Models\RaBillItem;
use App\Models\Role;
use App\Models\Site;
use App\Models\SiteNote;
use App\Models\Subcontractor;
use App\Models\Task;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Worker;
use App\Models\WorkOrder;
use App\Models\WorkOrderApproval;
use App\Models\WorkOrderItem;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class RenderDemoSeeder extends Seeder
{
    /**
     * Provisioned demo account summary for CLI output.
     *
     * @var array<int, array{role: string, email: string, password_source: string, generated_password: ?string}>
     */
    public array $provisionedAccounts = [];

    public function run(): void
    {
        $currentDatabase = (string) DB::connection()->getDatabaseName();

        if ($currentDatabase === 'nsd_crm_2') {
            throw new RuntimeException(
                'SAFETY ABORT: RenderDemoSeeder must never run against the protected [nsd_crm_2] database.'
            );
        }

        if (app()->environment('production') && $currentDatabase !== 'nsd_crm_demo') {
            throw new RuntimeException(
                "SAFETY ABORT: In production mode, RenderDemoSeeder may only execute against [nsd_crm_demo] (current: [{$currentDatabase}])."
            );
        }

        $demoConfig = (array) config('app.demo', []);
        $readEnvOrConfig = static function (string $envKey, string $configKey, string $default = '') use ($demoConfig): string {
            $runtimeEnv = getenv($envKey);
            if ($runtimeEnv !== false && trim((string) $runtimeEnv) !== '') {
                return trim((string) $runtimeEnv);
            }
            $cfgVal = trim((string) ($demoConfig[$configKey] ?? ''));

            return $cfgVal !== '' ? $cfgVal : $default;
        };

        // 1. Synthetic Demo Company
        $company = Company::firstOrCreate(
            ['code' => $readEnvOrConfig('DEMO_COMPANY_CODE', 'company_code', 'DEMO-NSD')],
            [
                'name' => $readEnvOrConfig('DEMO_COMPANY_NAME', 'company_name', 'NSD Demo Infrastructure Pvt Ltd'),
                'legal_name' => 'NSD Demo Infrastructure Private Limited (Synthetic Demo Tenant)',
                'gst_number' => '27AADCN9999D1Z5',
                'pan_number' => 'AADCN9999D',
                'email' => 'contact@nsd-demo.example',
                'phone' => '+91 22 4000 9900',
                'address' => 'Plot 42, Synthetic Demo Industrial Park, Navi Mumbai 400708',
                'is_active' => true,
            ]
        );

        TenantContext::setCompanyId($company->id);

        // 2. Standard RBAC Roles
        $roleDefinitions = [
            'super_admin' => [
                'name' => 'Super Administrator',
                'permissions' => ['*'],
            ],
            'company_admin' => [
                'name' => 'Company Administrator',
                'permissions' => ['*'],
            ],
            'senior_manager' => [
                'name' => 'Senior Operations Manager',
                'permissions' => [
                    'work_orders.approve_senior',
                    'work_orders.view',
                    'procurement.approve',
                    'procurement.view',
                    'attendance.view',
                    'reports.view',
                ],
            ],
            'site_manager' => [
                'name' => 'Site Manager',
                'permissions' => [
                    'work_orders.create',
                    'work_orders.approve_site',
                    'work_orders.view',
                    'procurement.request',
                    'attendance.record',
                    'attendance.view',
                    'inventory.view',
                    'reports.view',
                ],
            ],
            'site_supervisor' => [
                'name' => 'Site Supervisor',
                'permissions' => [
                    'attendance.record',
                    'attendance.view',
                    'inventory.receive',
                    'reports.view',
                ],
            ],
            'warehouse_manager' => [
                'name' => 'Warehouse Storekeeper',
                'permissions' => [
                    'inventory.manage',
                    'inventory.issue',
                    'inventory.receive',
                    'procurement.view',
                ],
            ],
            'office_staff' => [
                'name' => 'Office / Compliance Staff',
                'permissions' => [
                    'reports.view',
                    'work_orders.view',
                    'attendance.view',
                ],
            ],
        ];

        $roles = [];
        foreach ($roleDefinitions as $slug => $meta) {
            $roles[$slug] = Role::firstOrCreate(
                ['company_id' => $company->id, 'slug' => $slug],
                ['name' => $meta['name'], 'permissions' => $meta['permissions']]
            );
        }

        // 3. Synthetic Demo Users (No hardcoded passwords; never resets existing passwords)
        $accountSpecs = [
            'admin' => [
                'email' => $readEnvOrConfig('DEMO_ADMIN_EMAIL', 'admin_email', 'demo.admin@nsd-demo.example'),
                'name' => 'Demo System Administrator',
                'role' => 'company_admin',
                'env_password_key' => 'DEMO_ADMIN_PASSWORD',
                'config_password_key' => 'admin_password',
            ],
            'senior_manager' => [
                'email' => $readEnvOrConfig('DEMO_SENIOR_MANAGER_EMAIL', 'senior_manager_email', 'demo.senior@nsd-demo.example'),
                'name' => 'Demo Senior Operations VP',
                'role' => 'senior_manager',
                'env_password_key' => 'DEMO_MANAGER_PASSWORD',
                'config_password_key' => 'manager_password',
            ],
            'site_manager' => [
                'email' => $readEnvOrConfig('DEMO_SITE_MANAGER_EMAIL', 'site_manager_email', 'demo.sitemanager@nsd-demo.example'),
                'name' => 'Demo Lead Site Manager',
                'role' => 'site_manager',
                'env_password_key' => 'DEMO_MANAGER_PASSWORD',
                'config_password_key' => 'manager_password',
            ],
            'supervisor' => [
                'email' => $readEnvOrConfig('DEMO_SUPERVISOR_EMAIL', 'supervisor_email', 'demo.supervisor@nsd-demo.example'),
                'name' => 'Demo Field Supervisor',
                'role' => 'site_supervisor',
                'env_password_key' => 'DEMO_STAFF_PASSWORD',
                'config_password_key' => 'staff_password',
            ],
            'office' => [
                'email' => $readEnvOrConfig('DEMO_STAFF_EMAIL', 'staff_email', 'demo.office@nsd-demo.example'),
                'name' => 'Demo Billing & Compliance Executive',
                'role' => 'office_staff',
                'env_password_key' => 'DEMO_STAFF_PASSWORD',
                'config_password_key' => 'staff_password',
            ],
        ];

        $users = [];
        $this->provisionedAccounts = [];

        foreach ($accountSpecs as $key => $spec) {
            $envPass = $readEnvOrConfig($spec['env_password_key'], $spec['config_password_key'], '');
            $plainPassword = $envPass !== '' ? $envPass : Str::password(16);

            $existingUser = User::where('email', $spec['email'])->first();

            if (! $existingUser) {
                $user = User::create([
                    'name' => $spec['name'],
                    'email' => $spec['email'],
                    'password' => Hash::make($plainPassword),
                    'is_active' => true,
                    'two_factor_enabled' => false,
                ]);
                $passwordSource = $envPass !== '' ? "env:{$spec['env_password_key']}" : 'generated';
                $generatedOut = $envPass !== '' ? null : $plainPassword;
            } else {
                // Idempotent: preserve existing password and 2FA state across restarts/reseeds
                $user = $existingUser;
                $passwordSource = 'existing (preserved)';
                $generatedOut = null;
            }

            $user->companies()->syncWithoutDetaching([
                $company->id => [
                    'role_id' => $roles[$spec['role']]->id,
                    'status' => 'active',
                    'is_primary' => true,
                ],
            ]);

            $users[$key] = $user;
            $this->provisionedAccounts[] = [
                'role' => $spec['role'],
                'email' => $user->email,
                'password_source' => $passwordSource,
                'generated_password' => $generatedOut,
            ];
        }

        // 4. Synthetic Projects & Sites
        $project1 = Project::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-PRJ-01'],
            [
                'name' => 'Coastal Industrial Corridor Package A (Synthetic Demo)',
                'client_name' => 'Synthetic Infrastructure Development Authority',
                'description' => 'Synthetic earthwork, storm water Box Culvert, and industrial approach road package.',
                'status' => 'active',
                'manager_id' => $users['site_manager']->id,
                'start_date' => Carbon::now()->subMonths(3)->toDateString(),
                'end_date' => Carbon::now()->addMonths(9)->toDateString(),
            ]
        );

        $project2 = Project::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-PRJ-02'],
            [
                'name' => 'Elevated Logistics Park Yard Grading (Synthetic Demo)',
                'client_name' => 'Synthetic Freight Terminals Ltd',
                'description' => 'Synthetic container yard sub-base compaction and concrete pavement works.',
                'status' => 'active',
                'manager_id' => $users['site_manager']->id,
                'start_date' => Carbon::now()->subMonths(2)->toDateString(),
                'end_date' => Carbon::now()->addMonths(6)->toDateString(),
            ]
        );

        $site1 = Site::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-SITE-01'],
            [
                'project_id' => $project1->id,
                'name' => 'Demo Sector 1 — Box Culvert & Embankment',
                'location' => 'Synthetic Corridor Ch 10+000 to 14+500',
                'city' => 'Navi Mumbai',
                'state' => 'Maharashtra',
                'status' => 'active',
                'supervisor_id' => $users['supervisor']->id,
            ]
        );

        $site2 = Site::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-SITE-02'],
            [
                'project_id' => $project2->id,
                'name' => 'Demo Sector 2 — Container Yard Sub-Base',
                'location' => 'Synthetic Logistics Terminal Zone B',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'status' => 'active',
                'supervisor_id' => $users['supervisor']->id,
            ]
        );

        $site1->assignedUsers()->syncWithoutDetaching([
            $users['site_manager']->id,
            $users['supervisor']->id,
            $users['office']->id,
        ]);

        $site2->assignedUsers()->syncWithoutDetaching([
            $users['site_manager']->id,
            $users['supervisor']->id,
        ]);

        // 5. Warehouses, Materials & Initial Inventory Stock
        $centralWh = Warehouse::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-WH-CENTRAL'],
            [
                'name' => 'Demo Central Logistics Depot',
                'location_type' => 'central_warehouse',
                'manager_id' => $users['site_manager']->id,
                'address' => 'Synthetic Central Yard Plot 1',
                'is_active' => true,
            ]
        );

        $siteWh = Warehouse::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-WH-S1'],
            [
                'site_id' => $site1->id,
                'name' => 'Demo Sector 1 Field Store',
                'location_type' => 'site_store',
                'manager_id' => $users['supervisor']->id,
                'address' => 'Synthetic Sector 1 Base Camp',
                'is_active' => true,
            ]
        );

        $catAgg = MaterialCategory::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-AGG'],
            ['name' => 'Aggregates & Sub-Base']
        );

        $catCem = MaterialCategory::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-CEM'],
            ['name' => 'Cement & Concrete Binders']
        );

        $matGsb = Material::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-GSB-40'],
            [
                'category_id' => $catAgg->id,
                'name' => 'Granular Sub-Base (GSB) Grade-I (Synthetic)',
                'unit_of_measure' => 'M3',
                'minimum_stock_level' => 250.00,
                'standard_unit_cost' => 720.00,
                'is_active' => true,
            ]
        );

        $matCement = Material::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-OPC-53'],
            [
                'category_id' => $catCem->id,
                'name' => 'OPC 53 Grade Cement (Synthetic)',
                'unit_of_measure' => 'Bags',
                'minimum_stock_level' => 200.00,
                'standard_unit_cost' => 390.00,
                'is_active' => true,
            ]
        );

        InventoryStock::firstOrCreate(
            ['company_id' => $company->id, 'warehouse_id' => $centralWh->id, 'material_id' => $matCement->id],
            ['quantity' => 950.00, 'reserved_quantity' => 50.00]
        );

        InventoryStock::firstOrCreate(
            ['company_id' => $company->id, 'warehouse_id' => $siteWh->id, 'material_id' => $matGsb->id],
            ['quantity' => 640.00, 'reserved_quantity' => 0.00]
        );

        // 6. Synthetic Subcontractor & Workers
        $subcontractor = Subcontractor::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-SUB-01'],
            [
                'name' => 'Synthetic Earthworks & Civil Associates',
                'contact_person' => 'Kiran Demo (Operations Coordinator)',
                'phone' => '+91 90000 10001',
                'email' => 'contractor@nsd-demo.example',
                'gst_number' => '27AABCS1111A1Z9',
                'pan_number' => 'AABCS1111A',
                'is_active' => true,
            ]
        );

        $workerSpecs = [
            ['DEMO-W-001', 'Aarav', 'Demo', '+91 90000 20001', 'employee', 'Senior Excavator Operator', $site1->id, null, 'monthly', 30000.00, 1000.00],
            ['DEMO-W-002', 'Vihaan', 'Demo', '+91 90000 20002', 'employee', 'Site Surveyor', $site1->id, null, 'monthly', 27000.00, 900.00],
            ['DEMO-W-003', 'Reyansh', 'Demo', '+91 90000 20003', 'direct_labour', 'Mason Grade-I', $site1->id, null, 'daily', 0.00, 800.00],
            ['DEMO-W-004', 'Ishaan', 'Demo', '+91 90000 20004', 'direct_labour', 'Compaction Helper', $site1->id, null, 'daily', 0.00, 650.00],
            ['DEMO-W-005', 'Kabir', 'Demo', '+91 90000 20005', 'subcontractor_worker', 'Bar Bender', $site2->id, $subcontractor->id, 'daily', 0.00, 850.00],
            ['DEMO-W-006', 'Advait', 'Demo', '+91 90000 20006', 'subcontractor_worker', 'Shuttering Carpenter', $site2->id, $subcontractor->id, 'daily', 0.00, 850.00],
        ];

        $workers = [];
        foreach ($workerSpecs as $spec) {
            $workers[$spec[0]] = Worker::firstOrCreate(
                ['company_id' => $company->id, 'code' => $spec[0]],
                [
                    'first_name' => $spec[1],
                    'last_name' => $spec[2],
                    'phone' => $spec[3],
                    'worker_type' => $spec[4],
                    'trade_designation' => $spec[5],
                    'assigned_site_id' => $spec[6],
                    'subcontractor_id' => $spec[7],
                    'pay_type' => $spec[8],
                    'monthly_salary' => $spec[9],
                    'daily_rate' => $spec[10],
                    'overtime_rate_per_hour' => 125.00,
                    'pin_hash' => Hash::make($readEnvOrConfig('DEMO_WORKER_PIN', 'worker_pin', Str::random(12))),
                    'status' => 'active',
                    'joining_date' => Carbon::now()->subMonths(2)->toDateString(),
                ]
            );
        }

        // 7. Synthetic Daily Attendance
        $today = Carbon::today()->toDateString();
        foreach (['DEMO-W-001', 'DEMO-W-002', 'DEMO-W-003'] as $code) {
            DailyAttendance::firstOrCreate(
                [
                    'company_id' => $company->id,
                    'site_id' => $site1->id,
                    'project_id' => $project1->id,
                    'worker_id' => $workers[$code]->id,
                    'attendance_date' => $today,
                    'clock_in' => '08:00:00',
                ],
                [
                    'clock_out' => '17:00:00',
                    'worked_hours' => 8.00,
                    'overtime_hours' => 1.00,
                    'status' => 'present',
                    'has_conflict' => false,
                    'approval_status' => 'approved',
                    'submitted_by' => $users['supervisor']->id,
                    'submitted_at' => Carbon::now()->subHours(3),
                    'approved_by' => $users['site_manager']->id,
                    'approved_at' => Carbon::now()->subHours(1),
                ]
            );
        }

        // 8. Synthetic Client, Work Order, BOQ, Measurements & Approved RA Bill
        $client = Client::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'DEMO-CL-JSW'],
            [
                'name' => 'JSW Demo Steel Infrastructure Ltd (Synthetic)',
                'address' => 'Synthetic Industrial Complex, Dolvi Works, Maharashtra',
                'gstin' => '27AAACJ9999D1Z2',
                'pan' => 'AAACJ9999D',
                'vendor_code' => 'VEND-DEMO-901',
                'contact_person' => 'Synthetic Project Engineer',
                'phone' => '+91 22 6600 1100',
                'email' => 'projects@jsw-demo.example',
                'is_active' => true,
            ]
        );

        $workOrder = WorkOrder::firstOrCreate(
            ['company_id' => $company->id, 'order_number' => 'WO-DEMO-2026-001'],
            [
                'project_id' => $project1->id,
                'site_id' => $site1->id,
                'client_id' => $client->id,
                'order_type' => 'client_issued',
                'work_category' => 'CIVIL',
                'client_reference' => 'PO-DEMO-4700199001',
                'vendor_code' => 'VEND-DEMO-901',
                'title' => 'Synthetic Earthwork Excavation, Backfilling & PCC M15 for Box Culvert',
                'description' => 'Execution of mechanical earthwork excavation and PCC bed for Storm Water Box Culvert.',
                'scope_of_work' => "1. Earthwork excavation in all strata\n2. Plain Cement Concrete M-15 leveling course",
                'revision_number' => 0,
                'issue_date' => '2026-04-15',
                'completion_date' => '2026-12-31',
                'retention_percent' => 5.00,
                'cgst_rate' => 9.00,
                'sgst_rate' => 9.00,
                'estimated_amount' => 450000.00,
                'status' => 'senior_manager_approved',
                'lifecycle_status' => 'in_progress',
                'created_by' => $users['site_manager']->id,
                'site_manager_id' => $users['site_manager']->id,
                'site_manager_approved_at' => Carbon::now()->subDays(5),
                'site_manager_notes' => 'Synthetic BOQ verified against survey drawings.',
                'senior_manager_id' => $users['senior_manager']->id,
                'senior_manager_approved_at' => Carbon::now()->subDays(4),
                'senior_manager_notes' => 'Approved for demo billing and execution.',
            ]
        );

        WorkOrderApproval::firstOrCreate(
            ['work_order_id' => $workOrder->id, 'stage' => 'site_manager'],
            ['action' => 'approved', 'actor_id' => $users['site_manager']->id, 'comments' => 'BOQ verified.']
        );
        WorkOrderApproval::firstOrCreate(
            ['work_order_id' => $workOrder->id, 'stage' => 'senior_manager'],
            ['action' => 'approved', 'actor_id' => $users['senior_manager']->id, 'comments' => 'Approved for execution.']
        );

        $item10 = WorkOrderItem::firstOrCreate(
            ['company_id' => $company->id, 'work_order_id' => $workOrder->id, 'sr_no' => '10'],
            [
                'service_code' => '4005010',
                'sac_code' => '9954',
                'description' => 'Earthwork Excavation in Soft/Hard Soil including disposal up to 1.5 km lead',
                'specification' => 'IS 1200 Part 1 compliant mechanical excavation',
                'unit' => 'M3',
                'ordered_quantity' => 1000.000,
                'rate' => 250.00,
                'line_amount' => 250000.00,
                'sort_order' => 1,
            ]
        );

        $item20 = WorkOrderItem::firstOrCreate(
            ['company_id' => $company->id, 'work_order_id' => $workOrder->id, 'sr_no' => '20'],
            [
                'service_code' => '4005020',
                'sac_code' => '9954',
                'description' => 'Providing & Laying Plain Cement Concrete M-15 for Foundation Leveling',
                'specification' => '100mm thick PCC M-15 bed with machine-mixed concrete',
                'unit' => 'M3',
                'ordered_quantity' => 50.000,
                'rate' => 4000.00,
                'line_amount' => 200000.00,
                'sort_order' => 2,
            ]
        );

        $raBill = RaBill::firstOrCreate(
            ['company_id' => $company->id, 'work_order_id' => $workOrder->id, 'ra_number' => 1],
            [
                'site_id' => $site1->id,
                'client_id' => $client->id,
                'ra_label' => '1ST RA BILL',
                'invoice_number' => 'CIVIL/01/26-27',
                'invoice_date' => '2026-05-15',
                'period_from' => '2026-04-15',
                'period_to' => '2026-05-15',
                'work_order_revision' => 0,
                'previous_bill_amount' => 0.00,
                'current_bill_amount' => 180000.00,
                'cumulative_bill_amount' => 180000.00,
                'excess_unbilled_amount' => 0.00,
                'cgst_rate' => 9.00,
                'sgst_rate' => 9.00,
                'cgst_amount' => 16200.00,
                'sgst_amount' => 16200.00,
                'gross_amount' => 212400.00,
                'retention_percent' => 5.00,
                'retention_deduction' => 9000.00,
                'advance_recovery' => 0.00,
                'other_deductions' => 0.00,
                'rounding_mode' => 'nearest_rupee',
                'net_payable' => 203400.00,
                'status' => 'approved',
                'created_by' => $users['site_manager']->id,
                'site_manager_id' => $users['site_manager']->id,
                'site_manager_approved_at' => Carbon::now()->subDays(2),
                'site_manager_notes' => 'Joint measurement verified on site.',
                'senior_manager_id' => $users['senior_manager']->id,
                'senior_manager_approved_at' => Carbon::now()->subDay(),
                'senior_manager_notes' => 'Approved for tax invoice & abstract export.',
            ]
        );

        MeasurementEntry::firstOrCreate(
            [
                'company_id' => $company->id,
                'work_order_id' => $workOrder->id,
                'work_order_item_id' => $item10->id,
                'location_chainage' => 'Box Culvert Ch 10+200 to 10+240',
            ],
            [
                'site_id' => $site1->id,
                'ra_bill_id' => $raBill->id,
                'measured_on' => '2026-05-10',
                'nos' => 1.000,
                'length' => 40.000,
                'breadth' => 5.000,
                'depth_height' => 2.000,
                'computed_quantity' => 400.000,
                'hold_quantity' => 0.000,
                'remarks' => 'Jointly measured with client engineer',
                'status' => 'billed',
                'recorded_by' => $users['supervisor']->id,
                'verified_by' => $users['site_manager']->id,
                'verified_at' => Carbon::now()->subDays(3),
            ]
        );

        MeasurementEntry::firstOrCreate(
            [
                'company_id' => $company->id,
                'work_order_id' => $workOrder->id,
                'work_order_item_id' => $item20->id,
                'location_chainage' => 'Box Culvert Bed PCC Ch 10+200 to 10+240',
            ],
            [
                'site_id' => $site1->id,
                'ra_bill_id' => $raBill->id,
                'measured_on' => '2026-05-12',
                'nos' => 1.000,
                'length' => 40.000,
                'breadth' => 5.000,
                'depth_height' => 0.100,
                'computed_quantity' => 20.000,
                'hold_quantity' => 0.000,
                'remarks' => '100mm PCC bed completed',
                'status' => 'billed',
                'recorded_by' => $users['supervisor']->id,
                'verified_by' => $users['site_manager']->id,
                'verified_at' => Carbon::now()->subDays(3),
            ]
        );

        RaBillItem::firstOrCreate(
            [
                'company_id' => $company->id,
                'ra_bill_id' => $raBill->id,
                'work_order_item_id' => $item10->id,
            ],
            [
                'sr_no' => '10',
                'service_code' => '4005010',
                'sac_code' => '9954',
                'description' => $item10->description,
                'specification' => $item10->specification,
                'unit' => 'M3',
                'ordered_quantity' => 1000.000,
                'rate' => 250.00,
                'order_amount' => 250000.00,
                'previous_quantity' => 0.000,
                'claimed_quantity' => 400.000,
                'hold_quantity' => 0.000,
                'effective_claimed_quantity' => 400.000,
                'remaining_available_quantity' => 1000.000,
                'current_billable_quantity' => 400.000,
                'excess_quantity' => 0.000,
                'cumulative_quantity' => 400.000,
                'balance_quantity' => 600.000,
                'consumption_percent' => 40.00,
                'warning_level' => 'normal',
                'previous_amount' => 0.00,
                'current_amount' => 100000.00,
                'cumulative_amount' => 100000.00,
                'excess_amount' => 0.00,
                'sort_order' => 1,
            ]
        );

        RaBillItem::firstOrCreate(
            [
                'company_id' => $company->id,
                'ra_bill_id' => $raBill->id,
                'work_order_item_id' => $item20->id,
            ],
            [
                'sr_no' => '20',
                'service_code' => '4005020',
                'sac_code' => '9954',
                'description' => $item20->description,
                'specification' => $item20->specification,
                'unit' => 'M3',
                'ordered_quantity' => 50.000,
                'rate' => 4000.00,
                'order_amount' => 200000.00,
                'previous_quantity' => 0.000,
                'claimed_quantity' => 20.000,
                'hold_quantity' => 0.000,
                'effective_claimed_quantity' => 20.000,
                'remaining_available_quantity' => 50.000,
                'current_billable_quantity' => 20.000,
                'excess_quantity' => 0.000,
                'cumulative_quantity' => 20.000,
                'balance_quantity' => 30.000,
                'consumption_percent' => 40.00,
                'warning_level' => 'normal',
                'previous_amount' => 0.00,
                'current_amount' => 80000.00,
                'cumulative_amount' => 80000.00,
                'excess_amount' => 0.00,
                'sort_order' => 2,
            ]
        );

        // 9. Synthetic Tasks across all 5 Kanban states & Field Notes
        $taskSpecs = [
            ['DEMO-TSK-001', 'Set out benchmark pegs for Ch 10+500 Box Culvert', 'todo', 'medium', 4],
            ['DEMO-TSK-002', 'Excavate foundation trench Ch 10+240 to 10+280', 'in_progress', 'high', 2],
            ['DEMO-TSK-003', 'Utility relocation clearance at Ch 10+300', 'blocked', 'critical', 1],
            ['DEMO-TSK-004', 'Cube compressive strength check for M-15 PCC batch', 'review', 'high', 1],
            ['DEMO-TSK-005', 'Site Dewatering pump installation at Sector 1 pit', 'completed', 'medium', -2],
        ];

        foreach ($taskSpecs as [$code, $title, $status, $priority, $dueOffsetDays]) {
            Task::firstOrCreate(
                ['company_id' => $company->id, 'code' => $code],
                [
                    'project_id' => $project1->id,
                    'site_id' => $site1->id,
                    'title' => $title,
                    'description' => "Synthetic demo operational task [{$code}] for Kanban board evaluation.",
                    'priority' => $priority,
                    'status' => $status,
                    'due_date' => Carbon::today()->addDays($dueOffsetDays)->toDateString(),
                    'completed_at' => $status === 'completed' ? Carbon::now()->subDay() : null,
                    'assigned_to' => $users['supervisor']->id,
                    'assigned_worker_id' => $workers['DEMO-W-001']->id,
                    'created_by' => $users['site_manager']->id,
                ]
            );
        }

        SiteNote::firstOrCreate(
            [
                'company_id' => $company->id,
                'site_id' => $site1->id,
                'title' => 'Daily HSE Briefing — Trench Shoring & Barricading',
            ],
            [
                'project_id' => $project1->id,
                'user_id' => $users['supervisor']->id,
                'category' => 'safety_hse',
                'body' => 'Synthetic demo field note: Hard barricading and reflective tape verified around 2.0m deep box culvert excavation pit.',
                'is_pinned' => true,
            ]
        );

        SiteNote::firstOrCreate(
            [
                'company_id' => $company->id,
                'site_id' => $site1->id,
                'title' => 'Client Engineer Joint Level Check Completed',
            ],
            [
                'project_id' => $project1->id,
                'user_id' => $users['site_manager']->id,
                'category' => 'quality',
                'body' => 'Synthetic demo field note: Joint auto-level readings signed off for Ch 10+200 to 10+240 PCC bed.',
                'is_pinned' => true,
            ]
        );
    }
}


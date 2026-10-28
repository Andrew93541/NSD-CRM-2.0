<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\DailyAttendance;
use App\Models\InventoryStock;
use App\Models\Material;
use App\Models\MaterialCategory;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseRequest;
use App\Models\PurchaseRequestItem;
use App\Models\Role;
use App\Models\Site;
use App\Models\Subcontractor;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WorkOrder;
use App\Models\WorkOrderApproval;
use App\Models\Worker;
use App\Services\Security\TenantContext;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production') || config('app.env') === 'production') {
            // Production safeguard: prevent seeding demo users and dummy operational data
            return;
        }

        // 1. Company
        $company = Company::firstOrCreate(
            ['code' => 'NSD'],
            [
                'name' => 'NSD Earth Movers & Construction Ltd',
                'is_active' => true,
            ]
        );

        // Bind tenant context for seeder operations
        TenantContext::setCompanyId($company->id);

        // 2. Roles
        $roles = [
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

        $roleModels = [];
        foreach ($roles as $slug => $data) {
            $roleModels[$slug] = Role::firstOrCreate(
                ['company_id' => $company->id, 'slug' => $slug],
                ['name' => $data['name'], 'permissions' => $data['permissions']]
            );
        }

        // 3. Users
        $password = Hash::make('password');

        $users = [
            'admin@nsd.local' => ['name' => 'Executive Director', 'role' => 'company_admin'],
            'senior.manager@nsd.local' => ['name' => 'Vikram Singhania (Senior Operations VP)', 'role' => 'senior_manager'],
            'site.manager@nsd.local' => ['name' => 'Arun Deshmukh (Lead Site Manager)', 'role' => 'site_manager'],
            'supervisor1@nsd.local' => ['name' => 'Ramesh Pawar (Site 1 Supervisor)', 'role' => 'site_supervisor'],
            'supervisor2@nsd.local' => ['name' => 'Suresh Shinde (Site 2 Supervisor)', 'role' => 'site_supervisor'],
            'warehouse@nsd.local' => ['name' => 'Dinesh Kulkarni (Central Depot Keeper)', 'role' => 'warehouse_manager'],
            'office@nsd.local' => ['name' => 'Pooja Mehta (Compliance Officer)', 'role' => 'office_staff'],
        ];

        $userModels = [];
        foreach ($users as $email => $data) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $data['name'],
                    'password' => $password,
                    'is_active' => true,
                ]
            );

            // Attach user to company with role
            $user->companies()->syncWithoutDetaching([
                $company->id => ['role_id' => $roleModels[$data['role']]->id],
            ]);

            $userModels[$email] = $user;
        }

        // 4. Projects
        $projHighway = Project::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'NH-48'],
            [
                'name' => 'Highway NH-48 6-Laning & Grade Separator',
                'client_name' => 'National Highways Authority of India (NHAI)',
                'description' => 'Heavy earthwork, culvert construction and asphalt paving over 45km',
                'status' => 'active',
                'manager_id' => $userModels['site.manager@nsd.local']->id,
                'start_date' => Carbon::now()->subMonths(6),
                'end_date' => Carbon::now()->addMonths(12),
            ]
        );

        $projMetro = Project::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'MTR-P3'],
            [
                'name' => 'Metro Rail Elevated Viaduct Package 3',
                'client_name' => 'Metro Rail Development Corp',
                'description' => 'Pier foundation, piling and station 4 structural earthworks',
                'status' => 'active',
                'manager_id' => $userModels['site.manager@nsd.local']->id,
                'start_date' => Carbon::now()->subMonths(3),
                'end_date' => Carbon::now()->addMonths(18),
            ]
        );

        // 5. Sites
        $site1 = Site::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'SITE-NH48-1'],
            [
                'project_id' => $projHighway->id,
                'name' => 'NH-48 Sector 1 (km 24 to 32) Earthworks',
                'location' => 'Sector 1 km 24-32 Corridor',
                'status' => 'active',
                'supervisor_id' => $userModels['supervisor1@nsd.local']->id,
            ]
        );

        $site2 = Site::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'SITE-NH48-2'],
            [
                'project_id' => $projHighway->id,
                'name' => 'NH-48 Sector 2 (km 32 to 45) Paving & Culverts',
                'location' => 'Sector 2 km 32-45 Corridor',
                'status' => 'active',
                'supervisor_id' => $userModels['supervisor1@nsd.local']->id,
            ]
        );

        $site3 = Site::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'SITE-MTR-04'],
            [
                'project_id' => $projMetro->id,
                'name' => 'Metro Station 4 & Yard Viaduct',
                'location' => 'Urban Terminal Station 4 Hub',
                'status' => 'active',
                'supervisor_id' => $userModels['supervisor2@nsd.local']->id,
            ]
        );

        // Assign site users
        $site1->assignedUsers()->syncWithoutDetaching([
            $userModels['site.manager@nsd.local']->id,
            $userModels['supervisor1@nsd.local']->id,
        ]);

        $site2->assignedUsers()->syncWithoutDetaching([
            $userModels['site.manager@nsd.local']->id,
            $userModels['supervisor1@nsd.local']->id,
        ]);

        $site3->assignedUsers()->syncWithoutDetaching([
            $userModels['site.manager@nsd.local']->id,
            $userModels['supervisor2@nsd.local']->id,
        ]);

        // 6. Warehouses (Central & Site-level Stores)
        $whCentral = Warehouse::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'WH-CENTRAL'],
            [
                'name' => 'NSD Central Supply Depot & Logistics Yard',
                'location_type' => 'central_warehouse',
                'manager_id' => $userModels['warehouse@nsd.local']->id,
                'address' => 'Highway Interchange Logistics Hub, Plot 14',
                'is_active' => true,
            ]
        );

        $whSite1 = Warehouse::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'WH-SITE-1'],
            [
                'site_id' => $site1->id,
                'name' => 'Site 1 Materials Yard & Fuel Bunker',
                'location_type' => 'site_store',
                'manager_id' => $userModels['supervisor1@nsd.local']->id,
                'address' => 'Sector 1 km 27 Base Camp',
                'is_active' => true,
            ]
        );

        $whSite2 = Warehouse::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'WH-SITE-2'],
            [
                'site_id' => $site2->id,
                'name' => 'Site 2 Asphalt & Aggregate Stockpile',
                'location_type' => 'site_store',
                'manager_id' => $userModels['supervisor1@nsd.local']->id,
                'address' => 'Sector 2 km 38 Batch Plant Camp',
                'is_active' => true,
            ]
        );

        $whSite3 = Warehouse::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'WH-SITE-3'],
            [
                'site_id' => $site3->id,
                'name' => 'Metro Station 4 Steel & Formwork Yard',
                'location_type' => 'site_store',
                'manager_id' => $userModels['supervisor2@nsd.local']->id,
                'address' => 'Station 4 East Enclosure',
                'is_active' => true,
            ]
        );

        // 7. Subcontractor Companies
        $subApex = Subcontractor::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'SUB-APEX'],
            [
                'name' => 'Apex Heavy Plant & Earthmoving Works',
                'contact_person' => 'Rajesh Verma (General Manager)',
                'phone' => '+91 98230 11223',
                'email' => 'operations@apexplant.com',
                'gst_number' => '27AAACA1234A1Z1', 'pan_number' => 'AAACA1234A',
                'is_active' => true,
            ]
        );

        $subVanguard = Subcontractor::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'SUB-VANG'],
            [
                'name' => 'Vanguard Bar Bending & Steel Solutions',
                'contact_person' => 'Sunil Patil (Site Incharge)',
                'phone' => '+91 98221 44556',
                'email' => 'patil@vanguardsteel.in',
                'gst_number' => '27AAACB5678B1Z2', 'pan_number' => 'AAACB5678B',
                'is_active' => true,
            ]
        );

        $subSai = Subcontractor::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'SUB-SAI'],
            [
                'name' => 'Shree Sai Logistics & Haulage Carriers',
                'contact_person' => 'Manoj Kumar',
                'phone' => '+91 98212 77889',
                'email' => 'transport@shreesailogistics.com',
                'gst_number' => '27AAACC9012C1Z3', 'pan_number' => 'AAACC9012C',
                'is_active' => true,
            ]
        );

        // 8. Workers (Employees, Direct Labour, Subcontractor Workers)
        $sampleWorkers = [
            // Employees
            ['W-EMP-001', 'Anand', 'Kulkarni', '+91 91111 00001', 'employee', 'Senior Excavator Operator', $site1->id, null, 1200.00],
            ['W-EMP-002', 'Ganesh', 'Jadhav', '+91 91111 00002', 'employee', 'Bulldozer / Motor Grader Pilot', $site1->id, null, 1100.00],
            ['W-EMP-003', 'Mahesh', 'Chavan', '+91 91111 00003', 'employee', 'Surveyor & Level Instrument Tech', $site2->id, null, 1400.00],
            ['W-EMP-004', 'Nilesh', 'More', '+91 91111 00004', 'employee', 'Mechanical Master Mechanic', $site1->id, null, 1300.00],
            ['W-EMP-005', 'Santosh', 'Gaikwad', '+91 91111 00005', 'employee', 'Transit Mixer Operator', $site3->id, null, 1050.00],

            // Direct Labourers
            ['W-LAB-101', 'Ramu', 'Kashyap', '+91 92222 00001', 'direct_labour', 'Earth Trenching & Levelling', $site1->id, null, 650.00],
            ['W-LAB-102', 'Babu', 'Munda', '+91 92222 00002', 'direct_labour', 'Compaction Assistant', $site1->id, null, 650.00],
            ['W-LAB-103', 'Chhotu', 'Yadav', '+91 92222 00003', 'direct_labour', 'General Masonry Helper', $site2->id, null, 700.00],
            ['W-LAB-104', 'Dinesh', 'Majhi', '+91 92222 00004', 'direct_labour', 'Drainage Channel Digger', $site2->id, null, 650.00],
            ['W-LAB-105', 'Somnath', 'Tudu', '+91 92222 00005', 'direct_labour', 'Concrete Pouring Crew', $site3->id, null, 750.00],

            // Subcontractor Apex
            ['W-SUB-201', 'Vijay', 'Verma', '+91 93333 00001', 'subcontractor_worker', 'Hydraulic Breaker Operator', $site1->id, $subApex->id, 950.00],
            ['W-SUB-202', 'Amit', 'Tiwari', '+91 93333 00002', 'subcontractor_worker', 'JCB Excavator Specialist', $site1->id, $subApex->id, 950.00],
            ['W-SUB-203', 'Pradeep', 'Shukla', '+91 93333 00003', 'subcontractor_worker', 'Rock Drill Technician', $site2->id, $subApex->id, 900.00],

            // Subcontractor Vanguard
            ['W-SUB-301', 'Mohan', 'Suthar', '+91 94444 00001', 'subcontractor_worker', 'Master Bar Bender & Cutter', $site3->id, $subVanguard->id, 900.00],
            ['W-SUB-302', 'Kishore', 'Panchal', '+91 94444 00002', 'subcontractor_worker', 'Pillar Formwork Carpenter', $site3->id, $subVanguard->id, 850.00],
            ['W-SUB-303', 'Ratan', 'Lohar', '+91 94444 00003', 'subcontractor_worker', 'Tying Wire Specialist', $site3->id, $subVanguard->id, 800.00],

            // Subcontractor Shree Sai
            ['W-SUB-401', 'Gurmeet', 'Singh', '+91 95555 00001', 'subcontractor_worker', '10-Wheel Tipper Driver', $site1->id, $subSai->id, 900.00],
            ['W-SUB-402', 'Balwinder', 'Singh', '+91 95555 00002', 'subcontractor_worker', 'Heavy Dumper Operator', $site2->id, $subSai->id, 900.00],
        ];

        $workerModels = [];
        foreach ($sampleWorkers as $w) {
            $workerModels[$w[0]] = Worker::firstOrCreate(
                ['company_id' => $company->id, 'code' => $w[0]],
                [
                    'first_name' => $w[1],
                    'last_name' => $w[2],
                    'phone' => $w[3],
                    'worker_type' => $w[4],
                    'trade_designation' => $w[5],
                    'assigned_site_id' => $w[6],
                    'subcontractor_id' => $w[7],
                    'daily_rate' => $w[8],
                    'status' => 'active',
                    'joining_date' => Carbon::now()->subMonths(4),
                ]
            );
        }

        // 9. Material Categories & Materials
        $catAgg = MaterialCategory::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'AGG'],
            ['name' => 'Aggregates & Stone Base']
        );
        $catCem = MaterialCategory::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'CEM'],
            ['name' => 'Cement & Binding Materials']
        );
        $catStl = MaterialCategory::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'STL'],
            ['name' => 'Reinforcement Steel & TMT']
        );
        $catFuel = MaterialCategory::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'FUL'],
            ['name' => 'Fuel, Oils & Lubricants']
        );
        $catPpe = MaterialCategory::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'PPE'],
            ['name' => 'Safety Gear & PPE']
        );

        $matGSB = Material::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'GSB-40MM'],
            [
                'category_id' => $catAgg->id,
                'name' => 'Granular Sub-Base (GSB) 40mm Crushed Stone',
                'unit_of_measure' => 'MT',
                'minimum_stock_level' => 500.00,
                'standard_unit_cost' => 650.00,
                'is_active' => true,
            ]
        );

        $matCement = Material::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'CEM-OPC-53'],
            [
                'category_id' => $catCem->id,
                'name' => 'OPC 53 Grade High Performance Cement',
                'unit_of_measure' => 'Bags',
                'minimum_stock_level' => 300.00,
                'standard_unit_cost' => 380.00,
                'is_active' => true,
            ]
        );

        $matSteel16 = Material::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'TMT-16MM'],
            [
                'category_id' => $catStl->id,
                'name' => 'Fe 500D High Ductility TMT Rebar 16mm',
                'unit_of_measure' => 'MT',
                'minimum_stock_level' => 50.00,
                'standard_unit_cost' => 58000.00,
                'is_active' => true,
            ]
        );

        $matDiesel = Material::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'HSD-DIESEL'],
            [
                'category_id' => $catFuel->id,
                'name' => 'High Speed Industrial Diesel (HSD)',
                'unit_of_measure' => 'Litres',
                'minimum_stock_level' => 2000.00,
                'standard_unit_cost' => 92.50,
                'is_active' => true,
            ]
        );

        $matHelmet = Material::firstOrCreate(
            ['company_id' => $company->id, 'code' => 'PPE-HELMET'],
            [
                'category_id' => $catPpe->id,
                'name' => 'Industrial Safety Helmet EN397 (Yellow/White)',
                'unit_of_measure' => 'Units',
                'minimum_stock_level' => 50.00,
                'standard_unit_cost' => 220.00,
                'is_active' => true,
            ]
        );

        // 10. Initial Stock Balances
        InventoryStock::firstOrCreate(
            ['company_id' => $company->id, 'warehouse_id' => $whCentral->id, 'material_id' => $matDiesel->id],
            ['quantity' => 12500.00, 'reserved_quantity' => 0.00]
        );
        InventoryStock::firstOrCreate(
            ['company_id' => $company->id, 'warehouse_id' => $whCentral->id, 'material_id' => $matCement->id],
            ['quantity' => 1400.00, 'reserved_quantity' => 200.00]
        );
        InventoryStock::firstOrCreate(
            ['company_id' => $company->id, 'warehouse_id' => $whCentral->id, 'material_id' => $matSteel16->id],
            ['quantity' => 180.00, 'reserved_quantity' => 25.00]
        );

        InventoryStock::firstOrCreate(
            ['company_id' => $company->id, 'warehouse_id' => $whSite1->id, 'material_id' => $matGSB->id],
            ['quantity' => 1250.00, 'reserved_quantity' => 0.00]
        );
        InventoryStock::firstOrCreate(
            ['company_id' => $company->id, 'warehouse_id' => $whSite1->id, 'material_id' => $matDiesel->id],
            ['quantity' => 4500.00, 'reserved_quantity' => 0.00]
        );

        // 11. Work Orders with 2-Tier Approvals
        $wo1 = WorkOrder::firstOrCreate(
            ['company_id' => $company->id, 'order_number' => 'WO-NSD-2026-001'],
            [
                'project_id' => $projHighway->id,
                'site_id' => $site1->id,
                'order_type' => 'client_issued',
                'client_reference' => 'NHAI-PKG-48-WO-889',
                'title' => 'Rock Cutting & Trenching at km 26+400 Culvert Crossing',
                'description' => 'Excavate 12,000 cu.m rocky strata and construct reinforced bed for precast box culvert.',
                'scope_of_work' => '1. Excavation\n2. Bed leveling\n3. Culvert placement',
                'estimated_amount' => 450000.00,
                'status' => 'senior_manager_approved',
                'created_by' => $userModels['site.manager@nsd.local']->id,
                'site_manager_id' => $userModels['site.manager@nsd.local']->id,
                'site_manager_approved_at' => Carbon::now()->subDays(3),
                'site_manager_notes' => 'Verified with site surveys. Equipment allocated.',
                'senior_manager_id' => $userModels['senior.manager@nsd.local']->id,
                'senior_manager_approved_at' => Carbon::now()->subDays(2),
                'senior_manager_notes' => 'Approved for immediate execution under contract item 4.2.',
            ]
        );

        WorkOrderApproval::firstOrCreate(
            ['work_order_id' => $wo1->id, 'stage' => 'site_manager'],
            ['action' => 'approved', 'actor_id' => $userModels['site.manager@nsd.local']->id, 'comments' => 'Verified with site surveys.']
        );
        WorkOrderApproval::firstOrCreate(
            ['work_order_id' => $wo1->id, 'stage' => 'senior_manager'],
            ['action' => 'approved', 'actor_id' => $userModels['senior.manager@nsd.local']->id, 'comments' => 'Final authorization granted.']
        );

        $wo2 = WorkOrder::firstOrCreate(
            ['company_id' => $company->id, 'order_number' => 'WO-NSD-2026-002'],
            [
                'project_id' => $projHighway->id,
                'site_id' => $site2->id,
                'order_type' => 'internal',
                'title' => 'Emergency Haul Road Grading & Rain Slag Spreading',
                'description' => 'Strengthen 3.5km detour haul route to prevent dumper skidding during pre-monsoon showers.',
                'scope_of_work' => 'Grading and aggregate spreading',
                'estimated_amount' => 85000.00,
                'status' => 'site_manager_approved',
                'created_by' => $userModels['site.manager@nsd.local']->id,
                'site_manager_id' => $userModels['site.manager@nsd.local']->id,
                'site_manager_approved_at' => Carbon::now()->subHours(12),
                'site_manager_notes' => 'Recommended urgently to keep dumpers operational.',
            ]
        );

        WorkOrderApproval::firstOrCreate(
            ['work_order_id' => $wo2->id, 'stage' => 'site_manager'],
            ['action' => 'approved', 'actor_id' => $userModels['site.manager@nsd.local']->id, 'comments' => 'Recommended urgently.']
        );

        $wo3 = WorkOrder::firstOrCreate(
            ['company_id' => $company->id, 'order_number' => 'WO-NSD-2026-003'],
            [
                'project_id' => $projMetro->id,
                'site_id' => $site3->id,
                'order_type' => 'client_issued',
                'client_reference' => 'MRDC-METRO-PKG3-042',
                'title' => 'Pier Foundation Pile Cap Reinforcement Station 4',
                'description' => 'Binding 48 MT TMT Fe500D steel rebar for Pier 14 through Pier 18.',
                'scope_of_work' => 'TMT bar cutting, bending and tied cage installation',
                'estimated_amount' => 2800000.00,
                'status' => 'submitted',
                'created_by' => $userModels['site.manager@nsd.local']->id,
            ]
        );

        // 12. Realistic Multi-Site Attendance Demonstration
        // Anand Kulkarni (W-EMP-001) worked at Site 1 in morning, then moved to Site 2 in afternoon!
        $today = Carbon::today()->format('Y-m-d');
        DailyAttendance::firstOrCreate(
            [
                'company_id' => $company->id,
                'site_id' => $site1->id,
                'project_id' => $projHighway->id,
                'worker_id' => $workerModels['W-EMP-001']->id,
                'attendance_date' => $today,
                'clock_in' => '07:30:00',
            ],
            [
                'clock_out' => '12:30:00',
                'worked_hours' => 5.00,
                'status' => 'present',
                'has_conflict' => false,
                'approval_status' => 'approved',
                'submitted_by' => $userModels['supervisor1@nsd.local']->id,
                'submitted_at' => Carbon::now()->subHours(4),
                'approved_by' => $userModels['site.manager@nsd.local']->id,
                'approved_at' => Carbon::now()->subHours(2),
            ]
        );

        DailyAttendance::firstOrCreate(
            [
                'company_id' => $company->id,
                'site_id' => $site2->id,
                'project_id' => $projHighway->id,
                'worker_id' => $workerModels['W-EMP-001']->id,
                'attendance_date' => $today,
                'clock_in' => '14:00:00',
            ],
            [
                'clock_out' => '18:30:00',
                'worked_hours' => 4.50,
                'status' => 'present',
                'has_conflict' => false,
                'approval_status' => 'approved',
                'submitted_by' => $userModels['supervisor1@nsd.local']->id,
                'submitted_at' => Carbon::now()->subHour(),
                'approved_by' => $userModels['site.manager@nsd.local']->id,
                'approved_at' => Carbon::now()->subMinutes(30),
            ]
        );

        // Worker #2 at Site 1
        DailyAttendance::firstOrCreate(
            [
                'company_id' => $company->id,
                'site_id' => $site1->id,
                'project_id' => $projHighway->id,
                'worker_id' => $workerModels['W-EMP-002']->id,
                'attendance_date' => $today,
                'clock_in' => '08:00:00',
            ],
            [
                'clock_out' => '17:00:00',
                'worked_hours' => 9.00,
                'status' => 'present',
                'has_conflict' => false,
                'approval_status' => 'approved',
                'submitted_by' => $userModels['supervisor1@nsd.local']->id,
                'submitted_at' => Carbon::now()->subHours(4),
                'approved_by' => $userModels['site.manager@nsd.local']->id,
                'approved_at' => Carbon::now()->subHours(2),
            ]
        );

        // Subcontractor Apex worker at Site 1
        DailyAttendance::firstOrCreate(
            [
                'company_id' => $company->id,
                'site_id' => $site1->id,
                'project_id' => $projHighway->id,
                'worker_id' => $workerModels['W-SUB-201']->id,
                'attendance_date' => $today,
                'clock_in' => '08:30:00',
            ],
            [
                'clock_out' => '17:30:00',
                'worked_hours' => 9.00,
                'status' => 'present',
                'has_conflict' => false,
                'approval_status' => 'approved',
                'submitted_by' => $userModels['supervisor1@nsd.local']->id,
                'submitted_at' => Carbon::now()->subHours(4),
                'approved_by' => $userModels['site.manager@nsd.local']->id,
                'approved_at' => Carbon::now()->subHours(2),
            ]
        );
    }
}

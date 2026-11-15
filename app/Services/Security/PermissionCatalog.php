<?php

namespace App\Services\Security;

class PermissionCatalog
{
    /**
     * System-critical role slugs that cannot be deleted or have their slug renamed.
     */
    public const SYSTEM_ROLE_SLUGS = [
        'super_admin',
        'company_admin',
        'senior_manager',
        'site_manager',
        'site_supervisor',
        'supervisor',
        'warehouse_manager',
        'office_staff',
    ];

    public const PROTECTED_ADMIN_SLUGS = [
        'super_admin',
        'company_admin',
    ];

    /**
     * Equivalent permission keys for marking daily attendance (legacy + modern keys).
     */
    public const ATTENDANCE_MARK_KEYS = [
        'attendance.mark',
        'attendance.record',
        'attendance.muster',
    ];

    /**
     * Attendance permissions that are NEVER granted automatically to Company Admin / Super Admin
     * or via wildcard ('*') permissions, and require explicit grant.
     */
    public const ADMIN_RESTRICTED_ATTENDANCE_PERMISSIONS = [
        'attendance.mark',
        'attendance.record',
        'attendance.muster',
        'attendance.edit',
        'attendance.approve',
    ];

    /**
     * Attendance permissions that cannot be granted by a legacy '*' wildcard on any role.
     */
    public const WILDCARD_EXCLUDED_ATTENDANCE_PERMISSIONS = [
        'attendance.mark',
        'attendance.record',
        'attendance.muster',
        'attendance.edit',
        'attendance.approve',
        'attendance.export',
    ];

    /**
     * Administrative permissions that custom roles cannot acquire to prevent privilege escalation.
     */
    public const ADMIN_RESERVED_PERMISSIONS = [
        '*',
        'users.view',
        'users.create',
        'users.edit',
        'users.update',
        'users.deactivate',
        'users.reset_password',
        'users.assign_sites',
        'users.manage_permissions',
        'users.manage',
        'roles.view',
        'roles.create',
        'roles.edit',
        'roles.update',
        'roles.delete',
        'settings.manage',
    ];

    /**
     * Complete module-level permission catalog derived from actual CRM routes, controllers, and policies.
     *
     * @return array<string, array{label: string, description: string, permissions: array<string, string>}>
     */
    public static function modules(): array
    {
        return [
            'dashboard' => [
                'label' => 'Dashboard',
                'description' => 'Operational overview, KPIs, and site summaries',
                'permissions' => [
                    'dashboard.view' => 'View Dashboard',
                ],
            ],
            'users' => [
                'label' => 'Users & Roles (Admin)',
                'description' => 'Employee accounts, roles, permissions, and security controls',
                'permissions' => [
                    'users.view' => 'View Users & Roles',
                    'users.create' => 'Create User Accounts',
                    'users.edit' => 'Edit Users & Site Access',
                    'users.manage' => 'Manage Passwords, 2FA & Overrides',
                    'roles.view' => 'View Roles & Matrix',
                    'roles.create' => 'Create Custom Roles',
                    'roles.edit' => 'Edit Custom Roles',
                    'roles.delete' => 'Delete Unused Custom Roles',
                    'settings.manage' => 'Manage Company Profile, GST, Bank & Invoice Settings',
                ],
            ],
            'projects' => [
                'label' => 'Projects',
                'description' => 'Construction projects, milestones, and project documents',
                'permissions' => [
                    'projects.view' => 'View Projects',
                    'projects.create' => 'Create Projects',
                    'projects.update' => 'Edit Projects & Milestones',
                    'projects.manage' => 'Manage Project Documents',
                ],
            ],
            'sites' => [
                'label' => 'Sites',
                'description' => 'Construction sites, geofences, and site assignments',
                'permissions' => [
                    'sites.view' => 'View Sites',
                    'sites.create' => 'Create Sites',
                    'sites.update' => 'Edit Sites & Assignments',
                    'sites.delete' => 'Delete Empty Sites',
                    'sites.manage' => 'Manage All Site Settings',
                ],
            ],
            'workforce' => [
                'label' => 'Workers & Workforce',
                'description' => 'Direct employees, labourers, subcontractors, and worker documents',
                'permissions' => [
                    'workforce.view' => 'View Assigned Site Workers',
                    'workforce.view_all' => 'View All Company Workers',
                    'workforce.create' => 'Create & Import Workers',
                    'workforce.update' => 'Edit Workers & Reset PINs',
                    'workforce.delete' => 'Delete Worker Documents',
                    'workforce.export' => 'Export Workforce CSV',
                    'workforce.manage' => 'Full Workforce Management',
                ],
            ],
            'attendance' => [
                'label' => 'Attendance',
                'description' => 'Monthly attendance review, daily marking, corrections, approvals, and exports',
                'permissions' => [
                    'attendance.view' => 'View Monthly & Daily Attendance',
                    'attendance.mark' => 'Mark Daily Attendance',
                    'attendance.record' => 'Record Daily Muster & Punches',
                    'attendance.muster' => 'Manage Muster & Shifts',
                    'attendance.edit' => 'Request or Edit Attendance Corrections',
                    'attendance.approve' => 'Approve or Reject Attendance Corrections',
                    'attendance.export' => 'Export Attendance & Payroll CSV',
                    'attendance.manage' => 'Lock / Unlock Attendance Days',
                ],
            ],
            'payroll' => [
                'label' => 'Payroll & Payslips',
                'description' => 'Payroll periods, salary advances, adjustments, and payslips',
                'permissions' => [
                    'payroll.view' => 'View Payroll & Payslips',
                    'payroll.create' => 'Create Payroll Periods & Advances',
                    'payroll.edit' => 'Recalculate & Adjust Payroll Entries',
                    'payroll.approve' => 'Approve & Lock Payroll Periods',
                ],
            ],
            'work_orders' => [
                'label' => 'Work Orders & BOQ',
                'description' => 'Client PO uploads, BOQ items, revisions, and two-tier approvals',
                'permissions' => [
                    'work_orders.view' => 'View Work Orders',
                    'work_orders.create' => 'Create Work Orders & Upload POs',
                    'work_orders.edit' => 'Edit Revisions & Lifecycle',
                    'work_orders.approve_site' => 'Tier-1 Site Manager Approval',
                    'work_orders.approve_senior' => 'Tier-2 Senior Manager Approval',
                    'work_orders.export' => 'Print & Export Work Order PDF',
                ],
            ],
            'tasks' => [
                'label' => 'Site Tasks',
                'description' => 'Kanban board tasks, status transitions, and task comments',
                'permissions' => [
                    'tasks.view' => 'View Site Tasks',
                    'tasks.create' => 'Create Site Tasks',
                    'tasks.edit' => 'Update Task Status & Comments',
                    'tasks.delete' => 'Delete Site Tasks',
                ],
            ],
            'notes' => [
                'label' => 'Field Notes',
                'description' => 'Daily HSE, quality, handover notes, and task conversion',
                'permissions' => [
                    'notes.view' => 'View Field Notes',
                    'notes.create' => 'Create Field Notes',
                    'notes.edit' => 'Pin & Convert Field Notes',
                    'notes.delete' => 'Delete Field Notes',
                ],
            ],
            'measurements' => [
                'label' => 'Site Measurements',
                'description' => 'Joint measurement book entries and photo evidence',
                'permissions' => [
                    'measurements.view' => 'View Measurements',
                    'measurements.create' => 'Record Site Measurements',
                    'measurements.approve' => 'Verify Site Measurements',
                ],
            ],
            'ra_bills' => [
                'label' => 'RA Bills',
                'description' => 'Running Account bills, retention, GST, and two-tier approvals',
                'permissions' => [
                    'ra_bills.view' => 'View RA Bills',
                    'ra_bills.create' => 'Create & Submit RA Bills',
                    'ra_bills.edit' => 'Update Draft RA Bills',
                    'ra_bills.approve_site' => 'Tier-1 Verify RA Bills',
                    'ra_bills.approve_senior' => 'Tier-2 Approve RA Bills',
                    'ra_bills.export' => 'Export RA Bill Abstract PDF & Excel',
                ],
            ],
            'invoices' => [
                'label' => 'Tax Invoices',
                'description' => 'Financial-year tax invoice generation and PDF/Excel exports',
                'permissions' => [
                    'invoices.view' => 'View Tax Invoices',
                    'invoices.create' => 'Generate Tax Invoice Numbers',
                    'invoices.edit' => 'Edit Draft Invoice Metadata',
                    'invoices.delete' => 'Delete Draft Invoices',
                    'invoices.export' => 'Export Tax Invoice PDF & Excel',
                ],
            ],
            'procurement' => [
                'label' => 'Procurement',
                'description' => 'Material purchase requests and purchase orders',
                'permissions' => [
                    'procurement.view' => 'View Purchase Requests & POs',
                    'procurement.request' => 'Create Purchase Requests',
                    'procurement.approve' => 'Approve Requests & Issue POs',
                    'procurement.manage' => 'Manage Procurement Workflows',
                ],
            ],
            'inventory' => [
                'label' => 'Inventory & Stores',
                'description' => 'Warehouse stock balances, goods receipt, and material issues',
                'permissions' => [
                    'inventory.view' => 'View Warehouse Stock & Ledger',
                    'inventory.receive' => 'Receive Delivered Goods (GRN)',
                    'inventory.issue' => 'Issue Stock',
                    'inventory.manage' => 'Manage Warehouses & Materials',
                ],
            ],
            'reports' => [
                'label' => 'Operational Reports',
                'description' => 'Workforce, multi-site comparison, work order, and inventory reports',
                'permissions' => [
                    'reports.view' => 'View Operational Reports',
                    'reports.export' => 'Export Report CSVs',
                ],
            ],
            'audit_logs' => [
                'label' => 'Audit Logs',
                'description' => 'Immutable security and operational audit trail',
                'permissions' => [
                    'audit_logs.view' => 'View Security Audit Logs',
                    'audit_logs.export' => 'Export Audit Logs CSV',
                ],
            ],
        ];
    }

    /**
     * Flat list of all valid permission keys across all modules.
     *
     * @return array<int, string>
     */
    public static function allPermissionKeys(): array
    {
        $keys = [];
        foreach (self::modules() as $module) {
            foreach (array_keys($module['permissions']) as $permKey) {
                $keys[] = $permKey;
            }
        }

        return $keys;
    }

    /**
     * Non-admin assignable permission keys for custom roles.
     *
     * @return array<int, string>
     */
    public static function assignableCustomRolePermissionKeys(): array
    {
        return array_values(array_diff(
            self::allPermissionKeys(),
            self::ADMIN_RESERVED_PERMISSIONS
        ));
    }

    /**
     * Default permissions for predefined system roles (preserves all existing role capabilities).
     *
     * @return array<string, array<int, string>>
     */
    public static function defaultRolePermissions(): array
    {
        return [
            'super_admin' => ['*'],
            'company_admin' => ['*'],
            'senior_manager' => [
                'dashboard.view',
                'projects.view', 'projects.create', 'projects.update', 'projects.manage',
                'sites.view', 'sites.create', 'sites.update', 'sites.delete', 'sites.manage',
                'workforce.view', 'workforce.view_all', 'workforce.create', 'workforce.update', 'workforce.delete', 'workforce.export', 'workforce.manage',
                'attendance.view', 'attendance.export', 'attendance.manage',
                'payroll.view', 'payroll.create', 'payroll.edit', 'payroll.approve',
                'work_orders.view', 'work_orders.create', 'work_orders.edit', 'work_orders.approve_senior', 'work_orders.export',
                'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.delete',
                'notes.view', 'notes.create', 'notes.edit', 'notes.delete',
                'measurements.view', 'measurements.create', 'measurements.approve',
                'ra_bills.view', 'ra_bills.create', 'ra_bills.edit', 'ra_bills.approve_senior', 'ra_bills.export',
                'invoices.view', 'invoices.create', 'invoices.export',
                'procurement.view', 'procurement.approve', 'procurement.manage',
                'inventory.view', 'inventory.receive', 'inventory.issue', 'inventory.manage',
                'reports.view', 'reports.export',
                'audit_logs.view', 'audit_logs.export',
            ],
            'site_manager' => [
                'dashboard.view',
                'projects.view', 'projects.manage',
                'sites.view', 'sites.update',
                'workforce.view', 'workforce.create', 'workforce.update', 'workforce.delete', 'workforce.export',
                'attendance.view', 'attendance.mark', 'attendance.record', 'attendance.muster', 'attendance.edit', 'attendance.export', 'attendance.manage',
                'payroll.view', 'payroll.create', 'payroll.edit',
                'work_orders.view', 'work_orders.create', 'work_orders.edit', 'work_orders.approve_site', 'work_orders.export',
                'tasks.view', 'tasks.create', 'tasks.edit', 'tasks.delete',
                'notes.view', 'notes.create', 'notes.edit', 'notes.delete',
                'measurements.view', 'measurements.create', 'measurements.approve',
                'ra_bills.view', 'ra_bills.create', 'ra_bills.edit', 'ra_bills.approve_site', 'ra_bills.export',
                'invoices.view', 'invoices.create', 'invoices.export',
                'procurement.view', 'procurement.request',
                'inventory.view', 'inventory.receive', 'inventory.issue',
                'reports.view', 'reports.export',
            ],
            'site_supervisor' => [
                'dashboard.view',
                'projects.view',
                'sites.view',
                'workforce.view',
                'attendance.view', 'attendance.mark', 'attendance.record', 'attendance.muster', 'attendance.edit',
                'work_orders.view',
                'tasks.view', 'tasks.create', 'tasks.edit',
                'notes.view', 'notes.create', 'notes.edit',
                'measurements.view', 'measurements.create',
                'ra_bills.view',
                'procurement.view',
                'inventory.view', 'inventory.receive',
                'reports.view',
            ],
            'supervisor' => [
                'dashboard.view',
                'projects.view',
                'sites.view',
                'workforce.view',
                'attendance.view', 'attendance.mark', 'attendance.record', 'attendance.muster', 'attendance.edit',
                'work_orders.view',
                'tasks.view', 'tasks.create', 'tasks.edit',
                'notes.view', 'notes.create', 'notes.edit',
                'measurements.view', 'measurements.create',
                'ra_bills.view',
                'procurement.view',
                'inventory.view', 'inventory.receive',
                'reports.view',
            ],
            'warehouse_manager' => [
                'dashboard.view',
                'sites.view',
                'procurement.view', 'procurement.request', 'procurement.approve',
                'inventory.view', 'inventory.receive', 'inventory.issue', 'inventory.manage',
                'reports.view',
            ],
            'office_staff' => [
                'dashboard.view',
                'projects.view',
                'sites.view',
                'workforce.view', 'workforce.view_all', 'workforce.export',
                'attendance.view',
                'payroll.view', 'payroll.create', 'payroll.edit',
                'work_orders.view', 'work_orders.edit', 'work_orders.export',
                'tasks.view',
                'notes.view',
                'measurements.view', 'measurements.create',
                'ra_bills.view', 'ra_bills.create', 'ra_bills.edit', 'ra_bills.export',
                'invoices.view', 'invoices.create', 'invoices.export',
                'procurement.view',
                'inventory.view',
                'reports.view', 'reports.export',
                'audit_logs.view', 'audit_logs.export',
            ],
        ];
    }

    /**
     * Suggested custom role templates for quick creation by Company Admins.
     *
     * @return array<string, array{name: string, slug: string, description: string, permissions: array<int, string>}>
     */
    public static function customRoleTemplates(): array
    {
        return [
            'accountant' => [
                'name' => 'Accountant',
                'slug' => 'accountant',
                'description' => 'Manages RA bills, tax invoices, payroll calculations, and financial reports.',
                'permissions' => [
                    'dashboard.view',
                    'sites.view',
                    'workforce.view',
                    'attendance.view', 'attendance.export',
                    'payroll.view', 'payroll.create', 'payroll.edit',
                    'work_orders.view', 'work_orders.export',
                    'measurements.view',
                    'ra_bills.view', 'ra_bills.create', 'ra_bills.edit', 'ra_bills.export',
                    'invoices.view', 'invoices.create', 'invoices.export',
                    'reports.view', 'reports.export',
                ],
            ],
            'hr_manager' => [
                'name' => 'HR Manager',
                'slug' => 'hr_manager',
                'description' => 'Manages workforce onboarding, attendance approvals, leaves, and payroll.',
                'permissions' => [
                    'dashboard.view',
                    'sites.view',
                    'workforce.view', 'workforce.view_all', 'workforce.create', 'workforce.update', 'workforce.export', 'workforce.manage',
                    'attendance.view', 'attendance.record', 'attendance.muster', 'attendance.approve', 'attendance.export',
                    'payroll.view', 'payroll.create', 'payroll.edit', 'payroll.approve',
                    'reports.view', 'reports.export',
                ],
            ],
            'site_engineer' => [
                'name' => 'Site Engineer',
                'slug' => 'site_engineer',
                'description' => 'Records site measurements, manages field tasks/notes, and drafts RA bills.',
                'permissions' => [
                    'dashboard.view',
                    'projects.view',
                    'sites.view',
                    'workforce.view',
                    'attendance.view', 'attendance.record',
                    'work_orders.view', 'work_orders.export',
                    'tasks.view', 'tasks.create', 'tasks.edit',
                    'notes.view', 'notes.create', 'notes.edit',
                    'measurements.view', 'measurements.create',
                    'ra_bills.view', 'ra_bills.create', 'ra_bills.edit',
                    'procurement.view', 'procurement.request',
                    'inventory.view',
                ],
            ],
            'procurement_officer' => [
                'name' => 'Procurement Officer',
                'slug' => 'procurement_officer',
                'description' => 'Manages material requisitions, purchase orders, and warehouse deliveries.',
                'permissions' => [
                    'dashboard.view',
                    'sites.view',
                    'procurement.view', 'procurement.request', 'procurement.approve', 'procurement.manage',
                    'inventory.view', 'inventory.receive', 'inventory.issue', 'inventory.manage',
                    'reports.view', 'reports.export',
                ],
            ],
            'project_coordinator' => [
                'name' => 'Project Coordinator',
                'slug' => 'project_coordinator',
                'description' => 'Coordinates projects, milestones, work orders, tasks, and progress reports.',
                'permissions' => [
                    'dashboard.view',
                    'projects.view', 'projects.create', 'projects.update', 'projects.manage',
                    'sites.view', 'sites.update',
                    'workforce.view',
                    'attendance.view',
                    'work_orders.view', 'work_orders.create', 'work_orders.edit', 'work_orders.export',
                    'tasks.view', 'tasks.create', 'tasks.edit',
                    'notes.view', 'notes.create', 'notes.edit',
                    'measurements.view',
                    'ra_bills.view',
                    'reports.view', 'reports.export',
                ],
            ],
        ];
    }

    /**
     * Expand a permission key into its equivalent alias keys (e.g. attendance.mark <-> attendance.record <-> attendance.muster).
     *
     * @return array<int, string>
     */
    public static function expandAliases(string $permission): array
    {
        if (in_array($permission, self::ATTENDANCE_MARK_KEYS, true)) {
            return self::ATTENDANCE_MARK_KEYS;
        }

        return [$permission];
    }

    /**
     * Return all default effective permissions for a protected admin role (excluding restricted attendance actions).
     *
     * @return array<int, string>
     */
    public static function defaultAdminPermissionKeys(): array
    {
        return array_values(array_diff(
            self::allPermissionKeys(),
            self::ADMIN_RESTRICTED_ATTENDANCE_PERMISSIONS
        ));
    }

    /**
     * Check whether a role holds a permission, merging stored role JSON permissions
     * with predefined system role defaults when applicable.
     * Wildcard '*' never grants restricted attendance permissions.
     */
    public static function roleHasPermission(string $roleSlug, ?array $storedPermissions, string $permission): bool
    {
        $aliases = self::expandAliases($permission);

        // Strip '*' so legacy wildcard permissions can never bypass attendance restrictions
        $perms = array_values(array_filter(
            $storedPermissions ?? [],
            fn ($p) => is_string($p) && $p !== '*'
        ));

        $isCustomized = in_array('__customized__', $perms, true);

        if (in_array($roleSlug, self::PROTECTED_ADMIN_SLUGS, true)) {
            foreach ($aliases as $alias) {
                if (in_array($alias, $perms, true)) {
                    return true;
                }
            }

            if (in_array($permission, self::ADMIN_RESTRICTED_ATTENDANCE_PERMISSIONS, true)) {
                return false;
            }

            return true;
        }

        foreach ($aliases as $alias) {
            if (in_array($alias, $perms, true)) {
                return true;
            }
        }

        // For predefined system roles that were seeded with abbreviated or wildcard permission lists,
        // include their canonical system role default permissions unless the role was explicitly customized.
        $defaults = self::defaultRolePermissions()[$roleSlug] ?? [];

        if (! $isCustomized) {
            foreach ($aliases as $alias) {
                if (in_array($alias, $defaults, true)) {
                    return true;
                }
            }
        }

        return false;
    }
}

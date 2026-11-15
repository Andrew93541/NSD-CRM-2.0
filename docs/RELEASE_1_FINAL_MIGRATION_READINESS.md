# NSD CRM 2.0 — Release 1 Final Migration Readiness Review

**Review Date:** 2026-11-15  
**Review Mode:** Strictly Read-Only Final Pre-Migration Verification  
**Project Directory:** `/Users/andrew/Developer/NSD-CRM-2.0`  
**Target Database Verified (Read-Only):** `nsd_crm_2` (MySQL InnoDB, `utf8mb4_unicode_ci`, `STRICT_TRANS_TABLES`)  
**Final Verdict:** **READY FOR SEPARATE MIGRATION APPROVAL.**

---

## 1. Executive Summary

An independent, strictly read-only final migration readiness review was performed across:
1. [RELEASE_1_PRE_MIGRATION_AUDIT.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/RELEASE_1_PRE_MIGRATION_AUDIT.md) (`16` original findings: `0` Critical, `4` High, `7` Medium, `5` Low).
2. [RELEASE_1_AUDIT_REMEDIATION_REPORT.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/RELEASE_1_AUDIT_REMEDIATION_REPORT.md) and all remediated controllers, models, services, views, and tests.
3. The **complete final source code** of all **seven (7)** pending migration files against live `nsd_crm_2` `INFORMATION_SCHEMA.COLUMNS` and index metadata.

### Key Readiness Conclusions

- **All 16 Audit Findings Verified Resolved (`16 / 16`):** Every High (`HIGH-01`..`HIGH-04`), Medium (`MED-01`..`MED-07`), and Low (`LOW-01`..`LOW-05`) finding was cross-checked directly against the final PHP source, Blade views, migration files, and automated test suite (`218` passed, `0` failed, `2,552` assertions in isolated SQLite `:memory:`).
- **All Seven Pending Migrations Are Additive, Idempotent & Compatible with `nsd_crm_2`:** Zero pending migrations drop tables, drop columns, modify existing column types, or introduce un-defaulted `NOT NULL` columns in `up()`. All **24** referenced foreign-key parent columns and `AFTER <column>` anchors across all 7 migrations were verified present in live `nsd_crm_2` with matching data types.
- **Older Prerequisite Migrations (`2026_11_09_134500` & `2026_11_10_150000`) Independently Verified Safe:** Both migrations were audited line-by-line against live `users` (`7` rows), `company_user` (`7` rows), and `roles` (`7` rows). Defaults (`must_change_password = 0`, `restrict_to_assigned_sites = 0`, `is_active = 1`) and the DML `UPDATE roles SET is_system = 1` match all 7 built-in system roles in `nsd_crm_2` without locking out users or altering existing site-scoping behavior.
- **Protected Database & Git State Confirmed Untouched:** `nsd_crm_2` remains at exactly **`22` applied migrations** and **`7` pending migrations**, with all baseline records unchanged (`companies=1, users=7, sites=4, projects=3, workers=21, work_orders=3`), and `0` commits, pushes, or deployments executed.

---

## 2. Verification of Original Audit Findings Against Remediation Evidence (Task 1)

| Finding | Severity | Verification Against Final Code & Migrations | Status |
|---|---|---|---|
| **`HIGH-01`** | High | Verified `Schema::hasTable` and anchor-column guards in [2026_11_09_134500_add_two_factor_last_step_to_users_table.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_09_134500_add_two_factor_last_step_to_users_table.php) and [2026_11_10_150000_add_enterprise_access_management_columns.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_10_150000_add_enterprise_access_management_columns.php), and verified 7-step execution order in [RELEASE_1_IMPLEMENTATION_GUIDE.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/RELEASE_1_IMPLEMENTATION_GUIDE.md). | **Verified Resolved** |
| **`HIGH-02`** | High | Verified `'site_supervisor'` removed from [PayrollController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/PayrollController.php) `authorizePayrollView()`, desktop & mobile drawer links fixed in [app.blade.php](file:///Users/andrew/Developer/NSD-CRM-2.0/resources/views/layouts/app.blade.php), `authorizePayrollPeriodScope()` blocks site-restricted users from `site_id = NULL` periods & CSV exports, and [User.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Models/User.php) `canPerform()` never falls back to `$fallbackRoles` on custom or `__customized__` roles. | **Verified Resolved** |
| **`HIGH-03`** | High | Verified `canManageWorkerUserLinks()` (`users.edit`/`users.manage` or admin) and transactional `syncLinkedUserOnWorkerDeactivation()` (`lockForUpdate()`, Last Active Company Admin lockout check, self-deactivation check, session revocation, audit log) in [WorkforceController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/WorkforceController.php) and [AuthController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/AuthController.php). | **Verified Resolved** |
| **`HIGH-04`** | High | Verified [PayrollCalculationService.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Workforce/PayrollCalculationService.php) `approveAndLockPeriod()` locks the period row (`lockForUpdate()`), enforces `status === 'calculated'`, and throws `ValidationException` on `'statutory'` naming any `unconfigured` workers before locking. | **Verified Resolved** |
| **`MED-01`** | Medium | Verified [2026_11_15_100005_release1_scalability_composite_indexes.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100005_release1_scalability_composite_indexes.php) creates only the 2 net-new composite indexes (`idx_act_logs_comp_action_created`, `idx_po_comp_site_status`) using `Schema::hasIndex(...)` with zero `try/catch` error swallowing. | **Verified Resolved** |
| **`MED-02`** | Medium | Verified `max:30` codes / `max:20` UOM in [InventoryController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/InventoryController.php), `max:30` phone in [ProcurementController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/ProcurementController.php), and `string('phone', 30)` in [2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php). | **Verified Resolved** |
| **`MED-03`** | Medium | Verified independent `Schema::hasTable`, `Schema::hasColumn`, and `Schema::hasIndex` guards across Migrations `100001`–`100004` and single-column `down()` drops in Migrations `100003` and `100004`. | **Verified Resolved** |
| **`MED-04`** | Medium | Verified `attendance.manage` + explicit-deny checks on Leave Type CRUD and `attendance.mark`/`attendance.record` + explicit-deny + site-restriction checks on `storeLeaveRequest` in [AttendanceController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/AttendanceController.php). | **Verified Resolved** |
| **`MED-05`** | Medium | Verified `calculated_by !== $actor->id` maker-checker enforcement in both `PayrollController::approveAndLock()` (`403`) and `PayrollCalculationService::approveAndLockPeriod()`, plus `calculated_by` tracking on `adjustEntry()`. | **Verified Resolved** |
| **`MED-06`** | Medium | Verified `pt_amount`, `advance_deduction`, and `other_deduction` sequential capping against remaining `gross_pay` in `PayrollCalculationService` (`unrecovered_shortfall` recorded in `statutory_snapshot['pt']`) and `adjustEntry()` validation. | **Verified Resolved** |
| **`MED-07`** | Medium | Verified path-traversal & `false`-return `RuntimeException` guards in [VaultStorage.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Storage/VaultStorage.php), temp-file PDF parsing + orphan cleanup in [WorkOrderUploadService.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Operations/WorkOrderUploadService.php), and `signature_disk` + `$oldValues` audit logging in [CompanySettingsController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/CompanySettingsController.php). | **Verified Resolved** |
| **`LOW-01`** | Low | Verified `ra_bills.igst_amount` widened to `DECIMAL(16,2)` in `2026_11_15_100003`, matching `ra_bills.cgst_amount` and `sgst_amount` (`decimal(16,2)` in `nsd_crm_2`). | **Verified Resolved** |
| **`LOW-02`** | Low | Verified `clients_company_code_unique` (`UNIQUE(company_id, code)`) in `2026_11_15_100001` and `DB::transaction` + `worker_sequences` `lockForUpdate()` in `ProcurementController::nextAtomicVendorCode()` and `ClientController::nextAtomicClientCode()`. | **Verified Resolved** |
| **`LOW-03`** | Low | Verified SQL maker-checker pre-filters + `limit(200)` in [ApprovalCenterController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/ApprovalCenterController.php) and `redirect()->route('dashboard')` in `AuthController::switchCompany()`. | **Verified Resolved** |
| **`LOW-04`** | Low | Verified `BL-01`..`BL-15` alignment in `docs/RELEASE_1_IMPLEMENTATION_GUIDE.md`. *(Note: Minor prose summary inaccuracies in `RELEASE_1_AUDIT_REMEDIATION_REPORT.md` Section 4.2 regarding exact column names in `100002`/`100003` are superseded by the exact DDL matrix in Section 3 below; the migration files themselves are 100% accurate.)* | **Verified Resolved** |
| **`LOW-05`** | Low | Verified `sanitizePayrollCsvCell()` in `AttendanceController::exportPayrollCsv()` and `./vendor/bin/pint --dirty --test` (`exit 0`). | **Verified Resolved** |

---

## 3. Seven-Migration Technical Review Matrix (Task 2)

Every pending migration file was inspected in full and cross-checked against live `nsd_crm_2` table schemas, row counts, foreign keys, and indexes.

| # | Migration File | Exact Schema / Data Changes (`up()`) | Foreign Keys & Unique Constraints | Live `nsd_crm_2` Data & Anchor Compatibility | Backfill Needed? | Rollback (`down()`) Safety |
|---|---|---|---|---|---|---|
| **1** | [2026_11_09_134500_add_two_factor_last_step_to_users_table.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_09_134500_add_two_factor_last_step_to_users_table.php) | `users`: adds `two_factor_last_step BIGINT UNSIGNED NULL` after `two_factor_verified_at`. | None | `users` (`7` rows) has `two_factor_verified_at TIMESTAMP NULL`. All `7` rows receive `NULL`. | **No** | **Safe** — single-column `dropColumn('two_factor_last_step')` guarded by `hasTable` + `hasColumn`. |
| **2** | [2026_11_10_150000_add_enterprise_access_management_columns.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_10_150000_add_enterprise_access_management_columns.php) | 1. `users`: `must_change_password TINYINT(1) DEFAULT 0` (after `is_active`), `password_changed_at TIMESTAMP NULL`, `last_login_at TIMESTAMP NULL` (after `two_factor_last_step`), `last_login_ip VARCHAR(45) NULL`.<br>2. `company_user`: `permission_overrides JSON NULL` (after `is_primary`), `restrict_to_assigned_sites TINYINT(1) DEFAULT 0`.<br>3. `roles`: `is_system TINYINT(1) DEFAULT 0` (after `description`), `is_active TINYINT(1) DEFAULT 1`.<br>4. DML: `UPDATE roles SET is_system = 1 WHERE slug IN ('super_admin','company_admin','senior_manager','site_manager','site_supervisor','supervisor','warehouse_manager','office_staff')`. | None (`JSON NULL` compliant with MySQL/TiDB no-default rule). | `users` (`7`), `company_user` (`7`), `roles` (`7`) anchors all verified present. Existing users get `must_change_password = 0` (no forced lockout). Existing `company_user` rows get `restrict_to_assigned_sites = 0` (preserves org-wide access for admin/senior/office while `site_manager`/`site_supervisor`/`warehouse_manager` remain site-scoped by role). All `7` live `roles` match system slugs and receive `is_system = 1, is_active = 1`. | **Automatic in `up()`** (`roles.is_system = 1` DML). | **Safe before custom overrides are stored** — drops added columns on `roles` (2 cols), `company_user` (2 cols), and `users` (4 cols). *(TiDB v6.2+ required for multi-column `DROP`.)* |
| **3** | [2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php) | 1. Creates `vendors` (`id`, `company_id`, `code VARCHAR(40)`, `name VARCHAR(255)`, `vendor_type VARCHAR(50) DEFAULT 'material_supplier'`, `contact_person`, `phone VARCHAR(30) NULL`, `email`, `gstin`, `pan`, `address`, `payment_terms_days SMALLINT UNSIGNED DEFAULT 30`, `bank_name`, `bank_account_no`, `bank_ifsc`, `is_active TINYINT(1) DEFAULT 1`, timestamps).<br>2. `clients`: adds `UNIQUE clients_company_code_unique (company_id, code)`.<br>3. `purchase_orders`: adds `vendor_id BIGINT UNSIGNED NULL` after `warehouse_id`.<br>4. `materials`: adds `hsn_code VARCHAR(30) NULL` after `unit_of_measure`. | - `vendors.company_id -> companies(id) ON DELETE CASCADE`<br>- `purchase_orders.vendor_id -> vendors(id) ON DELETE SET NULL`<br>- `UNIQUE vendors_company_code_unique (company_id, code)`<br>- `UNIQUE clients_company_code_unique (company_id, code)` | `clients` (`0` rows, `0` duplicate codes), `purchase_orders` (`0` rows; `warehouse_id` & `supplier_phone VARCHAR(30)` verified), `materials` (`5` rows receive `hsn_code = NULL`). Separate `Schema::hasIndex` guards on both unique constraints. | **No** | **Safe** — drops `purchase_orders.vendor_id` FK+col, `materials.hsn_code`, `clients_company_code_unique`, then `vendors` (correct FK child-before-parent order). |
| **4** | [2026_11_15_100002_release1_inventory_movements_and_adjustments.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100002_release1_inventory_movements_and_adjustments.php) | 1. `inventory_transactions`: adds `work_order_id BIGINT UNSIGNED NULL` (after `purchase_order_id`), `adjustment_reason VARCHAR(255) NULL` (after `status`), `rejection_reason TEXT NULL`.<br>2. `inventory_transaction_items`: adds `previous_quantity DECIMAL(14,2) NULL` (after `unit_cost`), `resulting_quantity DECIMAL(14,2) NULL`. | - `inventory_transactions.work_order_id -> work_orders(id) ON DELETE SET NULL` | `inventory_transactions` (`0` rows; `transaction_type` is already `VARCHAR(30)`, `purchase_order_id` & `status` verified) and `inventory_transaction_items` (`0` rows; `unit_cost` verified). | **No** | **Safe** — drops added columns and `work_order_id` FK cleanly with `hasTable` + `hasColumn` guards. |
| **5** | [2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php) | 1. Adds `storage_disk VARCHAR(30) DEFAULT 'local'` to `work_order_attachments` (`0`), `work_order_uploads` (`0`), `bill_exports` (`2`), `measurement_entries` (`0`) *(note: `worker_documents` & `project_documents` already have `storage_disk` in `nsd_crm_2`)*.<br>2. `companies`: adds `signature_disk VARCHAR(30) DEFAULT 'local'` after `signature_path`.<br>3. `workers`: adds `user_id BIGINT UNSIGNED NULL` after `company_id` + `workers_company_user_unique`.<br>4. `work_orders`: adds `tax_mode VARCHAR(20) DEFAULT 'cgst_sgst'` (after `payment_terms`), `igst_rate DECIMAL(5,2) DEFAULT 0.00` (after `sgst_rate`).<br>5. `ra_bills`: adds `tax_mode VARCHAR(20) DEFAULT 'cgst_sgst'` (after `excess_unbilled_amount`), `igst_rate DECIMAL(5,2) DEFAULT 0.00`, `igst_amount DECIMAL(16,2) DEFAULT 0.00` (after `sgst_amount`). | - `workers.user_id -> users(id) ON DELETE SET NULL`<br>- `UNIQUE workers_company_user_unique (company_id, user_id)` (independent `Schema::hasIndex` guard) | - `bill_exports` (`2` legacy local files accurately tagged `'local'`).<br>- `companies` (`1` row gets `signature_disk = 'local'`).<br>- `workers` (`21` rows get `user_id = NULL`; MySQL InnoDB & TiDB permit multiple `(company_id, NULL)` rows under `UNIQUE`).<br>- `work_orders` (`3` rows get `'cgst_sgst'`, `0.00`) & `ra_bills` (`0` rows; `DECIMAL(16,2)` matches `sgst_amount`). | **No** (`DEFAULT 'local'` and `DEFAULT 'cgst_sgst'` accurately classify all existing rows). | **Safe before new `'vault'` files or IGST bills are written** — uses single-column `Schema::table` drops across all tables (100% MySQL & TiDB compatible). |
| **6** | [2026_11_15_100004_release1_individual_statutory_payroll.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100004_release1_individual_statutory_payroll.php) | 1. `workers`: adds 14 columns after `upi_id` — `pf_status` (`'unconfigured'`), `pf_employee_rate`, `pf_employer_rate`, `pf_wage_ceiling`, `uan_number VARCHAR(30)`, `esic_status` (`'unconfigured'`), `esic_employee_rate`, `esic_employer_rate`, `esic_wage_ceiling`, `esic_ip_number VARCHAR(30)`, `pt_status` (`'unconfigured'`), `pt_monthly_amount`, `statutory_effective_from DATE NULL`, `statutory_updated_by`.<br>2. `payroll_entries`: adds 7 columns after `other_deduction` — `pf_employee_amount`, `pf_employer_amount`, `esic_employee_amount`, `esic_employer_amount`, `pt_amount` (all `DECIMAL(12,2) DEFAULT 0`), `statutory_config_state VARCHAR(20) DEFAULT 'unconfigured'`, `statutory_snapshot JSON NULL`. | - `workers.statutory_updated_by -> users(id) ON DELETE SET NULL` | `workers` (`21` rows; `upi_id` verified) safely default to `'unconfigured'` + `NULL` rates (zero guessed percentages). `payroll_entries` (`0` rows; `other_deduction DECIMAL(12,2)` verified). | **Operational Only (Post-Migration HR Setup):** Configure each active worker's PF/ESIC/PT status in UI before first payroll lock. | **Safe before statutory profiles/payrolls are populated** — drops `statutory_updated_by` FK+col first, then drops each remaining column in individual single-column `Schema::table` calls. |
| **7** | [2026_11_15_100005_release1_scalability_composite_indexes.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100005_release1_scalability_composite_indexes.php) | Adds 2 net-new composite indexes:<br>1. `activity_logs`: `idx_act_logs_comp_action_created (company_id, action, created_at)`<br>2. `purchase_orders`: `idx_po_comp_site_status (company_id, site_id, status)` | 2 non-unique composite B-tree indexes (`<= 32`-char index names). | Verified against live `SHOW INDEX`: neither tuple exists in `nsd_crm_2`, and the 4 duplicate tuples from the initial draft were removed (zero MySQL Warning `1831`). | **No** | **Safe (`100%` Lossless)** — drops only the 2 index names if present. |

---

## 4. Ordered Migration Execution Plan & Split Assessment (Task 3)

### 4.1 Does Any Migration Need to Be Split Before Execution?

- **No — Splitting Is No Longer Required.**
  - In the pre-remediation audit, splitting `2026_11_15_100003` was recommended because `workers.user_id` creation and `workers_company_user_unique` shared a single `if (! Schema::hasColumn)` guard (creating an unindexed retry gap under implicit DDL commits) and `down()` used multi-column drops.
  - Because `2026_11_15_100003` now guards every table, column (`Schema::hasColumn`), and unique index (`Schema::hasIndex`) independently and executes single-column drops in `down()`, re-running the migration after any transient failure is **100% idempotent**.
  - Executing the 7 migrations **one file at a time via `--path`** (rather than a single batch `php artisan migrate`) provides step-by-step verification checkpoints without rewriting or deleting existing migration files.

### 4.2 Exact Ordered Execution Plan (For Separate Future Approval — DO NOT RUN NOW)

1. **Pre-Execution Mandatory Backup & Maintenance Window:**
   - Put application into maintenance mode or pause concurrent traffic.
   - Take full logical backup of `nsd_crm_2` (see Section 6).
2. **Step 1 — TOTP Replay Protection Column (`Prerequisite 1`):**
   - `php artisan migrate --path=database/migrations/2026_11_09_134500_add_two_factor_last_step_to_users_table.php`
   - *Dependency:* Existing `users.two_factor_verified_at` (`2026_11_01_000006`). Required before Step 2 so `users.last_login_at` positions cleanly after `two_factor_last_step`.
3. **Step 2 — Enterprise Access Management & Role Flags (`Prerequisite 2`):**
   - `php artisan migrate --path=database/migrations/2026_11_10_150000_add_enterprise_access_management_columns.php`
   - *Dependency:* Step 1 (`users`, `company_user`, `roles`). Required by Release 1 `BL-01` (`must_change_password`) and `BL-02` (`company_user.permission_overrides`, `company_user.restrict_to_assigned_sites`, `roles.is_system`, `roles.is_active`).
4. **Step 3 — Vendors Master, Client Unique Index & PO Vendor Link (`Release 1 — 1/5`):**
   - `php artisan migrate --path=database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php`
   - *Dependency:* `companies`, `clients`, `purchase_orders`, `materials`. Must run before `BL-10` Vendor-linked PO creation.
5. **Step 4 — Inventory Transfers, Returns & Adjustment Approval Columns (`Release 1 — 2/5`):**
   - `php artisan migrate --path=database/migrations/2026_11_15_100002_release1_inventory_movements_and_adjustments.php`
   - *Dependency:* `inventory_transactions`, `inventory_transaction_items`, `work_orders`. Powers `BL-09` stock transfers, site returns, and maker-checker adjustments.
6. **Step 5 — Private Vault Disk Tracking, IGST Columns & Worker-User Link (`Release 1 — 3/5`):**
   - `php artisan migrate --path=database/migrations/2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php`
   - *Dependency:* `companies`, `work_order_attachments`, `work_order_uploads`, `bill_exports`, `measurement_entries`, `workers`, `users`, `work_orders`, `ra_bills`. Powers `BL-04`, `BL-11`, `BL-12`, and `BL-13`.
7. **Step 6 — Individual Statutory Payroll Columns (`Release 1 — 4/5`):**
   - `php artisan migrate --path=database/migrations/2026_11_15_100004_release1_individual_statutory_payroll.php`
   - *Dependency:* `workers`, `users`, `payroll_entries`. Must run after Step 5 (`workers` table lock order) to add PF/ESIC/PT columns and snapshots (`STAT-PAY`).
8. **Step 7 — Scalability Composite Indexes (`Release 1 — 5/5`):**
   - `php artisan migrate --path=database/migrations/2026_11_15_100005_release1_scalability_composite_indexes.php`
   - *Dependency:* `activity_logs`, `purchase_orders`. Runs last after all structural table changes complete (`BL-15`).

---

## 5. MySQL & TiDB Compatibility Assessment (Task 2 & Task 6)

### 5.1 MySQL 8.0 / 8.4 / Homebrew MySQL `nsd_crm_2` (`VERIFIED COMPATIBLE`)

- **Strict SQL Mode (`STRICT_TRANS_TABLES`, `NO_ZERO_DATE`, `ONLY_FULL_GROUP_BY`):** Compatible. All `NOT NULL` additions specify valid scalar defaults; `JSON` columns (`permission_overrides`, `statutory_snapshot`) and `TEXT` columns (`address`, `rejection_reason`) are `NULL`able without literal defaults.
- **Foreign Keys & Unsigned Integer Parity:** All 5 new foreign keys (`vendors.company_id`, `purchase_orders.vendor_id`, `inventory_transactions.work_order_id`, `workers.user_id`, `workers.statutory_updated_by`) use `BIGINT UNSIGNED` referencing `BIGINT UNSIGNED PRIMARY KEY` columns on InnoDB tables.
- **`VARCHAR` Length Alignment:** Application validators enforce `max:30` on `vendors.phone`, `purchase_orders.supplier_phone`, `warehouses.code`, `material_categories.code`, and `materials.code`, and `max:20` on `materials.unit_of_measure`.
- **`NULL`-Safe Composite Unique Constraints:** Both `workers_company_user_unique (company_id, user_id)` and `clients_company_code_unique (company_id, code)` allow multiple `NULL` values per `company_id` under InnoDB semantics while strictly preventing duplicate non-`NULL` links/codes.

### 5.2 TiDB Serverless / TiDB v6.6+–v8.x (`STATICALLY COMPATIBLE — SEE LIMITATIONS`)

> **Note:** No live TiDB cluster is connected in this local environment; assessment is verified against TiDB MySQL-compatibility specifications.

1. **Implicit Per-DDL Commits:** Addressed. Every multi-step table change uses independent `Schema::hasColumn` and `Schema::hasIndex` guards so retries never fail or skip constraints.
2. **Multi-Schema Change (`TiDB v6.2+` Requirement):** Migrations `2026_11_10_150000` (`up` & `down`), `2026_11_15_100002` (`up` & `down`), `2026_11_15_100003` (`work_orders` & `ra_bills` `up`), and `2026_11_15_100004` (`workers` & `payroll_entries` `up`) group multiple `ADD COLUMN` operations inside one `Schema::table` call. TiDB v6.2+ (including all current TiDB Cloud Serverless / Dedicated clusters) supports this natively; TiDB `< v6.2` is unsupported. Migrations `100003` and `100004` use single-column drops in `down()`.
3. **Foreign Key Enforcement (`TiDB v6.6+`):** Requires `tidb_enable_foreign_key = ON` (default in TiDB v6.6+).
4. **Auto-Increment IDs:** TiDB allocates `AUTO_INCREMENT` IDs in per-TiDB-server ranges (monotonic per node, not globally contiguous). Business document numbers (`VEND-###`, `CLI-###`, `INV-*`, `PAY-*`) use locked sequence tables rather than `AUTO_INCREMENT` IDs, so TiDB ID gaps have zero business impact.

---

## 6. Rollback & Pre-Migration Backup Requirements (Task 6)

1. **Mandatory Pre-Migration Backup (Required Before Step 1):**
   ```bash
   mysqldump --single-transaction --routines --triggers --events nsd_crm_2 > backup_nsd_crm_2_pre_release1_$(date +%Y%m%d_%H%M%S).sql
   ```
   Verify the dump file is non-empty and ends with `-- Dump completed` before executing Step 1.
2. **Rollback Window & Point-of-No-Return Nuances:**
   - **Immediate Post-Migration Window (Before New Release 1 Data Is Written):** All 7 `down()` methods are lossless against existing `nsd_crm_2` records and can be rolled back in reverse order (`Step 7 -> Step 1`) if executed one migration per batch via `--path`.
   - **After Release 1 Production Data Is Created:** Rolling back Migration `100001` drops `vendors`, rolling back `100003` drops `storage_disk` / `tax_mode` / `igst_amount` / `workers.user_id`, and rolling back `100004` drops worker statutory configurations and payroll statutory snapshots. Once live Release 1 records exist, point-in-time backup restoration or a forward corrective migration must be used rather than `migrate:rollback`.

---

## 7. Payroll Sign-Off & Statutory Compliance Dependencies (Task 4)

### 7.1 Software Implementation Verified (Complete & Tested)

- **Zero Assumed Rates:** Backend calculation (`PayrollCalculationService`) contains zero hardcoded PF (`12%`), ESIC (`0.75%`/`3.25%`), or PT (`₹200`) rates; every calculation reads strictly from the individual `Worker`'s configured profile.
- **Hard Pre-Lock Gate (`HIGH-04`):** `PayrollCalculationService::approveAndLockPeriod()` blocks locking any period unless `status === 'calculated'` and **zero** entries have `statutory_config_state = 'unconfigured'`.
- **Independent Maker-Checker (`MED-05`):** A user who calculated or adjusted a payroll period is blocked (`403` / `ValidationException`) from approving and locking that period.
- **Low-Gross Capping & Ledger Integrity (`MED-06`):** Fixed `pt_monthly_amount`, `advance_deduction`, and `other_deduction` are sequentially capped to available `gross_pay` (recording `unrecovered_shortfall` in `statutory_snapshot['pt']`), guaranteeing `total_gross_amount - total_deductions === total_net_amount`.
- **Historical Immutability & RBAC (`HIGH-02`):** Full JSON `statutory_snapshot` is frozen per `PayrollEntry`, and payroll views/PDFs/NEFT CSV exports enforce role, explicit-deny, and site-restriction boundaries (including `site_id = NULL` periods).

### 7.2 Five Statutory Rules Requiring Professional CA / Labour-Law Validation (`SR-01`–`SR-05`)

| ID | Statutory Rule | Software Behavior Today | Required Professional Validation (CA / Labour Consultant) |
|---|---|---|---|
| **`SR-01`** | **EPF Wage Base Definition** (*EPF & MP Act, 1952, Sec. 2(b)*) | Applies `pf_employee_rate` & `pf_employer_rate` (capped at `pf_wage_ceiling` if configured) to `gross_pay` (`base_pay + overtime_pay + bonus_amount`). | Confirm whether PF wage base for company workers must exclude `overtime_pay` and non-guaranteed `bonus_amount` (or introduce a separate Basic+DA split). |
| **`SR-02`** | **Employer EPF Bifurcation (EPF vs EPS vs EDLI & Admin Charges)** | Stores aggregate `pf_employer_amount` per entry. | Confirm internal accounting treatment; note that future EPFO ECR text-file export requires splitting employer `12%` into EPS (`8.33%` capped at ₹15,000, age `< 58`) and EPF (`3.67%` / balance) plus EDLI & admin charges. |
| **`SR-03`** | **ESIC 6-Month Contribution Period Continuity** (*ESI Act, 1948*) | Evaluates ESIC to `₹0.00` (`above_wage_ceiling`) in any month `gross_pay > esic_wage_ceiling`. | Confirm operational rule when a covered employee's gross pay temporarily exceeds ₹21,000 mid-contribution-period (`1 Apr–30 Sep` or `1 Oct–31 Mar`) due to overtime. |
| **`SR-04`** | **State Professional Tax (PT) Slabs, Gender Exemptions & February Top-Up** | Deducts worker's configured `pt_monthly_amount` (capped at available post-PF/ESIC gross pay). | Confirm state-specific monthly slabs, gender exemptions (e.g., Maharashtra women's exemption threshold), February ₹300 adjustment, or half-yearly PT states (e.g., TN/KL). |
| **`SR-05`** | **Labour Welfare Fund (LWF), Section 194C TDS & Subcontractor Classification** | Supports `not_applicable` per worker and manual `other_deduction` with notes. | Establish HR onboarding SOP defining when direct employees vs subcontractor muster workers are marked `applicable` vs `not_applicable` and how LWF/194C TDS are handled. |

### 7.3 Items Blocking Production Payroll Use (Distinguished from Database Migration Execution)

> **Important Distinction:** None of `SR-01`..`SR-05` block **executing the 7 database migrations** or using non-payroll Release 1 modules (Projects, Sites, Attendance, Work Orders, RA Bills, Procurement, Inventory, Vault Storage, Users & Roles). However, before **locking a live production payroll run and filing statutory challans**, three operational/compliance gates apply:

1. **Mandatory Worker Statutory Configuration (`Blocking First Payroll Lock — Enforced in Software`):** All `21` existing workers in `nsd_crm_2` will start as `unconfigured` after Migration 6 (`100004`); HR / Company Admin must set each active worker's PF, ESIC, and PT status to `applicable` or `not_applicable` before the system permits locking a payroll period.
2. **Two-Person Preparer / Approver Workflow (`Blocking Self-Approval — Enforced in Software`):** Ensure separate preparer and approver logins are active before locking payroll.
3. **CA / Labour Advisor Sign-Off on `SR-01`–`SR-05` (`Blocking External Statutory Return Reliance`):** Obtain professional sign-off before relying on CRM payroll outputs for EPFO ECR, ESIC, or State PT statutory filings (especially if workers earn overtime or cross ESIC ceilings mid-period).

---

## 8. Final Safety Verification & Remaining Risks (Task 5 & Task 6)

### 8.1 Protected Environment Verification (`nsd_crm_2` & Git)

Verified via strictly read-only inspection on `2026-11-15`:

- **Applied Migrations in `nsd_crm_2`:** **`22`** (`0001_01_01_000000` through `2026_11_04_110000_create_work_order_uploads_and_lifecycle_columns`).
- **Pending Migrations on Disk:** **`7`** (`2026_11_09_134500`, `2026_11_10_150000`, `2026_11_15_100001`..`2026_11_15_100005` — all `Pending`).
- **Protected `nsd_crm_2` Row Counts:** `companies = 1`, `users = 7`, `sites = 4`, `projects = 3`, `workers = 21`, `work_orders = 3` (**100% unchanged**).
- **Automated Test Verification:** `218` passed, `0` failed, `2,552` assertions (`DB_CONNECTION=sqlite DB_DATABASE=:memory:`).
- **Git & Deployment State:** Zero commits, pushes, deployments, or source/migration edits were performed by the review agent (`git status --short` shows only `?? docs/RELEASE_1_FINAL_MIGRATION_READINESS.md`; `HEAD` is at user commit `8c738e6` created externally at `2026-11-15 18:32:49 +0530`).

### 8.2 Remaining Operational Risks

1. **Pre-Migration Backup Discipline (`Low Risk if Followed`):** Always take and verify a `mysqldump` snapshot of `nsd_crm_2` immediately before running Step 1.
2. **Step-by-Step `--path` Execution (`Low Risk`):** Run the 7 migrations sequentially via `--path` in the exact order in Section 4.2 rather than unreviewed bulk `migrate` so each step's completion is logged and verified.
3. **Post-Migration Statutory Onboarding (`Expected Operational Step`):** Communicate to HR / Company Admin that existing workers (`21`) must have their PF/ESIC/PT status configured (`applicable` or `not_applicable`) prior to locking the next payroll period, and obtain CA guidance on `SR-01`..`SR-05` prior to statutory challan filing.

---

## 9. Final Recommendation

All code, authorization, validation, storage, and schema remediation items from the Release 1 pre-migration audit are verified complete, all seven pending migrations are safe and compatible with `nsd_crm_2`, and the protected database remains completely untouched. The project is ready to proceed to a separate, explicitly authorized migration execution step backed by a `mysqldump` snapshot.

**Final Verdict:**  
**READY FOR SEPARATE MIGRATION APPROVAL.**

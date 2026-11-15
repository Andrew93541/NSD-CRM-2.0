# NSD CRM 2.0 — Release 1 Pre-Migration Technical Audit

**Audit Date:** 2026-11-15  
**Audit Mode:** Strictly Read-Only Technical, Security, Schema & Compliance Audit  
**Target Workspace:** `/Users/andrew/Developer/NSD-CRM-2.0`  
**Target Database Inspected (Read-Only):** `nsd_crm_2` (MySQL `26.7.0` Homebrew, InnoDB, `utf8mb4_unicode_ci`, `STRICT_TRANS_TABLES`)  
**Overall Verdict:** **READY AFTER REQUIRED FIXES**

---

## 1. Executive Summary

An independent, strictly read-only pre-migration technical audit was conducted across the **NSD CRM 2.0 — Release 1** implementation (`BL-01` through `BL-15` plus Individual Statutory Payroll `STAT-PAY`) and its pending database migrations prior to authorizing any schema changes against the local `nsd_crm_2` database.

### Headline Conclusions

1. **Database Safety & Non-Destructiveness (`0 CRITICAL` Issues):**
   - None of the five Release 1 migration files (`2026_11_15_100001` through `2026_11_15_100005`) drop tables, drop columns, rename columns, truncate records, or add un-defaulted `NOT NULL` columns to populated tables in `up()`.
   - Existing `nsd_crm_2` baseline data (`1` company, `7` users, `4` sites, `3` projects, `21` workers, `3` work orders, `22` applied migrations) was verified untouched before and after this audit.
   - Automated tests were independently reproduced in isolated in-memory SQLite (`208` passed, `2,407` assertions, `7.13s`), all `232` routes compiled cleanly, modified PHP files passed `./vendor/bin/pint --dirty --test`, and Vite `8.3.5` compiled production assets cleanly to an isolated temporary directory (`198ms`).

2. **Blocking Pre-Migration & Security Findings (`4 HIGH`, `7 MEDIUM`, `5 LOW`):**
   - **`HIGH-01` (Hidden Prerequisite Migrations):** Live `nsd_crm_2` has **22** applied migrations (stopping at `2026_11_04_110000`), whereas `database/migrations/` contains **29** files (**7** pending, not 5). Two earlier unapplied migrations (`2026_11_09_134500_add_two_factor_last_step_to_users_table.php` and `2026_11_10_150000_add_enterprise_access_management_columns.php`, which includes a DML `UPDATE` on `roles`) precede the five Release 1 migrations and provide the `company_user.permission_overrides` and `company_user.restrict_to_assigned_sites` columns that Release 1 relies upon.
   - **`HIGH-02` (Payroll Authorization & Site-Scope Bypass):** `PayrollController` grants `site_supervisor` view access via fallback roles despite `PermissionCatalog` excluding supervisors from `payroll.view`, `layouts/app.blade.php` exposes the Payroll navigation link to any user with `Worker` view access, `PayrollController` allows site-restricted users to view/download company-wide (`site_id = NULL`) payroll periods and full bank payout CSVs, and `User::canPerform()` allows `$fallbackRoles` to override permissions explicitly revoked from a customized system role.
   - **`HIGH-03` (Privilege Escalation / Admin Account Lockout via Worker-User Sync):** `WorkforceController` allows any user with `workforce.update` (such as a `site_manager`, who is barred from `ADMIN_RESERVED_PERMISSIONS`) to link a worker record to a `company_admin` or `senior_manager` `user_id` and mark the worker `terminated`, immediately deactivating that administrator's login and wiping their active sessions.
   - **`HIGH-04` (Unconfigured Statutory Workers Lockable at ₹0 Deductions):** After Migration 4, all `21` existing workers in `nsd_crm_2` default to `unconfigured` statutory status (`₹0.00` PF/ESIC/PT). `PayrollController::approveAndLock()` allows permanent approval and locking of payroll periods even when workers remain `unconfigured`, without blocking or requiring an explicit statutory override confirmation.
   - **Key `MEDIUM` Issues:** Migration 5 creates **4 duplicate composite indexes** that already exist in `nsd_crm_2` under earlier names while swallowing all `Throwable` exceptions (`MED-01`); `ProcurementController` and `InventoryController` allow 31–50 character codes/phones (`vendors.phone` `varchar(50)` copied into `purchase_orders.supplier_phone` `varchar(30)`) that throw `SQLSTATE[22001]` under MySQL `STRICT_TRANS_TABLES` (`MED-02`); non-transactional multi-step `ALTER TABLE` blocks have partial-failure retry gaps (`MED-03`); `AttendanceController::storeLeaveRequest()` lacks a permission gate (`MED-04`); `PayrollController::approveAndLock()` lacks maker-checker separation (`MED-05`); fixed PT or manual deductions exceeding small gross pay break ledger balance (`MED-06`); and `WorkOrderUploadService` calls local-only `->path()` on `VaultStorage` (`MED-07`).

3. **Readiness Recommendation:**
   - **Do NOT execute `php artisan migrate` yet.** Resolve the required `HIGH` and schema-level `MEDIUM` items documented in Section 11 and Section 12 first, then proceed to separate migration approval following Section 13.

---

## 2. Audit Scope and Methodology

### 2.1 Scope Inspected

1. **Approved Planning & Implementation Baseline Documents:**
   - [PRODUCT_COMPLETION_BLUEPRINT.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/PRODUCT_COMPLETION_BLUEPRINT.md)
   - [FEATURE_GAP_ANALYSIS.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/FEATURE_GAP_ANALYSIS.md)
   - [ARCHITECTURE_REVIEW.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/ARCHITECTURE_REVIEW.md)
   - [DEVELOPMENT_ROADMAP.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/DEVELOPMENT_ROADMAP.md)
   - [RELEASE_1_IMPLEMENTATION_GUIDE.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/RELEASE_1_IMPLEMENTATION_GUIDE.md)
2. **Database Migrations & Live Schema:**
   - All five Release 1 migration files (`2026_11_15_100001` through `2026_11_15_100005`) plus the two unapplied prior migrations (`2026_11_09_134500` and `2026_11_10_150000`).
   - Live read-only inspection of `nsd_crm_2` (`SHOW TABLES`, `SELECT * FROM migrations`, `SHOW CREATE TABLE` across all 32 core and extension tables, SQL mode, storage engine, character set/collation, and baseline row counts).
   - Dry-run compilation of Laravel `Blueprint::toSql()` against the live MySQL grammar in memory without executing any DDL/DML.
3. **Application Source Code:**
   - Controllers, Services, Models, Policies, Middleware, Routes, Blade templates, and Filesystem configuration covering `BL-01` through `BL-15` and `STAT-PAY`.
4. **Independent Quality Verification (Read-Only):**
   - `php artisan test` (verified `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:` in [phpunit.xml](file:///Users/andrew/Developer/NSD-CRM-2.0/phpunit.xml#L27-L28)).
   - `./vendor/bin/pint --dirty --test` and `./vendor/bin/pint --test` (read-only `--test` mode).
   - `php artisan route:list --json`.
   - `npx vite build --outDir /tmp/nsd_audit_vite_build` (output isolated outside workspace; temporary build artifacts deleted after inspection).

### 2.2 Strict Read-Only Controls Enforced

- Zero application source files, migrations, configurations, views, or public assets were modified.
- Zero database migrations or write statements were executed against `nsd_crm_2`.
- Pre-audit and post-audit `git status --porcelain` snapshots and `nsd_crm_2` table counts were compared to verify that `docs/RELEASE_1_PRE_MIGRATION_AUDIT.md` is the **only** workspace file created or changed during this audit.

---

## 3. Five-Migration Technical Review (Audit A)

### 3.0 Critical Discovery: Seven Pending Migrations Exist in `database/migrations/`, Not Five

Read-only inspection of `nsd_crm_2.migrations` confirmed **22** applied migrations ending at `2026_11_04_110000_create_work_order_uploads_and_lifecycle_columns` (batch `4`), whereas `database/migrations/` contains **29** migration files. Two earlier migrations are **also pending**:

1. [2026_11_09_134500_add_two_factor_last_step_to_users_table.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_09_134500_add_two_factor_last_step_to_users_table.php) — adds `users.two_factor_last_step BIGINT UNSIGNED NULL`.
2. [2026_11_10_150000_add_enterprise_access_management_columns.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_10_150000_add_enterprise_access_management_columns.php) — adds `users.must_change_password`, `company_user.permission_overrides` (`JSON NULL`), `company_user.restrict_to_assigned_sites` (`TINYINT(1) DEFAULT 0`), `roles.is_system`, `roles.is_active`, and executes a DML `UPDATE roles SET is_system = 1 WHERE slug IN (...)`.

See **`HIGH-01`** in Section 11 for operational impact.

---

### 3.1 Migration 1: `2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php`

- **File:** [2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php)
- **Schema Changes (`up()`):**
  1. Creates table `vendors` (guarded by `if (! Schema::hasTable('vendors'))`):
     - `id` (`BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY`)
     - `company_id` (`BIGINT UNSIGNED NOT NULL`, FK -> `companies(id) ON DELETE CASCADE`)
     - `code` (`VARCHAR(40) NOT NULL`)
     - `name` (`VARCHAR(200) NOT NULL`)
     - `contact_person` (`VARCHAR(150) NULL`), `phone` (`VARCHAR(50) NULL`), `email` (`VARCHAR(150) NULL`)
     - `gstin` (`VARCHAR(20) NULL`), `pan` (`VARCHAR(15) NULL`), `address` (`TEXT NULL`)
     - `payment_terms` (`VARCHAR(150) NULL`), `bank_account_name` (`VARCHAR(150) NULL`), `bank_account_number` (`VARCHAR(50) NULL`), `bank_ifsc` (`VARCHAR(20) NULL`)
     - `is_active` (`TINYINT(1) NOT NULL DEFAULT 1`), `created_at`, `updated_at`
     - Indexes: `UNIQUE vendors_company_code_unique (company_id, code)`, `INDEX vendors_company_active_idx (company_id, is_active)`
  2. Alters `purchase_orders` (guarded by `Schema::hasTable('purchase_orders')` and `! Schema::hasColumn('purchase_orders', 'vendor_id')`):
     - Adds `vendor_id BIGINT UNSIGNED NULL AFTER site_id`
     - Adds FK `purchase_orders_vendor_id_foreign` -> `vendors(id) ON DELETE SET NULL`
     - Adds composite index `po_company_vendor_idx (company_id, vendor_id)`
- **Rollback (`down()`):**
  - Drops FK `vendor_id`, index `po_company_vendor_idx`, and column `vendor_id` from `purchase_orders`, then `Schema::dropIfExists('vendors')`.
- **Technical Observations:**
  - Safe additive schema (`NULL` FK on `purchase_orders`, `0` existing rows in `nsd_crm_2.purchase_orders`).
  - **Mismatch Warning (`MED-02`):** `vendors.phone` is `VARCHAR(50)`, whereas `purchase_orders.supplier_phone` in `nsd_crm_2` is `VARCHAR(30)`. `ProcurementController::storeOrder()` copies `vendors.phone` into `purchase_orders.supplier_phone`, risking MySQL `SQLSTATE[22001]` overflow.
  - **Naming Drift (`LOW-01`):** Filename mentions `_and_leave_quotas`, but no leave-quota DDL exists in this file (`leave_types.annual_quota` already existed in `2026_10_28_000011`).

---

### 3.2 Migration 2: `2026_11_15_100002_release1_inventory_movements_and_adjustments.php`

- **File:** [2026_11_15_100002_release1_inventory_movements_and_adjustments.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100002_release1_inventory_movements_and_adjustments.php)
- **Schema Changes (`up()`):**
  1. Alters `inventory_transactions`:
     - Adds `work_order_id BIGINT UNSIGNED NULL AFTER site_id` + FK `inventory_transactions_work_order_id_foreign` -> `work_orders(id) ON DELETE SET NULL` (guarded by `! Schema::hasColumn`).
     - Adds `issued_to_name VARCHAR(150) NULL AFTER reference_number` (guarded by `! Schema::hasColumn`).
     - Adds composite index `inv_tx_comp_type_date_idx (company_id, type, transaction_date)` (unguarded inside `up()`).
  2. Creates table `stock_adjustments` (guarded by `if (! Schema::hasTable('stock_adjustments'))`):
     - `id`, `company_id` (FK -> `companies` `CASCADE`), `warehouse_id` (FK -> `warehouses` `CASCADE`), `material_id` (FK -> `materials` `CASCADE`), `adjustment_number` (`VARCHAR(50) NOT NULL`), `direction` (`VARCHAR(20) NOT NULL`), `quantity` (`DECIMAL(14,3) NOT NULL`), `current_stock_snapshot` (`DECIMAL(14,3) NOT NULL DEFAULT 0`), `reason_code` (`VARCHAR(60) NOT NULL`), `remarks` (`TEXT NULL`), `status` (`VARCHAR(30) NOT NULL DEFAULT 'pending'`), `requested_by` (FK -> `users` `CASCADE`), `reviewed_by` (`NULL` FK -> `users` `SET NULL`), `reviewed_at` (`TIMESTAMP NULL`), `review_remarks` (`TEXT NULL`), `inventory_transaction_id` (`NULL` FK -> `inventory_transactions` `SET NULL`), `created_at`, `updated_at`.
     - Indexes: `UNIQUE stock_adj_company_num_unique (company_id, adjustment_number)`, `INDEX stock_adj_company_status_idx (company_id, status)`.
- **Rollback (`down()`):**
  - Drops `stock_adjustments` first (properly respecting its FK to `inventory_transactions`), then drops `inv_tx_comp_type_date_idx`, `work_order_id` FK/column, and `issued_to_name` from `inventory_transactions`.
- **Technical Observations:**
  - `inventory_transactions.type` in `nsd_crm_2` is already `VARCHAR(30)` (not an `ENUM`), so new `'transfer'` and `'return'` movement types require **no** `ENUM` alteration.
  - Unlike Migrations 1, 3, and 4, `Schema::table('inventory_transactions', ...)` lacks a `Schema::hasTable('inventory_transactions')` guard, and `inv_tx_comp_type_date_idx` is added unconditionally without `Schema::hasIndex()` (`MED-03`).

---

### 3.3 Migration 3: `2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php`

- **File:** [2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php)
- **Schema Changes (`up()`):**
  1. Adds `storage_disk VARCHAR(30) NOT NULL DEFAULT 'local'` to four document tables (each guarded by `hasTable` + `! hasColumn`):
     - `worker_documents` (`AFTER file_path` — `0` rows in `nsd_crm_2`)
     - `project_documents` (`AFTER file_path` — `0` rows in `nsd_crm_2`)
     - `measurement_entries` (`AFTER photo_path` — `1` row in `nsd_crm_2`, `photo_path = NULL`)
     - `bill_exports` (`AFTER file_path` — `2` rows in `nsd_crm_2`, both stored on `'local'`)
  2. Alters `work_orders` (`3` rows in `nsd_crm_2`):
     - Adds `tax_mode VARCHAR(20) NOT NULL DEFAULT 'cgst_sgst' AFTER retention_percent`
     - Adds `igst_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER sgst_rate`
  3. Alters `ra_bills` (`1` row in `nsd_crm_2`):
     - Adds `tax_mode VARCHAR(20) NOT NULL DEFAULT 'cgst_sgst' AFTER work_order_revision`
     - Adds `igst_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER sgst_rate`
     - Adds `igst_amount DECIMAL(14,2) NOT NULL DEFAULT 0.00 AFTER sgst_amount`
  4. Alters `workers` (`21` rows in `nsd_crm_2`):
     - Adds `user_id BIGINT UNSIGNED NULL AFTER company_id`
     - Adds FK `workers_user_id_foreign` -> `users(id) ON DELETE SET NULL`
     - Adds `UNIQUE workers_company_user_unique (company_id, user_id)`
- **Rollback (`down()`):**
  - Drops `workers_company_user_unique` and `user_id` FK/column on `workers`, drops IGST/tax columns on `ra_bills` and `work_orders`, and drops `storage_disk` across the 4 document tables.
- **Technical Observations:**
  - In MySQL (`InnoDB`) and TiDB, `UNIQUE (company_id, user_id)` permits multiple rows where `user_id IS NULL` for the same `company_id`, so all `21` existing workers with `user_id = NULL` succeed without conflict.
  - **Multi-Concern Coupling (`MED-03`):** Alters **7 unrelated tables** across Vault storage, GST billing, and Workforce identity in a single non-transactional DDL migration. If a late step fails, earlier `ALTER TABLE` statements remain committed.
  - **Precision Mismatch (`LOW-01`):** `ra_bills.igst_amount` is `DECIMAL(14,2)`, whereas `ra_bills.cgst_amount`, `sgst_amount`, `current_bill_amount`, and `gross_amount` in `nsd_crm_2` are all `DECIMAL(16,2)`.

---

### 3.4 Migration 4: `2026_11_15_100004_release1_individual_statutory_payroll.php`

- **File:** [2026_11_15_100004_release1_individual_statutory_payroll.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100004_release1_individual_statutory_payroll.php)
- **Schema Changes (`up()`):**
  1. Alters `workers` (`21` rows in `nsd_crm_2`, each column guarded by `! Schema::hasColumn`):
     - `pf_status VARCHAR(20) NOT NULL DEFAULT 'unconfigured' AFTER bank_ifsc`
     - `pf_employee_rate DECIMAL(5,2) NULL`
     - `pf_employer_rate DECIMAL(5,2) NULL`
     - `pf_wage_ceiling DECIMAL(12,2) NULL`
     - `pf_cap_at_ceiling TINYINT(1) NOT NULL DEFAULT 1`
     - `uan_number VARCHAR(20) NULL`
     - `pf_number VARCHAR(40) NULL`
     - `esic_status VARCHAR(20) NOT NULL DEFAULT 'unconfigured'`
     - `esic_employee_rate DECIMAL(5,2) NULL`
     - `esic_employer_rate DECIMAL(5,2) NULL`
     - `esic_wage_ceiling DECIMAL(12,2) NULL`
     - `esic_number VARCHAR(30) NULL`
     - `pt_status VARCHAR(20) NOT NULL DEFAULT 'unconfigured'`
     - `pt_monthly_amount DECIMAL(10,2) NULL`
     - `statutory_notes TEXT NULL`
     - `statutory_updated_by BIGINT UNSIGNED NULL` + FK `workers_statutory_updated_by_foreign` -> `users(id) ON DELETE SET NULL`
     - `statutory_updated_at TIMESTAMP NULL`
  2. Alters `payroll_entries` (`0` rows in `nsd_crm_2`):
     - `pf_employer_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER pf_amount`
     - `esic_employer_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER esi_amount`
     - `pt_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER esic_employer_amount`
     - `statutory_config_state VARCHAR(20) NOT NULL DEFAULT 'configured' AFTER status`
     - `statutory_snapshot JSON NULL AFTER remarks`
- **Rollback (`down()`):**
  - Drops `statutory_snapshot`, `statutory_config_state`, `pt_amount`, `esic_employer_amount`, `pf_employer_amount` from `payroll_entries`.
  - Drops FK/column `statutory_updated_by`, then drops the remaining 16 statutory columns on `workers` in a single `$table->dropColumn($cols)` call.
- **Technical Observations:**
  - Defaults on `workers` (`'unconfigured'`, `1`, `NULL`) and `payroll_entries` (`0.00`, `'configured'`, `NULL`) are 100% non-destructive for existing data.
  - Note column naming consistency: existing `payroll_entries` uses `esi_amount` (`2026_11_01_000001`), while Migration 4 adds `esic_employer_amount` alongside it — `PayrollEntry` and `PayrollCalculationService` map both consistently.
  - In TiDB, dropping 16 columns in a single `ALTER TABLE` inside `down()` requires TiDB v6.2+ (`Multi-Schema Change`).

---

### 3.5 Migration 5: `2026_11_15_100005_release1_scalability_composite_indexes.php`

- **File:** [2026_11_15_100005_release1_scalability_composite_indexes.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100005_release1_scalability_composite_indexes.php)
- **Schema Changes (`up()`):**
  Calls private helper `addIndexSafely(string $table, array $columns, string $indexName)` for 6 composite indexes:
  1. `activity_logs`: `idx_act_logs_comp_site_created (company_id, site_id, created_at)` — **DUPLICATE** of existing `act_log_comp_site_time_idx (company_id, site_id, created_at)`!
  2. `activity_logs`: `idx_act_logs_comp_action_created (company_id, action, created_at)` — **NEW** (existing index is `(company_id, action)` without `created_at`).
  3. `daily_attendances`: `idx_daily_att_comp_site_date (company_id, site_id, attendance_date)` — **DUPLICATE** of existing `att_comp_site_date_idx (company_id, site_id, attendance_date)`!
  4. `daily_attendances`: `idx_daily_att_comp_worker_date (company_id, worker_id, attendance_date)` — **DUPLICATE** of existing `att_comp_worker_date_idx (company_id, worker_id, attendance_date)`!
  5. `tasks`: `idx_tasks_comp_site_status (company_id, site_id, status)` — **DUPLICATE** of existing `tasks_comp_site_status_idx (company_id, site_id, status)`!
  6. `purchase_orders`: `idx_po_comp_site_status (company_id, site_id, status)` — **NEW** (existing index is `(company_id, status)` without `site_id`).
- **Rollback (`down()`):**
  - Drops those 6 index names via `dropIndexSafely()`.
- **Technical Observations (`MED-01`):**
  - MySQL and TiDB do **not** throw an error when creating an index with identical columns under a *different* index name; MySQL creates a second physical B-tree index and emits `Warning 1831: Duplicate index`. Four of the six indexes in Migration 5 are pure duplicates of indexes already live in `nsd_crm_2`, increasing storage and `INSERT`/`UPDATE` write amplification on high-churn tables (`activity_logs`, `daily_attendances`, `tasks`).
  - Both `addIndexSafely()` and `dropIndexSafely()` wrap `Schema::table(...)` in `catch (\Throwable) {}`, which masks genuine database failures (lock wait timeouts, disk-full, privilege errors). `Schema::hasIndex($table, $columns)` (available natively in Laravel 11+/13) should be used instead.

---

## 4. MySQL / TiDB Compatibility Findings

### 4.1 MySQL Compatibility (`nsd_crm_2` — Verified Against Live MySQL `26.7.0`)

- **Engine / Charset / SQL Mode:** Live `nsd_crm_2` runs `InnoDB`, `utf8mb4` / `utf8mb4_unicode_ci`, and `ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION`.
- **DDL Grammar Verification:** Every `Blueprint` in Migrations 1–5 was compiled through MySQL's `MySqlGrammar` (`toSql()`) with **zero syntax errors**:
  - No SQLite-only pragmas or SQLite-specific types.
  - Index identifier lengths are all $\le 32$ characters (well below MySQL/TiDB's 64-character identifier limit).
  - `JSON` columns (`payroll_entries.statutory_snapshot`) have `NULL` defaults (compliant with MySQL's prohibition on literal defaults for `TEXT`/`BLOB`/`JSON`).
  - `UNIQUE (company_id, user_id)` on `workers` allows multiple `NULL` `user_id` values under ANSI/InnoDB semantics.
- **Strict-Mode Application Mismatch (`MED-02`):**
  - Because PHPUnit runs against SQLite (which does not enforce `VARCHAR(N)` lengths), several Release 1 controller validations permit 40–50 character inputs on `VARCHAR(20)`/`VARCHAR(30)` MySQL columns (`warehouses.code`, `material_categories.code`, `materials.code`, `materials.unit_of_measure`, `purchase_orders.supplier_phone`), which fail with `SQLSTATE[22001]` under MySQL `STRICT_TRANS_TABLES`.

### 4.2 TiDB Compatibility (Static Specification Analysis — Live TiDB Runtime `Unverified`)

> **Verification Note:** No local or remote TiDB cluster is configured in this environment. TiDB compatibility below was verified statically against TiDB v6.5 LTS / v7.x / v8.x MySQL-compatibility specifications and is marked **Conditionally Compatible (Static Analysis; Live Runtime Unverified)**.

1. **Non-Transactional Implicit DDL Commits:** Like MySQL, TiDB commits each DDL job independently. In Migrations 1–4, `Schema::table()` closures that add both columns and foreign keys/unique indexes emit multiple `ALTER TABLE` statements (`ADD COLUMN ...`, then `ADD CONSTRAINT ...`, then `ADD INDEX ...`).
2. **Multi-Column `ALTER TABLE` (`Multi-Schema Change`):**
   - Migration 3 (`work_orders`, `ra_bills`) and Migration 4 (`workers` — 17 columns in `up()`, 16 columns in `down()`; `payroll_entries` — 5 columns) emit multi-column `ALTER TABLE` statements. TiDB supports multi-column `ADD`/`DROP` natively from **TiDB v6.2.0+** (`tidb_enable_exchange_partition` / Multi-Schema Change GA). Older TiDB `< v6.2` rejects multi-column `ALTER TABLE`.
3. **Foreign Key Support (`ON DELETE CASCADE` / `ON DELETE SET NULL`):**
   - Migrations 1–4 create 8 foreign keys. TiDB enforces foreign keys only from **TiDB v6.6.0+** when `tidb_enable_foreign_key = ON` (earlier versions parse and ignore FK clauses). All referenced parent columns (`companies.id`, `vendors.id`, `work_orders.id`, `warehouses.id`, `materials.id`, `users.id`, `inventory_transactions.id`) are `BIGINT UNSIGNED PRIMARY KEY`, matching child column types cleanly.
4. **Column Positioning (`AFTER <col>`):**
   - Supported in TiDB when adding new columns (`ADD COLUMN ... AFTER ...`), which is the only pattern used in Migrations 1–4 (none reorder existing columns via `MODIFY COLUMN ... AFTER`).
5. **`AUTO_INCREMENT` Behavior:**
   - New tables `vendors` and `stock_adjustments` use standard `id()` (`BIGINT UNSIGNED AUTO_INCREMENT`). Code does **not** rely on contiguous monotonic IDs on either table (`adjustment_number` and `code` handle human-facing sequences).

---

## 5. Existing Database (`nsd_crm_2`) Compatibility Findings (Audit B)

### 5.1 Live Baseline Snapshot (Read-Only)

| Metric / Table | Pre-Audit Value | Post-Audit Value | Compatibility Assessment |
|---|---:|---:|---|
| `companies` | `1` | `1` | Untouched; compatible |
| `users` | `7` | `7` | Untouched; compatible (`2026_11_09` & `2026_11_10` pending — see `HIGH-01`) |
| `sites` | `4` | `4` | Untouched; compatible |
| `projects` | `3` | `3` | Untouched; compatible |
| `workers` | `21` | `21` | Untouched; all 21 receive `user_id = NULL` and `pf/esic/pt_status = 'unconfigured'` |
| `work_orders` | `3` | `3` | Untouched; all 3 receive `tax_mode = 'cgst_sgst'`, `igst_rate = 0.00` |
| `ra_bills` | `1` | `1` | Untouched; existing row receives `tax_mode = 'cgst_sgst'`, `igst_rate = 0.00`, `igst_amount = 0.00` |
| `measurement_entries` | `1` | `1` | Untouched; existing row (`photo_path = NULL`) receives `storage_disk = 'local'` |
| `bill_exports` | `2` | `2` | Untouched; both existing exports (`companies/1/work_orders/1/exports/...`) receive `storage_disk = 'local'`, matching their legacy storage location |
| `purchase_orders` | `0` | `0` | Untouched; `vendor_id = NULL` compatible |
| `inventory_transactions` | `0` | `0` | Untouched; `type` is already `VARCHAR(30)` |
| `payroll_periods` / `payroll_entries` | `0` / `0` | `0` / `0` | Untouched; compatible |
| `migrations` | `22` | `22` | Untouched; 7 migration files pending on disk (2 prior + 5 Release 1) |

### 5.2 Existing Constraint & Index Cross-Check

- **Referenced `AFTER` Columns Verified Present in `nsd_crm_2`:**
  - `purchase_orders.site_id`, `inventory_transactions.site_id`, `inventory_transactions.reference_number`, `worker_documents.file_path`, `project_documents.file_path`, `measurement_entries.photo_path`, `bill_exports.file_path`, `work_orders.retention_percent`, `work_orders.sgst_rate`, `ra_bills.work_order_revision`, `ra_bills.sgst_rate`, `ra_bills.sgst_amount`, `workers.company_id`, `workers.bank_ifsc`, `payroll_entries.pf_amount`, `payroll_entries.esi_amount`, `payroll_entries.status`, `payroll_entries.remarks` — **all 18 anchor columns exist** in `nsd_crm_2`.
- **Zero Naming Collisions on New Tables/Columns:**
  - Neither `vendors` nor `stock_adjustments` exists in `nsd_crm_2`.
  - None of the new columns in Migrations 1–4 currently exist in `nsd_crm_2`.
- **Redundant Composite Indexes Found (`MED-01`):**
  - Live `SHOW CREATE TABLE` confirmed `nsd_crm_2` already has `act_log_comp_site_time_idx`, `att_comp_site_date_idx`, `att_comp_worker_date_idx`, and `tasks_comp_site_status_idx` with identical column tuples to 4 of the 6 indexes in Migration 5.

---

## 6. Individual Statutory Payroll Review & Statutory Risk Report (Audit C)

### 6.1 Technical Implementation Verification (`STAT-PAY`)

1. **Zero Global Rate Assumption Confirmed:**
   - Inspected [PayrollCalculationService.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Workforce/PayrollCalculationService.php#L49-L119) and [WorkforceController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/WorkforceController.php#L469-L535). Neither service nor controller applies hardcoded `12%` PF, `0.75%` ESIC, or `₹200` PT defaults in backend calculation logic. Every deduction is computed exclusively from the individual `Worker`'s configured fields (`pf_status`, `pf_employee_rate`, `pf_employer_rate`, `pf_wage_ceiling`, `pf_cap_at_ceiling`, `esic_status`, `esic_employee_rate`, `esic_employer_rate`, `esic_wage_ceiling`, `pt_status`, `pt_monthly_amount`).
   - UI placeholder hints exist only as HTML `placeholder="..."` text in `resources/views/workforce/create.blade.php` and `edit.blade.php` and are never submitted as values.
2. **State Handling (`applicable`, `exempt`, `unconfigured`):**
   - `unconfigured`: Evaluates PF/ESIC/PT employee & employer amounts to `0.00` and flags `payroll_entries.statutory_config_state = 'unconfigured'`.
   - `exempt`: Evaluates that component to `0.00` and marks `configured` (provided no other component is `unconfigured`).
   - `applicable`: Validates required positive/non-negative rates (`0–100`) and optional wage ceilings (`> 0`) in `WorkforceController::validateStatutoryPayload()`.
3. **PF Wage Ceiling Cap Behavior:**
   - When `pf_cap_at_ceiling = true` and `pf_wage_ceiling > 0`, `pfWageBase = min(gross_pay, pf_wage_ceiling)` (`PayrollCalculationService.php:66-69`). When `pf_cap_at_ceiling = false` or `pf_wage_ceiling` is `NULL`, PF applies to full `gross_pay`.
4. **ESIC Wage Ceiling Behavior:**
   - When `esic_wage_ceiling === null || gross_pay <= esic_wage_ceiling`, ESIC employee and employer contributions are computed on `gross_pay`; when `gross_pay > esic_wage_ceiling`, both evaluate to `0.00` and `esic_above_ceiling = true` is recorded in `statutory_snapshot` (`PayrollCalculationService.php:82-92`).
5. **Audit Trail & Historical Immutability:**
   - Every change to a worker's statutory fields records `statutory_updated_by`, `statutory_updated_at`, and an `AuditLog` entry (`worker.statutory.configured` / `worker.statutory.updated`) with old/new statutory snapshots (`WorkforceController.php:613-623`, `838-850`).
   - Each `PayrollEntry` persists a full JSON `statutory_snapshot` capturing the exact worker configuration, wage bases, and computed employee/employer amounts at calculation time (`PayrollCalculationService.php:101-118`). Updating a worker's statutory settings later does **not** mutate locked or previously calculated `PayrollEntry` rows unless an unlocked period is explicitly recalculated.
6. **Implementation Defects Identified in Statutory Payroll:**
   - **`HIGH-04`:** `PayrollController::approveAndLock()` does **not** block locking when `statutory_config_state = 'unconfigured'` entries exist (all 21 existing workers in `nsd_crm_2` will be `unconfigured` immediately after Migration 4).
   - **`HIGH-02`:** Supervisors can view payroll and download PDF payslips; site-restricted users can access company-wide (`site_id = NULL`) payroll periods and bank payout CSVs.
   - **`MED-05`:** No maker-checker separation on `approveAndLock()` (same user can calculate, adjust, and permanently lock).
   - **`MED-06`:** Fixed `pt_monthly_amount` (or manual `other_deduction`) is not capped to remaining payable `gross_pay`, causing `total_gross_amount - total_deductions != total_net_amount` when `gross_pay < pt_monthly_amount`.

---

### 6.2 Separate Statutory Payroll Risk Report (Domain & Regulatory Review)

> **Important Advisory:** While the software architecture properly avoids hardcoded statutory percentages by delegating configuration to each worker profile, Indian statutory payroll compliance involves statutory wage definitions and contribution-period rules that go beyond flat per-worker percentages. **The items below require review and sign-off by a qualified Indian payroll / labour-law professional (CA / CS / Labour Law Advisor) before relying on CRM outputs for statutorychallan filing (ECR / ESIC / State PT returns).**

| # | Statutory Area | Configurable in Code Today | Statutory / Regulatory Nuance Not Modeled | Compliance Risk & Recommended Professional Validation |
|---|---|---|---|---|
| **SR-01** | **EPF Wage Base Definition** (*EPF & MP Act, 1952*) | PF rate (`%`), ceiling amount, and cap toggle are applied to `gross_pay` (`base_pay + overtime_pay + bonus_amount`). | Under Section 2(b) of the EPF Act, **Overtime Allowance** and non-guaranteed bonuses are excluded from "Basic Wages", whereas special/retaining allowances may be included (Supreme Court *Vivekananda Vidyamandir* ruling). Applying PF directly to `gross_pay` over-deducts PF whenever a worker has overtime or ad-hoc bonus. | **High Statutory Accuracy Risk:** Have a payroll advisor confirm whether PF wage base should exclude `overtime_pay` and `bonus_amount` (or introduce a separate `pf_able_wage` / Basic+DA split). |
| **SR-02** | **Employer EPF Split (EPF vs EPS vs EDLI & Admin Charges)** | Single `pf_employer_rate` stored as one `pf_employer_amount`. | Employer's 12% contribution in India is legally split between **EPS (`8.33%` capped at ₹15,000 wage ceiling, or `0%` to EPS for members joining after Sep 2014 above ₹15,000 / members aged $\ge 58$)** and **EPF (`3.67%` or balance)**, plus **EDLI (`0.50%`)** and **EPF Admin Charges (`0.50%`, minimum ₹500/company)**. | **Medium Reporting Risk:** Payslips show aggregate employer PF cleanly, but EPFO ECR text file generation (future scope) requires EPS vs EPF bifurcation and age/membership-date rules. |
| **SR-03** | **ESIC 6-Month Contribution Period Continuity Rule** (*ESI Act, 1948*) | Drops ESIC to `₹0.00` in any month `gross_pay > esic_wage_ceiling`. | Under ESIC regulations, once an employee is covered at the start of a **6-month contribution period** (1 Apr–30 Sep or 1 Oct–31 Mar), contributions **must continue until the end of that 6-month window** even if monthly gross wages temporarily exceed ₹21,000 (e.g., due to overtime or arrears). Moreover, overtime is included for contribution calculation *unlike* wage-ceiling coverage eligibility in specific notifications. | **High Statutory Compliance Risk:** Dropping ESIC to `₹0` mid-period when overtime pushes gross above ₹21,000 can cause ESIC short-deduction notices. Validate whether an "ESIC Active for Current 6-Month Period" override toggle is needed. |
| **SR-04** | **Professional Tax (PT) State Slabs, Gender Exemptions & 12th-Month Adjustment** | Fixed monthly `pt_monthly_amount` (`₹`) per worker whenever `gross_pay > 0`. | State PT laws vary by state, monthly gross slab, gender, and month: e.g., **Maharashtra** charges ₹0 below slab, ₹175/₹200 in regular months (**₹300 in February** to reach ₹2,500/yr), and exempts women up to ₹25,000/month; **Tamil Nadu / Kerala** deduct PT **half-yearly**, not monthly; low-attendance months where gross falls into a lower slab should reduce PT to ₹0/lower slab rather than charging full fixed PT. | **Medium–High Compliance Risk:** Fixed monthly PT will over-deduct in low-gross months (and under-deduct ₹100 in Feb for Maharashtra). Validate target operating states with company finance/CA. |
| **SR-05** | **Labour Welfare Fund (LWF), TDS, & Subcontractor Piece-Rate Workers** | Individual `exempt` status supported; `other_deduction` manual field available. | State LWF (half-yearly/annual employee + employer contribution) and 194C TDS on `subcontractor` workers are not separately itemized. Subcontractor workers on `per_unit` pay should typically be marked `exempt` from employee PF/ESIC unless on direct muster roll, subject to Principal Employer liability verification. | **Operational Guidance Needed:** Document clear SOPs for HR/Admin when onboarding direct vs subcontractor muster workers so `applicable` vs `exempt` is set accurately before locking payroll. |

---

## 7. Release 1 Backlog Completion Matrix (`BL-01` to `BL-15` & `STAT-PAY`) (Audit D)

> **Backlog Numbering Integrity Note:** This matrix strictly follows the authoritative backlog IDs approved in [DEVELOPMENT_ROADMAP.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/DEVELOPMENT_ROADMAP.md#L15-L35) and [FEATURE_GAP_ANALYSIS.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/FEATURE_GAP_ANALYSIS.md) (where **`BL-11`** = *Global RA Bills Register & Inter-State IGST* and **`BL-12`** = *Worker-User Linking, QR ID Cards, Payslip PDFs & Bank Payout CSV*). Note that [RELEASE_1_IMPLEMENTATION_GUIDE.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/RELEASE_1_IMPLEMENTATION_GUIDE.md#L15-L35) transposed several backlog numbers (`BL-05`/`06`/`07` and `BL-11`/`12`) — see **`LOW-04`**.

| Item | Authoritative Scope (`DEVELOPMENT_ROADMAP.md`) | Audit Status | Verification Evidence & Defects Found |
|---|---|---|---|
| **`BL-01`** | Self-service password change & profile security screen | **Complete** | Implemented in [ProfileSecurityController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/ProfileSecurityController.php) (`/profile/security`), enforces `Hash::check` on current password, `Password::min(8)`, clears `must_change_password`, rotates session, invalidates other device sessions, and displays 2FA status + active sessions. *(Note: `RELEASE_1_IMPLEMENTATION_GUIDE.md` inaccurately mentioned forgot/reset password, which is Release 3 `BL-24`.)* |
| **`BL-02`** | Custom role creation, cloning & custom permission overrides (incl. Office Staff procurement permissions) | **Partial** *(Defect `HIGH-02`, Prerequisite `HIGH-01`)* | Custom role creation/cloning/deletion (`CompanyUserController`), per-user `permission_overrides` (`grant`/`deny`/`inherit`), `ADMIN_RESERVED_PERMISSIONS` guardrail (`PermissionCatalog.php:27-37`), and Office Staff procurement opt-in (`PurchaseRequestPolicy`, `ProcurementController`) are implemented. **However:** (1) `User::canPerform()` lets `$fallbackRoles` override permissions revoked from customized system roles (`HIGH-02`); (2) `company_user.permission_overrides` requires unapplied prerequisite migration `2026_11_10_150000` (`HIGH-01`). |
| **`BL-03`** | Active company switcher for multi-company users | **Complete** *(Minor `LOW-03`)* | Implemented via `POST /company/switch` in [AuthController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/AuthController.php#L249-L280) and topbar dropdown in `layouts/app.blade.php`. Validates active membership in `company_user`, clears `active_site_id`, updates `current_company_id`, and writes `AuditLog`. *(Minor `LOW-03`: redirects `back()`, which returns `404` if switched while viewing a tenant-specific entity detail URL.)* |
| **`BL-04`** | Company Settings UI (profile, GSTIN/PAN, invoice prefix, rounding, authorised signatory, 2FA policy) | **Complete** *(Minor `MED-07`)* | Implemented in [CompanySettingsController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/CompanySettingsController.php) (`/settings/company`, `/settings/company/signature`). Enforces `company.settings` permission, GSTIN/PAN regex, private signatory upload/preview/delete, and audit log. *(Minor `MED-07`: `companies` lacks `signature_disk` column and `$oldValues` is computed but passed as `null` to `AuditLog::record()`.)* |
| **`BL-05`** | Dedicated Master Data Management UI (Clients, Subcontractors, Vendors, Warehouses, Categories, Materials) | **Partial** *(Defect `MED-02`, `LOW-02`)* | Full CRUD UIs and controllers exist (`ClientController`, `WorkforceController` subcontractors, `ProcurementController` vendors, `InventoryController` warehouses/categories/materials). **However:** `InventoryController` and `ProcurementController` allow 31–50 char codes/UOM/phones that exceed MySQL `VARCHAR(20)`/`VARCHAR(30)` columns under `STRICT_TRANS_TABLES` (`MED-02`), and `VEND-###`/`CLI-###` generators lack row locks (`LOW-02`). |
| **`BL-06`** | Leave Types setup & Leave Request / Approval UI | **Partial** *(Defect `MED-04`)* | Leave Type CRUD and Leave Request submission/approval/rejection UIs are wired in [AttendanceController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/AttendanceController.php#L905-L1165) (`/attendance/leaves`) with maker-checker check on `reviewLeaveRequest()` and auto-sync to `DailyAttendance` (`paid_leave`/`absent`). **However:** `storeLeaveRequest()` has **no** role/permission or explicit-deny check (`MED-04`), and Leave Type CRUD omits `isExplicitlyDenied('attendance.manage')`. |
| **`BL-07`** | Measurement Sheet (`MB`) dedicated verification (`draft -> verified`) & edit/delete before billing | **Complete** | Implemented in [RaBillingService.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Operations/RaBillingService.php#L93-L297) and `WorkOrderController`: supports `draft -> verified`, enforces non-self-verification for non-admins (`L207`), locks billed measurements (`ra_bill_id !== null || status === 'billed'`), supports edit/delete of unbilled rows with private photo cleanup, and restricts RA bill aggregation to `status = 'verified'` (`L438`). |
| **`BL-08`** | Unified Approval Center / Pending Inbox | **Complete** *(Minor `LOW-03`)* | Implemented in [ApprovalCenterController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/ApprovalCenterController.php) (`/approvals`) aggregating Work Orders (Tier 1 & Tier 2), Purchase Requests, Stock Adjustments, RA Bills, Attendance Corrections, Leave Requests, and Draft Site Measurements with policy/site filtering. *(Minor `LOW-03`: loads pending collections in memory before policy filtering.)* |
| **`BL-09`** | Warehouse-to-Warehouse Transfer, Site Return & Stock Adjustment with approval | **Complete** *(Minor `MED-03`)* | Implemented in [InventoryService.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Procurement/InventoryService.php) and [InventoryController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/InventoryController.php): atomic `transferStock()` with deterministic ascending `lockForUpdate()` ordering to prevent deadlocks, `returnFromSite()` (`RET-*`), `requestStockAdjustment()` (`ADJ-*`), and maker-checker `reviewStockAdjustment()` (`L354`). |
| **`BL-10`** | Vendor Master & PO Supplier Directory linkage | **Partial** *(Defect `MED-02`)* | `Vendor` model, `/procurement/vendors` CRUD, and `purchase_orders.vendor_id` linkage in `ProcurementController::storeOrder()` are implemented. **However:** auto-copying a 31–50 character `vendors.phone` into `purchase_orders.supplier_phone` (`VARCHAR(30)`) triggers a MySQL `SQLSTATE[22001]` error (`MED-02`). |
| **`BL-11`** | Global RA Bills Register (`/ra-bills`) & Inter-State IGST support | **Complete** *(Minor `LOW-01`)* | Global cross-WO register implemented in [RaBillController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/RaBillController.php#L24-L98) with status/site/tax_mode filters, KPI totals, and pagination; `cgst_sgst` vs `igst` tax mode implemented end-to-end across Work Orders, `RaBillingService`, PDF tax invoices, and Excel exports. *(Minor `LOW-01`: `ra_bills.igst_amount` is `DECIMAL(14,2)` vs `DECIMAL(16,2)` on `cgst_amount`/`sgst_amount`.)* |
| **`BL-12`** | Worker-User explicit linking, Worker QR ID card, Individual Payslip PDFs & Bank Payout CSV | **Partial** *(Defects `HIGH-02`, `HIGH-03`)* | All 4 features are built (`workers.user_id` link, QR SVG ID card `/workforce/{worker}/id-card`, DomPDF payslip `/payroll/{period}/entries/{entry}/pdf`, CSV formula-sanitized NEFT export `/payroll/{period}/export-bank-csv`). **However:** `WorkforceController` lets non-admin managers link & deactivate admin `User` accounts (`HIGH-03`), and `PayrollController` leaks company-wide payslips/bank CSVs to site-restricted users and supervisors (`HIGH-02`). |
| **`BL-13`** | Private Object Storage Vault Migration (`storage_disk` tracking & private serving) | **Partial** *(Defect `MED-07`)* | Private `vault` disk (`storage/app/private`) configured in [filesystems.php](file:///Users/andrew/Developer/NSD-CRM-2.0/config/filesystems.php#L42-L49), [VaultStorage.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Storage/VaultStorage.php) centralizes reads/writes with legacy `'local'` fallback, zero `public/storage` symlink exists, and all document downloads pass through authenticated controllers. **However:** `WorkOrderUploadService` calls local-only `->path()`, `VaultStorage` ignores `false` write returns when `throw => false`, and `CompanySettingsController` bypasses `VaultStorage` without a `signature_disk` column (`MED-07`). |
| **`BL-14`** | Fix Project Detail Activity Log column bug (`event` -> `action`) | **Complete** | Verified in [ProjectController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/ProjectController.php#L238-L263) and `resources/views/projects/show.blade.php`: queries `AuditLog::forCompany()` filtering on `project_id`, `subject_type = Project::class`, and visible `site_id`s using real `action` and `created_at` columns while excluding `auth.%` and `payroll.%` events. |
| **`BL-15`** | Server-side pagination, composite indexes & streaming CSV exports | **Partial** *(Defect `MED-01`)* | `TaskController` (`paginate($perPage)`), `WorkforceController`, `ProcurementController`, `RaBillController`, and `ReportsController` (`paginate()` + `lazyByIdDesc(250)` streaming CSV export) are implemented. **However:** 4 of 6 composite indexes in Migration 5 duplicate existing `nsd_crm_2` indexes (`MED-01`). |
| **`STAT-PAY`** | Individual Employee/Worker Statutory Payroll Configuration (PF, ESIC, PT) | **Partial** *(Defects `HIGH-02`, `HIGH-04`, `MED-05`, `MED-06`)* | Per-worker PF/ESIC/PT statuses, rates, ceilings, cap toggles, audit trail, JSON `statutory_snapshot`, and zero-assumption calculation engine are implemented. **However:** unconfigured workers can be permanently locked with ₹0 deductions (`HIGH-04`), payroll authorization/site-scoping has bypasses (`HIGH-02`), `approveAndLock` lacks maker-checker (`MED-05`), and fixed PT exceeding low gross pay breaks period ledger balance (`MED-06`). |

---

## 8. Security and Authorization Findings (Audit E)

1. **Tenant Isolation (`company_id` Scoping):**
   - Models consistently apply `BelongsToCompany` global scope (`TenantContext::getCompanyId()`), and multi-company switching (`AuthController::switchCompany`) verifies `company_user.status = 'active'` before updating `session('current_company_id')`.
2. **Office Staff Procurement Permissions:**
   - Verified in [PermissionCatalog.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Security/PermissionCatalog.php#L100-L120), [PurchaseRequestPolicy.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Policies/PurchaseRequestPolicy.php), and [ProcurementController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/ProcurementController.php): `office_staff` defaults to `purchase_requests.create` + `procurement.view` only; `purchase_requests.approve`, `purchase_orders.create`, `goods_receipts.create`, and `vendors.manage` require explicit custom-role or per-user permission grants, with self-approval blocked in `PurchaseRequestPolicy::approve()` (`(int) $pr->requested_by === (int) $user->id` denied for non-admins).
3. **Admin-Reserved Permission Guardrails:**
   - `PermissionCatalog::ADMIN_RESERVED_PERMISSIONS` (`company.settings`, `roles.manage`, `users.create`, `users.edit`, `users.deactivate`) properly prevents non-admin escalation via custom roles or per-user overrides in `CompanyUserController`.
   - **Bypass Found (`HIGH-03`):** `WorkforceController` inadvertently bypasses `users.deactivate` by letting any `workforce.update` actor link `workers.user_id` to an admin user and terminate the worker, deactivating that admin's login.
4. **Payroll & Role Matrix Authorization Flaws (`HIGH-02`):**
   - `PayrollController::authorizePayrollView()` includes `'site_supervisor'` in `$fallbackRoles`, contradicting `PermissionCatalog`.
   - `PayrollController::show()`, `payslip()`, `payslipPdf()`, and `exportBankPayoutCsv()` fail to block site-restricted users when `payroll_periods.site_id IS NULL`.
   - `User::canPerform()` falls back to `$this->hasRole($fallbackRoles)` even when a system role has been customized (`__customized__`) to remove a default permission.
5. **Leave Request Authorization Gap (`MED-04`) & Maker-Checker Gap on Payroll Lock (`MED-05`):**
   - `AttendanceController::storeLeaveRequest()` has no permission check; `PayrollController::approveAndLock()` allows self-approval of self-calculated/self-adjusted payroll periods.
6. **CSV Formula Injection Protection:**
   - Verified present in `PayrollController::exportBankPayoutCsv()` and `ReportsController::exportAuditLogsCsv()` (prefixes `=`, `+`, `-`, `@`, `\t`, `\r` with `'`). Note: legacy `AttendanceController::exportPayrollCsv()` does not sanitize worker/site names (`LOW-05`).

---

## 9. Private Document Storage Findings (Audit F)

1. **No Public Storage Exposure (`Verified Safe`):**
   - `public/` contains only `.htaccess`, `build/`, `favicon.ico`, `index.php`, and `robots.txt` — **no `public/storage` symlink exists**.
   - All newly uploaded worker documents, project documents, measurement photos, work order PDFs, RA bill exports, and company signatory images are stored outside `public/` (`storage/app/private` via the `vault` disk) and served exclusively behind `auth` + tenant + policy/site checks.
2. **Legacy File Compatibility (`Verified Safe`):**
   - [VaultStorage.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Storage/VaultStorage.php#L30-L49) checks the recorded `$preferredDisk` first and falls back across `['vault', 'local']`. Existing `bill_exports` rows (`2` rows in `nsd_crm_2`) receive `storage_disk = 'local'` in Migration 3, preserving seamless download resolution.
3. **Cloud-Readiness & Error-Handling Gaps (`MED-07`):**
   - [WorkOrderUploadService.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Operations/WorkOrderUploadService.php#L47-L55) stores the uploaded PDF via `VaultStorage::storeUploaded()` and then calls `VaultStorage::disk($diskName)->path($storedPath)` to parse the PDF locally. On an S3/R2/GCS Flysystem adapter, `->path()` is unsupported/meaningless; parsing should read `$pdfFile->getRealPath()` prior to/alongside vault upload, wrapped in `try/catch` to delete `$stored['path']` if DB persistence fails.
   - In [config/filesystems.php](file:///Users/andrew/Developer/NSD-CRM-2.0/config/filesystems.php#L33-L49), both `'local'` and `'vault'` set `'throw' => false`. `VaultStorage::put()` and `VaultStorage::storeUploaded()` do not verify that the underlying `Storage::disk()->put()` / `storeAs()` returned non-`false`, risking silent `''`/`false` path storage if disk permissions fail.
   - [CompanySettingsController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/CompanySettingsController.php#L200-L238) directly calls `Storage::disk('vault')` without a `companies.signature_disk` column.

---

## 10. Test Reproduction Results (Audit G)

All verification commands were executed in strictly read-only mode without touching `nsd_crm_2` or workspace build artifacts:

| Check | Previously Reported Baseline | Independently Reproduced Result | Notes |
|---|---|---|---|
| **PHPUnit Suite (`php artisan test`)** | `208` passed, `2,407` assertions | **`208` passed, `0` failed, `2,407` assertions** (`7,133 ms`) | Confirmed `DB_CONNECTION=sqlite` & `DB_DATABASE=:memory:` in `phpunit.xml`. Note: SQLite masks MySQL `VARCHAR(N)` strict-mode overflows (`MED-02`) and duplicate index warnings (`MED-01`). |
| **Laravel Pint — Release 1 Modified Files (`./vendor/bin/pint --dirty --test`)** | Clean | **`PASSED` (`exit code 0`)** | All Release 1 modified/untracked PHP files conform to Pint rules. |
| **Laravel Pint — Full Repository (`./vendor/bin/pint --test`)** | Not separately reported | **`FAILED` (`exit code 1`) on legacy pre-Release-1 files** | Style drift exists in older `2026_10_28_*`–`2026_11_10_*` migrations, seeders, models, and legacy tests (`LOW-05`). |
| **Route Compilation (`php artisan route:list --json`)** | Clean | **`PASSED` (`232` routes compiled, `0` errors)** | Verified zero unauthenticated document vault routes. |
| **Frontend Build (`npx vite build --outDir /tmp/nsd_audit_vite_build`)** | Vite production build successful | **`PASSED` (`198 ms`, Vite `8.3.5`)** | Compiled `app.css` (`142.62 kB`) and `app.js` (`54.32 kB`) into `/tmp/nsd_audit_vite_build` (subsequently cleaned up) so `public/build/` remained untouched. |

---

## 11. Critical, High, Medium, and Low Severity Issues

### Summary Count by Severity

- **CRITICAL:** `0`
- **HIGH:** `4`
- **MEDIUM:** `7`
- **LOW:** `5`

---

### CRITICAL Severity Issues (`0`)

None identified. No migration drops data, corrupts existing `nsd_crm_2` rows, or exposes private files publicly.

---

### HIGH Severity Issues (`4`)

#### `HIGH-01` — Two Unapplied Earlier Migrations Precede the Five Release 1 Migrations in `nsd_crm_2`
- **Affected Files:**
  - [2026_11_09_134500_add_two_factor_last_step_to_users_table.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_09_134500_add_two_factor_last_step_to_users_table.php)
  - [2026_11_10_150000_add_enterprise_access_management_columns.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_10_150000_add_enterprise_access_management_columns.php)
  - [RELEASE_1_IMPLEMENTATION_GUIDE.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/RELEASE_1_IMPLEMENTATION_GUIDE.md#L94)
- **Relevant Method / Schema:** `migrations` table (`22` rows applied up to `2026_11_04_110000`), `users.two_factor_last_step`, `users.must_change_password`, `company_user.permission_overrides`, `company_user.restrict_to_assigned_sites`, `roles.is_system`, `roles.is_active`, and DML `DB::table('roles')->whereIn('slug', ...)->update(['is_system' => true])`.
- **The Problem:** Live `nsd_crm_2` has **7** pending migrations on disk, not 5. Running plain `php artisan migrate` will execute the two earlier November 9–10 migrations (including a DML `UPDATE` on `roles`) alongside the five Release 1 migrations. Conversely, running only the 5 `2026_11_15_*` files via `--path` leaves `company_user.permission_overrides` and `company_user.restrict_to_assigned_sites` missing from `nsd_crm_2`, breaking Release 1 per-user permission overrides (`BL-02`), Office Staff procurement overrides, and site-restriction flags.
- **Potential Impact:** Unintended execution of unapproved migrations (including live DML on `roles`) if `php artisan migrate` is run blindly, OR runtime feature failure of `BL-02` custom per-user permissions if only the 5 `2026_11_15_*` files are run.
- **Recommended Resolution:** Explicitly include `2026_11_09_134500` and `2026_11_10_150000` as Steps 0a and 0b of the formal Migration Approval Plan (verified additive + safe `is_system = 1` backfill on system role slugs) and execute migrations one-by-one using `php artisan migrate --path=...`.

---

#### `HIGH-02` — Payroll Authorization Bypass (Supervisor Access, Company-Wide Period Leak to Site-Restricted Users, & Role Matrix Override Flaw)
- **Affected Files:**
  - [PayrollController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/PayrollController.php#L35-L48) (`authorizePayrollView`, `show` `L170-173`, `payslip` `L372-375`, `payslipPdf` `L398-401`, `exportBankPayoutCsv` `L449-452`)
  - [app.blade.php](file:///Users/andrew/Developer/NSD-CRM-2.0/resources/views/layouts/app.blade.php#L303)
  - [User.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Models/User.php#L323-L342) (`canPerform`)
- **Relevant Method / Schema:** `PayrollController::authorizePayrollView()`, `PayrollController::show()`, `PayrollController::payslip()`, `PayrollController::payslipPdf()`, `PayrollController::exportBankPayoutCsv()`, `User::canPerform()`.
- **The Problem:**
  1. `PermissionCatalog` excludes `site_supervisor`/`supervisor` from `payroll.view`, yet `PayrollController::authorizePayrollView()` passes `'site_supervisor'` in `$fallbackRoles`, and `layouts/app.blade.php:303` renders the Payroll navigation item whenever `Auth::user()->can('viewAny', Worker::class)` (true for supervisors).
  2. When a `PayrollPeriod` is company-wide (`site_id = NULL`), `PayrollController::index()` hides it from site-restricted users (`whereIn('site_id', $accessibleSiteIds)`), **but** `show()`, `payslip()`, `payslipPdf()`, and `exportBankPayoutCsv()` only check `if ($payrollPeriod->site_id && ! $user->isAssignedToSite(...))` — allowing any site-restricted user to hit `/payroll/{id}` or `/payroll/{id}/export-bank-csv` directly and download salaries, bank account numbers, IFSC codes, and payslips for workers across **all** sites in the company.
  3. In `User::canPerform()`, if a Company Admin customizes a system role (`__customized__` marker in Role Matrix) to **revoke** a default permission, `hasPermission()` returns `false`, **but `canPerform()` then falls back to `$this->hasRole($fallbackRoles)` and grants access anyway**.
- **Potential Impact:** Unauthorized exposure of sensitive compensation, Aadhaar-linked UAN/ESIC numbers, bank account details, and NEFT payout files across sites and to field supervisors; customized role revocations silently ignored wherever `canPerform(..., $fallbackRoles)` is used.
- **Recommended Resolution:**
  1. Remove `'site_supervisor'` from `PayrollController::authorizePayrollView()` `$fallbackRoles` and gate the sidebar Payroll link on `canPerform('payroll.view', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'office_staff'])`.
  2. In `PayrollController::show()`, `payslip()`, `payslipPdf()`, and `exportBankPayoutCsv()`, reject site-restricted users (`! $this->hasUnrestrictedPayrollSiteAccess($user)`) whenever `$payrollPeriod->site_id === null` (or filter entries/payslips strictly to `$user->accessibleSiteIds()`).
  3. Update `User::canPerform()` so `$fallbackRoles` is consulted **only** when none of the user's active roles in the company have been customized via `__customized__` (or when the `permissions` table is unseeded).

---

#### `HIGH-03` — Privilege Escalation / Administrator Account Lockout via Worker-User Deactivation Sync
- **Affected File:** [WorkforceController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/WorkforceController.php#L861-L903) (`store` `L549-555`, `L625-633`; `update` `L736-742`, `L861-903`; `destroy` `L1088-1099`)
- **Relevant Method / Schema:** `workers.user_id`, `company_user.status`, `users.is_active`, `sessions.user_id`.
- **The Problem:** Any user with `workforce.create` or `workforce.update` (such as a `site_manager`, who is intentionally barred from `ADMIN_RESERVED_PERMISSIONS` like `users.edit` and `users.deactivate`) can set `user_id` on a worker to **any** user in the same company — including a `company_admin` or `senior_manager` — and then change the worker's `status` to `'inactive'` or `'terminated'`. `WorkforceController` immediately updates `company_user.status = 'inactive'`, sets `users.is_active = false`, and deletes all sessions for that administrator.
- **Potential Impact:** Horizontal/vertical denial-of-service and administrative lockout: a Site Manager can lock Company Admins and Senior Managers out of the CRM.
- **Recommended Resolution:**
  1. Require `users.edit` (or Company Admin / Super Admin role) to link or change `workers.user_id`.
  2. Never allow worker deactivation/termination to deactivate a `User` holding `super_admin`, `company_admin`, or `senior_manager` roles unless performed by a `super_admin` / `company_admin` with `users.deactivate`, and prevent linking `workers.user_id` to `super_admin` / `company_admin` accounts.

---

#### `HIGH-04` — Unconfigured Workers Can Be Permanently Approved & Locked with ₹0 Statutory Deductions
- **Affected Files:**
  - [PayrollController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/PayrollController.php#L330-L358) (`approveAndLock`)
  - [PayrollCalculationService.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Workforce/PayrollCalculationService.php#L49-L71) (`evaluateStatutoryDeductions`, `approveAndLockPeriod` `L389-L424`)
- **Relevant Method / Schema:** `workers.pf_status`, `workers.esic_status`, `workers.pt_status` (`DEFAULT 'unconfigured'`), `payroll_entries.statutory_config_state`.
- **The Problem:** Immediately after Migration 4 runs on `nsd_crm_2`, all **21 existing workers** (as well as any workers later imported via CSV) have `pf_status = 'unconfigured'`, `esic_status = 'unconfigured'`, and `pt_status = 'unconfigured'`. `PayrollCalculationService` evaluates `unconfigured` to `₹0.00` deductions and marks `statutory_config_state = 'unconfigured'`. However, `PayrollController::approveAndLock()` neither blocks locking when `unconfigured` entries exist nor requires an explicit admin confirmation checkbox, AND allows locking a `draft` period before calculation has even been run.
- **Potential Impact:** High risk of accidentally permanently locking a payroll run and exporting NEFT bank payouts with ₹0 PF, ₹0 ESIC, and ₹0 PT deductions for existing workers whose statutory profiles were not yet configured after migration.
- **Recommended Resolution:**
  1. Block `approveAndLockPeriod()` when `$period->status !== 'calculated'` or `$period->entries()->count() === 0`.
  2. Block `approveAndLock()` whenever `$period->entries()->where('statutory_config_state', 'unconfigured')->exists()` (requiring HR/Admin to explicitly mark each worker's PF/ESIC/PT as either `applicable` or `exempt` before final payroll lock, or require an explicit `confirm_unconfigured_override` boolean restricted to `company_admin` and logged in `AuditLog`).

---

### MEDIUM Severity Issues (`7`)

#### `MED-01` — Four Duplicate Composite Indexes & Blanket `catch (Throwable)` in Migration 5
- **Affected File:** [2026_11_15_100005_release1_scalability_composite_indexes.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100005_release1_scalability_composite_indexes.php#L15-L71)
- **Relevant Method / Schema:** `up()`, `down()`, `addIndexSafely()`, `dropIndexSafely()` on `activity_logs`, `daily_attendances`, `tasks`.
- **The Problem:** Live `nsd_crm_2` already has identical composite indexes on 4 of the 6 target column tuples (`act_log_comp_site_time_idx`, `att_comp_site_date_idx`, `att_comp_worker_date_idx`, `tasks_comp_site_status_idx`). Creating them under new names creates redundant physical B-tree indexes in MySQL/TiDB (`Warning 1831`). Additionally, `catch (\Throwable)` swallows real database errors instead of inspecting schema metadata.
- **Potential Impact:** Unnecessary B-tree index bloat and write-amplification overhead on the highest-write tables in the CRM (`activity_logs` and `daily_attendances`), plus silent masking of DDL lock/permission errors.
- **Recommended Resolution:** Remove the 4 duplicate index definitions (keeping only `idx_act_logs_comp_action_created` and `idx_po_comp_site_status`) and replace `try/catch (\Throwable)` with `Schema::hasIndex($table, $columns)` (for `up()`) and `Schema::hasIndex($table, $indexName)` (for `down()`).

---

#### `MED-02` — MySQL `STRICT_TRANS_TABLES` `VARCHAR` Length Validation Mismatches (Masked by SQLite Tests)
- **Affected Files:**
  - [ProcurementController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/ProcurementController.php#L294-L316) (`storeOrder`)
  - [InventoryController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/InventoryController.php#L137-L228) (`storeWarehouse`, `updateWarehouse`, `storeCategory`, `updateCategory`, `storeMaterial`, `updateMaterial`)
  - [2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php#L23)
- **Relevant Method / Schema:**
  - `vendors.phone` (`VARCHAR(50)`) & `storeOrder` `supplier_phone` (`max:50`) vs `purchase_orders.supplier_phone` (`VARCHAR(30)` in `nsd_crm_2`).
  - `InventoryController` `code` (`max:40` / `max:50`) vs `warehouses.code`, `material_categories.code`, `materials.code` (all `VARCHAR(30)` in `nsd_crm_2`).
  - `InventoryController` `unit_of_measure` (`max:30`) vs `materials.unit_of_measure` (`VARCHAR(20)` in `nsd_crm_2`).
- **The Problem:** SQLite ignores `VARCHAR` length limits during `php artisan test`, but live `nsd_crm_2` runs MySQL with `STRICT_TRANS_TABLES`. Submitting valid-per-validator 31–50 character codes/UOMs, or creating a PO linked to a Vendor whose `phone` is 31–50 characters, triggers an unhandled `QueryException` (`SQLSTATE[22001]: Data too long for column`).
- **Potential Impact:** `HTTP 500` database exceptions in production on valid form submissions and vendor-linked PO creation.
- **Recommended Resolution:** Tighten controller validation rules to match exact MySQL column lengths (`max:30` for warehouse/category/material codes and `supplier_phone`/`vendors.phone`, `max:20` for `materials.unit_of_measure`), or widen `purchase_orders.supplier_phone` to `VARCHAR(50)`.

---

#### `MED-03` — Non-Transactional Multi-Step `ALTER TABLE` Idempotency Gaps, Multi-Table Coupling in Migration 3, & TiDB Multi-Column `DROP` Considerations
- **Affected Files:**
  - [2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php#L52-L59)
  - [2026_11_15_100002_release1_inventory_movements_and_adjustments.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100002_release1_inventory_movements_and_adjustments.php#L14-L35)
  - [2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php#L16-L77)
  - [2026_11_15_100004_release1_individual_statutory_payroll.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100004_release1_individual_statutory_payroll.php#L52-L55)
- **Relevant Method / Schema:** `up()` / `down()` across Migrations 1–4.
- **The Problem:**
  1. MySQL and TiDB commit each `ALTER TABLE` implicitly. When `if (! Schema::hasColumn(...))` wraps both `ADD COLUMN` and a separate `ADD CONSTRAINT` / `ADD UNIQUE` statement (`purchase_orders.vendor_id`, `inventory_transactions.work_order_id`, `workers.user_id`, `workers.statutory_updated_by`), a transient failure on the second `ALTER TABLE` leaves the column present so a retry skips the block and **never creates the foreign key or unique constraint**.
  2. Migration 2 omits `if (Schema::hasTable('inventory_transactions'))` and `Schema::hasIndex('inventory_transactions', 'inv_tx_comp_type_date_idx')`.
  3. Migration 3 couples 7 unrelated tables across 3 independent domains (Vault storage, GST/IGST billing, and Worker-User linking).
  4. Migration 4 `down()` drops 16 columns in one `ALTER TABLE`, requiring TiDB v6.2+ if deployed to TiDB.
- **Potential Impact:** Partial schema drift without FK/unique constraints if an online `ALTER TABLE` times out mid-migration.
- **Recommended Resolution:** Guard indexes separately with `Schema::hasIndex(...)`, add `Schema::hasTable('inventory_transactions')` in Migration 2, and consider splitting Migration 3 into three single-responsibility migrations before first execution (see Section 13.2).

---

#### `MED-04` — Missing Permission Gate on `AttendanceController::storeLeaveRequest()` & Explicit-Deny Bypass on Leave Type CRUD
- **Affected File:** [AttendanceController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/AttendanceController.php#L963-L1075) (`storeLeaveType`, `updateLeaveType`, `destroyLeaveType`, `storeLeaveRequest`)
- **Relevant Method / Schema:** `POST /attendance/leaves`, `POST/PUT/DELETE /attendance/leave-types`.
- **The Problem:** `storeLeaveRequest()` verifies site membership when `$worker->assignedSite` is set, **but checks zero attendance/workforce permissions and ignores `isExplicitlyDenied()`**, allowing roles with no attendance rights (e.g., `warehouse_manager`) or users explicitly denied `attendance.*` to file leave requests (especially for unassigned workers where `$worker->assignedSite` is `null`). Additionally, `storeLeaveType`, `updateLeaveType`, and `destroyLeaveType` check `hasRole(['super_admin', 'company_admin', 'senior_manager'])` without checking `isExplicitlyDenied('attendance.manage')`.
- **Potential Impact:** Unauthorized leave request creation and inability to explicitly deny `attendance.manage` to a `senior_manager` via per-user overrides.
- **Recommended Resolution:** Enforce `$user->canPerform('attendance.mark', ['super_admin', 'company_admin', 'senior_manager', 'site_manager', 'site_supervisor', 'office_staff'])` on `storeLeaveRequest()`, and check `$user->isExplicitlyDenied('attendance.manage')` in Leave Type CRUD methods.

---

#### `MED-05` — Missing Maker-Checker Separation of Duties on `PayrollController::approveAndLock()`
- **Affected Files:**
  - [PayrollController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/PayrollController.php#L330-L358)
  - [PayrollCalculationService.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Workforce/PayrollCalculationService.php#L389-L424)
- **Relevant Method / Schema:** `payroll_periods.calculated_by`, `payroll_periods.approved_by`.
- **The Problem:** Whereas Purchase Requests, Stock Adjustments, Attendance Corrections, Leave Approvals, and Site Measurements all enforce non-self-approval for non-admins, a `senior_manager` (who holds both `payroll.edit` and `payroll.approve` by default) can calculate a payroll period, add manual `bonus_amount` adjustments, and immediately approve and permanently lock that same payroll period alone.
- **Potential Impact:** Fraud/control risk on payroll disbursements due to absence of two-person review for non-admin managers.
- **Recommended Resolution:** Enforce `(int) $payrollPeriod->calculated_by !== (int) $actor->id` (and check whether `$actor` adjusted entries in the period) when `! $actor->hasRole(['super_admin', 'company_admin'])`.

---

#### `MED-06` — Fixed `pt_monthly_amount` or Manual `other_deduction` Exceeding Low `gross_pay` Breaks Period Ledger Balance
- **Affected Files:**
  - [PayrollCalculationService.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Workforce/PayrollCalculationService.php#L97-L99) (`evaluateStatutoryDeductions`, `calculatePeriod` `L237`, `recalculatePeriodTotals` `L366-L380`)
  - [PayrollController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/PayrollController.php#L253-L287) (`adjustEntry`)
- **Relevant Method / Schema:** `payroll_entries.pt_amount`, `other_deduction`, `advance_deduction`, `total_deductions`, `net_pay`; `payroll_periods.total_gross_amount`, `total_deductions`, `total_net_amount`.
- **The Problem:** If a worker works a fraction of a day (e.g., `gross_pay = ₹150.00`) while configured with `pt_monthly_amount = ₹200.00`, `pt_amount` is set to `200.00`, `total_deductions` becomes `200.00`, and `net_pay` is clamped to `0.00` via `max(0.0, ...)`. Consequently, `payroll_periods.total_gross_amount - payroll_periods.total_deductions != payroll_periods.total_net_amount` (`150 - 200 != 0`). Similarly, `adjustEntry()` allows `other_deduction` up to `999999` without capping against available post-statutory pay, and reducing a previously entered `bonus_amount` does not re-cap `advance_deduction`.
- **Potential Impact:** Accounting reconciliation discrepancy between gross pay, itemized statutory/other deductions, and net payout totals on payslips and period summaries.
- **Recommended Resolution:** Cap `pt_amount` (or skip PT when `gross_pay < pt_monthly_amount`), cap `advance_deduction` and `other_deduction` against remaining `gross_pay - statutory_deductions` in both `calculatePeriod()` and `adjustEntry()`, and validate `other_deduction <= remainingPayable`.

---

#### `MED-07` — Local-Only `->path()` Call in `WorkOrderUploadService`, Unchecked `VaultStorage` Write Return Booleans, & Missing `signature_disk` on `companies`
- **Affected Files:**
  - [WorkOrderUploadService.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Operations/WorkOrderUploadService.php#L35-L55)
  - [VaultStorage.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Services/Storage/VaultStorage.php#L51-L67)
  - [filesystems.php](file:///Users/andrew/Developer/NSD-CRM-2.0/config/filesystems.php#L33-L49)
  - [CompanySettingsController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/CompanySettingsController.php#L129-L238)
- **Relevant Method / Schema:** `WorkOrderUploadService::uploadAndExtract()`, `VaultStorage::put()`, `VaultStorage::storeUploaded()`, `companies.signature_path`.
- **The Problem:**
  1. `WorkOrderUploadService` calls `VaultStorage::disk($diskName)->path($storedPath)` after uploading, which works on local `storage/app/private` but fails on remote object-storage drivers (S3/R2/GCS) and lacks orphan-file cleanup if `extractFromPdf()` or DB creation throws.
  2. `filesystems.php` sets `'throw' => false` on `'vault'` and `'local'`; `VaultStorage::put()` / `storeUploaded()` do not check for `false` return values and throw a `RuntimeException`.
  3. `CompanySettingsController` stores `companies.signature_path` directly via `Storage::disk('vault')` without a `signature_disk` column and passes `null` instead of the computed `$oldValues` to `AuditLog::record()`.
- **Potential Impact:** Future cloud-storage migration breakage on Work Order PDF extraction, silent broken DB file links on filesystem write failures, and incomplete `old_values` audit diff on Company Settings updates.
- **Recommended Resolution:** Parse `$pdfFile->getRealPath()` before/during upload with `try/catch` cleanup on `$stored['path']`, throw `RuntimeException` in `VaultStorage` if `put()`/`storeAs()` returns `false` (and reject `..` path segments centrally), pass `$oldValues` to `AuditLog::record()` in `CompanySettingsController`, and route signatory files through `VaultStorage`.

---

### LOW Severity Issues (`5`)

#### `LOW-01` — Precision Mismatch on `ra_bills.igst_amount` (`DECIMAL(14,2)` vs `DECIMAL(16,2)`) & Misleading Filename on Migration 1
- **Affected Files:** [2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php#L50), [2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php).
- **Relevant Schema:** `ra_bills.igst_amount` vs `ra_bills.cgst_amount` / `sgst_amount`.
- **The Problem:** Existing `ra_bills` tax/amount columns use `DECIMAL(16,2)`, whereas `igst_amount` in Migration 3 uses `DECIMAL(14,2)`. Migration 1's filename includes `_and_leave_quotas` despite making zero changes to leave tables.
- **Potential Impact:** Theoretical overflow only on single RA bills whose IGST alone exceeds ₹999.99 Crore; minor developer confusion from Migration 1 filename.
- **Recommended Resolution:** Change `igst_amount` in Migration 3 to `decimal('igst_amount', 16, 2)` before running Migration 3.

---

#### `LOW-02` — Non-Atomic Code Auto-Generation (`VEND-###`, `CLI-###`) & Missing DB Unique Index on `clients(company_id, code)`
- **Affected Files:** [ProcurementController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/ProcurementController.php#L92-L98), [ClientController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/ClientController.php#L99-L106), `clients` table.
- **Relevant Method / Schema:** `ProcurementController::storeVendor()`, `ClientController::store()`.
- **The Problem:** When `code` is left blank, `VEND-###` and `CLI-###` are generated via `count() + 1` + `exists()` loops outside a `worker_sequences` row lock. On `vendors`, the DB `UNIQUE (company_id, code)` catches collisions with an unhandled `QueryException`; on `clients`, no DB-level `UNIQUE (company_id, code)` exists in `nsd_crm_2`.
- **Potential Impact:** Under simultaneous blank-code submissions, `vendors` can throw a `500` duplicate-key error and `clients` can insert duplicate `CLI-###` codes.
- **Recommended Resolution:** Route `VEND-###` and `CLI-###` auto-numbers through the existing `DB::transaction` + `worker_sequences` (`lockForUpdate()`) helper used elsewhere in Phase 4, and add a composite unique index on `clients(company_id, code)` after verifying zero duplicate client codes.

---

#### `LOW-03` — In-Memory Collection Loading in `ApprovalCenterController` & Detail-Page `404` on `AuthController::switchCompany()`
- **Affected Files:** [ApprovalCenterController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/ApprovalCenterController.php#L38-L120), [AuthController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/AuthController.php#L249-L280).
- **Relevant Method / Schema:** `ApprovalCenterController::index()`, `AuthController::switchCompany()`.
- **The Problem:** `ApprovalCenterController::index()` loads all pending records per module with `->get()` before filtering via `Gate::forUser($user)` in PHP; `AuthController::switchCompany()` returns `back()`, causing a `404` when switching companies from a tenant-specific URL like `/projects/3` or `/work-orders/1`.
- **Potential Impact:** Higher memory usage if a tenant accumulates thousands of unreviewed drafts; `404` redirect after switching companies on an entity detail screen.
- **Recommended Resolution:** Scope queries by `$accessibleSiteIds` in SQL + `limit(100)` per bucket in `ApprovalCenterController`, and redirect `switchCompany()` to `route('dashboard')`.

---

#### `LOW-04` — Backlog ID Transposition & Inaccurate Claims in `docs/RELEASE_1_IMPLEMENTATION_GUIDE.md`
- **Affected File:** [RELEASE_1_IMPLEMENTATION_GUIDE.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/RELEASE_1_IMPLEMENTATION_GUIDE.md#L15-L45)
- **Relevant Section:** Section 2 (`Release 1 Backlog Mapping`) & Section 5 (`Migration Execution Plan`).
- **The Problem:** Swaps `BL-11` and `BL-12` (and `BL-05`/`06`/`07`) relative to `docs/DEVELOPMENT_ROADMAP.md`, claims `forgot-password`/`reset-password` routes were built in `BL-01` (they are Release 3 `BL-24` and do not exist in `routes/web.php`), mentions `legal_name`/`cin`/company logo under `BL-04`, writes `RTN-*` instead of `RET-*`, and omits the 2 unapplied November 9–10 migrations from its migration list.
- **Potential Impact:** Handover and QA confusion when cross-referencing roadmap IDs or running `php artisan migrate`.
- **Recommended Resolution:** Update `docs/RELEASE_1_IMPLEMENTATION_GUIDE.md` to align with `docs/DEVELOPMENT_ROADMAP.md` and the 7-migration execution sequence.

---

#### `LOW-05` — Legacy Repository Pint Formatting Drift & Legacy Attendance Payroll CSV Formula Sanitization
- **Affected Files:** Pre-Release-1 migrations/seeders/models/tests (`./vendor/bin/pint --test`), [AttendanceController.php](file:///Users/andrew/Developer/NSD-CRM-2.0/app/Http/Controllers/AttendanceController.php) (`exportPayrollCsv`).
- **Relevant Method / Schema:** Code style CI gate; `AttendanceController::exportPayrollCsv()`.
- **The Problem:** While `./vendor/bin/pint --dirty --test` passes (`exit 0`), full `./vendor/bin/pint --test` exits `1` on older files; legacy `AttendanceController::exportPayrollCsv()` writes worker/site names to CSV without the formula-prefix sanitizer used in `PayrollController` and `ReportsController`.
- **Potential Impact:** CI lint failure if full `pint --test` is enabled in GitHub Actions; minor CSV formula risk in legacy attendance CSV export.
- **Recommended Resolution:** Apply the `$sanitizeCell` closure to `AttendanceController::exportPayrollCsv()` and run `./vendor/bin/pint` in a dedicated formatting-only commit when approved.

---

## 12. Recommended Fixes (Documented Only — Not Implemented)

Before approving database migrations and Release 1 production rollout, execute the following targeted remediation pass (after explicit user authorization):

### 12.1 Pre-Migration Schema & Migration Fixes (Apply Before Running Migrations)
1. **Include Prerequisite Migrations (`HIGH-01`):** Formally review and include `2026_11_09_134500_add_two_factor_last_step_to_users_table.php` and `2026_11_10_150000_add_enterprise_access_management_columns.php` ahead of `2026_11_15_100001..5`.
2. **Clean Up Migration 5 (`MED-01`):** Remove the 4 duplicate composite indexes (`idx_act_logs_comp_site_created`, `idx_daily_att_comp_site_date`, `idx_daily_att_comp_worker_date`, `idx_tasks_comp_site_status`) that already exist in `nsd_crm_2`, keeping only `idx_act_logs_comp_action_created` and `idx_po_comp_site_status`, and replace `catch (\Throwable)` with `Schema::hasIndex(...)`.
3. **Harden Migrations 1–3 (`MED-02`, `MED-03`, `LOW-01`):**
   - Align `vendors.phone` (`VARCHAR(30)`) or widen `purchase_orders.supplier_phone` to `VARCHAR(50)`.
   - Add `Schema::hasTable('inventory_transactions')` and `Schema::hasIndex(...)` guards in Migration 2, plus separate `Schema::hasIndex(...)` guards around unique/index creation in Migrations 1 and 3.
   - Widen `ra_bills.igst_amount` in Migration 3 from `decimal(14, 2)` to `decimal(16, 2)` to match `cgst_amount` and `sgst_amount`.
   - Optionally split Migration 3 into 3 single-concern migrations (see Section 13.2).

### 12.2 Pre-Release Application & Security Fixes
1. **Fix Payroll Authorization, Site Scoping, & Role Matrix Fallback (`HIGH-02`):** Remove `'site_supervisor'` from `PayrollController::authorizePayrollView()`, block site-restricted users from viewing/exporting company-wide (`site_id = NULL`) payroll periods, tighten the sidebar Payroll visibility check in `layouts/app.blade.php`, and fix `User::canPerform()` so customized roles (`__customized__`) do not fall back to `$fallbackRoles`.
2. **Protect Linked User Accounts in `WorkforceController` (`HIGH-03`):** Require `users.edit` to link/modify `workers.user_id` and prohibit worker status changes by non-admins from deactivating `company_admin`/`senior_manager`/`super_admin` logins.
3. **Enforce Statutory Configuration & Maker-Checker Before Payroll Lock (`HIGH-04`, `MED-05`, `MED-06`):** Block `PayrollController::approveAndLock()` when `status !== 'calculated'`, when `statutory_config_state = 'unconfigured'` entries exist (unless an explicit `company_admin` override is confirmed and audited), or when a non-admin attempts to approve a period they calculated/adjusted; cap fixed `pt_monthly_amount`, `advance_deduction`, and `other_deduction` to remaining payable gross.
4. **Fix MySQL `VARCHAR` Validation Rules, Leave Request Gate, & `VaultStorage` Cloud Safety (`MED-02`, `MED-04`, `MED-07`):** Align `InventoryController` and `ProcurementController` max lengths with MySQL `VARCHAR` definitions, add permission + explicit-deny checks in `AttendanceController::storeLeaveRequest()` and Leave Type CRUD, parse uploaded WO PDFs from `$pdfFile->getRealPath()` before `VaultStorage` upload with orphan-file cleanup, check `false` returns in `VaultStorage`, and pass `$oldValues` in `CompanySettingsController`.
5. **Add Focused Regression Tests:** Add PHPUnit tests covering supervisor `403` on `/payroll`, site-restricted `403` on company-wide `/payroll/{id}/export-bank-csv`, customized role permission revocation on `canPerform()`, `site_manager` blocked from linking/deactivating `company_admin` via `workers.user_id`, and `422`/redirect block when locking an `unconfigured` payroll period.

---

## 13. Migration Execution Order (Audit H)

### 13.1 Prerequisite Pending Migrations (Currently Unapplied in `nsd_crm_2`)

Before executing the five `2026_11_15_*` migrations, these two earlier pending migrations in `database/migrations/` **must** be explicitly approved and executed first so `company_user.permission_overrides` and `company_user.restrict_to_assigned_sites` exist:

| Prerequisite Migration File | Purpose & DDL/DML Summary | Existing Data Impact (`nsd_crm_2`) | Recommended Step | Approval Required |
|---|---|---|---|---|
| `2026_11_09_134500_add_two_factor_last_step_to_users_table.php` | Adds nullable `users.two_factor_last_step BIGINT UNSIGNED NULL` (replay-protection for TOTP codes). | Safe (`7` users receive `NULL`). | **Step 0a** | **Yes** |
| `2026_11_10_150000_add_enterprise_access_management_columns.php` | Adds `users.must_change_password`, `company_user.permission_overrides` (`JSON NULL`), `company_user.restrict_to_assigned_sites`, `roles.is_system`, `roles.is_active` + DML `UPDATE roles SET is_system = 1` on standard slugs. | Safe additive columns + non-destructive `is_system = 1` flag on existing system roles. | **Step 0b** | **Yes** |

### 13.2 Five Release 1 Migrations Execution Matrix

| Migration file | Dependencies | MySQL compatibility | TiDB compatibility | Existing data compatibility | Rollback safety | Backfill required | Recommended order | Approval required |
|---|---|---|---|---|---|---|---|---|
| `2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php` | Existing `companies` & `purchase_orders` tables (`2026_10_28_000001`, `000004`) | **Compatible** *(align `vendors.phone` `VARCHAR(50)` vs `purchase_orders.supplier_phone` `VARCHAR(30)` per `MED-02`)* | **Conditionally Compatible** *(requires TiDB v6.6+ for FK enforcement; implicit DDL commits)* | **Compatible** (`0` rows in `purchase_orders`; `vendor_id` nullable) | **Safe** (drops `vendor_id` FK/index/col, then drops `vendors` if no post-migration data needed) | Optional (only if linking future/historical POs to `vendors`) | **1** (after Steps 0a & 0b) | **Yes (Explicit)** |
| `2026_11_15_100002_release1_inventory_movements_and_adjustments.php` | Existing `companies`, `warehouses`, `materials`, `users`, `work_orders`, `inventory_transactions` | **Compatible** *(add `hasTable` / `hasIndex` guards per `MED-03`)* | **Conditionally Compatible** *(requires TiDB v6.6+ for FKs; implicit DDL commits)* | **Compatible** (`0` rows in `inventory_transactions`; `type` already `VARCHAR(30)`) | **Safe** (drops `stock_adjustments` before `inventory_transactions` columns) | **No** | **2** | **Yes (Explicit)** |
| `2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php` | Existing `worker_documents`, `project_documents`, `measurement_entries`, `bill_exports`, `work_orders`, `ra_bills`, `workers`, `users` | **Compatible** *(widen `igst_amount` to `DECIMAL(16,2)` per `LOW-01`)* | **Conditionally Compatible** *(multi-column `ALTER` requires TiDB v6.2+; FKs require v6.6+; 7 tables in one non-transactional file)* | **Compatible** (`2` `bill_exports` & `1` `measurement_entries` default to `'local'`; `3` `work_orders` & `1` `ra_bills` default to `'cgst_sgst'`/`0.00`; `21` `workers` get `user_id = NULL`) | **Safe before new Vault uploads / IGST bills** *(dropping `storage_disk` after writing `'vault'` files would lose disk pointer)* | **No** (`DEFAULT 'local'` & `'cgst_sgst'` match all existing rows) | **3** *(recommend splitting into `3a`, `3b`, `3c` below)* | **Yes (Explicit)** |
| `2026_11_15_100004_release1_individual_statutory_payroll.php` | Existing `workers`, `users`, `payroll_entries` (`2026_10_28_000002`, `2026_11_01_000001`) | **Compatible** | **Conditionally Compatible** *(16-column `DROP` in `down()` requires TiDB v6.2+ Multi-Schema Change; FK requires v6.6+)* | **Compatible** (`21` workers default safely to `'unconfigured'` / `NULL`; `0` rows in `payroll_entries`) | **Safe** prior to configuring workers / running payroll | **Yes — Operational HR Configuration** (configure PF/ESIC/PT for `21` existing workers before first payroll lock) | **4** | **Yes (Explicit)** |
| `2026_11_15_100005_release1_scalability_composite_indexes.php` | Existing `activity_logs`, `daily_attendances`, `tasks`, `purchase_orders` | **Needs Cleanup First (`MED-01`)** *(4 of 6 indexes duplicate existing `nsd_crm_2` indexes; remove duplicates & replace `catch(Throwable)`)* | **Needs Cleanup First (`MED-01`)** *(same 4 duplicate indexes)* | **Compatible** (read-only index build; remove 4 duplicates to avoid unnecessary B-tree overhead) | **Safe** (`dropIndex` only) | **No** | **5** *(after removing 4 duplicate indexes)* | **Yes (Explicit)** |

### 13.3 Should Any Migration Be Split Before Execution?

- **Yes — Recommended Split for Migration 3 (`2026_11_15_100003`):**
  Because none of the `2026_11_15_*` migrations have been executed yet on any environment, splitting `2026_11_15_100003` into three small, single-domain migrations eliminates cross-module rollback coupling under MySQL/TiDB's implicit DDL commit behavior:
  - `100003a`: Add `storage_disk` to `worker_documents`, `project_documents`, `measurement_entries`, and `bill_exports` (plus `companies.signature_disk` if adopted per `MED-07`).
  - `100003b`: Add `tax_mode`, `igst_rate`, and `igst_amount` (`DECIMAL(16,2)`) to `work_orders` and `ra_bills`.
  - `100003c`: Add `user_id` FK and `workers_company_user_unique` index to `workers` (with separate `Schema::hasColumn` and `Schema::hasIndex` guards).
- Migrations 1, 2, 4, and 5 are already domain-cohesive and do not need splitting once the minor guards/duplicate-index fixes are applied.

---

## 14. Backfill Requirements

1. **Mandatory Pre-Migration Backup:**
   - Capture a complete `mysqldump --single-transaction --routines --triggers nsd_crm_2 > backup_pre_release1.sql` snapshot prior to running Step 0a.
2. **Prerequisite Migration Backfill (`Step 0b` — Automatic):**
   - `2026_11_10_150000` automatically sets `roles.is_system = 1` on existing system role slugs (`super_admin`, `company_admin`, `senior_manager`, `site_manager`, `site_supervisor`, `supervisor`, `warehouse_manager`, `office_staff`, `worker`). No manual SQL needed.
3. **Document Storage Disk Backfill (`Migration 3` — Automatic):**
   - `nsd_crm_2` currently has `2` rows in `bill_exports` (`companies/1/work_orders/1/exports/...` on disk `'local'`), `1` row in `measurement_entries` (`photo_path = NULL`), `0` rows in `worker_documents`, and `0` rows in `project_documents`. Migration 3's `DEFAULT 'local'` accurately labels all existing records with **zero** manual SQL backfill required. (Physical file relocation from `storage/app` to `storage/app/private` is optional because `VaultStorage` transparently falls back to `'local'`.)
4. **Work Orders & RA Bills GST Mode Backfill (`Migration 3` — Automatic):**
   - Existing `3` `work_orders` and `1` `ra_bills` in `nsd_crm_2` use intra-state `CGST + SGST` (`9% + 9%`). Migration 3's `DEFAULT 'cgst_sgst'` and `0.00` IGST defaults accurately preserve existing invoice totals with **zero** manual backfill.
5. **Individual Statutory Payroll Configuration (`Migration 4` — Mandatory Post-Migration Operational Step Before First Payroll Lock):**
   - All **21 existing workers** in `nsd_crm_2` will transition to `pf_status = 'unconfigured'`, `esic_status = 'unconfigured'`, `pt_status = 'unconfigured'`. **No SQL backfill should guess statutory rates.** Instead, Company Admin / HR must configure each active worker's individual statutory applicability (`applicable` with exact rates/ceilings, or `exempt`) in the Workforce Edit screen before approving and locking any payroll period.

---

## 15. Explicit Migration Readiness Verdict

### Verdict Assessment

- **Schema & Existing Data Safety:** All 5 Release 1 migrations are additive and non-destructive toward `nsd_crm_2` data, **provided** the 2 unapplied prerequisite migrations (`HIGH-01`) are accounted for and the 4 duplicate composite indexes in Migration 5 (`MED-01`) + `igst_amount` precision (`LOW-01`) + `VARCHAR` validation mismatches (`MED-02`) are cleaned up before execution.
- **Application & Security Gate:** Four `HIGH` severity findings (`HIGH-01` through `HIGH-04`) — specifically unapplied prerequisite migrations, payroll supervisor/site-scope/role-matrix authorization bypasses, non-admin worker-user deactivation lockout of admin accounts, and unblocked permanent locking of `unconfigured` ₹0 statutory payroll periods — **must be fixed and verified by regression tests** before migrations are approved and executed.

### Final Audit Verdict

**READY AFTER REQUIRED FIXES**

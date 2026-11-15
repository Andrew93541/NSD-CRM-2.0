# NSD CRM 2.0 — Release 1 Audit Remediation & Reverification Report

**Date:** 2026-11-15  
**Source Audit:** [RELEASE_1_PRE_MIGRATION_AUDIT.md](file:///Users/andrew/Developer/NSD-CRM-2.0/docs/RELEASE_1_PRE_MIGRATION_AUDIT.md)  
**Environment:** Local Workspace (`/Users/andrew/Developer/NSD-CRM-2.0`) — **Zero Migrations Executed / Protected `nsd_crm_2` Untouched**  
**Remediation Verdict:** **READY FOR FINAL MIGRATION REVIEW.**

---

## 1. Executive Summary

All **16** technical findings (`0 Critical`, `4 High`, `7 Medium`, `5 Low`) identified in `docs/RELEASE_1_PRE_MIGRATION_AUDIT.md` have been remediated in application code, Blade templates, pending migration files, tests, and Release 1 documentation without executing any database migration or touching the protected local `nsd_crm_2` MySQL database.

| Metric | Pre-Remediation Audit Baseline | Post-Remediation Verification |
|---|---|---|
| **Critical Findings** | `0` | **`0`** |
| **High Findings** | `4` (`HIGH-01`..`HIGH-04`) | **`0` Remaining (`4` Resolved)** |
| **Medium Findings** | `7` (`MED-01`..`MED-07`) | **`0` Remaining (`7` Resolved)** |
| **Low Findings** | `5` (`LOW-01`..`LOW-05`) | **`0` Remaining (`5` Resolved)** |
| **Automated PHPUnit Suite (SQLite `:memory:`)** | `208` passed (`2,407` assertions) | **`218` passed, `0` failed (`2,552` assertions)** |
| **Laravel Pint (`--dirty --test`)** | Passed | **Passed (`exit 0`)** |
| **Route Registration (`php artisan route:list`)** | `232` routes | **`232` routes (`exit 0`)** |
| **Vite 8 Production Build (`npm run build`)** | Passed | **Passed (`191ms`, `exit 0`)** |
| **Protected `nsd_crm_2` Database** | `22` applied migrations, `7` pending | **`22` applied migrations, `7` pending (100% untouched)** |

---

## 2. Complete Remediation Matrix (Items 1–6: All 16 Findings, Severity, Exact Files/Methods, Fixes & Tests)

### 2.1 High Severity Findings (`4 / 4 Resolved`)

| ID | Original Severity & Summary | Exact Files & Methods Changed | Fix Implemented | Tests Added (`Release1AuditRemediationTest`) | Result |
|---|---|---|---|---|---|
| **`HIGH-01`** | **High** — Two unapplied prerequisite migrations (`2026_11_09_134500`, `2026_11_10_150000`) omitted from Release 1 migration plan (`company_user.permission_overrides` & `restrict_to_assigned_sites` missing in `nsd_crm_2`). | - `database/migrations/2026_11_09_134500_add_two_factor_last_step_to_users_table.php` (`up`, `down`)<br>- `database/migrations/2026_11_10_150000_add_enterprise_access_management_columns.php` (`up`, `down`)<br>- `docs/RELEASE_1_IMPLEMENTATION_GUIDE.md` (Section 5) | Added `Schema::hasTable(...)` and defensive `after('two_factor_last_step')` / `after('two_factor_verified_at')` column-anchor guards to both prerequisite migrations and formally documented all **7** pending migrations in strict chronological execution order in `RELEASE_1_IMPLEMENTATION_GUIDE.md`. | `test_high01_med01_med03_low01_low02_schema_indexes_and_idempotency_guards` | **PASS** |
| **`HIGH-02`** | **High** — Payroll & Bank CSV authorization bypass (`site_supervisor` allowed, `site_id = NULL` company-wide payroll exposed to site-restricted users, sidebar leak, and `User::canPerform()` overriding customized roles via `$fallbackRoles`). | - `app/Models/User.php` (`canPerform`)<br>- `app/Http/Controllers/PayrollController.php` (`index`, `store`, `show`, `recalculate`, `adjustEntry`, `approveAndLock`, `payslip`, `payslipPdf`, `exportBankPayoutCsv`, `authorizePayrollView`, `hasUnrestrictedPayrollSiteAccess`, `authorizePayrollPeriodScope`)<br>- `resources/views/layouts/app.blade.php` (desktop sidebar & mobile drawer) | 1. Removed `'site_supervisor'` from `authorizePayrollView()` and updated both desktop & mobile navigation links.<br>2. Added `hasUnrestrictedPayrollSiteAccess()` and `authorizePayrollPeriodScope()` blocking site-restricted users from viewing, adjusting, or exporting unassigned-site AND company-wide (`site_id = NULL`) payroll periods, payslips, PDFs, and bank CSVs, while preserving full company-wide visibility for unrestricted `company_admin`/`office_staff`/`senior_manager`.<br>3. Scoped `exportBankPayoutCsv` entries by `company_id` and `accessibleSiteIds()`.<br>4. Updated `User::canPerform()` to accept `?int $companyId = null` and strictly enforce the role permission matrix (never falling back to `$fallbackRoles`) whenever a role is inactive, custom (`!isSystemRole()`), or customized (`__customized__`). | `test_high02_payroll_and_bank_csv_authorization_site_scope_and_customized_roles` | **PASS** |
| **`HIGH-03`** | **High** — Privilege escalation & admin lockout via `workers.user_id` linking and non-atomic worker deactivation in `WorkforceController` / `AuthController`. | - `app/Http/Controllers/WorkforceController.php` (`store`, `update`, `canManageWorkerUserLinks`, `syncLinkedUserOnWorkerDeactivation`, `linkableCompanyUsers`)<br>- `app/Http/Controllers/AuthController.php` (`toggleUserActive`) | 1. Gated `workers.user_id` linking/relinking and `$linkableUsers` behind `canManageWorkerUserLinks()` (`company_admin`/`super_admin` or `users.edit`/`users.manage` without explicit deny).<br>2. Moved linked-user deactivation inside the same `DB::transaction` as worker save (`store` and `update`) with `lockForUpdate()`, requiring `company_admin`/`super_admin` to deactivate a linked admin and `users.deactivate` for non-admin linked users.<br>3. Enforced Last Active Company Admin lockout protection and self-deactivation protection (`ValidationException` rolling back `workers.status`), multi-company membership safety, session deletion, and `worker.linked_user_deactivated` audit logging.<br>4. Wrapped `AuthController::toggleUserActive()` in `DB::transaction` with multi-company membership safety. | `test_high03_worker_user_link_permission_admin_lockout_and_atomic_deactivation_rollback` | **PASS** |
| **`HIGH-04`** | **High** — `PayrollController::approveAndLock()` allowed locking payroll periods while workers still had `statutory_config_state = 'unconfigured'`. | - `app/Services/Workforce/PayrollCalculationService.php` (`approveAndLockPeriod`)<br>- `app/Http/Controllers/PayrollController.php` (`approveAndLock`)<br>- `resources/views/payroll/show.blade.php`<br>- `tests/Feature/Release1Batch1CriticalFixesTest.php`, `PayrollWorkOrderBoqAndKioskTest.php`, `Release1Batch5StatutoryPayrollTest.php` | Enforced a hard pre-lock gate inside `PayrollCalculationService::approveAndLockPeriod()` (with `lockForUpdate()`): requires `status === 'calculated'` and throws `ValidationException` on `'statutory'` if any entry has `statutory_config_state = 'unconfigured'` (or `null`), naming affected workers and codes. Updated `payroll/show.blade.php` banner to list affected unconfigured workers and disable the lock button until configured (`applicable` or `not_applicable`) and recalculated. | `test_high04_unconfigured_statutory_blocks_locking_until_configured_and_preserves_historical_snapshot` | **PASS** |

---

### 2.2 Medium Severity Findings (`7 / 7 Resolved`)

| ID | Original Severity & Summary | Exact Files & Methods Changed | Fix Implemented | Tests Added (`Release1AuditRemediationTest`) | Result |
|---|---|---|---|---|---|
| **`MED-01`** | **Medium** — 4 redundant duplicate composite indexes & broken `catch (Throwable)` namespace in Migration `2026_11_15_100005`. | - `database/migrations/2026_11_15_100005_release1_scalability_composite_indexes.php` (`up`, `down`) | Removed the 4 duplicate composite indexes already present in `nsd_crm_2` (`idx_act_logs_comp_site_created`, `idx_daily_att_comp_site_date`, `idx_daily_att_comp_worker_date`, `idx_tasks_comp_site_status`), kept the 2 net-new composite indexes (`idx_act_logs_comp_action_created`, `idx_po_comp_site_status`), and replaced `try/catch` error swallowing with `Schema::hasIndex($table, $columns)` and `Schema::hasIndex($table, $indexName)`. | `test_high01_med01_med03_low01_low02_schema_indexes_and_idempotency_guards` | **PASS** |
| **`MED-02`** | **Medium** — MySQL `VARCHAR` length mismatches (`InventoryController` codes/UOM and `ProcurementController` / `vendors.phone` `max:50` vs `purchase_orders.supplier_phone` `VARCHAR(30)`). | - `app/Http/Controllers/InventoryController.php` (`storeWarehouse`, `updateWarehouse`, `storeCategory`, `updateCategory`, `storeMaterial`, `updateMaterial`)<br>- `app/Http/Controllers/ProcurementController.php` (`storeOrder`, `storeVendor`, `updateVendor`)<br>- `database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php` | Tightened validation rules to match exact MySQL `STRICT_TRANS_TABLES` column widths: `max:30` on warehouse/category/material `code`, `max:20` on `materials.unit_of_measure`, `max:30` on `supplier_phone` and `vendors.phone` (plus `string('phone', 30)` in Migration `100001` and `mb_substr` safety in `storeOrder`). | `test_med02_inventory_and_procurement_max_length_validation_matches_mysql_schema` | **PASS** |
| **`MED-03`** | **Medium** — Multi-step `ALTER TABLE` idempotency gaps under MySQL/TiDB implicit DDL commits and multi-column `down()` drops. | - `database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php`<br>- `database/migrations/2026_11_15_100002_release1_inventory_movements_and_adjustments.php`<br>- `database/migrations/2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php`<br>- `database/migrations/2026_11_15_100004_release1_individual_statutory_payroll.php` | Separated column creation from index/unique creation with independent `Schema::hasColumn` and `Schema::hasIndex` guards across Migrations 1–3, added `Schema::hasTable('inventory_transactions')` / `Schema::hasTable('inventory_transaction_items')` in Migration 2, and converted multi-column `dropColumn(...)` calls in Migrations 3 & 4 `down()` into single-column `Schema::table` operations for TiDB rollback compatibility. | `test_high01_med01_med03_low01_low02_schema_indexes_and_idempotency_guards` | **PASS** |
| **`MED-04`** | **Medium** — Missing permission gate on `AttendanceController::storeLeaveRequest()` and missing explicit-deny checks on Leave Type CRUD. | - `app/Http/Controllers/AttendanceController.php` (`storeLeaveType`, `updateLeaveType`, `destroyLeaveType`, `storeLeaveRequest`) | Added `attendance.manage` permission + `isExplicitlyDenied('attendance.manage')` enforcement on `storeLeaveType`, `updateLeaveType`, and `destroyLeaveType`; added `attendance.mark` / `attendance.record` permission + explicit-deny enforcement and site-restriction guard for unassigned workers on `storeLeaveRequest`. | `test_med04_leave_type_and_leave_request_mutations_enforce_explicit_rbac_and_denies` | **PASS** |
| **`MED-05`** | **Medium** — Missing maker-checker separation of duties on `PayrollController::approveAndLock()`. | - `app/Http/Controllers/PayrollController.php` (`adjustEntry`, `approveAndLock`)<br>- `app/Services/Workforce/PayrollCalculationService.php` (`approveAndLockPeriod`)<br>- `resources/views/payroll/show.blade.php` | Updated `adjustEntry()` to record the modifier in `payroll_periods.calculated_by` and emit an `AuditLog` (`payroll.entry.adjusted`). Enforced maker-checker separation (`calculated_by !== $actor->id`) in both `PayrollController::approveAndLock()` (`403`) and `PayrollCalculationService::approveAndLockPeriod()` (`ValidationException` on `maker_checker`), and disabled self-approval in `payroll/show.blade.php`. | `test_med05_payroll_approval_enforces_independent_maker_checker` | **PASS** |
| **`MED-06`** | **Medium** — Fixed `pt_monthly_amount` or manual `other_deduction` exceeding low `gross_pay` broke period ledger reconciliation (`total_gross - total_deductions != total_net`). | - `app/Services/Workforce/PayrollCalculationService.php` (`evaluateStatutoryDeductions`, `calculateWorkerMetrics`)<br>- `app/Http/Controllers/PayrollController.php` (`adjustEntry`) | Capped `pt_amount` to remaining post-PF/ESIC `gross_pay` (recording `reason = 'capped_to_available_gross'` and `unrecovered_shortfall` in `statutory_snapshot['pt']`), capped `advance_deduction` and `other_deduction` sequentially against remaining payable gross so `gross_pay - total_deductions === net_pay` always holds, and rejected manual `other_deduction` values exceeding remaining payable gross in `adjustEntry()`. | `test_med06_low_gross_pt_capping_prevents_negative_net_pay_and_excess_manual_deduction` | **PASS** |
| **`MED-07`** | **Medium** — Local-only `->path()` call in `WorkOrderUploadService`, unchecked `false` write returns in `VaultStorage`, and missing `signature_disk` / `old_values` audit diff in `CompanySettingsController`. | - `app/Services/Storage/VaultStorage.php` (`storeUploaded`, `put`, `exists`, `delete`, `download`, `response`)<br>- `app/Services/Operations/WorkOrderUploadService.php` (`uploadAndExtract`)<br>- `app/Http/Controllers/CompanySettingsController.php` (`update`, `downloadSignature`)<br>- `database/migrations/2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php` | 1. Added path-traversal / null-byte guards and `RuntimeException` throws on `false`/empty write returns in `VaultStorage`.<br>2. Updated `WorkOrderUploadService::uploadAndExtract()` to parse `$pdfFile->getRealPath()` before storage and clean up `$storedPath` via `VaultStorage::delete()` if DB creation fails.<br>3. Added `companies.signature_disk` in Migration `100003`, routed signatory uploads/downloads/deletes through `VaultStorage`, and passed `$oldValues` to `AuditLog::record('company.settings_updated', ...)`. | `test_med07_vault_storage_traversal_and_failure_guards_work_order_cleanup_and_company_signature_audit` | **PASS** |

---

### 2.3 Low Severity Findings (`5 / 5 Resolved`)

| ID | Original Severity & Summary | Exact Files & Methods Changed | Fix Implemented | Tests Added (`Release1AuditRemediationTest`) | Result |
|---|---|---|---|---|---|
| **`LOW-01`** | **Low** — `ra_bills.igst_amount` used `DECIMAL(14,2)` instead of `DECIMAL(16,2)` matching `cgst_amount` / `sgst_amount`. | - `database/migrations/2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php` (`up`) | Widened `ra_bills.igst_amount` to `decimal('igst_amount', 16, 2)->default(0)` to match `cgst_amount` and `sgst_amount`. | `test_high01_med01_med03_low01_low02_schema_indexes_and_idempotency_guards` | **PASS** |
| **`LOW-02`** | **Low** — Non-atomic `VEND-###` / `CLI-###` auto-code generation and missing DB composite unique constraint on `clients(company_id, code)`. | - `app/Http/Controllers/ProcurementController.php` (`storeVendor`, `nextAtomicVendorCode`)<br>- `app/Http/Controllers/ClientController.php` (`store`, `nextAtomicClientCode`)<br>- `database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php` | Wrapped blank-code `VEND-###` and `CLI-###` generation inside `DB::transaction` locking `companies` and `worker_sequences` (`vendor` / `client` sequence rows) with collision-skipping loop, and added `clients_company_code_unique` (`UNIQUE(company_id, code)`) in Migration `100001` (verified `0` duplicate client codes exist in `nsd_crm_2`). | `test_low02_low03_low05_atomic_vendor_client_codes_switch_company_redirect_and_payroll_csv_sanitization` | **PASS** |
| **`LOW-03`** | **Low** — Unbounded in-memory collection fetch in `ApprovalCenterController::index()` and detail-page `404` on `AuthController::switchCompany()`. | - `app/Http/Controllers/ApprovalCenterController.php` (`index`)<br>- `app/Http/Controllers/AuthController.php` (`switchCompany`) | Added SQL maker-checker pre-filters (`requested_by != $user->id` / `performed_by != $user->id` where applicable) and `->limit(200)` caps per module query in `ApprovalCenterController::index()`; updated `AuthController::switchCompany()` to sync both `current_company_id` and `active_company_id` session keys and redirect cleanly to `route('dashboard')`. | `test_low02_low03_low05_atomic_vendor_client_codes_switch_company_redirect_and_payroll_csv_sanitization` | **PASS** |
| **`LOW-04`** | **Low** — Transposed `BL-*` IDs and inaccurate claims in `docs/RELEASE_1_IMPLEMENTATION_GUIDE.md`. | - `docs/RELEASE_1_IMPLEMENTATION_GUIDE.md` | Reconciled `BL-01`..`BL-15` numbering and descriptions with `docs/DEVELOPMENT_ROADMAP.md`, corrected `RTN-*` to `RET-*`, corrected `Company` settings columns, removed nonexistent `forgot-password` claim, documented statutory lock gate + maker-checker rules, and documented all 7 pending migrations. | Verified via documentation cross-check & `route:list` | **PASS** |
| **`LOW-05`** | **Low** — Legacy `AttendanceController::exportPayrollCsv()` lacked CSV formula-injection sanitization; Pint formatting on modified files. | - `app/Http/Controllers/AttendanceController.php` (`exportPayrollCsv`, `sanitizePayrollCsvCell`)<br>- All dirty PHP files formatted via `./vendor/bin/pint --dirty` | Added `sanitizePayrollCsvCell()` prefixing `=`, `+`, `-`, `@`, `\t`, and `\r` with `'` on `AttendanceController::exportPayrollCsv()`, and ran `./vendor/bin/pint --dirty` (`exit 0`). | `test_low02_low03_low05_atomic_vendor_client_codes_switch_company_redirect_and_payroll_csv_sanitization` | **PASS** |

---

## 3. Findings Not Fixed and Why (Item 7)

All **16** technical findings (`HIGH-01`..`HIGH-04`, `MED-01`..`MED-07`, `LOW-01`..`LOW-05`) are **resolved**. Two deliberate scope-preservation notes apply in accordance with the task's non-negotiable restrictions:

1. **In-Place Idempotent Hardening vs Deleting/Splitting Uncommitted Migration Files (`MED-03` / `LOW-01`):**
   - Per the non-negotiable instruction *"Do not remove existing uncommitted files"* and *"The five Release 1 migrations remain unexecuted"*, `2026_11_15_100001` and `2026_11_15_100003` were retained at their existing filenames rather than deleted/renamed. All DDL idempotency and rollback risks in `MED-03` were resolved in-place via granular per-column `Schema::hasColumn`, per-index `Schema::hasIndex`, and single-column `down()` drops.
2. **Pre-Existing Formatting in Untouched Legacy Files (`LOW-05`):**
   - `./vendor/bin/pint --dirty --test` passes with `0` issues across all Release 1 and remediated files. Unmodified historical Phase 1–4 files were intentionally not mass-reformatted so `git diff` remains focused strictly on Release 1 functional changes; a standalone `pint` formatting commit can be run post-release if desired.

---

## 4. Migration Files Modified & Dependency Execution Order (Items 8–9)

### 4.1 Migration Files Modified (`7` Modified, `0` Executed)

1. [2026_11_09_134500_add_two_factor_last_step_to_users_table.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_09_134500_add_two_factor_last_step_to_users_table.php)
2. [2026_11_10_150000_add_enterprise_access_management_columns.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_10_150000_add_enterprise_access_management_columns.php)
3. [2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php)
4. [2026_11_15_100002_release1_inventory_movements_and_adjustments.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100002_release1_inventory_movements_and_adjustments.php)
5. [2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php)
6. [2026_11_15_100004_release1_individual_statutory_payroll.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100004_release1_individual_statutory_payroll.php)
7. [2026_11_15_100005_release1_scalability_composite_indexes.php](file:///Users/andrew/Developer/NSD-CRM-2.0/database/migrations/2026_11_15_100005_release1_scalability_composite_indexes.php)

### 4.2 Authoritative Pending Migration Dependency Order (When Approved Later)

| Step | Migration File | Prerequisite Dependencies | Additive Schema Changes |
|---|---|---|---|
| **1** | `2026_11_09_134500_add_two_factor_last_step_to_users_table` | `users` (`2026_11_01_000006`) | Adds `users.two_factor_last_step` (`BIGINT UNSIGNED NULL`) for RFC 6238 TOTP replay protection. |
| **2** | `2026_11_10_150000_add_enterprise_access_management_columns` | Step 1 (`users`, `company_user`, `roles`) | Adds `users` lifecycle/audit columns (`must_change_password`, `restrict_to_assigned_sites`, `last_login_at`, etc.), `company_user.restrict_to_assigned_sites`, `company_user.permission_overrides`, and `roles.is_system` / `roles.is_active`. |
| **3** | `2026_11_15_100001_release1_master_data_vendors_and_leave_quotas` | `companies`, `users`, `purchase_orders`, `clients` | Creates `vendors` (`phone VARCHAR(30)`, `vendors_company_code_unique`), adds `purchase_orders.vendor_id` (`NULLOnDelete`), and adds `clients_company_code_unique` (`UNIQUE(company_id, code)`). |
| **4** | `2026_11_15_100002_release1_inventory_movements_and_adjustments` | `inventory_transactions`, `inventory_transaction_items`, `warehouses`, `users` | Adds `destination_warehouse_id`, `approval_status`, `approved_by`, `approved_at`, `rejection_reason` + composite index `inv_tx_comp_type_date_idx` on `inventory_transactions`, and `unit_cost` on `inventory_transaction_items`. |
| **5** | `2026_11_15_100003_release1_vault_storage_igst_and_worker_links` | `companies`, `worker_documents`, `project_documents`, `measurement_entries`, `work_order_attachments`, `work_order_uploads`, `bill_exports`, `work_orders`, `ra_bills`, `workers` | Adds `companies.signature_disk`, `storage_disk` across 6 document tables (`default('local')`), `tax_mode`/`igst_rate` on `work_orders`, `tax_mode`/`igst_rate`/`igst_amount DECIMAL(16,2)` on `ra_bills`, and `workers.user_id` + `workers_company_user_unique`. |
| **6** | `2026_11_15_100004_release1_individual_statutory_payroll` | `workers`, `payroll_entries`, `users` | Adds 14 individual PF/ESIC/PT configuration & audit columns (`default('unconfigured')`) on `workers` and 7 statutory snapshot/amount columns on `payroll_entries`. |
| **7** | `2026_11_15_100005_release1_scalability_composite_indexes` | `activity_logs`, `purchase_orders` | Adds net-new composite indexes `idx_act_logs_comp_action_created` and `idx_po_comp_site_status` with `Schema::hasIndex` guards. |

---

## 5. MySQL & TiDB Compatibility Status (Items 10–11)

- **MySQL 8.0 / 8.4 Compatibility (`COMPATIBLE`):**
  - All 7 pending migrations use strictly additive `CREATE TABLE` and `ALTER TABLE ... ADD COLUMN / ADD INDEX / ADD UNIQUE` operations with zero `CHANGE COLUMN`, `DROP TABLE`, or data-truncating statements.
  - All controller validation rules (`InventoryController`, `ProcurementController`) now match exact MySQL `VARCHAR(20)` and `VARCHAR(30)` column definitions under `STRICT_TRANS_TABLES`.
  - Pre-verified `nsd_crm_2` live `clients` table has **0** duplicate `(company_id, code)` pairs prior to `clients_company_code_unique`.
  - Composite unique indexes `(company_id, user_id)` on `workers` allow multiple `(company_id, NULL)` rows per SQL-92 / MySQL InnoDB semantics for unlinked field workers.
- **TiDB Serverless / TiDB v6.x–v8.x Compatibility (`COMPATIBLE`):**
  - Every multi-step `ALTER TABLE` separates column creation from index/unique creation with independent `Schema::hasColumn(...)` and `Schema::hasIndex(...)` checks so implicit DDL commits never leave an unindexed retry state.
  - Multi-column `dropColumn([...])` calls in `2026_11_15_100003` and `2026_11_15_100004` `down()` methods were converted to single-column `Schema::table` drops so rollbacks work even on TiDB versions or configurations that restrict multi-schema change `DROP COLUMN`.
  - No `ENUM` column alterations, stored procedures, triggers, or unsupported spatial/fulltext indexes are used.

---

## 6. Statutory Payroll Professional Validation Requirements (Item 12)

While all **software engineering** defects in the statutory payroll module (`HIGH-02`, `HIGH-04`, `MED-05`, `MED-06`) have been resolved—enforcing zero hardcoded percentages, mandatory explicit per-worker configuration (`applicable` or `not_applicable`) before locking, PT/deduction gross-pay capping, immutable JSON `statutory_snapshot` persistence, and independent maker-checker approval—Indian statutory payroll compliance requires **external sign-off by a qualified Chartered Accountant (CA) / Labour Law Consultant** before using CRM outputs to file government challans:

1. **`SR-01` — EPF Wage Base Definition (*EPF & MP Act, 1952*):** Confirm whether company policy and labour counsel require PF to be calculated on `base_pay` (excluding `overtime_pay` and ad-hoc `bonus_amount`) rather than total `gross_pay`.
2. **`SR-02` — Employer EPF Bifurcation (EPF 3.67% vs EPS 8.33% & EDLI/Admin Charges):** Aggregate `pf_employer_amount` suffices for internal cost accounting and payslips, whereas future EPFO ECR text-file export will require EPS (`8.33%` up to ₹15,000 ceiling, age `< 58`) vs EPF split.
3. **`SR-03` — ESIC 6-Month Contribution Period Continuity (*ESI Act, 1948*):** Verify operational handling for covered employees whose monthly gross temporarily crosses ₹21,000 mid-contribution-period (1 Apr–30 Sep / 1 Oct–31 Mar) due to overtime.
4. **`SR-04` — State Professional Tax (PT) Slabs, Gender Exemptions & February Adjustment:** Because `pt_monthly_amount` is configured per worker as a monthly rupee amount (now safely capped at available gross pay), HR/Finance must verify state-specific slabs (e.g., Maharashtra ₹300 February top-up and ₹25,000/month women's exemption; half-yearly PT states).
5. **`SR-05` — LWF, Section 194C Subcontractor TDS & Onboarding SOP:** Establish an HR onboarding checklist specifying when direct employees vs subcontractor muster workers must be marked `applicable` vs `not_applicable` before first payroll lock.

---

## 7. Protected Database Confirmation & Verification Evidence (Item 13)

Read-only verification against local MySQL `nsd_crm_2` before and after remediation confirms **zero schema changes, zero executed migrations, and zero record mutations**:

| Table / Metric | Pre-Remediation Count | Post-Remediation Count | Status |
|---|---|---|---|
| `companies` | `1` | `1` | **UNCHANGED** |
| `users` | `7` | `7` | **UNCHANGED** |
| `sites` | `4` | `4` | **UNCHANGED** |
| `projects` | `3` | `3` | **UNCHANGED** |
| `workers` | `21` | `21` | **UNCHANGED** |
| `work_orders` | `3` | `3` | **UNCHANGED** |
| `migrations` (Applied) | `22` | `22` | **UNCHANGED (`7` Pending)** |

### Verification Commands Executed

- `./vendor/bin/pint --dirty && ./vendor/bin/pint --dirty --test` -> **PASS (`exit 0`)**
- `DB_CONNECTION=sqlite DB_DATABASE=:memory: php artisan test --compact` -> **PASS (`218` tests, `2,552` assertions, `0` failures)**
- `php artisan route:list --json` -> **PASS (`232` routes, `exit 0`)**
- `npm run build` -> **PASS (Vite `v8.3.5` built in `191ms`, `exit 0`)**
- `php artisan migrate:status --no-ansi` -> **PASS (`22` Ran, `7` Pending)**

---

## 8. Remaining Operational Risks Before Live Migration (Item 14)

1. **First-Time Statutory Setup After Migration (`Operational — Expected by Design`):** Immediately after executing Migration `2026_11_15_100004`, existing active workers (`21` in `nsd_crm_2`) will default to `pf_status = 'unconfigured'`, `esic_status = 'unconfigured'`, and `pt_status = 'unconfigured'`. Because `HIGH-04` now strictly blocks locking payroll periods containing `unconfigured` workers, HR / Company Admin must mark each active worker's PF, ESIC, and PT status as either `applicable` or `not_applicable` before locking the next payroll period.
2. **Two-Person Payroll Sign-Off (`Operational — Expected by Design`):** Because `MED-05` enforces maker-checker separation on `PayrollController::approveAndLock()`, a single user cannot both calculate/adjust and lock the same payroll period; organizations must have either a separate preparer (`office_staff` / `site_manager` / `senior_manager`) and approver (`company_admin` / second `senior_manager`), or two admin/manager accounts.
3. **Pre-Migration Backup Requirement:** Even though all 7 pending migrations are strictly additive and idempotent, take a full `mysqldump` backup of `nsd_crm_2` before executing `php artisan migrate` upon user approval.
4. **External Statutory Advisory (`Non-Blocking for Migration`):** Review `SR-01`..`SR-05` with a CA / payroll consultant prior to statutory return filing.

---

**Final Technical Readiness Verdict:**  
`READY FOR FINAL MIGRATION REVIEW.`

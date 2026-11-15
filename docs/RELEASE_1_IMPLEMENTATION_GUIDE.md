# NSD CRM 2.0 — Release 1 Implementation & Operations Guide

This guide documents the completed **Release 1** implementation (`BL-01` through `BL-15`, plus individual employee/worker statutory payroll configuration) for **NSD CRM 2.0**.

---

## 1. Release 1 Feature Summary (`BL-01` – `BL-15` + `STAT-PAY`)

| ID | Module / Capability | Summary |
|---|---|---|
| **BL-01** | Audit Log Tenant Isolation & Secret Redaction | `AuditLog::forCompany()` fails closed when tenant context is unset and recursively redacts sensitive keys (`password`, `pin`, `two_factor_secret`, `bank_account_no`, `kiosk_token`) before persistence. |
| **BL-02** | Dashboard Pending GRN & Permission Precedence | Dashboard pending Goods Receipt KPI matches `issued` and `partially_received` POs; explicit per-user `deny` overrides strictly supersede role grants across policies, controllers, and Blade views; `procurement.request` is **not** auto-granted to `office_staff`. |
| **BL-03** | Private Document Vault (`VaultStorage`) | Clean Laravel `Storage`-compatible disk abstraction (`config('filesystems.vault_disk')`, defaulting to local `storage/app/private`) with `storage_disk` persistence across all private document, signature, and export tables. Zero public URLs; cloud provider deferred. |
| **BL-04** | Company Profile, Tax/Bank Settings & Switcher | Editable organization profile (`name`, `gstin`, `pan`, `vendor_code`, `registered_address`, bank details, `invoice_prefix`, `invoice_rounding_mode`, private signatory stamp `signature_path` / `signature_disk`) under Administration, plus multi-company switcher redirecting cleanly to `dashboard`. |
| **BL-05** | Client Master Management | Tenant-scoped Client Master CRUD (`clients.*`) with atomic `CLI-###` sequence generation, unique `(company_id, code)` index, GSTIN/PAN validation, active/inactive lifecycle, and safe archive guard when linked to Work Orders or RA Bills. |
| **BL-06** | Vendor & Subcontractor Masters + Dynamic Prefixes | Tenant-scoped Vendor Master CRUD (`procurement.vendors.*`) with atomic `VEND-###` sequence generation linked to Purchase Orders, Subcontractor Master CRUD (`workforce.subcontractors.*`), and dynamic per-company document numbering replacing hardcoded `NSD` prefixes. |
| **BL-07** | Warehouse & Material Catalog Management | Full CRUD for Central Warehouses/Site Yards (`inventory.warehouses.*`), Material Categories, and Materials (`inventory.materials.*`) with strict schema-aligned code/UOM lengths, HSN codes, minimum stock levels, and reference deletion guards. |
| **BL-08** | Inventory Stock Movements & Maker-Checker Adjustments | Atomic `Stock Issue` (`ISS-*` to site/work order), `Warehouse Transfer` (`TRF-*`), `Site Return` (`RET-*`), and maker-checker `Stock Adjustment` (`ADJ-*`, requiring independent Senior Manager/Admin confirmation; self-approval blocked). |
| **BL-09** | Procurement Lifecycle & Printable PO / GRN PDFs | Purchase Request rejection (with mandatory reason) and requester cancellation, plus printable Dompdf Purchase Order (`procurement.orders.pdf`) and Goods Receipt Note (`inventory.grn_pdf`) PDFs. |
| **BL-10** | Unified Approvals Center & Deduplicated Notifications | Multi-module `/approvals` workspace (Work Orders, Revisions, RA Bills, Purchase Requests, Stock Adjustments, Draft Measurements, Attendance Corrections, Leave Requests) with bounded queries + `NotificationDispatcher` deduplicated unread alerts. |
| **BL-11** | Operational Printouts & Sanitized Bank Payout CSV | Printable Worker QR ID Card PDF, Payroll Payslip PDF, standalone Measurement Book PDF, and formula-injection-safe Bank Payout Schedule & Attendance Payroll CSV exports (`payroll.export_payout`, `attendance.export_payroll`). |
| **BL-12** | Inter-State (`IGST`) Billing & Standalone RA Bills Register | Full `IGST` (`DECIMAL(16,2)`) vs `CGST + SGST` tax calculation across Work Orders, RA Bills, PDFs, and live-formula XLSX workbooks, plus standalone `/ra-bills` register under Finance. |
| **BL-13** | Holiday Calendar & Leave Type Configuration | Tenant/site Holiday Calendar CRUD (`attendance.holidays.*`) and Leave Type quota/paid-flag CRUD (`attendance.leave_types.*`) honoring explicit user permission overrides. |
| **BL-14** | Measurement Maker-Checker, Worker-Login Link & Milestones | Supervisor `draft` measurements require independent `verified` sign-off before RA Billing; unbilled edit/delete; Project Milestone lifecycle; Project Activity tab tenant/site scoping; authorized 1-to-1 `Worker <-> User` linking with atomic bidirectional deactivation sync and last-active Company Admin lockout protection. |
| **BL-15** | Pagination, Streaming Exports & Composite Indexes | Server-side pagination (`withQueryString()`) on Monthly Attendance Matrix, Tasks & Site Notes, Work Order/Inventory Reports, and Audit History, plus `lazyById` CSV streaming and non-duplicate composite scalability indexes. |
| **STAT-PAY** | Individual Statutory Payroll (`PF`, `ESIC`, `PT`) | Per-employee/worker statutory configuration (`unconfigured`, `applicable`, `not_applicable`), individual rates/ceilings/effective dates, PT gross-pay cap with shortfall audit snapshot, non-deductible employer PF/ESIC tracking, maker-checker payroll approval, and hard block against locking unconfigured workers. |

---

## 2. Role & Permission Rules

1. **Evaluation Precedence (`User::hasPermission` / `User::canPerform`):**
   - **Step 1 — Explicit User Deny (`permission_overrides[key] === 'deny'`):** Always returns `false`, even if the user's assigned role grants the permission or falls in a controller's default role list.
   - **Step 2 — Explicit User Allow (`permission_overrides[key] === 'allow'`):** Grants the permission for that user within their authorized site scope.
   - **Step 3 — Role Permission Grant:** Evaluates the user's active company role (`roles.permissions`).
2. **Office Staff Procurement Policy:**
   - Default `office_staff` permissions include `procurement.view` and `inventory.view`, **not** `procurement.request`.
   - Company Admins can enable purchase requisitions for specific Office Staff members via a custom role or per-user `allow` override on `procurement.request`.
3. **Site Restrictions:**
   - Users with `restrict_to_assigned_sites = true` (including Site Managers and Supervisors) are strictly limited to their assigned `user_site` records across listings, detail views, PDFs, CSV/XLSX exports, and `/approvals`.

---

## 3. Individual Statutory Payroll Rules (`PF`, `ESIC`, `PT`)

- **No Company-Wide Assumptions:** Statutory deductions are never assumed globally. Every worker/employee record stores individual `pf_status`, `esic_status`, and `pt_status` (`unconfigured` | `applicable` | `not_applicable`).
- **Explicit Unconfigured State & Hard Lock Gate:** Newly created workers start as `unconfigured`. Entries for unconfigured workers record `statutory_config_state = 'unconfigured'`, display affected worker names on the Payroll Period screen, and **strictly block `Approve & Lock`** until every worker in the period is explicitly configured as `applicable` or `not_applicable` and recalculated.
- **Maker-Checker Payroll Approval:** The user who prepared or last adjusted a payroll period (`calculated_by`) cannot approve and lock the same period; an independent authorized approver is required.
- **Calculation Rules (when `applicable` and `gross_pay > 0`):**
  - **PF:** Wage base is `min(gross_pay, pf_wage_ceiling)` when a positive ceiling is configured (otherwise `gross_pay`). Employee PF reduces `net_pay`; Employer PF is tracked separately and **never** deducted from `net_pay`.
  - **ESIC:** If a positive `esic_wage_ceiling` is configured and `gross_pay > esic_wage_ceiling`, both employee and employer ESIC evaluate to `0.00` (`reason = 'exceeds_wage_ceiling'`); otherwise calculated on `gross_pay`.
  - **PT:** Deducts the worker's configured `pt_monthly_amount`, capped to remaining gross pay after PF/ESIC (`reason = 'capped_to_available_gross'` with `unrecovered_shortfall` logged in `statutory_snapshot`) so net pay never goes negative.
  - **Effective Date:** If `statutory_effective_from` is after the payroll period's `end_date`, statutory deductions evaluate to `0.00` (`not_yet_effective`).
- **Historical Immutability:** Every calculated `PayrollEntry` persists a JSON `statutory_snapshot`. Once a `PayrollPeriod` is locked (`status = 'locked'`), recalculation and adjustments are blocked, and later edits to a worker's statutory profile affect **only** unlocked/future payroll periods.

---

## 4. Inventory Movement & Maker-Checker Adjustment Rules

- **Stock Issue (`ISS-*`):** Deducts available stock from a warehouse (`SELECT ... FOR UPDATE` row lock) and logs consumption against a target `Site` and optional `WorkOrder`. Rejects insufficient stock.
- **Warehouse Transfer (`TRF-*`):** Atomically decrements the source warehouse and increments the destination warehouse inside a single database transaction.
- **Site Return (`RET-*`):** Returns unused site material back into warehouse stock with mandatory provenance notes.
- **Stock Adjustment (`ADJ-*`):** Two-step maker-checker workflow:
  1. Authorized storekeeper/manager submits a positive or negative variance request (`status = 'pending_confirmation'`, stock unchanged).
  2. An **independent** Senior Manager or Company Admin (`confirmed_by != performed_by`) confirms or rejects the variance. Confirmation applies the stock delta under row lock; self-confirmation is rejected with `403 Forbidden`.

---

## 5. Private Document Vault & Deferred Cloud Storage Integration

- All uploaded and generated files (Project Documents, Work Order Attachments, Work Order PDF Uploads, Measurement Photos, Worker Documents, Company Logos, and RA Bill/Service Order Exports) are routed through `App\Services\Storage\VaultStorage`.
- **Current Local Configuration:**
  - `config/filesystems.php` defines `'vault_disk' => env('CRM_VAULT_DISK', 'local')` and a private `'vault'` disk rooted at `storage/app/private` (`'visibility' => 'private'`, `'serve' => false`).
  - Every file download is authorized through Laravel Policies/Controllers and streamed with `X-Content-Type-Options: nosniff`. No files are stored under `public/` or exposed via unauthenticated URLs.
- **Future Cloud Storage Integration Point (When Approved Later):**
  1. Install the chosen Flysystem adapter (e.g., `league/flysystem-aws-s3-v3` for S3 / Cloudflare R2).
  2. Configure the `'vault'` disk driver in `config/filesystems.php` with private visibility (`'visibility' => 'private'`).
  3. Set `CRM_VAULT_DISK=vault` in the deployment environment. Historical local files continue resolving seamlessly via each record's persisted `storage_disk` column.

---

## 6. Pending Additive Database Migrations (Awaiting Separate Approval)

There are **7 pending additive, non-destructive migration files** in `database/migrations/` (**2 prerequisite access/security migrations** + **5 Release 1 migrations**). None have been executed against `nsd_crm_2` (`migrations = 22`). Because running bare `php artisan migrate` executes **all** pending files in timestamp order, all 7 files must be reviewed and approved together in this exact dependency sequence:

1. `database/migrations/2026_11_09_134500_add_two_factor_last_step_to_users_table.php` *(Prerequisite 1: adds `users.two_factor_last_step`, which Prerequisite 2 references as its `AFTER` anchor)*
2. `database/migrations/2026_11_10_150000_add_enterprise_access_management_columns.php` *(Prerequisite 2: adds `users.must_change_password` / `password_changed_at` / `last_login_at` / `last_login_ip`, `company_user.permission_overrides` / `restrict_to_assigned_sites`, and `roles.is_system` / `is_active` with system-role backfill — required for Release 1 `BL-02` permission overrides and site scoping)*
3. `database/migrations/2026_11_15_100001_release1_master_data_vendors_and_leave_quotas.php` *(Release 1 — Vendors table, `clients` unique code index, `purchase_orders.vendor_id`, `materials.hsn_code`)*
4. `database/migrations/2026_11_15_100002_release1_inventory_movements_and_adjustments.php` *(Release 1 — `inventory_transactions` work order & adjustment columns, `inventory_transaction_items` quantity audit columns)*
5. `database/migrations/2026_11_15_100003_release1_vault_storage_igst_and_worker_links.php` *(Release 1 — `storage_disk` / `signature_disk`, `workers.user_id` 1-to-1 unique link, `work_orders` & `ra_bills` IGST columns with `DECIMAL(16,2)`)*
6. `database/migrations/2026_11_15_100004_release1_individual_statutory_payroll.php` *(Release 1 — Individual `PF`, `ESIC`, `PT` columns on `workers` and `payroll_entries`)*
7. `database/migrations/2026_11_15_100005_release1_scalability_composite_indexes.php` *(Release 1 — Composite indexes `idx_act_logs_comp_action_created` and `idx_po_comp_site_status`)*

When separately approved to apply to a target database:
```bash
# 1. Always take a verified mysqldump backup first
# 2. Verify pending list before executing:
php artisan migrate:status
# 3. Execute in exact order (or one-by-one via --path):
php artisan migrate
```

---

## 7. Local Development, Formatting, Testing & Build Commands

```bash
# Format PHP code according to project style rules
./vendor/bin/pint

# Run the complete PHPUnit test suite (uses isolated in-memory SQLite; never touches nsd_crm_2)
php artisan test

# Build production frontend assets (Vite 8 + Tailwind CSS v4 + Alpine.js)
npm run build
```

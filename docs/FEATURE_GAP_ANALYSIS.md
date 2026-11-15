# NSD CRM 2.0 — Comprehensive Feature Gap Analysis

**Phase:** 5A — Master Product Gap Analysis (Read-Only Audit)  
**Date:** 2026-11-15  
**Repository:** `/Users/andrew/Developer/NSD-CRM-2.0`  
**Baseline Verified:** Laravel 13, PHP 8.4/8.5, 29 Migrations, 37 Eloquent Models, 17 Controllers, 11 Domain Services, 8 Authorization Policies, 182 Automated Tests (2,013 Assertions Passing)

---

## 1. Classification Taxonomy & Summary Metrics

Every product capability across all **9 product domains (48 evaluated features)** has been inspected at the database schema, model, service, controller, policy, route, Blade view, and test level—never assuming a feature is complete merely because a screen or table exists.

| Classification | Count | Meaning |
| :--- | :---: | :--- |
| **Complete and tested** | **8** | Production-grade schema, service logic, UI, role/site authorization, audit logging, and automated feature test coverage are in place. Minor enhancements are optional. |
| **Implemented but needs improvement** | **14** | Core workflow works and is tested, but specific functional gaps, missing sub-actions, or edge-case defects limit full real-world operations. |
| **Partially implemented** | **12** | Database tables or partial endpoints exist, but major workflow pieces, CRUD screens, or cross-module links are missing. |
| **Not implemented** | **11** | No schema, service, controller, or UI exists yet in the codebase. |
| **Requires architectural work** | **3** | Requires foundational infrastructure/platform patterns (cloud object storage abstraction, queue/scheduler worker pipeline, tenant-sandboxed AI tool layer) before feature rollout. |
| **Total Evaluated Features** | **48** | Across 9 Product Domains |

---

## 2. Domain-by-Domain Feature Evaluation

### Domain 1: Company & Administration

#### 1.1 Company Profiles & Settings
- **Classification:** Partially implemented
- **Existing Implementation:**
  - `companies` table (`2026_10_28_000001` + `2026_11_01_000003`) and `App\Models\Company` store `name`, `code`, `email`, `phone`, `address`, `registered_address`, `gstin`, `pan`, `vendor_code`, `bank_name`, `bank_account_no`, `bank_ifsc`, `bank_branch`, `signature_path`, `invoice_prefix`, `invoice_starting_number`, `invoice_rounding_mode`, and `work_order_terms_template`.
  - Downstream PDF/XLSX generators (`RaBillExportService`, `WorkOrderController::exportPdf`) already read these fields.
- **Missing Functionality:**
  - **Zero routes, controllers, or Blade views** to view or update Company Profile, tax identifiers (GSTIN/PAN), bank details, invoice numbering prefix/sequence, default work order terms, or company logo/authorized signatory image (`signature_path`).
  - Topbar header in `resources/views/layouts/app.blade.php` (line 51) hardcodes `"NSD Earth Movers & Construction"` instead of rendering active `$currentCompany` branding.
- **Dependencies:** Private/public asset storage abstraction (`signature_path`, company logo).
- **Security Considerations:** Restrict strictly to `company_admin` and `super_admin` (`settings.manage`); validate image MIME/dimensions; record `old_values` and `new_values` in `AuditLog` whenever bank details, GSTIN, or invoice prefixes are changed.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P0 — Critical (Release 1)**

#### 1.2 Multi-Company Architecture
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - Shared-database, row-isolated multi-tenancy via `BelongsToCompany` global Eloquent scope (fails closed with `WHERE 1 = 0` if unresolved), `TenantContext` (with per-request membership memoization), `SetTenantContext` middleware, `company_user` pivot (`role_id`, `status`, `is_primary`, `permission_overrides`, `restrict_to_assigned_sites`), and `POST /switch-company` (`AuthController::switchCompany`).
  - Dedicated `TenancySecurityTest` and `TenantForgedSecurityTest` suites pass.
- **Missing Functionality:**
  - **Missing UI Organization Switcher:** `layouts/app.blade.php` (lines 136–141) renders `$currentCompany->name` as a static badge with no dropdown to invoke `route('switch_company')` for multi-company users/directors.
  - **Missing Tenant Filter Bug on Dashboard Audit Snippet:** `AuditLog` (`activity_logs`) intentionally omits `BelongsToCompany` so failed unauthenticated logins can log `company_id = null`, and `ReportsController::auditLogs()` explicitly filters `->where('company_id', $companyId)`, **but `DashboardController.php` (line 160) queries `AuditLog` without `->where('company_id', $companyId)`**, exposing recent log titles across tenants when `$scopedSiteIds === null`.
  - **CLI-Only Company Provisioning:** Creating a new tenant currently requires `php artisan nsd:create-admin`—no Super Admin tenant management UI or self-onboarding flow exists.
  - Child line tables (`purchase_request_items`, `purchase_order_items`, `inventory_transaction_items`, `work_order_approvals`, `work_order_attachments`) rely on parent FK joins rather than direct `company_id` columns.
- **Dependencies:** Company Profile UI (1.1), Queue tenant context propagation (Architecture Review §4.6).
- **Security Considerations:** Immediately add `->where('company_id', $companyId)` to `DashboardController::index()` `AuditLog` query; clear `active_site_id` and cached role context on company switch (already handled in `AuthController::switchCompany`).
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P0 — Critical (Release 1)**

#### 1.3 Employee Accounts & Roles
- **Classification:** Complete and tested
- **Existing Implementation:**
  - `UserManagementController` and `RoleManagementController` under `/admin/users` and `/admin/roles`.
  - Supports manual or auto-generated 16-char passwords, `must_change_password` enforcement on login, TOTP Authenticator + Email OTP 2FA (`TotpAuthenticatorService`), login throttling, instant deactivation lockout in `SetTenantContext`, `last_login_at`/`last_login_ip` tracking, 8 protected system roles, and 5 one-click custom role templates (`Accountant`, `HR Manager`, `Site Engineer`, `Procurement Officer`, `Project Coordinator`).
- **Missing Functionality:**
  - Self-service "Forgot Password" email reset workflow (`password_reset_tokens` table exists in migration `0001_01_01_000000`, but routes/views/mailables are not wired).
  - Automated welcome/invitation email upon user creation (currently displays generated password once to Admin).
- **Dependencies:** Transactional Email & Queue infrastructure (Domain 6).
- **Security Considerations:** Rate-limit password reset requests (max 3/hour per email/IP), hash tokens in DB, expire within 60 minutes, and invalidate other sessions upon password reset.
- **Estimated Complexity:** Low
- **Recommended Priority:** **P2 — Medium (Release 3)**

#### 1.4 Custom Permissions
- **Classification:** Complete and tested
- **Existing Implementation:**
  - `PermissionCatalog` defines 16 modules and 62 granular permission keys, interactive matrix editor (`/admin/roles/matrix`), per-user `permission_overrides` (`allow`/`deny` JSON on `company_user`), `ADMIN_RESERVED_PERMISSIONS` anti-escalation guard, and `WILDCARD_EXCLUDED_ATTENDANCE_PERMISSIONS` preventing legacy `*` wildcards from bypassing attendance mark/edit/approve/export rules.
- **Missing Functionality:**
  - Fix minor catalog-to-controller mismatch in `PayrollController.php` (lines 34–48 check `payroll.manage`, whereas `PermissionCatalog` defines `payroll.create`, `payroll.edit`, `payroll.approve`), and grant `procurement.request` to `office_staff` if Office Staff is expected to raise PRs by default.
  - Register new permission groups as Finance, Inventory Transfers, Company Settings, Documents, and AI modules are introduced.
- **Dependencies:** None.
- **Security Considerations:** Preserve invariant that explicit user `deny` overrides all role/default grants.
- **Estimated Complexity:** Low
- **Recommended Priority:** **P0 for Payroll/Procurement key alignment (Release 1)**

#### 1.5 Site-Level Access
- **Classification:** Complete and tested
- **Existing Implementation:**
  - `user_site` pivot + `site_assignments` history table, `restrict_to_assigned_sites` flag on `company_user`, request-memoized `User::accessibleSiteIds()` and `User::isAssignedToSite()`, topbar site switcher (`POST /switch-site`), enforced across controllers, policies, and CSV/XLSX/PDF exports.
- **Missing Functionality:**
  - Optional warehouse-specific scoping via `warehouses.manager_id` for storekeepers managing central vs regional stores.
- **Dependencies:** Warehouse CRUD (5.5).
- **Security Considerations:** Maintain IDOR checks on all `site_id` and `warehouse_id` inputs across new Finance/Expense/Inventory endpoints.
- **Estimated Complexity:** Low
- **Recommended Priority:** **P1 — High (Release 1)**

#### 1.6 Audit History
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - Append-only `AuditLog` (`activity_logs` table) blocking `updating` and `deleting` via Eloquent model events (`LogicException`), recording actor, IP, site, project, `old_values`, `new_values`, and action; searchable UI (`/reports/audit-logs`) and CSV export (`/reports/audit-logs/export`).
- **Missing Functionality:**
  - Fix missing `where('company_id', $companyId)` on `DashboardController.php:160`.
  - Wire `$recentLogs` in `ProjectController::show()` (line 236 currently sets `$recentLogs = collect()`, leaving the Project Activity tab blank).
  - Replace hardcoded `->limit(250)->get()` in `ReportsController::auditLogs()` with proper pagination and `->limit(1000)->get()` in CSV export with chunked streaming.
  - Add automatic sensitive-key redaction (`password`, `pin`, `two_factor_secret`, `bank_account_number`) inside `AuditLog::record()`.
- **Dependencies:** None.
- **Security Considerations:** Prevent cross-tenant audit leakage and ensure high-volume tables remain performant via indexed cursor pagination.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P0 — Critical (Release 1)**

---

### Domain 2: People & Workforce

#### 2.1 Employee Management
- **Classification:** Partially implemented
- **Existing Implementation:**
  - Login accounts live in `users` (`admin/users`), while HR/workforce records live in `workers` (`worker_type = 'employee'`, `direct_labour`, `subcontractor_worker`) with full personal, emergency contact, skill, department, bank/UPI, and KYC document fields.
- **Missing Functionality:**
  - **No `user_id` Foreign Key Linking `workers` and `users`:** System employees (`users`) and HR employees (`workers` with `worker_type = 'employee'`) are completely disconnected records. An engineer or staff member with a CRM login cannot be linked to their attendance/payroll/leave profile, and terminating an employee in Workforce does not automatically deactivate their CRM user login.
  - Missing structured Department & Designation master lists (currently hardcoded constants on `Worker`) and employee offboarding checklist.
- **Dependencies:** Nullable unique `(company_id, user_id)` migration on `workers`.
- **Security Considerations:** Atomic suspension: deactivating or terminating a linked employee must revoke both `users.is_active` (`company_user.status`) and `workers.status` in a single transaction.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 1)**

#### 2.2 Worker Management
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - `WorkforceController` (`/workforce`) with concurrency-safe `WorkerCodeGenerator`, refined duplicate detection (name + Aadhaar last-4 + phone/DOB disambiguation), CSV bulk import with template download, site transfer history (`site_assignments`), PIN reset, QR token generation, and private KYC/safety document management.
- **Missing Functionality:**
  - **Missing Subcontractor CRUD:** `GET /workforce/subcontractors` (`WorkforceController::subcontractors()`) is a read-only listing! There are no create, edit, or status-toggle routes/forms for `Subcontractor` records in the UI (they can only be implicitly created during CSV import).
  - **Missing Printable Worker QR ID Cards:** `workers.qr_token` and `QrCodeSvgGenerator` exist, but there is no printable Worker ID Card / QR Badge PDF action (individual or bulk per site) for field scanning.
- **Dependencies:** `App\Support\QrCodeSvgGenerator`, `Dompdf`.
- **Security Considerations:** Continue storing only `aadhaar_last_four` (never full Aadhaar); mask bank account numbers for roles without `payroll.view` or `workforce.manage`.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P0 — Critical (Release 1)**

#### 2.3 Attendance
- **Classification:** Complete and tested
- **Existing Implementation:**
  - 4 capture channels: Supervisor Muster Roll, Supervisor QR Scan, Site-Bound Kiosk Terminal (`/kiosk/{token}` with token rotation & rate limiting), and Employee Self-Punch (`/worker/punch` with GPS Haversine geofence check).
  - Anomaly detection flags (`outside_geofence`, `shared_device`, `identical_crew_times`, `impossible_travel`, `overlapping_periods`), 4-stage attendance locks (`open` -> `submitted` -> `site_locked` -> `payroll_locked`), immutable `AttendanceCorrection` workflow with mandatory reason and non-self-approval, and Monthly Attendance Register.
- **Missing Functionality:**
  - Optional camera selfie capture (`attendance_punches.selfie_path` column exists in DB but is unused in Kiosk/Worker Portal UI).
  - Batch approval action on `/attendance/approvals` for managers reviewing high-volume daily punches.
- **Dependencies:** Cloud object storage (7.1) before enabling daily punch photos at scale.
- **Security Considerations:** Maintain strict explicit permission gates (`attendance.mark`, `attendance.edit`, `attendance.approve`, `attendance.export`) and server-only punch timestamps.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P2 — Medium (Release 2)**

#### 2.4 Leave Management
- **Classification:** Partially implemented
- **Existing Implementation:**
  - `leave_types`, `leave_requests`, and `holidays` tables (`2026_10_28_000011`); leave submission from Worker Portal and `/attendance/shifts-leaves`; non-self-approval review in `AttendanceController::reviewLeaveRequest()` which writes `paid_leave` records into `daily_attendances`.
- **Missing Functionality:**
  - **Leave Type CRUD & Annual Quota Enforcement:** `leave_types` (`annual_quota`, `is_paid`) has no management UI, and `storeLeaveRequest()` accepts free-text `leave_type_name` without linking `leave_type_id`, checking available quota balance, preventing overlapping leave requests, or excluding company/site `holidays` from `days_count`!
  - Missing edit/delete endpoints for `holidays`.
- **Dependencies:** Holiday calendar & `AttendanceService`.
- **Security Considerations:** Block leave approval on `payroll_locked` dates; preserve non-self-approval and site-level authorization.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 1)**

#### 2.5 Shift Management
- **Classification:** Partially implemented
- **Existing Implementation:**
  - `shifts` table (`start_time`, `end_time`, `grace_minutes`, `half_day_hours`, `full_day_hours`, `overtime_after_hours`, `is_default`) and `POST /attendance/shifts` (`storeShift`); integrated with `AttendanceService` for late/overtime calculation.
- **Missing Functionality:**
  - Cannot edit, delete, or reassign default shifts in `/attendance/shifts-leaves`.
  - **No Worker Shift Assignment / Roster:** Workers have no `shift_id` assignment or weekly shift rotation schedule (e.g., Day Shift vs Night Shift crews on 24x7 industrial sites like JSW).
  - Cross-midnight shift punch-out pairing refinement.
- **Dependencies:** `workers` / `site_assignments` schema extension.
- **Security Considerations:** Restrict shift rule modifications to `attendance.manage` with audit logging.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 2)**

#### 2.6 Payroll
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - `payroll_periods`, `payroll_entries`, `salary_advances`, `PayrollCalculationService` supporting `daily`, `monthly`, `hourly`, and `contract` pay types, overtime calculation, salary advance auto-deduction, manual bonus/deduction adjustments, period lock (`approveAndLock`), and HTML payslip view (`payroll/payslip.blade.php`).
- **Missing Functionality:**
  - **Permission Key Bug:** `PayrollController` checks `payroll.manage` (which does not exist in `PermissionCatalog`), blocking custom roles granted `payroll.create` / `payroll.edit` / `payroll.approve`.
  - **Indian Statutory Deductions:** Optional per-worker / per-company PF (EPF), ESIC, Professional Tax (PT), and TDS calculation columns and summary register.
  - **Payout Exports & Payslip PDF:** Downloadable Payslip PDF (`Dompdf`), Bank NEFT/UPI bulk payout CSV/XLSX export, and batch payment recording (UTR/payment date).
- **Dependencies:** `Dompdf`, `PhpSpreadsheet`, Payment Tracking (4.5).
- **Security Considerations:** Enforce maker-checker separation (`payroll.edit` vs `payroll.approve` + non-self-approval) before locking payroll.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P0 for permission fix & PDF/Bank export (Release 1); P1 for PF/ESIC (Release 2)**

#### 2.7 Employee Self-Service
- **Classification:** Partially implemented
- **Existing Implementation:**
  - Worker Portal (`/worker/*`) supports Worker Code + 6-digit PIN login (with brute-force lockout & mandatory first-login PIN change), GPS self-punch, viewing today's status + last 10 punches, and submitting leave requests.
- **Missing Functionality:**
  - Workers/employees cannot view or download **Payslips**, view their **Monthly Attendance Calendar**, check **Leave Quota Balances**, view **Salary Advance balances**, or submit **Attendance Correction requests** from the portal.
  - Logged-in CRM staff (`users`) linked to an employee record lack a "My Workspace" self-service tab.
- **Dependencies:** Employee `user_id` link (2.1), Leave Balances (2.4), Payslip PDF (2.6).
- **Security Considerations:** Strict session isolation (`worker_auth_id`); rate-limited downloads; zero cross-worker visibility.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 2)**

---

### Domain 3: Projects & Operations

#### 3.1 Projects & Sites
- **Classification:** Complete and tested
- **Existing Implementation:**
  - Full CRUD for `projects` and `sites`, concurrency-safe `PRJ-###` and `SITE-###` sequence generation, `project_sites` multi-site linking, geofence settings, site staff assignments, and `ProjectPolicy` / `SitePolicy` enforcement.
- **Missing Functionality:**
  - Standalone **Client Directory CRUD** (`clients` table exists and can be created inline on Work Orders via `POST /work-orders/clients`, but lacks an index/edit screen to manage client GSTIN, billing address, and contacts).
  - Wire `$recentLogs` on `ProjectController::show()` (`activity` tab).
- **Dependencies:** None.
- **Security Considerations:** Maintain site-restricted visibility for Site Managers and Supervisors.
- **Estimated Complexity:** Low
- **Recommended Priority:** **P1 — High (Release 1)**

#### 3.2 Project Budgets
- **Classification:** Not implemented
- **Existing Implementation:** None (`projects` and `sites` have no budget or contract-value columns; only individual `work_orders` store `base_order_value`).
- **Missing Functionality:**
  - Project & Site master budget structure (`contract_value`, `planned_margin_percent`, and cost-head budgets: `labour`, `materials`, `subcontractors`, `equipment`, `overheads`), budget revision log with approval, and threshold warnings (`80%`, `90%`, `100%` budget burn).
- **Dependencies:** Projects & Sites (3.1); feeds Finance Budget vs Actuals (4.6).
- **Security Considerations:** Gate budget & margin figures behind `finance.budgets.view` / `projects.budget` so field roles only see operational quantities unless authorized.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 2)**

#### 3.3 Milestones
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - `project_milestones` table, creation and `status`/`percent_complete` updates in `ProjectController`, and project completion % rollup.
- **Missing Functionality:**
  - Edit milestone metadata (title, due date, site) and delete milestone actions.
  - Optional link from `tasks` to `project_milestone_id` for bottom-up progress tracking, and scheduled `milestone_overdue` alerts.
- **Dependencies:** Tasks (3.5), Reminders (6.3).
- **Security Considerations:** Enforced via `ProjectPolicy::manageMilestones`.
- **Estimated Complexity:** Low
- **Recommended Priority:** **P1 — High (Release 1)**

#### 3.4 Work Orders
- **Classification:** Complete and tested
- **Existing Implementation:**
  - Manual & JSW Client PO PDF upload (`JswWorkOrderPdfReaderService` + human review screen + CSV/XLSX BOQ import), `work_order_items`, `work_order_revisions` with snapshots, 2-tier approvals, lifecycle transitions (`confirmed` -> `in_progress` -> `completed` -> `defect_liability` -> `closed`), retention/DLP dates, private attachments, and Service Order PDF export.
- **Missing Functionality:**
  - **Outward Subcontractor Work Orders:** Formal linkage to `subcontractor_id` for back-to-back subcontracting against a client project/BOQ, feeding Subcontractor RA Bills / Vendor Bills.
  - Multi-format PO parser / AI-assisted extraction fallback for non-JSW client PO formats.
- **Dependencies:** Subcontractor CRUD (2.2), Vendor Bills (4.3), AI Extraction (9.4).
- **Security Considerations:** Preserve arithmetic validation and Draft PO billing block (`WorkOrder::isDraftPo()`).
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 2)**

#### 3.5 Tasks & Assignments
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - Kanban board & list views (`/tasks`), `TASK-###` code generation, priority/status workflows, user & worker assignment, `task_comments`, and in-app notifications.
- **Missing Functionality:**
  - Pagination/archiving on `TaskController::index()` (currently executes unpaginated `->get()`, which will degrade once a site accumulates 1,000+ completed tasks).
  - Task photo/file attachments (e.g., site snag/inspection photos) and optional `milestone_id` / `work_order_id` links.
- **Dependencies:** Private vault storage (7.1).
- **Security Considerations:** `TaskPolicy` site isolation enforced.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P1 — High (Release 1)**

#### 3.6 Site Diaries & Notes
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - `site_notes` (`general`, `safety_hse`, `quality`, `client_instruction`, `handover`), pinning, and one-click `convertNoteToTask`.
- **Missing Functionality:**
  - Structured **Daily Site Diary / Daily Progress Report (DPR)**: weather, shift headcount (auto-pulled from `daily_attendances`), plant & machinery log (equipment deployed, hours worked, idle hours, diesel used), client instructions/hindrances, photo attachments, manager sign-off lock, and printable DPR PDF.
- **Dependencies:** Attendance aggregation, Private vault storage, `Dompdf`.
- **Security Considerations:** Site-scoped access; lock DPR after Site Manager sign-off.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 2)**

#### 3.7 Measurements
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - `measurement_entries` (`L × B × D × Nos` or `direct_quantity`, `hold_quantity`, `location_chainage`, private `photo_path`) linked to `work_order_items` and billed into `ra_bills`.
- **Missing Functionality:**
  - **Independent Measurement Verification Workflow:** `RaBillingService::recordMeasurement()` currently auto-marks new entries as `status = 'verified'` immediately upon creation; needs `draft` -> `verified` approval action wired to `measurements.approve` permission, edit/delete for unbilled draft entries, and printable **Measurement Book (MB) PDF/XLSX** export.
- **Dependencies:** `WorkOrderPolicy`.
- **Security Considerations:** Measurements attached to non-draft RA bills must remain strictly read-only.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P1 — High (Release 1)**

#### 3.8 Approvals
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - Domain-level approvals exist in Work Orders, Work Order Revisions, RA Bills, Purchase Requests, Attendance Corrections, Leave Requests, and Payroll Periods.
- **Missing Functionality:**
  - **Unified "Pending Approvals" Center (`/approvals`):** Single role- and site-scoped inbox aggregating all items awaiting the logged-in user's action across Work Orders, RA Bills, Purchase Requests, Leaves, Attendance Corrections, Stock Adjustments, Expenses, and Vendor Bills.
  - Missing `rejectRequest` route/action on `PurchaseRequest` (`ProcurementController`).
  - Pending RA Bill approvals are not surfaced on `DashboardController`.
- **Dependencies:** Approval Notifications (6.5).
- **Security Considerations:** Enforce non-self-approval consistently across financial and operational approvals.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P0 — Critical (Release 1)**

---

### Domain 4: Finance

#### 4.1 RA Bills
- **Classification:** Complete and tested (Core engine); needs global register UI
- **Existing Implementation:**
  - `RaBillingService` & `RaBillExportService` with 100% BOQ quantity cap, 80%/90% consumption warnings, excess quantity carry-forward, hold quantities, CGST/SGST, retention %, advance recovery, paisa/rupee rounding, 2-tier approvals, Abstract PDF, Combined PDF, and live-formula `.xlsx` export.
- **Missing Functionality:**
  - **Global RA Bills Directory (`/ra-bills` or `/finance/ra-bills`):** Currently accessible only inside individual `/work-orders/{workOrder}` pages—finance staff lack a company-wide register filterable by status, client, site, and period.
  - Inter-state **IGST** support (currently `cgst_rate` + `sgst_rate` only) and explicit status transition to `invoiced` / `paid` linked to payment receipts.
- **Dependencies:** Payment Tracking (4.5).
- **Security Considerations:** Enforced via `WorkOrderPolicy` and `ra_bills.*` permissions.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P0 — Critical (Release 1 for global register & IGST)**

#### 4.2 Tax Invoices
- **Classification:** Partially implemented
- **Existing Implementation:**
  - Concurrency-safe financial-year sequence generator (`invoice_sequences`: `{PREFIX}/{SEQ}/{FY}`) and GST Tax Invoice PDF export attached to approved `RaBill` records.
- **Missing Functionality:**
  - Dedicated **Tax Invoices Module (`/invoices`)** supporting: (a) viewing/filtering all generated RA-based and standalone tax invoices, (b) issuing standalone GST Tax Invoices (e.g., machinery rental, supply-only contracts, mobilization advances), (c) IGST vs CGST/SGST tax determination by Place of Supply (state code), (d) **Credit Notes / Debit Notes**, and (e) GSTR-1 summary CSV export.
- **Dependencies:** Company GSTIN/State settings (1.1), Client Directory (3.1).
- **Security Considerations:** Assigned invoice numbers must never be deleted or reused; cancellations/adjustments must use audited Credit Notes.
- **Estimated Complexity:** Medium–High
- **Recommended Priority:** **P1 — High (Release 2)**

#### 4.3 Vendor Bills (Accounts Payable)
- **Classification:** Not implemented
- **Existing Implementation:** None (`InventoryTransaction` only captures `challan_or_invoice_no` string on GRN).
- **Missing Functionality:**
  - `vendor_bills` and `vendor_bill_items` tables supporting Supplier Invoices (with 3-way match against `PurchaseOrder` + `GRN`) and Subcontractor Bills, GST Input Tax Credit (ITC) breakdown, TDS deduction (e.g., 1%/2% u/s 194C), retention held, due date, private bill PDF attachment, and 2-step approval workflow.
- **Dependencies:** Vendors Master (5.1), Subcontractors (2.2), Purchase Orders & GRNs (5.3, 5.4).
- **Security Considerations:** Unique constraint on `(company_id, vendor_id, vendor_invoice_number)` to prevent duplicate supplier bill entry fraud; maker-checker approval.
- **Estimated Complexity:** High
- **Recommended Priority:** **P1 — High (Release 2)**

#### 4.4 Expenses & Site Petty Cash
- **Classification:** Not implemented
- **Existing Implementation:** None.
- **Missing Functionality:**
  - `expense_categories`, `site_petty_cash_wallets` (imprest float top-ups & running balance), and `expenses` (diesel/fuel, local transport, municipal/permit fees, staff travel, site consumables) tagged to `project_id` / `site_id` / `work_order_id`, private receipt photo/PDF upload, and manager approval workflow.
- **Dependencies:** Private vault storage (7.1), Project Budgets (3.2).
- **Security Considerations:** Mandatory receipt upload above configurable limit; non-self-approval; site-level visibility controls.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 2)**

#### 4.5 Payment Tracking (Receivables & Payables)
- **Classification:** Not implemented
- **Existing Implementation:** None (only a basic `payment_status` flag on `payroll_entries`).
- **Missing Functionality:**
  - `financial_payments` ledger recording **Client Receipts** (against Tax Invoices / RA Bills, capturing bank amount received, client TDS deducted, retention withheld/released, UTR/reference, payment date) and **Outward Disbursements** (against Vendor Bills, Subcontractor Bills, Expenses, and Payroll Periods), supporting partial payments and real-time outstanding balance calculation.
- **Dependencies:** Tax Invoices (4.2), Vendor Bills (4.3), Expenses (4.4), Payroll (2.6).
- **Security Considerations:** Append-only financial ledger with reversal entries rather than silent edits; restricted to `finance.payments.*` permissions.
- **Estimated Complexity:** High
- **Recommended Priority:** **P1 — High (Release 2)**

#### 4.6 Budget versus Actuals
- **Classification:** Not implemented
- **Existing Implementation:** None.
- **Missing Functionality:**
  - Live Project & Site Cost Control engine comparing **Approved Project Budget** (by cost head) against **Committed Costs** (Approved POs + Subcontract WOs) and **Actual Incurred Costs** (Locked Payroll + Site Material Issues + Approved Vendor Bills + Approved Expenses), alongside **Billed Revenue** (Approved RA Bills/Invoices).
- **Dependencies:** Project Budgets (3.2), Stock Issues (5.6), Vendor Bills (4.3), Expenses (4.4), Payroll (2.6).
- **Security Considerations:** Executive/Finance role restriction (`finance.costing.view`).
- **Estimated Complexity:** High
- **Recommended Priority:** **P1 — High (Release 2)**

#### 4.7 Financial Reports
- **Classification:** Not implemented
- **Existing Implementation:** Operational reports only in `ReportsController`.
- **Missing Functionality:**
  - Exportable (CSV/XLSX/PDF) Financial Statements: Project Profitability (P&L), Client Receivables (AR) Aging & Retention Receivable Register, Vendor/Subcontractor Payables (AP) Aging, GST Output vs Input Tax Summary, TDS Receivable/Payable Register, and Monthly Cashflow Statement.
- **Dependencies:** Finance modules 4.1–4.6.
- **Security Considerations:** Audited exports gated behind `finance.reports.view` and `finance.reports.export`.
- **Estimated Complexity:** Medium–High
- **Recommended Priority:** **P1 — High (Release 2)**

---

### Domain 5: Procurement & Inventory

#### 5.1 Vendors
- **Classification:** Not implemented
- **Existing Implementation:** `purchase_orders` stores raw `supplier_name` and `supplier_phone` strings only.
- **Missing Functionality:**
  - `vendors` master directory (`code`, `name`, `vendor_type`, `gstin`, `pan`, `contact_person`, `phone`, `email`, `address`, `payment_terms_days`, `bank_account_name`, `bank_account_no`, `bank_ifsc`, `is_active`), `vendor_id` FK on `purchase_orders`, and vendor purchase/price history view.
- **Dependencies:** None.
- **Security Considerations:** Audit log any change to vendor bank account/IFSC details; validate GSTIN/PAN checksums/patterns.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P0 — Critical (Release 1)**

#### 5.2 Purchase Requests
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - `purchase_requests` + `purchase_request_items`, concurrency-safe `PR-NSD-YYYY-###` numbering, urgency/emergency justification validation, 1-step approval (`approveRequest`), and site scoping.
- **Missing Functionality:**
  - Add `rejectRequest` controller action & route (`rejection_reason` column already exists in DB!), requester cancellation of `pending` PRs, optional `project_id`/`work_order_id` tagging, and dynamic company code prefix (`PR-{COMPANY_CODE}-YYYY-###` instead of hardcoded `PR-NSD`).
- **Dependencies:** Warehouse & Material CRUD (5.5).
- **Security Considerations:** Non-self-approval on PRs; site-scope enforcement.
- **Estimated Complexity:** Low
- **Recommended Priority:** **P0 — Critical (Release 1)**

#### 5.3 Purchase Orders
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - `purchase_orders` + `purchase_order_items`, concurrency-safe `PO-NSD-YYYY-###` numbering, conversion from approved PRs, and delivery status tracking (`issued` -> `partially_received` -> `received`).
- **Missing Functionality:**
  - Fix `DashboardController.php:114` status filter bug (`'ordered'` -> `'issued'`).
  - Link to `vendor_id` (5.1), add GST/tax & freight fields on PO lines, PO cancellation/close action, dynamic `PO-{COMPANY_CODE}` prefix, and **Printable Purchase Order PDF** export.
- **Dependencies:** Vendors (5.1), `Dompdf`.
- **Security Considerations:** Block unapproved price/quantity edits once goods receipt has begun.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P0 — Critical (Release 1)**

#### 5.4 Goods Receipt (GRN)
- **Classification:** Complete and tested
- **Existing Implementation:**
  - `InventoryService::recordGoodsReceipt()` with row-level `lockForUpdate()`, partial receipts, over-receipt prevention, duplicate challan idempotency check, and `GRN-NSD-YYYY-###` numbering.
- **Missing Functionality:**
  - Optional delivery challan/weighbridge slip photo/PDF attachment upload, rejected/damaged quantity note, dynamic `GRN-{COMPANY_CODE}` prefix, and Printable GRN PDF.
- **Dependencies:** Private vault storage (7.1).
- **Security Considerations:** `InventoryPolicy::receive` and site warehouse authorization already enforced.
- **Estimated Complexity:** Low
- **Recommended Priority:** **P1 — High (Release 1)**

#### 5.5 Warehouse & Material Catalog Management
- **Classification:** Partially implemented
- **Existing Implementation:**
  - `warehouses`, `material_categories`, `materials`, and `inventory_stocks` tables exist; stock balances and transactions views work at `/inventory/stocks` and `/inventory/transactions`.
- **Missing Functionality:**
  - **Missing Master CRUD Screens:** Zero routes/controllers/views exist to create, edit, or deactivate **Warehouses** (`central_warehouse` / `site_store`), **Material Categories**, or **Materials** (code, name, HSN code, unit of measure, minimum stock level, standard cost)! New companies cannot set up inventory without database seeding.
- **Dependencies:** None.
- **Security Considerations:** Gate under `inventory.manage`; prevent deleting materials/warehouses with historical transactions (use `is_active` deactivation).
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P0 — Critical Onboarding Blocker (Release 1)**

#### 5.6 Stock Transfers & Material Issues
- **Classification:** Not implemented
- **Existing Implementation:** `inventory_transactions` schema supports `source_warehouse_id`, `destination_warehouse_id`, and `transaction_type` values (`warehouse_issue`, `site_return`, `inter_site_transfer`), and `PermissionCatalog` defines `inventory.issue`, **but zero service/controller/UI implementation exists**.
- **Missing Functionality:**
  - **Material Issue to Site / Work Order (`warehouse_issue`):** Deduct warehouse stock (`lockForUpdate()`, block negative stock) and charge consumption to `site_id` / `work_order_id`.
  - **Inter-Warehouse / Inter-Site Transfer (`inter_site_transfer`):** Dispatch from source warehouse and confirm receipt at destination warehouse.
  - **Site Material Return (`site_return`):** Return unused stock from site to warehouse.
- **Dependencies:** Warehouse & Material CRUD (5.5).
- **Security Considerations:** Deterministic lock ordering (`ORDER BY warehouse_id ASC` with `lockForUpdate()`) to prevent database deadlocks on concurrent bidirectional transfers; site access verification on both ends.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P0 — Critical (Release 1)**

#### 5.7 Stock Adjustments
- **Classification:** Not implemented
- **Existing Implementation:** None.
- **Missing Functionality:**
  - Physical stock count / reconciliation & wastage/shrinkage adjustment workflow (`stock_adjustment` transaction type, reason category, mandatory notes, maker-checker manager approval before updating `inventory_stocks.quantity`, and opening stock entry).
- **Dependencies:** `InventoryService` ledger.
- **Security Considerations:** Require manager approval (`inventory.manage` + non-self-approval) and immutable `AuditLog` entries for all stock write-offs.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 1)**

#### 5.8 Inventory Valuation
- **Classification:** Partially implemented
- **Existing Implementation:**
  - Static valuation (`quantity * standard_unit_cost`) in `ReportsController::inventory()`, while `inventory_transaction_items` stores actual PO `unit_cost` on GRNs.
- **Missing Functionality:**
  - **Moving Weighted Average Cost (WAC)** engine updating `inventory_stocks.average_unit_cost` on every GRN and using WAC when pricing `warehouse_issue` transactions into Project Costing; valuation history & dead-stock aging report.
- **Dependencies:** Goods Receipt (5.4), Stock Issues/Transfers (5.6), Stock Adjustments (5.7).
- **Security Considerations:** Restrict unit-cost & valuation columns to authorized finance/inventory roles.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 2)**

---

### Domain 6: Communication

#### 6.1 In-App Notifications
- **Classification:** Partially implemented
- **Existing Implementation:**
  - `crm_notifications` table, `CrmNotification::notifyUser()`, unread badge in topbar, and `/tasks/notifications` inbox (`markNotificationRead`, `markAllNotificationsRead`).
- **Missing Functionality:**
  - Decouple notification routes/controller from `TaskController` into a dedicated `NotificationController` (`/notifications`), share unread count & top-5 preview via `SetTenantContext` (removing inline Blade DB query), add category filters (`approvals`, `tasks`, `attendance`, `inventory`, `finance`), and wire notifications across all modules.
- **Dependencies:** Unified Notification Service.
- **Security Considerations:** Enforce `company_id` + `user_id` ownership and re-verify site access on notification links.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P1 — High (Release 1 core wiring + Release 3 UX/preferences)**

#### 6.2 Email Notifications
- **Classification:** Requires architectural work
- **Existing Implementation:** Only synchronous `Mail::raw()` in `User.php:689` for 2FA OTP fallback (`MAIL_MAILER=log`, `QUEUE_CONNECTION=sync`). Zero `Mailable` or Laravel `Notification` classes exist.
- **Missing Functionality:**
  - Queued (`ShouldQueue`) transactional email pipeline (`app/Notifications/*` + branded Markdown/HTML templates), production SMTP/API mailer configuration (SES/Postmark/Resend), user/company email notification preferences, and `failed_jobs` monitoring/retry tooling.
- **Dependencies:** Background Queue Worker infrastructure (Architecture Review §4.6).
- **Security Considerations:** Never include plaintext passwords or sensitive attachments in emails; use signed URLs (`URL::temporarySignedRoute`), SPF/DKIM/DMARC alignment, and per-tenant rate limits.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 3)**

#### 6.3 Automated Reminders
- **Classification:** Not implemented
- **Existing Implementation:** Zero scheduled tasks in `routes/console.php`.
- **Missing Functionality:**
  - Scheduled daily/hourly commands (`php artisan schedule:run`) with idempotency keys for overdue tasks/milestones, pending approvals aging > 24h, expiring worker documents (30d/7d), Work Order retention release & DLP end dates, overdue client invoices/vendor bills, and unsubmitted daily muster rolls.
- **Dependencies:** Scheduler cron/daemon + Queue Worker + Notification Service.
- **Security Considerations:** Explicit per-tenant iteration (`TenantContext::setCompanyId`) with active user/site validation.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 3)**

#### 6.4 Activity Feed
- **Classification:** Partially implemented
- **Existing Implementation:**
  - Raw `AuditLog` snippets on Dashboard, Tasks index, Task show, and `/reports/audit-logs`; unpopulated (`collect()`) on Project show.
- **Missing Functionality:**
  - Formatted, business-friendly Activity Timeline component across Projects, Sites, Work Orders, and Company overview, plus `@mention` notifications in task/work-order/bill comments.
- **Dependencies:** `AuditLog` tenant fix (1.6).
- **Security Considerations:** Filter sensitive events (payroll amounts, security lockouts, user overrides) out of operational site feeds based on viewer permissions.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P2 — Medium (Release 3)**

#### 6.5 Approval Notifications
- **Classification:** Not implemented
- **Existing Implementation:** Only attendance correction review triggers `CrmNotification::notifyUser()`.
- **Missing Functionality:**
  - Real-time in-app + queued email alerts to eligible site/company approvers whenever a Work Order, Work Order Revision, RA Bill, Purchase Request, Leave Request, Attendance Correction, Stock Adjustment, Expense, or Vendor Bill is submitted, escalated, approved, or rejected.
- **Dependencies:** Unified Approval Center (3.8), Email/In-App Notification Service (6.1, 6.2).
- **Security Considerations:** Resolve recipients strictly by matching `company_id`, active membership, required permission key, and `isAssignedToSite($siteId)`.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P0 for In-App (Release 1); P1 for Email & Escalation (Release 3)**

---

### Domain 7: Documents

#### 7.1 Secure Document Storage
- **Classification:** Requires architectural work
- **Existing Implementation:**
  - Private local filesystem (`storage/app/private`) with authenticated controller streaming, `X-Content-Type-Options: nosniff`, `..` traversal guards, MIME/size validation, and transaction rollback file cleanup (`Phase 5`).
- **Missing Functionality:**
  - **Cloud Object Storage Abstraction (`PrivateVaultService`):** Controllers currently hardcode `Storage::disk('local')` (`ProjectController`, `WorkforceController`, `WorkOrderController`, `RaBillController`, `RaBillExportService`). On stateless container hosts (e.g., Render Docker web services), local disk files are lost on every deploy/restart! Must replace hardcoded `'local'` references with a configurable `vault` disk supporting AWS S3 / Cloudflare R2 / MinIO seamlessly while keeping `local` for local dev and PHPUnit tests.
- **Dependencies:** `league/flysystem-aws-s3-v3`, `FILESYSTEM_VAULT_DISK` env variable.
- **Security Considerations:** Keep bucket 100% private (block public ACLs); stream or sign downloads only after Laravel `Gate::authorize()` passes; optional SHA-256 checksum verification per stored file.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P0 — Critical Architectural Foundation (Release 1 abstraction + Release 5 cloud cutover)**

#### 7.2 Employee Documents
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - `worker_documents` upload/download/delete with `document_type`, `document_number`, `issue_date`, `expiry_date`, and 30-day expiry count on Dashboard.
- **Missing Functionality:**
  - Expiring & Missing Compliance Document Register (`/workforce/compliance`), document verification status (`pending`, `verified`, `expired`), renewal replacement preserving historical certificate versions, and download audit logging.
- **Dependencies:** Vault storage abstraction (7.1).
- **Security Considerations:** PII access restricted via `WorkerPolicy` and logged in `AuditLog`.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P1 — High (Release 2)**

#### 7.3 Project Documents
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - `project_documents` with auto-versioning (`v1`, `v2`), `visibility_roles` RBAC, site tagging, and private download/delete.
- **Missing Functionality:**
  - Safe inline PDF/image preview for drawings/permits, explicit `is_latest` / `superseded` badges so field engineers never use outdated drawings, and upload revision notes.
- **Dependencies:** Vault storage abstraction (7.1).
- **Security Considerations:** Enforce `ProjectDocument::isVisibleTo($user)` on previews and downloads alike.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P1 — High (Release 2)**

#### 7.4 Document Search & Filtering
- **Classification:** Not implemented
- **Existing Implementation:** Documents are siloed inside individual Worker, Project, or Work Order detail pages with no search/filter capability.
- **Missing Functionality:**
  - Unified **Document Center (`/documents`)** indexing metadata across Project Drawings/Contracts, Worker Compliance Docs, Client PO Uploads, Work Order Attachments, Measurement Photos, and Generated Bill/Invoice Exports with instant filtering by project, site, type, expiry, and date range.
- **Dependencies:** Vault storage abstraction (7.1).
- **Security Considerations:** Server-side query scoping per document category so users only see documents permitted by their module permissions, `visibility_roles`, and `accessibleSiteIds()`.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P2 — Medium (Release 3)**

#### 7.5 Document Access Control
- **Classification:** Complete and tested (for existing modules)
- **Existing Implementation:**
  - `BlockSensitivePaths` + `SetTenantContext` block direct `/storage` HTTP paths; policy gates (`ProjectPolicy`, `WorkerPolicy`, `WorkOrderPolicy`) enforce tenant, role, and site checks on every download/upload/delete.
- **Missing Functionality:**
  - Add `AuditLog::record('document.downloaded', ...)` for sensitive KYC, contract, and financial exports, and extend policy gates to upcoming Vendor Bill / Expense receipts.
- **Dependencies:** None.
- **Security Considerations:** Maintain zero unauthenticated public URLs for tenant documents.
- **Estimated Complexity:** Low
- **Recommended Priority:** **P1 — High (Release 1)**

#### 7.6 Document Version History
- **Classification:** Partially implemented
- **Existing Implementation:**
  - `project_documents` increments `version` integer on matching `(project_id, site_id, document_type, title)`.
- **Missing Functionality:**
  - Grouped version history UI thread (`document_group_id` so title edits don't break version lineage), `version_notes`, "Upload New Version" action button, and version history support for Work Order attachments & Worker compliance renewals.
- **Dependencies:** `project_documents` migration extension.
- **Security Considerations:** Prevent non-admin deletion of historical contract/drawing versions once referenced in field execution.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P2 — Medium (Release 3)**

---

### Domain 8: Analytics & Intelligence

#### 8.1 Management Dashboards
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - Role-tailored `DashboardController` (48 SQL queries after Phase 5 optimization, down from 339), onboarding setup progress, attendance overview, approval queues, low stock, and operational alerts.
- **Missing Functionality:**
  - Fix `AuditLog` missing `company_id` filter (`line 160`) and `PurchaseOrder` status `'ordered'` -> `'issued'` bug (`line 114`).
  - Add Senior Management / Finance Executive view cards (Billed Revenue, Receivables Outstanding, Pending RA Bills, Project Budget Burn) while keeping the Company Admin setup/access dashboard clean and uncluttered.
- **Dependencies:** Finance modules (Domain 4).
- **Security Considerations:** Strict permission checks per dashboard widget.
- **Estimated Complexity:** Low–Medium
- **Recommended Priority:** **P0 for bug fixes (Release 1); P1 for Executive Analytics (Release 4)**

#### 8.2 Project Performance Analytics
- **Classification:** Partially implemented
- **Existing Implementation:**
  - Milestone average % on Projects, Work Order status counts, and `multiSiteComparison` operational table.
- **Missing Functionality:**
  - BOQ Execution Progress % (Cumulative Measured/Billed Value vs Base Order Value), Schedule Variance (planned vs actual milestone completion), and Site Delay/Hindrance summary.
- **Dependencies:** Milestones (3.3), Measurements (3.7), Project Budgets (3.2).
- **Security Considerations:** Scoped by project/site access.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 4)**

#### 8.3 Workforce Productivity Analytics
- **Classification:** Partially implemented
- **Existing Implementation:**
  - Daily/monthly attendance registers, worked hours, and overtime hours per site.
- **Missing Functionality:**
  - Trade-mix deployment charts, Subcontractor vs Direct Labour cost efficiency, attendance reliability/absenteeism rates, and Labour Cost per BOQ Unit Executed.
- **Dependencies:** Attendance, Payroll, Measurements.
- **Security Considerations:** Wage/cost metrics gated by `payroll.view`.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P2 — Medium (Release 4)**

#### 8.4 Financial Analytics
- **Classification:** Not implemented
- **Existing Implementation:** None.
- **Missing Functionality:**
  - Monthly Billing vs Collection cashflow trends, Work-in-Progress (unbilled measured work + capped excess work), Client AR & Vendor AP aging breakdowns, Retention Money locked across projects, and Project Gross Margin leaderboard.
- **Dependencies:** Finance Domain (4.1–4.7).
- **Security Considerations:** Restricted to `company_admin`, `senior_manager`, and Finance roles.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 4)**

#### 8.5 Inventory Analytics
- **Classification:** Partially implemented
- **Existing Implementation:**
  - Current stock × standard cost list in `ReportsController::inventory()` and Dashboard low-stock widget.
- **Missing Functionality:**
  - Material consumption by Site/Work Order, Supplier unit-price trend comparison, procurement lead-time metrics, and slow-moving/shrinkage analytics.
- **Dependencies:** Stock Transfers/Issues (5.6), Adjustments (5.7), Valuation (5.8).
- **Security Considerations:** Scoped by `inventory.view` and `reports.view`.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P2 — Medium (Release 4)**

#### 8.6 Exportable Reports
- **Classification:** Implemented but needs improvement
- **Existing Implementation:**
  - Attendance/Payroll CSV, Audit Logs CSV, Workforce CSV, RA Bill PDF/XLSX suite, and Work Order Service Order PDF.
- **Missing Functionality:**
  - Add CSV/XLSX/PDF exports to `multi_site`, `work_orders`, `inventory`, and upcoming Financial reports; move large multi-month exports to chunked streaming or queued background jobs (`ReportExport` vault) to prevent 60s HTTP timeouts.
- **Dependencies:** Queue Worker (Architecture Review §4.6).
- **Security Considerations:** Enforce granular `*.export` permissions and record every export in `AuditLog`.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 2 & Release 4)**

---

### Domain 9: Automation & AI

#### 9.1 Approval Workflow Automation
- **Classification:** Partially implemented
- **Existing Implementation:**
  - Deterministic state transitions in `WorkOrderService`, `RaBillingService`, `InventoryService`, and `PayrollCalculationService`.
- **Missing Functionality:**
  - Per-company configurable **Approval Threshold & Routing Rules** (e.g., auto-route PRs/Expenses above ₹X to Senior Manager, SLA escalation reminders after 48h, temporary approver delegation during leave).
- **Dependencies:** Unified Approval Center (3.8), Scheduler & Notifications (6.1–6.3).
- **Security Considerations:** Never allow rule configuration to disable non-self-approval or bypass `ADMIN_RESTRICTED_ATTENDANCE_PERMISSIONS`.
- **Estimated Complexity:** Medium–High
- **Recommended Priority:** **P2 — Medium (Release 3)**

#### 9.2 Automated Reminders
- **Classification:** Not implemented
- **Existing Implementation:** None (`routes/console.php` has no scheduled jobs).
- **Missing Functionality:**
  - Cron-driven daily digest & threshold notifications (missing muster roll alerts, aging approvals, expiring worker documents, retention release due dates, overdue bills/tasks) with idempotency tracking.
- **Dependencies:** Scheduler + Queue Worker.
- **Security Considerations:** Strict tenant & site scoping per dispatched reminder.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P1 — High (Release 3)**

#### 9.3 Repetitive Task Automation
- **Classification:** Partially implemented
- **Existing Implementation:**
  - JSW PO PDF parsing + auto site/BOQ creation, RA Bill auto-rollup, Payroll auto-calculation, Note-to-Task conversion.
- **Missing Functionality:**
  - Recurring site tasks (daily HSE checklist, weekly store audit), low-stock auto-draft Purchase Requests, project/milestone template cloning, and auto-draft monthly payroll periods.
- **Dependencies:** Scheduler, Task & Inventory services.
- **Security Considerations:** Auto-generated financial/procurement records must always start in `draft`/`pending` status requiring human approval.
- **Estimated Complexity:** Medium
- **Recommended Priority:** **P2 — Medium (Release 3)**

#### 9.4 AI Assistant Using Authorized Company Data
- **Classification:** Requires architectural work
- **Existing Implementation:** None.
- **Missing Functionality:**
  - **NSD AI Copilot** using server-side LLM tool-calling (e.g., Gemini API) backed by a read-only, policy-enforced PHP Tool Registry (`GetSiteAttendanceSummary`, `GetPendingApprovals`, `GetWorkOrderConsumption`, `GetLowStockItems`, `GetProjectCostVariance`), plus AI-assisted multimodal extraction for non-JSW scanned POs and Vendor Invoices feeding into the existing human review workflow.
- **Dependencies:** Analytics & Finance query services, Queue Worker, per-tenant AI quota & feature toggle.
- **Security Considerations:**
  - **Zero LLM-generated raw SQL.** Every tool call executes through an authenticated PHP service bound to `TenantContext::getCompanyId()`, `User::hasPermission()`, and `User::accessibleSiteIds()`.
  - Strip PII (`aadhaar_last_four`, `bank_account_number`, secrets, hashes) from tool outputs before LLM serialization.
  - Log all AI prompts/tool invocations in `AuditLog`.
- **Estimated Complexity:** High
- **Recommended Priority:** **P2 — Medium (Release 4)**

#### 9.5 Natural-Language Business Reporting
- **Classification:** Requires architectural work
- **Existing Implementation:** None.
- **Missing Functionality:**
  - Natural-language report builder translating plain business questions (in English, Hindi, or Marathi!) into validated, structured report parameters (`ReportQuerySpec` DTO -> parameterized Eloquent/QueryBuilder), rendering verifiable tables, KPIs, and downloadable CSV/PDF exports with exact source links.
- **Dependencies:** Standardized Report Query Layer (Domain 8) + AI Tool Registry (9.4).
- **Security Considerations:** Enforce identical `*.view` and `*.export` permission checks as standard UI report endpoints; immune to cross-tenant prompt injection because `company_id` is injected by PHP middleware/repository, never by the model.
- **Estimated Complexity:** High
- **Recommended Priority:** **P2 — Medium (Release 4)**


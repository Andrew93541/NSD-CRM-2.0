# NSD CRM 2.0 — Architecture & Scalability Review

**Phase:** 5A — Master Product Gap Analysis (Read-Only Audit)  
**Date:** 2026-11-15  
**Repository:** `/Users/andrew/Developer/NSD-CRM-2.0`

---

## 1. Executive Architectural Verdict

**Verdict: PRESERVE AND EXTEND — DO NOT REWRITE.**

NSD CRM 2.0 has a clean, modular Laravel 13 architecture with strong domain boundaries (`App\Services\Workforce`, `App\Services\Operations`, `App\Services\Procurement`, `App\Services\Security`), row-level multi-tenancy (`BelongsToCompany` + `TenantContext`), fine-grained RBAC (`PermissionCatalog` + per-user overrides + site scoping), concurrency-safe pessimistic locking (`lockForUpdate()`), append-only audit logging (`AuditLog`), and 182 passing feature/concurrency tests.

However, transitioning from a **single-container synchronous web app** into a **multi-tenant, high-volume, cloud-deployed SaaS & mobile-ready platform** requires targeted architectural upgrades in **five specific areas**:
1. **Cloud-Ready Private Vault Storage Abstraction** (eliminating ephemeral local container disk data loss).
2. **Tenant-Aware Background Queue & Scheduler Pipeline** (enabling async emails, reminders, heavy exports, and AI OCR).
3. **Pagination, Indexing & Streaming Guardrails** for large workforces (`10,000+` workers) and high-frequency punch/audit tables.
4. **Cross-Tenant Defense-in-Depth Hardening** on non-scoped tables (`activity_logs` and child line-item tables).
5. **Versioned `/api/v1` + Laravel Sanctum Layer** reusing the existing Domain Services for mobile apps and external integrations.

---

## 2. Current Architecture Baseline

| Layer | Current Stack & Pattern | Strengths | Key Constraints Found in Audit |
| :--- | :--- | :--- | :--- |
| **Runtime & Framework** | PHP 8.4 / 8.5, Laravel 13.17, Apache + OPcache (`Dockerfile`) | Fast cold starts (`config:cache`, `route:cache`, `view:cache`), zero heavy framework bloat | `max_execution_time = 60s`, `memory_limit = 256M` constrain synchronous bulk PDF/XLSX/payroll runs |
| **Frontend** | Blade + Alpine.js + Tailwind CSS 4 + Vite 8, Multilingual (`en`, `hi`, `mr`), Dark/Light theme | Zero SPA state drift, fast SSR, mobile-responsive, accessible | Large unpaginated DOM tables on Monthly Attendance Matrix & Kanban Tasks above ~500 rows |
| **Database** | MySQL 8.0 / MariaDB 10.4 / TiDB Cloud Starter (TLS), SQLite in fast tests | Strong FK constraints, `UNIQUE` composite indexes, `lockForUpdate()` sequences | TiDB distributed locking nuances if scaled horizontally; several `->get()` calls lack pagination |
| **Tenancy & Auth** | Session auth + TOTP/Email 2FA + Worker PIN Session + Stateless Site Kiosk Token | Fail-closed `BelongsToCompany` scope (`1 = 0`), Phase 5 request memoization (`339 -> 48` queries) | Static properties on `TenantContext` require explicit lifecycle hooks for Queue Workers / Octane |
| **Storage** | `Storage::disk('local')` (`storage/app/private`) | Outside `public/`, `nosniff` streaming, path-traversal protection, rollback cleanup | Hardcoded `'local'` disk in controllers; **ephemeral disk on Render Docker wipes files on redeploy** |
| **Async / Jobs** | `jobs` & `failed_jobs` tables migrated | Ready-made schema for `database` queue driver | `QUEUE_CONNECTION=sync` in `render.yaml`; zero `app/Jobs`, zero `Mailables`, zero scheduled commands |
| **API Layer** | Web routes only (`routes/web.php`) | Clean `App\Services\*` domain layer decoupled from Blade views | No `routes/api.php`, no `laravel/sanctum`, no API Resources, no webhook signing |

---

## 3. Evaluation Across the 8 Architectural Dimensions

### 3.1 Supporting Multiple Companies (Multi-Tenancy)

**Can the current architecture support it?** **Yes — up to hundreds of companies on a single cluster with targeted hardening.**

- **What Works Well:**
  - Almost all 37 domain models use `BelongsToCompany`, which automatically injects `WHERE table.company_id = ?` on queries and stamps `company_id` on creation.
  - `SetTenantContext` validates active membership in `company_user` and active `companies.is_active` on every request, purging forged session IDs.
  - Sequence tables (`worker_sequences`, `invoice_sequences`) are partitioned by `company_id`.
- **Gaps to Close:**
  1. **`AuditLog` Tenant Scope Fix:** `AuditLog` (`activity_logs`) deliberately omits `BelongsToCompany` so failed unauthenticated logins can record `company_id = null`. While `ReportsController` filters `->where('company_id', $companyId)`, `DashboardController.php` (line 160) does not! **Recommendation:** Add a dedicated ` AuditLog::forCurrentCompany()` local scope (or apply `BelongsToCompany` on `AuditLog` and use `TenantContext::bypass()` exclusively for unauthenticated login failure events).
  2. **Hardcoded Document Prefixes:** `InventoryService` hardcodes `'PR-NSD'`, `'PO-NSD'`, and `'GRN-NSD'` instead of using the active tenant's `$company->code` (`PR-{COMPANY_CODE}-YYYY-###`).
  3. **UI Company Switcher & Tenant Onboarding:** Expose `POST /switch-company` in `layouts/app.blade.php` whenever `$currentUser->companies()->count() > 1`, and build Company Profile Settings (`/admin/company`) + Super Admin Tenant Provisioning.
  4. **Child Line Tables:** `purchase_request_items`, `purchase_order_items`, `inventory_transaction_items`, `work_order_approvals`, and `work_order_attachments` do not carry `company_id` (unlike `ra_bill_items` and `work_order_items` which do). Adding `company_id` to new financial line tables (`vendor_bill_items`, `payment_allocations`, `expense_items`) is recommended for defense-in-depth and fast tenant-level analytics.

---

### 3.2 Supporting Large Numbers of Employees & Workers (`1,000 – 25,000+`)

**Can the current architecture support it?** **Up to ~1,500 workers/company today; requires query pagination & async batching for `5,000 – 25,000+` workers.**

- **Bottlenecks Identified in Code:**
  1. **Unpaginated Monthly Attendance Matrix (`AttendanceService::getMonthWiseAttendanceMatrix` & `AttendanceController::register`):** Loads all matching active `Worker` models plus up to `31 × N` `DailyAttendance` records into PHP memory at once. At `2,000` workers (`62,000` Eloquent models), PHP hits the `256M` memory limit!
     - *Architectural Fix:* Paginate workers (`50` or `100` workers per page) in the UI register, and fetch attendance rows only for `$paginatedWorkers->pluck('id')` using lightweight `DB::table()` projections rather than hydrating 62,000 full Eloquent models.
  2. **Synchronous Full-Company Payroll Calculation (`PayrollCalculationService::calculatePeriod`):** Iterates over every worker in a single synchronous HTTP transaction.
     - *Architectural Fix:* Use batched `chunkById(200)` processing; for periods > `500` workers, dispatch a queued `CalculatePayrollPeriodJob` with progress status polling (`calculating` -> `calculated`).
  3. **Unbounded Worker `<select>` Dropdowns:** `PayrollController::index()`, `TaskController::index()`, and `AttendanceController::shiftsAndLeaves()` load `Worker::where('status', 'active')->get()` into HTML `<option>` tags.
     - *Architectural Fix:* Filter by selected site first and provide an async search/autocomplete endpoint (`/api/v1/workers/search` or Alpine combobox) when worker count > `200`.

---

### 3.3 Supporting Multiple Simultaneous Projects (`50 – 500+` Active Projects)

**Can the current architecture support it?** **Yes, with minor query/pagination refinements.**

- **What Works Well:**
  - Dual hierarchy (`projects` <-> `project_sites` <-> `sites`) cleanly supports both single-site projects and multi-location infrastructure contracts (e.g., highway chainages, plant zones).
  - Phase 5 refactored `ReportsController::multiSiteComparison()` from `5 × N` queries to `O(1)` bulk `GROUP BY site_id` queries.
- **Gaps to Close:**
  1. Paginate `TaskController::index()` (Kanban column limits + "Load more" or date bound for `completed` column) and `ReportsController::workOrders()` (currently `->get()`).
  2. Add `project_id` foreign keys onto `purchase_requests`, `purchase_orders`, `expenses`, and `vendor_bills` (currently PRs/POs only reference `site_id`, requiring a join through `sites` and ambiguity when a site serves multiple linked projects via `project_sites`).

---

### 3.4 Supporting Growing Transaction Volumes (`Millions` of Punches, Ledger Rows & Logs)

**Can the current architecture support it?** **Requires index tuning, cursor pagination, and export streaming.**

- **High-Growth Tables:**
  - `attendance_punches` & `daily_attendances` (~50,000–100,000 rows/month per large tenant)
  - `activity_logs` (~100,000+ rows/month across tenants)
  - `inventory_transactions` & `inventory_transaction_items`
  - `crm_notifications`
- **Recommended Enhancements (No Rewrite Required):**
  1. **Composite Covering Indexes:**
     - `activity_logs`: Add `INDEX(company_id, created_at, id)` and `INDEX(company_id, action, created_at)` (currently only `(company_id, site_id, created_at)` and `(company_id, subject_type, subject_id)` exist, causing filesorts when `site_id` is unfiltered!).
     - `daily_attendances`: Add `INDEX(company_id, attendance_date, site_id)` for company-wide date dashboards.
     - `purchase_orders`: Add `INDEX(company_id, status, site_id)`.
  2. **Cursor / Standard Pagination Everywhere:** Replace `limit(250)` in `ReportsController::auditLogs()` with `paginate(50)` or `cursorPaginate(50)`.
  3. **Chunked Streaming Exports:** Replace `->get()` inside CSV/XLSX exporters (`AttendanceController::exportPayrollCsv`, `ReportsController::exportAuditLogsCsv`) with `->lazyById(1000)` streaming directly to `php://output` or queued export files.
  4. **TiDB / MySQL Compatibility:** Avoid relying on `AUTO_INCREMENT` sequential contiguity for business numbers (already handled properly via `worker_sequences` and `invoice_sequences` with `lockForUpdate()`!). Note that Render demo `docker/render-entrypoint.sh` currently hardcodes a safety check `DB_DATABASE == nsd_crm_demo`—for production rollout (Release 5), separate `render-demo-entrypoint.sh` from `production-entrypoint.sh` while keeping the `DB_DATABASE != nsd_crm_2` guard intact.

---

### 3.5 Secure File Storage

**Can the current architecture support it?** **Locally yes; Cloud Production NO until disk abstraction is completed.**

- **Critical Production Risk:**
  - `Dockerfile` and `render.yaml` run stateless containers with `FILESYSTEM_DISK=local` (`storage/app/private`). Every container rebuild, redeploy, or horizontal scale-out wipes `storage/app/private`, resulting in **permanent loss of uploaded client POs, worker KYC documents, project drawings, measurement photos, and archived tax invoice PDFs**.
  - Five classes hardcode `'local'` instead of reading configuration:
    - `ProjectController.php` (`lines 470, 481, 489, 507, 511, 530`)
    - `WorkforceController.php` (worker documents upload/download/delete)
    - `WorkOrderController.php` (attachments & PO upload PDFs)
    - `RaBillingService.php` (`line 106, 152` measurement photos) & `RaBillController.php` (`line 95, 99`)
    - `RaBillExportService.php` (`line 178` bill PDF/XLSX exports)
- **Target Architecture (`VaultStorage` Service):**
  - Configure a dedicated `'vault'` disk in `config/filesystems.php` defaulting to `'local'` (`storage/app/private`) in development/PHPUnit (`Storage::fake('vault')`) and `'s3'` (AWS S3 / Cloudflare R2 / Backblaze B2 with private bucket ACLs and SSE-S3 encryption) in production.
  - Store `storage_disk` column on all attachment tables (already present on `worker_documents` and `project_documents`; add to `work_order_attachments`, `work_order_uploads`, `bill_exports`, `measurement_entries`) so existing `'local'` records and new `'s3'` records both resolve cleanly without migration downtime.
  - Keep **policy-gated server streaming** (or 60-second `temporaryUrl()` redirects only after `Gate::authorize()` + `AuditLog::record()` succeed) so bucket URLs are never publicly enumerable.

---

### 3.6 Background Jobs, Queues & Scheduled Tasks

**Can the current architecture support it?** **Schema is ready (`jobs`, `job_batches`, `failed_jobs` exist), but runtime & tenant-propagation glue must be added.**

- **Critical Multi-Tenant Queue Pitfall to Prevent:**
  - Currently, `TenantContext` stores `$companyId` in a static PHP property populated from `Auth::user()` and HTTP `session()` inside `SetTenantContext` middleware.
  - **In a background queue worker (`php artisan queue:work`), there is NO HTTP session and `Auth::check()` is `false`!** Any queued job querying a model with `BelongsToCompany` will hit the fail-closed `WHERE 1 = 0` clause unless `TenantContext::setCompanyId($this->companyId)` is set before `handle()` and `TenantContext::reset()` is called in `finally`!
- **Target Queue Architecture:**
  1. Create an abstract `App\Jobs\TenantAwareJob` base class (or job middleware `SetJobTenantContext`) that serializes `public int $companyId`, sets `TenantContext::setCompanyId($this->companyId)` prior to execution, verifies the company is still `is_active`, and calls `TenantContext::reset()` in `JobProcessed` / `JobFailed` listeners in `AppServiceProvider`.
  2. Set `'after_commit' => true` on queue connections in `config/queue.php` so jobs dispatched inside `DB::transaction()` never execute before the database transaction commits!
  3. Run `database` queue driver initially (zero extra Redis cost for small deployments) and support `redis` seamlessly when scaling; add a Worker service + Cron Scheduler (`php artisan schedule:run` every minute) in production deployment config.

---

### 3.7 APIs & Third-Party Integrations

**Can the current architecture support it?** **Requires adding an API transport layer (`routes/api.php` + `laravel/sanctum`), while reusing 90%+ of existing Services & Policies.**

- **Why No Rewrite Is Needed:**
  - Core business logic (`recordPunch`, `recordMusterRoll`, `calculatePeriod`, `recordMeasurement`, `createOrRecalculateRaBill`, `createPurchaseRequest`, `recordGoodsReceipt`) already lives in `App\Services\*` and throws domain exceptions (`DomainException`, `InvalidArgumentException`) rather than returning HTTP redirects!
- **Target Integration Architecture:**
  1. Install `laravel/sanctum` and register `api: __DIR__.'/../routes/api.php'` in `bootstrap/app.php`.
  2. Support two token types:
     - **Mobile User / Worker Tokens:** Scoped to `(user_id, company_id, device_name)` or `(worker_id, company_id, device_id)` with token expiration and instant revocation on user/worker deactivation.
     - **Integration / ERP API Keys (Release 4/5):** Company-scoped read/webhook tokens (e.g., Tally Prime / Zoho Books accounting export, biometric attendance machine webhook).
  3. Build an `ApiSetTenantContext` middleware resolving `company_id` from the Sanctum token abilities/metadata (never trusting an unverified client `company_id` header) and reusing existing Laravel `Policy` classes + `JsonResource` transformers.

---

### 3.8 Future Mobile Applications (Android & iOS Field Apps)

**Can the current architecture support it?** **Yes, once `/api/v1`, Cloud Vault Storage, and Offline Idempotency Keys are added.**

- **Construction Field Realities (Low/Intermittent Connectivity at Sites):**
  - Supervisors marking muster rolls, scanning worker QR badges, recording chainage measurements, or snapping site diary photos often lose 4G/5G connectivity inside plant basements or remote earthwork sites.
- **Mobile Readiness Requirements to Build Into `/api/v1`:**
  1. **Client Idempotency Keys (`X-Idempotency-Key` / `client_uuid`):** Add nullable unique `(company_id, client_uuid)` on `attendance_punches`, `measurement_entries`, `site_notes`, and `tasks` so mobile offline retry queues never create duplicate punches or double measurements.
  2. **Server vs Device Timestamp Separation:** Preserve `punched_at` (authoritative server receipt timestamp) alongside optional `device_captured_at` (signed offline punch timestamp, flagged automatically in `attendance_flags` if drift > 15 minutes for supervisor review!).
  3. **Compact Reference Sync Endpoints:** `GET /api/v1/sync/bootstrap?updated_since=...` returning assigned sites, geofences, active site workers + QR tokens, and active BOQ items for offline caching.
  4. **Push Notification Device Registry:** `user_devices` table (`fcm_token`, `platform`) integrated as an additional channel alongside `CrmNotification` in Release 3/5.

---

## 4. Summary of Architectural Action Items (Zero-Rewrite Evolution)

| ID | Architectural Upgrade | Target Release | Risk Mitigated |
| :--- | :--- | :---: | :--- |
| **ARCH-01** | `VaultStorage` disk abstraction (`local` <-> `s3`/R2) + `storage_disk` columns | Release 1 (code) / Release 5 (S3) | Prevents catastrophic file loss on stateless Docker redeployments |
| **ARCH-02** | `AuditLog` `company_id` global/local scope hardening + sensitive field redaction | Release 1 | Eliminates cross-tenant log leakage risk on non-trait queries |
| **ARCH-03** | Dynamic `{COMPANY_CODE}` document prefixes via unified `DocumentSequenceService` | Release 1 | Replaces hardcoded `-NSD-` prefixes across multi-company tenants |
| **ARCH-04** | Paginated Attendance Matrix & Task queries + composite indexes on `activity_logs` & `daily_attendances` | Release 1 & 2 | Scales smoothly from 100 to 10,000+ workers without OOM (`256M`) |
| **ARCH-05** | `TenantAwareJob` + `after_commit=true` + Queue Worker & Cron Scheduler | Release 3 | Enables safe multi-tenant background emails, reminders, and heavy exports |
| **ARCH-06** | Sandboxed Read-Only AI Tool Registry (`AuthorizedToolExecutor`) | Release 4 | Guarantees zero cross-tenant or unauthorized data exposure to LLM |
| **ARCH-07** | `/api/v1` + `laravel/sanctum` + offline `client_uuid` idempotency + separate Prod entrypoint | Release 5 | Unlocks Android/iOS apps, Tally/biometric webhooks, and live production rollout |


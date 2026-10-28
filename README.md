# NSD CRM 2.0 — Earth Movers & Construction Operations

A production-grade, multi-site operational CRM built from scratch for **NSD Earth Movers & Construction Ltd**, managing 500+ workers, heavy excavation plant, multiple active project sites, 2-tier work order approvals, and distributed materials inventory.

---

## 1. System Architecture & Tech Stack

- **Backend**: Laravel 13.39 (PHP 8.5+)
- **Frontend**: Blade, Tailwind CSS v4, Alpine.js (reactive multi-period rows & modals)
- **Database**: MySQL 9 (`nsd_crm_2` for dev, SQLite `:memory:` for automated testing)
- **Security & Multi-Tenancy**: Fail-closed company isolation via `TenantContext` & `BelongsToCompany` trait; site-level role authorization enforced at policy and controller levels
- **Automated Test Suite**: PHPUnit 12 (100% passing across Static, Unit, Feature, Multi-Tenancy Security, and Workflow layers)

---

## 2. Core Implemented Modules

### Phase A — Foundation & Multi-Tenant Authorization
- **Company & Site Structure**: Multi-site hierarchy linked to client projects (e.g. NHAI NH-48 Highway, Metro Viaduct Package 3).
- **Fail-Closed Isolation**: If tenant context cannot be resolved safely, all database queries fail closed via `1 = 0` raw SQL guard.
- **Site-Level Security**: Supervisors are strictly restricted to their assigned sites. Cross-site submissions return `403 Forbidden`.
- **Role-Based Access Control**: Granular permissions across `super_admin`, `company_admin`, `senior_manager`, `site_manager`, `site_supervisor`, `warehouse_manager`, and `office_staff`.

### Phase B — Workforce Identity & Multi-Site Attendance
- **Three Workforce Categories**:
  1. Direct Employees (`W-EMP-xxx`)
  2. Direct Labourers (`W-LAB-xxx`)
  3. Subcontractor Workers (`W-SUB-xxx`) explicitly linked to named subcontractor companies.
- **Mobile-Friendly Muster Roll**: Touch-friendly supervisor attendance interface.
- **No Fabricated Default Times**: Initial muster starts with blank clock inputs. Unverified hours are never guessed or defaulted to `08:00`/`17:00`.
- **Multiple Work Periods per Worker/Day**: Supervisors can dynamically add multiple shifts on the same day (e.g. morning excavation at Site 1, afternoon grading at Site 2) via "+ Add Work Period".
- **Pairwise Overlap Detection Engine**:
  - Automatically identifies overlapping shifts across the same or different sites.
  - Sets `has_conflict = true` and generates descriptive audit messages.
  - Automatically clears stale conflict flags (`has_conflict = false`) once shifts are adjusted or removed.
- **Non-Double-Counting Payroll Aggregation**:
  - Operational workforce reports count distinct worker IDs, ensuring a worker attending multiple sites in one day is never double-counted as two individuals.
- **Auditable Corrections**: Site supervisors can submit correction requests with prior vs proposed values, reviewed and approved by Site/Senior Managers.

### Phase C — 2-Tier Work Orders & Approvals
- **Order Types**: Client-issued contracts (with external reference tracking) and internal operational tasks.
- **Strict 2-Tier Approval Stepper**:
  1. **Tier 1 (Site Manager)**: Verification of equipment availability and site readiness.
  2. **Tier 2 (Senior Operations VP)**: Final contract authorization before execution.
- **Gated Progression**: Tier 2 approval cannot precede Tier 1; unauthorized users are blocked at policy level.
- **Rejection Tracking**: Requires mandatory justification notes and halts order lifecycle.
- **Immutable Audit Trail**: `work_order_approvals` logs every approval or rejection event with timestamps, actor IDs, and comments.

### Phase D — Procurement & Distributed Stores
- **Material Requisitions**: Site-level and central depot purchase requests.
- **Controlled Emergency Purchase Exception**: Explicit emergency checkbox requiring mandatory operational justification before approval.
- **Purchase Order Issuance**: PO generation only allowed after senior management approval.
- **Goods Receipt Notes (GRN)**: Authorized site supervisors or warehouse storekeepers physically verify incoming deliveries, incrementing live stock balances at destination yards.
- **Central Depots & Site Stores**: Distinct location types (`central_warehouse` and `site_store`) with low-stock threshold monitoring.

### Phase E — Operational Reporting
- **Live Workforce Summary**: Unique headcount breakdown by category (Employee, Direct Labour, Subcontractor) and total man-hours without double counting.
- **Work Orders Funnel**: Active contract orders categorized by lifecycle stage and order type.
- **Inventory Stock Valuation**: Live physical quantities on hand and total valuation based on standard unit rates.

---

## 3. Seeded Demo Accounts (Password: `password`)

| Role | Email | Responsibilities |
|---|---|---|
| **Executive Director** | `admin@nsd.local` | Full organization-wide administration |
| **Senior VP Operations** | `senior.manager@nsd.local` | Tier 2 work order authorization, procurement approvals |
| **Lead Site Manager** | `site.manager@nsd.local` | Tier 1 work order review, site management |
| **Site 1 Supervisor** | `supervisor1@nsd.local` | NH-48 Sector 1 & 2 Daily Muster Roll, site receipts |
| **Site 2 Supervisor** | `supervisor2@nsd.local` | Metro Station 4 Daily Muster Roll |
| **Depot Storekeeper** | `warehouse@nsd.local` | Central logistics depot stock issues & receipts |

---

## 4. Local Setup & Verification

```bash
# 1. Environment Configuration
cp .env.example .env
php artisan key:generate

# 2. Database Migrations & Demo Seeder (MySQL nsd_crm_2)
php artisan migrate
php artisan db:seed

# 3. Compile Frontend Assets (Vite, Tailwind v4, Alpine.js)
npm install
npm run build

# 4. Run Automated Test Suite
php artisan test
```

---

## 5. Automated Test Coverage Summary

- `AttendanceAndConflictTest`: Multi-site shifts, pairwise overlap detection, automatic conflict clearing, blank unverified times, non-double-counting payroll aggregation.
- `WorkOrderTwoTierApprovalTest`: 2-tier approval progression, gate enforcement, rejection reason auditing.
- `ProcurementAndInventoryTest`: Purchase request approval gate, emergency purchase justification enforcement, Goods Receipt Note stock balance increment.
- `TenancySecurityTest`: Fail-closed tenant context, strict company isolation, unassigned site authorization prevention.
- `AuthenticationAndNavigationTest`: Session security, deactivated user lockouts, protected route redirection.
- `ControllerWorkflowTest`: End-to-end dashboard, muster roll submission, work order creation, and reporting page rendering.

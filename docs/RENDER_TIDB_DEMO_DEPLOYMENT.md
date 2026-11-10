# NSD CRM 2.0 — Render Free Web Service + TiDB Cloud Starter Demo Deployment Guide

## 1. Architecture & Isolation Overview

This deployment configuration runs **NSD CRM 2.0** on a **Render Free Docker Web Service** paired with a **TiDB Cloud Starter** MySQL-compatible serverless cluster (`nsd_crm_demo`).

- **Zero Coupling to `nsd_crm_2`:** Both [`docker/render-entrypoint.sh`](file:///Users/andrew/Developer/NSD-CRM-2.0/docker/render-entrypoint.sh) and [`RenderDemoSeeder`](file:///Users/andrew/Developer/NSD-CRM-2.0/database/seeders/RenderDemoSeeder.php) enforce a hard runtime abort if `DB_DATABASE=nsd_crm_2`.
- **Authenticated Access Only:** Public visitors cannot view CRM data without signing in (`/login`) or using a valid Worker/Kiosk credential. Public self-registration is disabled.
- **Database-Backed Sessions & Cache:** `SESSION_DRIVER=database`, `CACHE_STORE=database`, and `users.two_factor_last_step` are persisted in TiDB so logins, worker PIN lockouts, and TOTP replay protection survive Render container restarts.

---

## 2. Step-by-Step TiDB Cloud Starter Setup

1. Sign in to [TiDB Cloud](https://tidbcloud.com/) and create a **Starter** (Free) cluster in the region closest to your Render service (e.g., Singapore `ap-southeast-1` or Oregon `us-west-2`).
2. Once the cluster is created, click **Connect**:
   - **Connection Type:** `Public`
   - **Connect With:** `General` / `MySQL CLI`
   - Copy the generated **Host** (e.g., `gateway01.ap-southeast-1.prod.aws.tidbcloud.com`), **Port** (`4000`), **User** (e.g., `<prefix>.root`), and **Password**.
3. Create the dedicated demo database `nsd_crm_demo` via the TiDB Cloud **SQL Editor** (or MySQL CLI):
   ```sql
   CREATE DATABASE IF NOT EXISTS `nsd_crm_demo`
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;
   ```
4. **Schema Initialization Strategy (Recommended):**
   - **Option A — Automatic on Render Startup (Recommended):** Set `RENDER_AUTO_MIGRATE=true` and `RENDER_AUTO_SEED_DEMO=true` in Render Environment Variables. On container startup, [`docker/render-entrypoint.sh`](file:///Users/andrew/Developer/NSD-CRM-2.0/docker/render-entrypoint.sh) executes `php artisan migrate --force --no-interaction` (applying all 23 migrations including `users.two_factor_last_step`) followed by `php artisan nsd:seed-demo --no-interaction`.
   - **Option B — Manual SQL Import:** Import [`database/sql/nsd_crm_2_cpanel_xampp.sql`](file:///Users/andrew/Developer/NSD-CRM-2.0/database/sql/nsd_crm_2_cpanel_xampp.sql) into `nsd_crm_demo` and run `php artisan nsd:create-admin` or `php artisan nsd:seed-demo`.

---

## 3. Step-by-Step Render Free Web Service Setup

1. Push the demo branch/repository to a dedicated GitHub repository or demo branch.
2. In the [Render Dashboard](https://dashboard.render.com/), click **New +** -> **Web Service** (or **Blueprint** using [`render.yaml`](file:///Users/andrew/Developer/NSD-CRM-2.0/render.yaml)):
   - **Runtime:** `Docker`
   - **Dockerfile Path:** `./Dockerfile`
   - **Instance Type:** `Free`
   - **Health Check Path:** `/healthz`
3. Configure the Environment Variables listed in [`.env.render.example`](file:///Users/andrew/Developer/NSD-CRM-2.0/.env.render.example):
   - Generate a fresh `APP_KEY` locally via `php artisan key:generate --show` and paste it into Render's `APP_KEY` variable.
   - Set `DB_HOST`, `DB_PORT=4000`, `DB_DATABASE=nsd_crm_demo`, `DB_USERNAME`, `DB_PASSWORD`, and `MYSQL_ATTR_SSL_CA=/etc/ssl/certs/ca-certificates.crt`.
   - Set strong passwords in Render for `DEMO_ADMIN_PASSWORD`, `DEMO_MANAGER_PASSWORD`, and `DEMO_STAFF_PASSWORD`.

---

## 4. Storage, Cold-Start & SMTP Limitations on Render Free

1. **Cold Starts (15-Minute Idle Sleep):**
   - Render Free Web Services spin down after 15 minutes of no traffic. The first request after idle takes ~30–50 seconds while the container wakes up.
2. **Ephemeral Local Filesystem (`storage/app/private`):**
   - Render Free does not include a persistent local disk. Uploaded files (`worker-documents`, `project-documents`, `work-order-attachments`, measurement photos) stored on `FILESYSTEM_DISK=local` are cleared whenever the container redeploys or restarts.
   - **RA Bill PDF & Excel Exports (`exportPdf`, `exportXlsx`):** Generated on-the-fly from database records and streamed directly to the browser, so live PDF/Excel generation works reliably across restarts.
   - **Optional Persistent Object Storage:** To persist uploaded documents across container restarts, set `FILESYSTEM_DISK=s3` and configure a free-tier S3-compatible bucket (e.g., Cloudflare R2) using `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`, and `AWS_ENDPOINT`.
3. **Outbound SMTP Blocked on Render Free:**
   - Render Free blocks outbound TCP ports `25`, `465`, and `587`.
   - Set `MAIL_MAILER=log` and `AUTH_ENFORCE_MANAGER_2FA=false` initially.
   - For Manager Two-Factor Authentication demonstrations, use the built-in **RFC 6238 Authenticator App (TOTP)** flow (local SVG QR code + manual Base32 secret entry), which requires zero external network or SMTP calls.

---

## 5. Rollback & Decommissioning Plan

- **Disable Public Access Instantly:** In the Render Dashboard -> **Settings** -> **Suspend Service** (takes effect in <10 seconds without deleting data).
- **Reset Synthetic Demo Data:** Run `php artisan nsd:seed-demo` or recreate `nsd_crm_demo` in TiDB Cloud.
- **Full Teardown:** Delete the Render Web Service (`nsd-crm-2-demo`) and the TiDB Cloud Starter cluster (`nsd_crm_demo`). Because the demo uses an isolated repository/branch, `.env`, `APP_KEY`, and TiDB cluster, deleting the demo environment has **zero impact** on the local/production `nsd_crm_2` system.


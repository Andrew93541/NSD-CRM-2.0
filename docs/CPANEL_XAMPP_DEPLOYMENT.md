# NSD CRM 2.0 — cPanel & XAMPP Deployment Guide

This guide covers deploying **NSD CRM 2.0** on:
1. **Windows XAMPP** (`C:\xampp\htdocs\NSD-CRM-2.0`)
2. **macOS XAMPP** (`/Applications/XAMPP/xamppfiles/htdocs/NSD-CRM-2.0`)
3. **cPanel Shared Hosting** (`public_html` or Subdomain Document Root, without requiring SSH or Node.js)

---

## 1. Verified Runtime & PHP Compatibility

| Component | Minimum Required | Tested / Recommended | Notes |
|---|---|---|---|
| **PHP** | **`8.4.1`** (`^8.4.1 \|\| ^8.5`) | **`8.5.12`** (or `8.4.x`) | Locked `symfony/*` (`v8.1.10`) and `phpunit/phpunit` (`13.4.5`) require `PHP >= 8.4.1`, and `laravel/framework` (`v13.39.0`) requires `PHP >= 8.3`. PHP 8.2 and 8.3 are not supported by the locked Symfony 8.1 tree. |
| **Database** | MySQL `8.0+` or MariaDB `10.4+` | MySQL `8.0+` / MariaDB `10.6+` | Uses `InnoDB` and `utf8mb4_unicode_ci` collation for cross-compatibility between MySQL and MariaDB. |
| **Web Server** | Apache `2.4+` (`mod_rewrite` enabled) or LiteSpeed | Apache `2.4+` / LiteSpeed | Root `.htaccess` + `index.php` fallback included when `DocumentRoot` cannot point to `/public`. |
| **Node.js / Vite** | **Not required on server** | Pre-built in `public/build/` | Production CSS, JS, and fonts are pre-compiled in `public/build/` with relative font paths (`./`). |

### Required PHP Extensions
Enable these in **cPanel MultiPHP INI Editor / Select PHP Version** or **XAMPP `php.ini`**:
- `pdo_mysql`, `mysqli`
- `mbstring`, `iconv`, `intl`
- `openssl`
- `gd` (required by `dompdf/dompdf` and `phpoffice/phpspreadsheet`)
- `zip`, `xml`, `dom`, `simplexml` (required for `.xlsx` Excel generation)
- `fileinfo`, `ctype`, `tokenizer`, `session`

---

## 2. Database Import via phpMyAdmin (No SSH Required)

A complete, GTID-free, MariaDB/MySQL-compatible SQL package containing all **61 tables** and synthetic demo seed data is pre-packaged at:
`database/sql/nsd_crm_2_cpanel_xampp.sql`

1. Open **phpMyAdmin** (via XAMPP Control Panel -> MySQL *Admin*, or **cPanel -> phpMyAdmin**).
2. Create a database (e.g. `nsd_crm_2` on XAMPP, or `cpaneluser_nsdcrm` via **cPanel -> MySQL Database Wizard**) with collation **`utf8mb4_unicode_ci`**.
3. Select the database in phpMyAdmin and click the **Import** tab.
4. Choose `database/sql/nsd_crm_2_cpanel_xampp.sql` and click **Import** (**Go**).
5. Verify that **61 tables** are listed.

### Default Demo Accounts Included in Seed Package
> **Important:** Change these passwords immediately after logging into a live environment, or set `APP_ENV=production` in `.env` (which hides the demo credential shortcuts on `/login`).

| Role | Email | Default Password |
|---|---|---|
| Super Administrator | `superadmin@nsd.local` | `password` |
| Company Administrator | `admin@nsd.local` | `password` |
| Senior Operations Manager | `senior.manager@nsd.local` | `password` |
| Site Manager | `site.manager@nsd.local` | `password` |
| Site Supervisor | `supervisor@nsd.local` | `password` |
| Warehouse Storekeeper | `warehouse@nsd.local` | `password` |
| Office / Compliance Staff | `office@nsd.local` | `password` |

---

## 3. Windows XAMPP Setup (`C:\xampp\htdocs\NSD-CRM-2.0`)

1. **Verify PHP Version:** Ensure your XAMPP installation uses PHP `8.4.1+` or `8.5.x` (`C:\xampp\php\php.exe -v`).
2. **Copy Project Files:** Place the project folder at:
   ```text
   C:\xampp\htdocs\NSD-CRM-2.0
   ```
3. **Create `.env`:** Copy `.env.example` to `.env` and configure:
   ```dotenv
   APP_NAME="NSD Earth Movers & Construction CRM 2.0"
   APP_ENV=local
   APP_KEY=base64:GENERATE_OR_PASTE_32_BYTE_KEY_HERE
   APP_DEBUG=true
   APP_URL=http://localhost/NSD-CRM-2.0

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=nsd_crm_2
   DB_USERNAME=root
   DB_PASSWORD=
   DB_CHARSET=utf8mb4
   DB_COLLATION=utf8mb4_unicode_ci
   ```
   *(If you have terminal access, run `php artisan key:generate` inside `C:\xampp\htdocs\NSD-CRM-2.0`.)*
4. **Import SQL:** Import `database/sql/nsd_crm_2_cpanel_xampp.sql` in `http://localhost/phpmyadmin`.
5. **Open Application:**
   - **Subdirectory URL (works out of the box via root `.htaccess` & `index.php`):** `http://localhost/NSD-CRM-2.0`
   - **Optional VirtualHost (`C:\xampp\apache\conf\extra\httpd-vhosts.conf`):** Point `DocumentRoot "C:/xampp/htdocs/NSD-CRM-2.0/public"` to serve at `http://nsd-crm.test`.

---

## 4. macOS XAMPP Setup (`/Applications/XAMPP/xamppfiles/htdocs/NSD-CRM-2.0`)

1. **Place Project:** Copy the project to `/Applications/XAMPP/xamppfiles/htdocs/NSD-CRM-2.0`.
2. **Set Writable Permissions for Storage & Cache:**
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/NSD-CRM-2.0
   chmod -R 775 storage bootstrap/cache
   ```
3. **Configure `.env`:** Copy `.env.example` to `.env`, set `APP_URL=http://localhost/NSD-CRM-2.0`, and set `DB_DATABASE=nsd_crm_2`.
4. **Import SQL:** Import `database/sql/nsd_crm_2_cpanel_xampp.sql` via `http://localhost/phpmyadmin`.
5. **Access:** Visit `http://localhost/NSD-CRM-2.0/login`.

---

## 5. cPanel Shared Hosting Deployment

### Option A — Recommended: Subdomain or Domain DocumentRoot Pointed to `/public`
1. Upload the project to `/home/<cpanel_user>/nsd_crm_2` (outside `public_html`), or inside `/home/<cpanel_user>/ subdomains/crm`.
2. In **cPanel -> Domains**, set the domain/subdomain **Document Root** to `/home/<cpanel_user>/nsd_crm_2/public`.

### Option B — Shared `public_html` Root (When `DocumentRoot` Cannot Be Changed)
1. Upload the project files directly into `public_html` (or `public_html/crm`), **including the root `.htaccess` and root `index.php`**.
   - In **cPanel File Manager -> Settings (top right)**, check **"Show Hidden Files (dotfiles)"** to ensure `.htaccess` and `.env` are visible.

### Step-by-Step cPanel Configuration
1. **MultiPHP Manager:** Select **PHP 8.4** or **PHP 8.5** for your domain.
2. **MySQL Database Wizard:**
   - Create database `cpaneluser_nsdcrm`.
   - Create database user `cpaneluser_crmuser` with a strong password.
   - Grant **ALL PRIVILEGES** to the user on that database.
3. **phpMyAdmin:** Import `database/sql/nsd_crm_2_cpanel_xampp.sql` into `cpaneluser_nsdcrm`.
4. **Configure Production `.env`:**
   ```dotenv
   APP_NAME="NSD Earth Movers & Construction CRM 2.0"
   APP_ENV=production
   APP_KEY=base64:YOUR_GENERATED_APP_KEY
   APP_DEBUG=false
   APP_URL=https://crm.yourdomain.com

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=cpaneluser_nsdcrm
   DB_USERNAME=cpaneluser_crmuser
   DB_PASSWORD="YOUR_STRONG_DB_PASSWORD"
   DB_CHARSET=utf8mb4
   DB_COLLATION=utf8mb4_unicode_ci

   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   FILESYSTEM_DISK=local
   ```
5. **Directory Permissions:**
   - Set directories to `0755` and files to `0644`.
   - Ensure `storage/` (including `storage/app/private/worker-documents`, `storage/app/private/work-order-attachments`, `storage/app/private/project-documents`, `storage/framework/*`, `storage/logs`) and `bootstrap/cache/` are writable (`0755` or `0775`).

---

## 6. Post-Deployment Security Verification Checklist

Verify that the following URLs return **`403 Forbidden`** in your browser:
- `https://crm.yourdomain.com/.env` -> `403 Forbidden`
- `https://crm.yourdomain.com/composer.json` -> `403 Forbidden`
- `https://crm.yourdomain.com/artisan` -> `403 Forbidden`
- `https://crm.yourdomain.com/storage/app/private/` -> `403 Forbidden`
- `https://crm.yourdomain.com/database/sql/nsd_crm_2_cpanel_xampp.sql` -> `403 Forbidden`


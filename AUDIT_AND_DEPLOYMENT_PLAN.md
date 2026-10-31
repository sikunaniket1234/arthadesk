# Artha-Desk — Audit, API Review, and Production Deployment Plan

**Prepared:** 2026-10-31  
**Scope:** Current repository state vs. `plan.md` / `prd.md` / `architecture.md` / `HISTORY.md`, route/API review, Hostinger deployment, multi-tenant URL strategy, production API hardening, logging, environment switching, and login CAPTCHA.

> This document is a plan and audit, not a claim that the listed items are implemented. Items marked **DONE** exist in code; **PARTIAL** exist but are incomplete or unsafe; **MISSING** do not exist.

---

## 1. Executive Summary

The application is a working PHP 8 + MariaDB/MySQL monolith with a functional POS, sales, returns, inventory movements, basic accounting, GST-aware tax calculation, reports, and a tenant admin area. It is **not production-ready**.

The most important findings:

1. **Authorization is broken on `/admin/*`** — any logged-in tenant user can create/suspend tenants because the admin group only runs `AuthMiddleware`, not a super-admin check.
2. **`/auth/switch-tenant` is unauthenticated and unprotected by CSRF** — anyone can switch the session to any tenant ID, which breaks tenant isolation.
3. **Passwordless demo login remains in production code** — `quick_tenant_id`, `quick_admin`, and the `admin@arthadesk.local` shortcut bypass password verification.
4. **`TemplateController.php` is corrupted** — every `$variable` was replaced with `\`, so the template designer routes fatal.
5. **Several forms submit the wrong CSRF field name** (`csrf_token` instead of `_csrf_token`), so settings/profile/exchange/template saves fail with 419.
6. **No audit logging, no login throttling, no CAPTCHA, no idempotency, no PDF generation, no purchase bills, no role-based permission checks inside `/app`.**
7. **Migrations auto-run on every web request** — unsafe and slow for production.
8. **No `.gitignore`, no `.env` protection, no tests, no backup/rotation scripts.**

A realistic path to a safe pilot is: fix P0 security defects, add CAPTCHA + throttling, add logging, add environment switching, deploy to Hostinger staging, then add subdomain tenancy. Estimated **3–4 weeks to a controlled pilot**, excluding GST/accounting sign-off and feature gaps.

---

## 2. Status vs. the Delivery Plan

| Plan phase | Status | What exists | What is missing / broken |
|---|---|---|---|
| Phase 0 — Requirements & decisions | **PARTIAL** | PRD, architecture, plan, history, demo credentials | Decision log, GST practitioner tax-matrix sign-off, printer/device confirmation, hosting capability checklist, risk register |
| Phase 1 — Foundation & tenant model | **PARTIAL** | Router, PDO, migrations, seeds, login/logout, session rotation, CSRF middleware, tenant context, tenant status enforcement, admin tenant create/list/suspend | `/admin` role check, open tenant switch, passwordless demo login, login throttling, password reset, audit events, logging, error handler, CSRF form bugs |
| Phase 2 — Business setup & catalogue | **PARTIAL** | Business/GST settings, tax categories, units, products/customers/suppliers create + list, GSTIN format validation | Product/customer/supplier edit/delete, image upload, CSV import/export, server-side permission checks, audit logging, pagination/search |
| Phase 3 — Inventory & purchasing | **PARTIAL** | Opening stock, stock movements, adjustments, low-stock, simple valuation | Purchase bills, supplier receiving, supplier payments, stock balance projection/reconciliation report, concurrency lock on adjustments, valuation method sign-off |
| Phase 4 — POS, sales, payments, invoices, returns | **PARTIAL** | POS, server-side tax calc, atomic sale, stock decrement, invoice/payment/journal, invoice list/detail, thermal HTML, returns, exchanges | Idempotency, invoice prefix honored, PDF, void/cancel workflow, split payments, credit balance updates, automated tax/concurrency tests, real printer/Odia PDF validation |
| Phase 5 — Accounting & reports | **PARTIAL** | Journal header/items, balanced sales/expense/return journals, daybook/cashbook, trial balance, GST summary, reports | COGS/inventory posting, P&L report, account mapping UI, reversals workflow, exports, reconciliation fixtures, interstate tax reversal correctness, accounting sign-off |
| Phase 6 — SaaS ops, security, release | **MOSTLY MISSING** | Basic CSRF, bcrypt, prepared statements, tenant-scoped queries | Plans/subscriptions/entitlements, authorization matrix, security testing, backup/restore drill, logging/correlation IDs, performance testing, pilot onboarding, runbook |

### History notes

`HISTORY.md` shows v0.6.0 and v0.7.0 (2026-10-27). `PRODUCTION_READY.md` claims tenant isolation and CSRF are complete, but the audit below shows both are currently broken in specific paths. Treat that checklist as aspirational, not evidence.

---

## 3. Route / API Inventory

There is no `/api` namespace. The app uses server-rendered routes plus three JSON endpoints. All routes are defined in `public/index.php`.

### Public routes

| Method | Route | Auth | CSRF | Notes |
|---|---|---|---|---|
| GET | `/` | No | No | Redirects to `/app/pos` |
| GET | `/auth/login` | No | No | Publicly lists all tenants (info disclosure) |
| POST | `/auth/login` | No | **Not validated** | Demo bypasses exist |
| GET | `/auth/logout` | No | No | GET logout, no CSRF |
| POST | `/auth/switch-tenant` | **No** | **No** | **Critical cross-tenant vulnerability** |
| GET | `/auth/locale` | No | No | Session locale switch |

### Platform admin routes (`AuthMiddleware` only — no super-admin check)

| Method | Route | Intended auth | Actual auth |
|---|---|---|---|
| GET | `/admin/tenants` | super_admin | Any logged-in user |
| POST | `/admin/tenants` | super_admin | Any logged-in user |
| POST | `/admin/tenants/status` | super_admin | Any logged-in user |

### Tenant business routes (`Auth` + `TenantContext` + `TenantStatus` + `CSRF`)

| Method | Route | Type | Notes |
|---|---|---|---|
| GET | `/app/pos` | Page | Product grid + POS |
| POST | `/app/pos/calculate` | JSON | CSRF-exempt; returns tax preview |
| POST | `/app/pos/checkout` | JSON | Atomic sale |
| POST | `/app/pos/quick-customer` | JSON | Creates customer; returns raw exception text on failure |
| GET | `/app/invoices` | Page | List + stats |
| GET | `/app/invoices/{id}` | Page | Detail |
| GET | `/app/invoices/{id}/thermal` | Page | Thermal HTML |
| GET | `/app/returns` | Page | Return list |
| GET | `/app/returns/create` | Page | Return form |
| POST | `/app/returns` | Form | Process return |
| GET | `/app/returns/exchange` | Page | Exchange search |
| POST | `/app/returns/exchange` | Form | Return + new sale (partial) |
| GET | `/app/products` | Page | List + add modal |
| POST | `/app/products` | Form | Create |
| GET | `/app/customers` | Page | List + add modal |
| POST | `/app/customers` | Form | Create |
| GET | `/app/suppliers` | Page | List + add modal |
| POST | `/app/suppliers` | Form | Create |
| GET | `/app/inventory` | Page | Stock + movements |
| POST | `/app/inventory/adjust` | Form | Adjustment |
| GET | `/app/accounting` | Page | Cashbook/khata/expenses/trial balance/GST |
| POST | `/app/accounting/expenses` | Form | Expense + journal |
| GET | `/app/reports` | Page | Sales/GST/low stock/top products |
| GET | `/app/settings` | Page | Business/GST/ops/users/templates |
| POST | `/app/settings` | Form | **CSRF field-name bug** |
| POST | `/app/settings/users` | Form | **CSRF field-name bug**; no role check |
| GET | `/app/templates` | Page | **Controller corrupted** |
| GET | `/app/templates/editor` | Page | **Controller corrupted** |
| POST | `/app/templates/save` | Form | **Controller corrupted + CSRF bug** |
| GET | `/app/profile` | Page | Profile |
| POST | `/app/profile` | Form | **CSRF field-name bug** |

### API-specific findings

- **No rate limiting, no request-size limits, no `Origin`/`Referer` checks, no API token/HMAC, no idempotency key.**
- `pos/calculate` is CSRF-exempt by design but should still be rate-limited.
- `pos/checkout` and `pos/quick-customer` return exception messages directly. PDO/database errors can leak schema details.
- `switch-tenant` accepts an arbitrary `tenant_id` from the browser — never trust this.
- Login is not tenant-scoped by host. Because `users` is unique on `(tenant_id, email)`, the same email can exist in multiple tenants; the current query returns the first match. This must be host-scoped for subdomain multi-tenancy.
- There is no API versioning or deprecation policy.

---

## 4. Critical Defects (Fix Before Any Production Use)

### P0 — Security / broken functionality

| # | Defect | Location | Impact |
|---|---|---|---|
| 1 | Admin routes only require any authenticated user | `public/index.php:46-50`, `AuthMiddleware.php:10-19` | Tenant user can create/suspend tenants |
| 2 | Open tenant switch without auth/CSRF | `public/index.php:42`, `AuthController.php:102-122` | Cross-tenant data access |
| 3 | Passwordless demo login shortcuts | `AuthController.php:29-56`, `Views/auth/login.php:137-171` | Full account takeover of demo/admin |
| 4 | `TemplateController` corrupted | `app/Controllers/TemplateController.php:10-55` | 500 on all template routes |
| 5 | CSRF field name mismatch | `Views/settings/index.php:32,230`, `Views/profile/index.php:33`, `Views/returns/exchange.php:14`, `Views/templates/editor.php:12` | Saves fail with 419 |
| 6 | Auto-migrations on every request | `public/index.php:21-28` | Slowness, schema risk, noise |
| 7 | No audit logging writes | `audit_logs` table only read in `ProfileController.php:16-22` | No forensic trail |
| 8 | No login throttling/CAPTCHA | `AuthController.php` | Brute force |
| 9 | User `is_active` not rechecked after login | `AuthMiddleware.php:10-19` | Deactivated users keep access |
| 10 | Tenant context auto-selects first tenant | `TenantContextMiddleware.php:35-45` | Wrong-tenant fallback |
| 11 | Public tenant list on login | `AuthController.php:17-22`, `Views/auth/login.php` | Info disclosure |
| 12 | JSON endpoints leak exception text | `PosController.php:77-82,126-128` | Schema/DB detail disclosure |
| 13 | Login CSRF token not validated | `public/index.php:40`, `AuthController.php:24` | Login CSRF |
| 14 | Seed password hash not verified | `database/seeds/001_seed_demo_data.sql:26-58` | Possible demo login failure |

### P1 — Correctness / production gaps

| # | Defect | Location |
|---|---|---|
| 15 | Invoice prefix setting ignored (hardcoded `INV-`) | `SalesTransactionService.php:80-82` vs `SettingsController.php:45` |
| 16 | Return tax reversal always split CGST/SGST, never IGST | `ReturnTransactionService.php:195-212` |
| 17 | No idempotency for checkout | `SalesTransactionService.php` |
| 18 | Invoice always marked `paid`; credit balance not updated | `SalesTransactionService.php:98` |
| 19 | No PDF generation; no PDF library | `composer.json` |
| 20 | No purchase bills/receiving | absent |
| 21 | No role/permission enforcement in `/app` | `public/index.php:53-116` |
| 22 | Migration `ADD COLUMN IF NOT EXISTS` is MariaDB-only | `003_settings_exchange_enhancements.sql:6-7,16,20` |
| 23 | No `.gitignore`; `.env` could be committed | repository root |
| 24 | Google Fonts loaded from public CDN | `Views/layouts/app.php:15-17`, `Views/auth/login.php:7` |
| 25 | No tests (empty `tests/Unit`, `tests/Integration`) | `tests/` |
| 26 | No backup/restore or log-rotation scripts | `bin/` |
| 27 | Session lifetime hardcoded, ignores env | `Session.php:17` |
| 28 | Profile update stores full DB row including password hash in session | `ProfileController.php:82-83` |
| 29 | Login lists tenants and admin quick button on same form | `Views/auth/login.php` |
| 30 | No password reset / email delivery | absent |
| 31 | No plan/subscription/entitlement tables | absent |

**Recommended immediate sequence:** #1, #2, #3, #4, #5, #6, #9, #10, #11, #13, then logging/CAPTCHA/throttling (#7, #8).

---

## 5. Hostinger Deployment Plan (Normal Shared/Cloud Hosting)

### 5.1 Hosting plan to choose

Use **Hostinger Business or Cloud hosting**, not the cheapest single-site plan, because you need:

- PHP 8.2/8.3 with `pdo_mysql`, `mbstring`, `intl`, `bcmath`, `opcache`, `fileinfo`, `openssl`, `json`
- MySQL 8 or MariaDB 10.6+
- SSH access (for migrations/backups) or at least WP-CLI-style terminal
- Cron jobs
- Multiple subdomains / wildcard DNS
- Free SSL, daily backups, staging
- `.htaccess`/LiteSpeed rewrite support
- Enough memory/CPU for PDF and reports

Verify the actual plan limits in hPanel before purchase: PHP version, `memory_limit`, `max_execution_time`, DB size/connections, cron frequency, backup retention, wildcard SSL support.

### 5.2 Directory layout (keep app outside the web root)

Hostinger gives each domain a folder like:

```text
/home/uXXXX/domains/arthadesk.in/
├── app/                 # private (sibling of public_html)
├── bin/
├── config/
├── database/
├── storage/
├── tests/
├── composer.json
├── .env                 # chmod 600, outside public_html
└── public_html/         # document root
    ├── index.php        # copied from /public/index.php
    ├── assets/
    └── .htaccess
```

The current code expects `app/`, `config/`, etc. to be siblings of the public entry point (`dirname(__DIR__)`), so this layout works **without changing the bootstrap path**. Do not upload `.env`, `app/`, `config/`, `database/`, `storage/`, `tests/`, `bin/`, or docs into `public_html`.

If Hostinger only allows files inside `public_html`, create a private folder and deny it, then adjust the bootstrap path; but the sibling layout above is strongly preferred.

### 5.3 Deployment steps

1. Create the website/domain in hPanel and enable free SSL.
2. Create a MySQL database and least-privilege user. Grant only that DB.
3. Upload `app/`, `bin/`, `config/`, `database/`, `storage/`, `tests/`, `composer.json` to the domain root (not `public_html`).
4. Upload the contents of `public/` into `public_html/`.
5. Create `.env` from `.env.prod` (see section 8), set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://www.arthadesk.in`, DB/SMTP/CAPTCHA keys.
6. Set permissions: directories `755`, files `644`, `storage/` writable by PHP (`775`), `.env` `600`.
7. Run migrations once via SSH: `php bin/migrate.php`. **Disable auto-migration** (`AUTO_MIGRATE=false`) before going live.
8. Do **not** seed demo tenants in production. Add a `--no-seed` flag and a clean production seed containing only the platform super-admin (with a strong password set during provisioning).
9. Configure cron:
   - `0 2 * * *` — database backup (`bin/backup.php`)
   - `0 3 * * *` — cleanup/retention (`bin/cleanup.php`)
   - `0 4 * * 0` — log rotation (`bin/log-rotate.php`)
   - `*/5 * * * *` — queue/retry jobs (future)
10. Force HTTPS and canonical host (`www` or non-`www`) in `.htaccess`.
11. Smoke test: login, POS sale, invoice, thermal print, return, expense, reports, settings, logout, suspended tenant block.
12. Keep the demo docs (`USERS.md`, `PRODUCTION_READY.md`) out of production.

### 5.4 Database and backups

- One database, shared schema, `tenant_id` on every tenant-owned table (current design). Do not create one DB per shop on shared hosting (connection limits).
- Use InnoDB, `utf8mb4`, `DECIMAL` for money, composite indexes on `(tenant_id, ...)`.
- Enable Hostinger daily backups and add an off-host encrypted copy (`mysqldump --single-transaction | gzip`).
- Test a restore before onboarding paying tenants.
- Keep a migration rollback plan: back up DB before each migration, record migration state, restore on failure.

### 5.5 Email

- Use Hostinger SMTP (or a transactional provider) for password reset, invitations, and critical alerts.
- Add SPF, DKIM, and DMARC records.
- Add a mailer library (PHPMailer) or a small SMTP client; `mail()` alone is unreliable on shared hosting.

### 5.6 Security configuration

- `display_errors=Off`, `log_errors=On`, `error_log` to `storage/logs/php-error.log`.
- Disable directory listing; block dotfiles and sensitive extensions.
- Deny direct access to `app`, `config`, `database`, `storage`, `tests`, `bin`, `.env`, `composer.*`, `*.sql`, `*.md`, `*.log`.
- Add headers: HSTS (after HTTPS verified), CSP, `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Content-Type-Options`.
- Hide `X-Powered-By` and server signature.
- Disable PHP execution in `storage/uploads`.
- Put the platform admin on `admin.arthadesk.in` with IP allowlist and 2FA where possible.
- Use Cloudflare (free) in front for WAF, bot protection, rate limiting, and wildcard SSL (see section 6).

### 5.7 Deployment and rollback process

1. Put the site in maintenance mode.
2. Back up files and DB.
3. Deploy new code (Git pull or ZIP).
4. Run migrations.
5. Clear OPcache (`php -r "opcache_reset();"` or hPanel).
6. Run smoke tests.
7. Remove maintenance mode.
8. If anything fails: restore files and DB backup, clear cache, re-test.

Keep a staging site (`staging.arthadesk.in`) with a separate DB and `noindex`/basic auth. Deploy to staging first.

---

## 6. Domain and Multi-Tenant URL Plan

### 6.1 Recommendation: subdomain per shop (primary)

Use:

- `www.arthadesk.in` / `arthadesk.in` — public marketing site and platform landing.
- `admin.arthadesk.in` — platform super-admin portal.
- `{shop-slug}.arthadesk.in` — each tenant shop, e.g. `maatarini.arthadesk.in`.

Why subdomains:

- The app already uses root-relative URLs (`/app/pos`, `/assets/...`). On subdomains these work unchanged.
- Each shop gets a clean identity and its own login.
- Cookies can stay **host-only** per subdomain, reducing cross-tenant session leakage.
- No per-tenant path rewriting or helper refactor is needed.

### 6.2 DNS and SSL

- `A @ → server IP`
- `CNAME www → @`
- `A admin → server IP` (or `CNAME admin → @`)
- Wildcard `A * → server IP` for shops, **or** create each subdomain in hPanel.

Wildcard SSL is the main constraint. Hostinger's free auto-SSL may not issue `*.arthadesk.in`. Two safe options:

1. **Cloudflare in front** (recommended): Cloudflare Universal SSL covers `*.arthadesk.in`; use Full (strict) with a Hostinger origin certificate. Enable WAF/bot protection.
2. **Folder URLs** as a fallback (section 6.4) if wildcard SSL cannot be obtained.

### 6.3 Tenant resolution (server-side)

Add a `TenantResolverMiddleware` that runs before auth:

1. Read `HTTP_HOST`, strip port, lowercase.
2. Strip `www.`.
3. If host is `arthadesk.in` or `www.arthadesk.in`: platform/marketing; `/admin` only for super_admin; `/app` requires explicit tenant selection by super_admin.
4. If host is `admin.arthadesk.in`: platform admin only.
5. If subdomain is reserved (`www`, `admin`, `api`, `mail`, `ftp`, `cdn`, `static`, `status`, `support`, `blog`, `help`, `staging`): handle accordingly.
6. Otherwise look up `tenants.slug = subdomain` with `status = active`; set tenant on the request/session. If not found or suspended: generic 404/403.
7. **Never trust `tenant_id` from query/body for tenant users.** The host/session is the authority.
8. Scope tenant login by host: `WHERE tenant_id = :hostTenantId AND email = :email`.

Cookie strategy:

- Tenant sessions: host-only (`maatarini.arthadesk.in`), `Secure`, `HttpOnly`, `SameSite=Lax`.
- Platform admin session: host-only on `admin.arthadesk.in`.
- Do not use a `.arthadesk.in` cookie for tenant sessions.

### 6.4 Folder fallback (only if subdomains/wildcard SSL are not possible)

URL: `https://www.arthadesk.in/s/{shop-slug}/`

This requires meaningful refactoring because the app hardcodes root paths in many views:

- Add `APP_BASE_PATH` per request.
- Update `url()`/`asset()` and all hardcoded `/app/...`, `/auth/...`, `/assets/...` links to use `url()`.
- Rewrite `/s/{slug}/(.*)` to `index.php` and preserve the slug.
- Set the session cookie path to `/s/{slug}/`.
- Ensure redirects include the base path.

Because this touches many views, **subdomains are strongly preferred**. Use folders only for an internal pilot or if SSL constraints force it.

### 6.5 Onboarding flow for a new shop

1. Platform admin enters business name, owner contact, GST mode, GSTIN (if any).
2. System generates a slug from the name + short random suffix (3–30 chars, lowercase alphanumeric/hyphen), checks the reserved list and DB uniqueness (`tenants.slug` is unique).
3. Create tenant, tax profile, default chart of accounts, and owner user (invite link or forced password reset).
4. Create the subdomain/DNS record:
   - If wildcard DNS + wildcard SSL exist: instant, no per-shop action.
   - Otherwise create via Cloudflare/Hostinger API or queue a manual step.
5. Send a welcome email with `https://{slug}.arthadesk.in`.
6. Admin tests login and a sample sale before enabling the tenant.
7. Suspension must invalidate access server-side (already handled by `TenantStatusMiddleware`, but recheck admin/switch paths).

### 6.6 Deployment choice summary

| Option | URL | SSL | Code changes | Recommendation |
|---|---|---|---|---|
| Subdomain + Cloudflare wildcard | `shop.arthadesk.in` | Wildcard via Cloudflare | Tenant resolver + host-scoped login | **Recommended** |
| Subdomain + per-shop certs | `shop.arthadesk.in` | One cert per shop | Same + DNS automation | Acceptable, more ops work |
| Folder path | `www.arthadesk.in/s/shop/` | Single domain cert | Path/base-path refactor | Fallback only |
| Query parameter | `www.arthadesk.in/?shop=slug` | Single cert | Minimal but ugly/bad for branding | Do not use |

---

## 7. Production API Hardening Plan ("nobody should know the APIs")

Important truth: **a browser app cannot hide its HTTP endpoints from a determined user.** The URL is in the page source and network tab. The goal is to reduce discoverability, remove unnecessary surface, and make abuse hard. Do not rely on obscurity alone.

### 7.1 Remove and lock down

- Delete demo login buttons and `quick_tenant_id` / `quick_admin` / admin-email bypasses.
- Stop listing all tenants on the login page.
- Require super-admin role for `/admin/*`.
- Require auth + CSRF for `switch-tenant`; allow it only for super_admin and only to tenants they are authorized to open.
- Remove or gate dev-only routes and seed/demo data in production.
- Restrict `/admin` to `admin.arthadesk.in` (host check), plus IP allowlist and 2FA.

### 7.2 Obscurity layer (defense in depth, not security)

- Add an env-driven JSON route prefix, e.g. `API_ROUTE_PREFIX=/x9f2c1` → `/x9f2c1/pos/checkout`. Update the POS JS from a server-rendered config variable, not hardcoded strings.
- Optionally hash internal route names per deployment. Document that this only defeats casual probing.
- Minify/obfuscate JS with esbuild/terser; disable source maps in production; add content-hashed asset filenames.
- Do not expose framework/version headers, stack traces, SQL errors, or debug pages.

### 7.3 Real API controls

- Enforce `POST` for mutations; reject unsupported methods.
- Require `Content-Type: application/json` for JSON endpoints.
- Check `Origin`/`Referer` against `APP_URL` for JSON POSTs.
- Require `X-Requested-With: XMLHttpRequest` and a per-session API token in a header (validated server-side, rotated on login).
- Keep CSRF tokens for all mutations (including login).
- Add per-IP and per-user rate limits: general 60/min, calculate 30/min, checkout 10/min, quick-customer 20/min.
- Add request size limits (e.g., 1 MB) and input length/type validation.
- Add an idempotency key header for checkout (`Idempotency-Key`), stored in an `idempotency_keys` table; return the original result on retry.
- Return generic errors; log details with a correlation ID.
- Add WAF rules (Cloudflare) and bot protection for auth endpoints.
- Version the API (`/v1/...`) and document deprecation.
- Run an external penetration test before onboarding paying tenants.

### 7.4 File/source protection

Block from the web server:

- `.env`, `.git*`, `composer.json`, `composer.lock`, `*.md`, `*.sql`, `*.log`, `*.bak`, `*.zip`
- `app/`, `config/`, `database/`, `storage/`, `tests/`, `bin/`, `vendor/` (if inside web root)
- Directory listing everywhere

Add security headers and disable `TRACE`. Verify from an external browser and `curl` that these paths return 403/404.

---

## 8. Environment Switching with a Key

### 8.1 Recommended model: deploy-time CLI switch (not a web toggle)

Runtime environment switching from the browser is dangerous. Use a key-protected CLI command via SSH/cron.

Files:

- `.env` — active environment (never committed, `chmod 600`)
- `.env.dev` — development template
- `.env.prod` — production template
- `.env.example` — placeholders only
- `storage/backups/env-<timestamp>` — automatic backups

Required variables:

```dotenv
APP_NAME="Artha-Desk"
APP_ENV=production            # development|staging|production
APP_DEBUG=false
APP_KEY=base64:...            # 32 random bytes
APP_URL=https://www.arthadesk.in
APP_BASE_PATH=/
AUTO_MIGRATE=false
SESSION_LIFETIME=7200
LOG_CHANNEL=file
LOG_LEVEL=info
LOG_RETENTION_DAYS=90
AUDIT_RETENTION_DAYS=365
ENV_SWITCH_KEY=...            # long random secret, stored in password manager
DB_HOST=localhost
DB_PORT=3306
DB_NAME=...
DB_USER=...
DB_PASS=...
CAPTCHA_ENABLED=true
CAPTCHA_PROVIDER=turnstile
CAPTCHA_SITE_KEY=...
CAPTCHA_SECRET_KEY=...
CAPTCHA_FAIL_OPEN=false
SMTP_HOST=...
SMTP_PORT=587
SMTP_USER=...
SMTP_PASS=...
SMTP_FROM=no-reply@arthadesk.in
```

### 8.2 `bin/switch-env.php`

Behavior:

1. Usage: `php bin/switch-env.php production --key=<ENV_SWITCH_KEY>`.
2. Verify the key with `hash_equals` against `ENV_SWITCH_KEY` from the currently loaded env (or a root-owned key file).
3. Confirm the target template exists (`.env.prod` / `.env.dev`).
4. Refuse production switch if the key is missing/default or the target has `APP_DEBUG=true`.
5. Back up the current `.env`.
6. Copy the target atomically (`temp` + `rename`), set `600`.
7. Reset OPcache / restart PHP-FPM if available.
8. Write an audit entry: actor, time, old env, new env, IP.
9. Support `--dry-run` and `--force` (with explicit confirmation).

### 8.3 Boot-time guard

In `public/index.php`, after config load, if `APP_ENV === 'production'`:

- Refuse to boot if `APP_DEBUG` is true, `APP_KEY` is missing/short, `APP_URL` is not HTTPS, DB credentials are defaults, or `ENV_SWITCH_KEY` is default.
- Show a generic 500 page and log the reason (never print secrets).
- Disable demo login/seed paths when not in development.

### 8.4 Optional web switch (not recommended)

If a web switch is required, expose `/admin/environment` only on `admin.arthadesk.in`, require super_admin + 2FA + CAPTCHA, require the `ENV_SWITCH_KEY`, restrict by IP, require a typed confirmation phrase, and set `ALLOW_WEB_ENV_SWITCH=false` by default in production. Prefer SSH/CLI.

### 8.5 Helper commands

- `bin/key.php` — generate `APP_KEY`/`ENV_SWITCH_KEY`.
- `bin/health.php` — print safe env summary, DB connectivity, writable dirs, migration status, PHP extensions.
- `bin/check-env.php` — validate the active `.env` against production rules.

---

## 9. Logging Plan

### 9.1 Goals

- No secrets or sensitive PII in logs.
- Every request has a correlation/request ID.
- Errors are traceable from the user-facing reference code to server logs.
- Audit events are durable and separate from debug logs.
- Logs live outside the web root and rotate automatically.

### 9.2 Structure

```text
storage/logs/
├── app-YYYY-MM-DD.log       # info/debug/warnings
├── error-YYYY-MM-DD.log     # exceptions, DB errors, fatals
├── auth-YYYY-MM-DD.log      # login success/failure, throttling, CAPTCHA
├── audit-YYYY-MM-DD.log     # sensitive business/admin events
├── php-error.log            # PHP engine errors
└── access-YYYY-MM-DD.log    # optional app-level request log
```

Use JSON lines:

```json
{"ts":"2026-10-31T10:00:00+05:30","level":"error","env":"production","request_id":"...","tenant_id":12,"user_id":45,"route":"/app/pos/checkout","method":"POST","ip":"...","message":"Checkout failed","context":{"invoice_id":null},"duration_ms":123}
```

### 9.3 Implementation

- Add `app/Core/Logger.php` with `debug/info/warning/error/critical`, channel support, and redaction.
- Bootstrap in `public/index.php`:
  - generate `request_id` (UUID v4);
  - `set_exception_handler`, `set_error_handler`, `register_shutdown_function` for fatal errors;
  - render a generic error page with the request ID in production;
  - log request method/route/duration/status.
- Redact: `password`, `password_hash`, `token`, `csrf`, `authorization`, `cookie`, `secret`, `key`, `card`, `otp`, and mask emails/phones/GSTIN where not needed.
- Trusted proxy handling for client IP behind Cloudflare (`CF-Connecting-IP`) — configure only trusted proxies.
- Log levels via `LOG_LEVEL`; production default `info`, staging `debug`.
- Rotation: daily filenames plus `bin/log-rotate.php` (compress > 7 days, delete > retention). Hostinger cron.
- Audit events to write:
  - login success/failure, logout, CAPTCHA failure, throttling/lockout
  - tenant create/suspend/reactivate/switch
  - user create/role change/deactivate/password reset
  - product/customer/supplier create/update/delete
  - stock adjustment
  - invoice create, return, exchange, void
  - expense/journal post/reversal
  - settings/tax-profile changes
  - environment switch
- Keep `audit_logs` DB table for business audit plus file logs for ops. Both have retention policies.
- Alert on `critical`/5xx spikes via email/webhook.
- Set `display_errors=Off`, `log_errors=On`, `error_log=storage/logs/php-error.log`; recommend disabling detailed error output in UI.
- Retention policy: app logs 90 days, audit logs 365 days (or per client policy); document DPDP Act 2023 / privacy considerations.

---

## 10. CAPTCHA and Login Hardening Plan

### 10.1 Provider choice

| Provider | Notes |
|---|---|
| **Cloudflare Turnstile (recommended)** | Free, privacy-friendly, low friction, works well with Cloudflare; supports managed/non-interactive modes |
| Google reCAPTCHA v2/v3 | Widely known; v3 is score-based; more privacy concerns |
| hCaptcha | Privacy-focused alternative |
| Self-hosted math/image CAPTCHA | No third party; weaker; more maintenance; accessibility work |

Recommendation: **Turnstile**, with a config switch so the provider can be changed without code changes. Include a self-hosted fallback if third-party calls are blocked in some networks.

### 10.2 Add CAPTCHA to all user logins

- Tenant login (`/auth/login` on `{shop}.arthadesk.in`)
- Platform admin login (`admin.arthadesk.in`)
- Any future password reset, invitation acceptance, or registration form

Flow:

1. Render the CAPTCHA widget with the public site key.
2. User submits the form with the CAPTCHA token.
3. Server verifies the token with the secret key, remote IP, and expected action/hostname.
4. If verification fails: generic error, log the event, increment throttle counters, do not reveal why.
5. Only then verify the password.
6. On success: rotate session ID (already done), clear throttle counters, log success.

### 10.3 Rate limiting and lockout

Add a migration for `login_attempts` (or `login_events`):

- Fields: `id`, `tenant_id`, `email`, `ip`, `user_agent`, `success`, `reason`, `created_at`.
- Throttle: 5 failures per 15 minutes per email+IP; 20 failures per hour per IP; 15-minute lockout; exponential backoff.
- Use generic messages to prevent account enumeration.
- Notify admin on repeated failures for the same account/IP.
- Consider 2FA (TOTP) for super_admin and tenant_admin.

### 10.4 Other login hardening

- Validate CSRF on login (currently missing).
- Scope login by host tenant (for subdomains).
- Add honeypot field + minimum form-fill time as lightweight bot defense.
- Keep session fixation protection (`session_regenerate_id(true)`).
- Set `session.use_strict_mode=1`, `Secure`, `HttpOnly`, `SameSite=Lax`.
- Enforce idle timeout from `SESSION_LIFETIME` and an absolute session lifetime.
- Make logout a POST with CSRF (or at least accept POST; GET logout is CSRF-able).
- Do not reveal whether email or password was wrong.
- Lock down the admin login host with IP allowlist/2FA.

### 10.5 CSP and privacy

- Allow Turnstile domains in CSP (`challenges.cloudflare.com`, `*.cloudflare.com`).
- Add a privacy-policy note for CAPTCHA data processing.
- Ensure CAPTCHA fails **closed** in production (`CAPTCHA_FAIL_OPEN=false`); if the provider is unreachable, log and show a retry message.

---

## 11. Implementation Roadmap

| Priority | Work | Effort | Exit criteria |
|---|---|---:|---|
| P0 | Fix admin authorization, open switch-tenant, demo logins, TemplateController, CSRF field names, disable auto-migrate, recheck user active, fix tenant fallback, remove public tenant list, validate login CSRF | 2–3 days | No cross-tenant/admin escalation; template settings work; forms save |
| P0 | Audit logging + structured logger + error handler + correlation IDs | 2 days | Sensitive actions logged; generic error pages with reference ID |
| P1 | CAPTCHA (Turnstile) + login throttling + login_events + password reset email | 2–3 days | Brute force mitigated; reset flow works |
| P1 | Environment switch CLI + config guard + health checks + `.gitignore` | 1–2 days | Prod/dev switch only with key; prod boot guard |
| P1 | Hostinger staging deployment, DB, SSL, cron, backups, smoke tests | 2–3 days | Staging URL works end to end; restore tested |
| P2 | Subdomain tenant resolver + host-scoped login + onboarding flow + wildcard DNS/SSL via Cloudflare | 3–5 days | New shop gets `slug.arthadesk.in` and isolated session |
| P2 | API hardening: remove demo surface, JSON origin/session checks, rate limits, idempotency, error sanitization, headers, asset minify | 2–3 days | Endpoints guarded; no schema leaks; idempotent checkout |
| P2 | Hostinger production cutover + monitoring + runbook | 1–2 days | Production live with rollback plan |
| P3 | Functional gaps: PDF (Dompdf + Odia font), purchase bills, role permissions, product/customer edit/delete, invoice void, split payments, exports, tests | 3–6 weeks | Aligns with PRD acceptance criteria |

**Indicative time to controlled pilot: 3–4 weeks** (P0–P2), assuming decisions are made quickly and GST/accounting sign-off is parallel. Full PRD parity remains 6–10+ weeks depending on scope.

---

## 12. Go-Live Checklist

### Security
- [ ] Admin routes require `super_admin`
- [ ] `switch-tenant` authenticated, CSRF-protected, super-admin only
- [ ] All demo/passwordless login paths removed
- [ ] CSRF field names consistent; login CSRF validated
- [ ] All POST/PUT/DELETE tenant routes tenant-scoped and permission-checked
- [ ] User `is_active` and tenant status rechecked per request
- [ ] CAPTCHA + throttling live on every login
- [ ] `.env` outside web root, `600`; `.gitignore` present
- [ ] Sensitive paths blocked from HTTP; headers/HSTS/CSP set
- [ ] `APP_DEBUG=false`; generic error pages; no stack traces
- [ ] `ENV_SWITCH_KEY` set; prod boot guard active
- [ ] Backup/restore tested; audit logging verified

### Deployment
- [ ] Hostinger plan capabilities confirmed (PHP 8.2+, MySQL, SSH, cron, SSL)
- [ ] App outside `public_html`; only `public/` in web root
- [ ] Production DB + least-privilege user; no demo tenants
- [ ] Migrations run once; auto-migrate disabled
- [ ] HTTPS forced; canonical domain; SSL valid
- [ ] Cron for backup/cleanup/log rotation
- [ ] SMTP/SPF/DKIM/DMARC configured; password reset works
- [ ] Staging deployed and tested
- [ ] Monitoring/uptime + error alerting

### Tenancy
- [ ] Domain `www.arthadesk.in` live
- [ ] `admin.arthadesk.in` live and restricted
- [ ] Wildcard DNS + wildcard SSL (or documented folder fallback)
- [ ] Tenant resolver by host; reserved slugs enforced
- [ ] Host-scoped login; host-only session cookies
- [ ] New-shop onboarding creates URL + owner + accounts
- [ ] Suspension blocks access server-side immediately

### Product
- [ ] GST tax matrix signed off by a qualified practitioner
- [ ] Sale/return/expense/journal reconciliation on sample data
- [ ] Thermal print verified on actual 58/80 mm printer
- [ ] Odia rendering verified in browser and PDF
- [ ] Backup restore + sample financial reconciliation verified
- [ ] Pilot tenant completes a real day of sales

---

## 13. Decisions Needed

1. **CAPTCHA provider:** Cloudflare Turnstile (recommended), reCAPTCHA, hCaptcha, or self-hosted?
2. **Tenant URL strategy:** subdomains (recommended) or folder paths?
3. **Wildcard SSL:** Is Cloudflare acceptable in front of Hostinger? If not, folder fallback?
4. **Hostinger plan:** Business/Cloud with SSH + cron + wildcard DNS? Confirm capabilities.
5. **Environment switching:** CLI-only (recommended) or also a web toggle?
6. **Admin host:** `admin.arthadesk.in` with IP allowlist/2FA?
7. **Email/SMTP provider:** Hostinger SMTP or a transactional provider?
8. **Pilot scope:** which of the P3 functional gaps (PDF, purchases, roles, idempotency) are required before the first paying shop?
9. **GST/accounting sign-off:** who is the qualified reviewer, and when?

---

## 14. Recommended Immediate Next Step

Do **not** deploy the current build to production. Start with the P0 block:

1. Add a `SuperAdminMiddleware` and apply it to `/admin/*`.
2. Move `switch-tenant` into the authenticated/CSRF group and allow only super_admin.
3. Remove demo passwordless login paths.
4. Repair `TemplateController.php` and the CSRF field names.
5. Add the logger/error handler and audit writes.
6. Disable auto-migrations via `AUTO_MIGRATE=false`.

Then add CAPTCHA + throttling, the environment switch key, and deploy to Hostinger staging before adding subdomain tenancy.

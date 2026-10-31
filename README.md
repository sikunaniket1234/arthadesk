# Artha-Desk

A bilingual (English / ଓଡ଼ିଆ) multi-tenant point-of-sale, inventory, GST billing and basic accounting platform for small and medium Indian retail businesses.

Built as a single self-contained PHP 8.1+ application with MySQL/MariaDB — no framework, no build step, no Node toolchain. It is designed to run on ordinary shared hosting (Hostinger-style) as well as a VPS or Docker.

---

## Table of contents

- [What it does](#what-it-does)
- [Feature overview](#feature-overview)
- [Tech stack](#tech-stack)
- [Requirements](#requirements)
- [Quick start](#quick-start)
- [Configuration](#configuration)
- [Project structure](#project-structure)
- [Multi-tenancy](#multi-tenancy)
- [Roles and permissions](#roles-and-permissions)
- [Security](#security)
- [Database](#database)
- [Deployment](#deployment)
- [Operations and cron](#operations-and-cron)
- [Known limitations](#known-limitations)

---

## What it does

Artha-Desk gives every shop its own isolated workspace on a subdomain (`maatarini.arthadesk.in`). A platform administrator creates shops; shop owners log in and run their business from a browser — no install required.

The core loop:

1. **Set up** the business — GST profile, units, tax slabs, product catalogue.
2. **Buy stock** — record purchase bills from dealers, stock is received automatically.
3. **Sell** — ring up sales on the POS with a live tax preview, split payments, credit (khata) sales, thermal print.
4. **Track** — inventory movements, low-stock alerts, customer balances, supplier payables.
5. **Account** — automatic double-entry journals for every sale, return, purchase and expense; trial balance and GST summary.
6. **Report** — sales, GST, stock, expense and purchase reports with CSV export.

---

## Feature overview

### Point of sale
- Touch-friendly product grid with category filtering and bilingual (English/Odia) search
- Live server-side tax calculation — the client never decides the price or tax
- Split tenders: cash + UPI + card in a single sale, plus credit (khata)
- Credit sales require a registered customer; credit limit is enforced server-side
- Idempotent checkout (`Idempotency-Key` header) so a double-tap or network retry can never create two invoices
- Quick customer creation directly from the POS
- 58 mm / 80 mm thermal receipt layout and A4 invoice view
- Negative-stock protection (configurable per tenant)

### Billing and GST
- `TAX_INVOICE`, `BILL_OF_SUPPLY` and `COMMERCIAL_INVOICE` document types
- Configurable invoice numbering prefix (`INV-2026-0001`, per tenant)
- Intra-state (CGST + SGST) vs inter-state (IGST) decided by place of supply
- Regular / composition / unregistered GST registration modes
- Immutable invoice line snapshots — later catalogue edits never rewrite history
- Invoice PDF export (Dompdf) and CSV export
- Void/cancel workflow: restores stock, refunds payments, reverses the ledger, records reason and actor

### Inventory
- Opening stock, receipts, sales, returns and manual adjustments
- Full movement ledger with reference links back to the causing document
- Low-stock and out-of-stock alerts against a per-product reorder level
- Cost price updated on purchase receipt

### Purchasing
- Purchase bill numbering (`PUR-2026-0001`)
- Dealer/GSTIN reference capture
- Automatic stock receipt and cost-price update on save
- Supplier payments with partial settlement, running payable balance
- Purchase and payment journals posted automatically

### Returns and exchanges
- Credit notes against an existing invoice with quantity validation
- Restocking of returned items
- Exchanges: return + replacement sale in one flow
- Refund by cash / UPI / card / credit note

### Accounting
- Double-entry `journal_entries` + `journal_items`, validated to balance before every insert
- Seeded chart of accounts for every new tenant
- Auto-posted journals for sales, returns, purchases, supplier payments, expenses and voids
- Daybook, cashbook, trial balance and GST summary
- Expense vouchers with account mapping

### Reports
- Sales, GST rate breakdown, stock, expenses, returns and purchases
- Top-selling products, low-stock alerts, net profit estimate
- Date-range filtering and CSV export on every report type

### Administration
- Platform admin portal to create, suspend and reactivate shops
- Automatic slug generation, subdomain URL, owner account and full chart of accounts on shop creation
- Role-based access control per module
- Structured JSON logging with per-request correlation IDs
- Append-only audit trail for sensitive actions

### Internationalisation
- English and Odia (ଓଡ଼ିଆ) UI
- Per-tenant default locale and in-session locale switch
- Noto Sans Oriya loaded for Odia rendering

---

## Tech stack

| Layer | Choice |
|---|---|
| Language | PHP 8.1+ (`strict_types` in the bootstrap) |
| Framework | None — custom router, container-less PSR-4 autoloader |
| Database | MySQL 8 / MariaDB 10.6+ via PDO |
| Front end | Vanilla HTML/CSS/JS, no bundler |
| PDF | `dompdf/dompdf` (optional — falls back to a printable page) |
| Server | Apache/LiteSpeed (`.htaccess`), nginx, or PHP built-in server |
| Container | Docker Compose (`php:8.2-apache` + `mariadb:10.11`) |

Only one runtime dependency is declared in `composer.json`: `dompdf/dompdf`. The application works fully without `vendor/` installed — PDF export degrades to a browser print dialog.

---

## Requirements

- PHP **8.1 or newer** with extensions: `pdo_mysql`, `mbstring`, `json`, `openssl`, `session`, `fileinfo`, `dom`
- Recommended: `gd`, `intl`, `bcmath`, `opcache`
- MySQL 8.0+ or MariaDB 10.6+
- Web server with rewrite support
- Composer *(optional, only for PDF export)*

---

## Quick start

### Option A — Docker (recommended for development)

```bash
docker compose up -d
```

Then open <http://localhost:8000>.

The Compose file wires the app to the `mariadb` service (`mariadb:10.11`) on `mariadb:3306` inside the Docker network, with database `arthadesk`, user `arthadesk`, password `arthapass` — matching `.env.dev`. MariaDB is also published to the host on port `3307`, and the app on port `8000`.

### Option B — Manual

```bash
# 1. Clone and enter the project
git clone <repo-url> arthadesk
cd arthadesk

# 2. Install the optional PDF dependency
composer install

# 3. Create your environment file
php bin/key.php          # prints APP_KEY and ENV_SWITCH_KEY
cp .env.example .env     # then edit DB_* and paste the keys

# 4. Create the database
mysql -u root -p -e "CREATE DATABASE arthadesk CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 5. Run migrations (optional demo seed for a first look)
php bin/migrate.php

# 6. Serve
php -S localhost:8000 -t public
```

Open <http://localhost:8000>.

### Option C — Migration only

```bash
php bin/migrate.php                 # apply schema
php bin/check-env.php               # validate .env against production rules
php bin/health.php                  # DB, extensions, writable dirs, migration status
```

---

## Configuration

All configuration comes from environment variables, loaded from `.env` at the project root. **`.env` is git-ignored and must never be committed.**

| Variable | Default | Purpose |
|---|---|---|
| `APP_ENV` | `production` | `development` \| `staging` \| `production` |
| `APP_DEBUG` | `true` | **Must be `false` in production** — the boot guard refuses to start otherwise |
| `APP_KEY` | — | 32-byte encryption/randomness key. Generate with `php bin/key.php` |
| `APP_URL` | — | Must be `https://` outside development |
| `ENV_SWITCH_KEY` | — | Key required to switch environments via CLI |
| `AUTO_MIGRATE` | `false` | Run migrations per request. **Keep `false` in production** |
| `SEED_DEMO` | `false` | Seed demo tenants. **Must be `false` in production** |
| `SESSION_LIFETIME` | `7200` | Session/cookie lifetime in seconds |
| `LOG_LEVEL` / `LOG_RETENTION_DAYS` | `info` / `90` | Logging |
| `AUDIT_RETENTION_DAYS` | `365` | Audit trail retention |
| `DB_HOST` `DB_PORT` `DB_NAME` `DB_USER` `DB_PASS` | — | Database connection |
| `CAPTCHA_PROVIDER` | `math` | `turnstile` or `math`. Production requires `turnstile` unless explicitly overridden |
| `TURNSTILE_SITE_KEY` / `TURNSTILE_SECRET` | — | Cloudflare Turnstile credentials |
| `LOGIN_MAX_ATTEMPTS` / `LOGIN_WINDOW_MINUTES` | `5` / `15` | Login throttling |
| `API_RATE_LIMIT` / `API_CHECKOUT_RATE_LIMIT` | `120` / `30` | Per-minute API limits |
| `TENANCY_BASE_DOMAIN` | `arthadesk.in` | Shop subdomains resolve under this |
| `TENANCY_ADMIN_HOST` | `admin.arthadesk.in` | Platform admin host |
| `TENANCY_ENFORCE_HOSTS` | `true` | Return 404 for unknown hosts |
| `API_ROUTE_PREFIX` | *(empty)* | Optional opaque prefix for JSON endpoints |

Templates live in `.env.example` (placeholders), `.env.dev` (Docker) and `.env.prod` (production). Switch between them with:

```bash
php bin/switch-env.php production --key=<ENV_SWITCH_KEY> [--dry-run]
```

The switch verifies the key with `hash_equals`, refuses templates that have `APP_DEBUG=true` or default credentials, backs up the current `.env` to `storage/backups/`, and replaces it atomically with mode `600`.

### Production boot guard

When `APP_ENV=production`, `public/index.php` refuses to boot — and renders a generic 500 page without leaking details — if:

- `APP_DEBUG` is `true`
- `APP_KEY` is missing or shorter than 32 characters
- `APP_URL` is not `https://`
- `DB_PASS` is empty or a known default
- CAPTCHA is enabled but using the math provider without an explicit override, or Turnstile keys are missing

---

## Project structure

```text
ArthaDesk/
├── public/               # web root — the only folder served
│   ├── index.php         # front controller, boot guard, routes, headers
│   ├── .htaccess         # HTTPS, canonical host, deny lists, headers
│   └── assets/           # css / js
├── app/
│   ├── Controllers/      # HTTP handlers
│   ├── Core/             # Router, Request, Response, Config, Database, Session, View, Logger, MigrationRunner
│   ├── Middleware/        # Auth, TenantResolver, TenantContext, CSRF, Authorization, ApiGuard, SuperAdmin
│   ├── Services/         # Sales/Return services, TaxEngine, AccountingPostingService, Captcha, Audit, Idempotency
│   ├── Views/            # PHP templates, grouped by module
│   ├── Policies/ Repositories/ Validators/ Support/ Localization/
│   └── autoload.php      # PSR-4 fallback autoloader + helpers
├── config/               # app.php, database.php, security.php, captcha.php, tenancy.php
├── database/
│   ├── migrations/       # 001–006 SQL, applied in order, tracked once
│   └── seeds/
├── bin/                  # CLI: migrate, health, check-env, switch-env, key, backup, cleanup, log-rotate
├── storage/              # logs/, cache/, uploads/, receipts/, backups/  (git-ignored, writable)
├── tests/
├── docs/                 # internal documentation (not published)
├── .env                  # active environment, chmod 600, never committed
└── composer.json
```

Everything except `public/` sits **outside the web root** in production.

---

## Multi-tenancy

**Shared schema, one database.** Every tenant-owned table carries `tenant_id`, and every query filters on it. This avoids per-shop connection limits on shared hosting.

| Host | Behaviour |
|---|---|
| `arthadesk.in`, `www.arthadesk.in` | Platform/marketing host |
| `admin.arthadesk.in` | Platform super-admin portal |
| `{slug}.arthadesk.in` | Resolved to `tenants.slug`; tenant login is scoped to that tenant |
| Anything else | 404 when `TENANCY_ENFORCE_HOSTS=true` |

Key guarantees:

- **Host-scoped login.** Credentials are matched as `WHERE tenant_id = :hostTenantId AND email = :email`, so the same email can exist in two shops without cross-contamination.
- **Never trust tenant IDs from the client.** The host and session are authoritative; `switch-tenant` requires an authenticated platform super-admin plus CSRF.
- **Host-only cookies.** Each shop's session cookie is scoped to its own subdomain — no shared `.arthadesk.in` cookie.
- **Server-side suspension.** Suspending a tenant blocks access on the next request.
- **Reserved slugs** (`www`, `admin`, `api`, `mail`, …) cannot be claimed.

A new shop gets `slug`, owner login, tax profile and a full chart of accounts created in one transaction by the platform admin portal.

---

## Roles and permissions

Defined in `app/Services/Authorization.php`, enforced by `AuthorizationMiddleware` on every request.

| Role | Access |
|---|---|
| `super_admin` | Everything, including the platform admin portal and tenant switching |
| `tenant_admin` | Everything within their own shop |
| `cashier` | POS, invoices, returns, customers, profile |
| `inventory_manager` | POS, invoices, returns, products, customers, suppliers, inventory, purchases, profile |
| `accountant` | Invoices, returns, accounting, reports, customers, suppliers, purchases, profile |

Additional server-side rules:

- **Voiding an invoice** requires `super_admin`, `tenant_admin` or `accountant`.
- **Platform admin routes** require `super_admin` (`SuperAdminMiddleware`).
- `users.is_active` and tenant `status` are re-checked on **every** request, not just at login.

---

## Security

| Control | Implementation |
|---|---|
| CSRF | Per-session token; every form uses `_csrf_token`, JSON clients send `X-CSRF-TOKEN` |
| Passwords | bcrypt via `password_hash` / `password_verify` |
| Brute force | `LoginThrottle` + `login_attempts` table (5 per 15 min per email, 20 per hour per IP) |
| CAPTCHA | Cloudflare Turnstile (production) with a self-hosted math fallback for development |
| Bot defence | Honeypot field + minimum form-fill time |
| Login CSRF | Validated; session ID rotated on success (`session_regenerate_id(true)`) |
| Session hardening | `use_strict_mode`, `HttpOnly`, `SameSite=Lax`, `Secure` under HTTPS, env-driven lifetime |
| API | `ApiGuardMiddleware`: JSON `Content-Type`, allowed origin/referer, per-session `X-API-Token`, rate limits |
| Idempotency | `Idempotency-Key` on checkout, stored in `idempotency_keys` |
| Injection | PDO prepared statements throughout; no string-built SQL |
| Error leakage | Generic messages to clients, full detail to `storage/logs` with a request ID users can quote |
| Sensitive paths | `.htaccess` denies dotfiles, `.env`, `.sql`, `.log`, `.md`, `app/`, `config/`, `database/`, `storage/`, `bin/` |
| Headers | HSTS, CSP (allows Turnstile), `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `X-Content-Type-Options`, `X-Robots-Tag: noindex` |
| Secrets | `.env` outside the web root, mode `600`, git-ignored; database password never passed on a shell command line |

Request correlation: every response carries an `X-Request-ID`. Users can quote it and you can find the exact log entry.

---

## Database

Six migrations define **26 tables** across platform/identity, catalogue, sales, inventory, purchasing, accounting and audit domains.

```bash
php bin/migrate.php
```

Migrations are tracked in the `migrations` table and each runs exactly once. Take a database backup before applying new migrations in production (`bin/backup.php`).

Full column-level reference is maintained in `docs/DATABASE_SCHEMA.md`.

Highlights:

- **Money** is always `DECIMAL(12,2)`; quantities are `DECIMAL(12,3)`.
- **Accounting** is true double-entry with a balance check before every insert.
- **`invoice_items` and `journal_items` are immutable** — corrections happen through reversal or void, never mutation.
- **`stock_movements` is the source of truth**; `products.current_stock` is a cached total.
- Financial documents use `ON DELETE RESTRICT`; master data cascades.

---

## Deployment

### Directory layout (app outside the web root)

```text
/home/uXXXX/domains/arthadesk.in/
├── app/  bin/  config/  database/  storage/  tests/
├── composer.json
├── .env                 # chmod 600, outside public_html
└── public_html/         # document root ← contents of public/
    ├── index.php
    ├── assets/
    └── .htaccess
```

The bootstrap resolves paths as siblings of `public/`, so this layout works unchanged.

### Steps

1. Create the domain in your panel and enable SSL.
2. Create a MySQL database with a least-privilege user (grant only that DB).
3. Upload `app/`, `bin/`, `config/`, `database/`, `storage/`, `tests/`, `composer.json` to the domain root (**not** `public_html`).
4. Upload the contents of `public/` into `public_html/`.
5. Create `.env` from `.env.prod` and fill in `APP_KEY`, `ENV_SWITCH_KEY`, `DB_*`, `TURNSTILE_*`, SMTP.
6. Permissions: directories `755`, files `644`, `storage/` writable by PHP (`775`), `.env` `600`.
7. Run migrations once over SSH: `php bin/migrate.php`. Keep `AUTO_MIGRATE=false`.
8. Do **not** seed demo tenants in production (`SEED_DEMO=false`).
9. Verify: `php bin/check-env.php && php bin/health.php`.
10. Smoke test: login → POS sale → invoice → thermal print → return → expense → reports → settings → logout → suspended-tenant block.

### DNS and SSL

| Record | Value |
|---|---|
| `A @` | server IP |
| `CNAME www` | `@` |
| `A admin` | server IP |
| `A *` | server IP (wildcard, for shops) |

Wildcard SSL is the usual constraint on shared hosting. Cloudflare Universal SSL covers `*.arthadesk.in` — run it in front of the origin with **Full (strict)**. Until wildcard DNS/SSL exists, deploy and test on the base domain (`www.arthadesk.in`) only; unknown subdomains intentionally return 404.

### Rollback

1. Enable maintenance mode → 2. back up files and DB → 3. deploy → 4. migrate → 5. clear OPcache → 6. smoke test → 7. disable maintenance. On failure, restore both backups and re-test.

---

## Operations and cron

| Schedule | Command | Purpose |
|---|---|---|
| `0 2 * * *` | `php bin/backup.php` | `mysqldump` to `storage/backups/db-<timestamp>.sql`, pruned after 14 days |
| `0 3 * * *` | `php bin/cleanup.php` | Expire idempotency keys, login attempts, password resets, old audit rows, stale rate-limit cache |
| `0 4 * * 0` | `php bin/log-rotate.php` | Compress logs older than 7 days, delete older than retention |

Other helpers:

```bash
php bin/key.php                  # generate APP_KEY / ENV_SWITCH_KEY
php bin/check-env.php            # validate .env against production rules
php bin/health.php               # connectivity, extensions, writable dirs, migrations
php bin/switch-env.php <env> --key=... --dry-run
php bin/cleanup.php --dry-run    # preview what would be deleted
```

Logs are JSON-lines under `storage/logs/`, split by channel (`app`, `error`, `auth`, `audit`, `security`, `api`, `backup`) and named `channel-YYYY-MM-DD.log`.

---

## Known limitations

Documented so there are no surprises:

- **Odia in PDF** — Odia glyphs require a bundled OFL font (e.g. Noto Sans Oriya) registered with Dompdf. Dompdf runs with `isRemoteEnabled=false` for security, so it will not fetch Google Fonts; Odia currently renders as blank boxes in PDF export. Browser views and thermal printing render Odia correctly.
- **Tests** — `tests/Unit` and `tests/Integration` are placeholders; no automated suite is wired up yet.
- **Email** — password reset and notification emails need SMTP credentials; `mail()` alone is unreliable on shared hosting. No mailer library is bundled yet.
- **Logout is a GET request** without CSRF — low-risk (forced logout only), pending conversion to POST.
- **Fonts load from Google Fonts CDN** — degrades gracefully to system fonts if unreachable.
- **GST logic needs practitioner sign-off** before handling filing-grade data.

---

## Documentation

| Document | Contents |
|---|---|
| [`docs/DATABASE_SCHEMA.md`](docs/DATABASE_SCHEMA.md) | Column-level reference for all 26 tables, relationships, invariants |
| [`docs/ONBOARDING_GUIDE.md`](docs/ONBOARDING_GUIDE.md) | Step-by-step guide for bringing a new shop live |
| [`docs/SHOP_SETUP_GUIDE.md`](docs/SHOP_SETUP_GUIDE.md) | Shareable guide for shop owners: business setup and daily usage |
| [`AUDIT_AND_DEPLOYMENT_PLAN.md`](AUDIT_AND_DEPLOYMENT_PLAN.md) | Security audit findings, Hostinger deployment plan, go-live checklist |

---

## License

Proprietary. All rights reserved.

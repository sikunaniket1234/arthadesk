# Artha-Desk — System Architecture

**Status:** Revised architecture baseline  
**Deployment target:** PHP 8.x + MySQL/MariaDB on Hostinger-compatible hosting  
**UI:** Server-rendered PHP views, Alpine.js for small interactions, responsive CSS; Tailwind may be used via a pinned/build-generated stylesheet, but production should not depend on an unpinned third-party CDN.

## 1. Architecture Principles

1. Tenant isolation is a security boundary, not a UI feature.
2. The server is authoritative for prices, permissions, tax, invoice numbering, stock, and accounting.
3. Sales, payment, inventory, and accounting changes are atomic.
4. Posted financial documents are immutable; corrections use reversals/returns.
5. Prefer a modular monolith over microservices for the first release and shared-hosting constraints.
6. Use schema migrations, automated tests, and repeatable deployments from the beginning.
7. Do not promise offline POS, direct printer hardware control, or statutory tax integrations unless separately implemented and tested.

## 2. Logical Architecture

```text
Browser (mobile / tablet / desktop)
  ├── PHP-rendered pages + responsive CSS
  ├── Alpine.js interactions (cart, dialogs, language toggle)
  └── Browser print / PDF download
             │ HTTPS
             ▼
Web server (Apache / LiteSpeed)
  └── PHP front controller / router
       ├── Authentication + session middleware
       ├── Tenant context resolver + tenant status check
       ├── Authorization / permission policy
       ├── Request validation + CSRF protection
       ├── Application services
       │    ├── Tenant & subscription service
       │    ├── Product / customer / supplier service
       │    ├── Sales / invoice / payment service
       │    ├── Inventory movement service
       │    ├── Accounting posting service
       │    ├── Reporting / export service
       │    └── Audit service
       ├── PDF renderer (e.g. Dompdf after Odia font validation)
       └── Data access layer (PDO prepared statements)
             │
             ▼
       MySQL / MariaDB (InnoDB)
       ├── Tenant and identity data
       ├── Sales and payment records
       ├── Inventory and purchasing records
       ├── Accounting journal and accounts
       ├── Subscription/entitlement records
       └── Audit events
```

## 3. Recommended Application Structure

```text
project-root/
├── public/
│   ├── index.php                 # Single public entry point
│   ├── assets/                   # Versioned CSS/JS/images
│   └── .htaccess                 # Deny sensitive paths; route requests
├── app/
│   ├── Core/                     # Router, Request, Response, View, DB
│   ├── Middleware/               # Auth, TenantContext, TenantStatus, CSRF
│   ├── Policies/                 # Server-side authorization rules
│   ├── Controllers/
│   ├── Services/                 # Sales, stock, accounting, subscriptions
│   ├── Repositories/             # PDO queries; tenant scope mandatory
│   ├── Validators/
│   ├── Views/
│   ├── Localization/             # en.php, or.php
│   └── Support/                  # Money, dates, invoice numbering, logging
├── config/                       # Outside public web root
├── database/
│   ├── migrations/
│   ├── seeds/
│   └── test-data/
├── storage/
│   ├── logs/
│   ├── private-uploads/
│   └── generated-pdfs/           # Private or short-lived files
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── Security/
├── vendor/                       # Composer dependencies; not web-accessible
├── composer.json
├── .env.example
└── README.md
```

If the host requires `public_html` as the document root, expose only the `public/` contents there and keep `app/`, `config/`, `storage/`, migrations, and secrets outside the public directory. If the hosting layout cannot support this, apply a reviewed deny-list and verify it from an external browser; moving to a host with a configurable document root may be safer.

## 4. Request Processing and Tenant Isolation

For every protected request:
1. Start a secure session and authenticate the user.
2. Resolve the user's tenant membership from trusted server-side identity data; never accept `tenant_id` from the browser as the authority.
3. Load current tenant status from the database or a deliberately short-lived cache with invalidation. Do not trust a session-only `tenant_is_active` value indefinitely.
4. Check role/permission for the requested action.
5. Validate request data and CSRF token for state-changing browser requests.
6. Execute a tenant-scoped service operation.
7. Use tenant scope in repository queries and joins; include tenant ID in unique constraints where applicable.
8. Record an audit event for sensitive actions.

For records with parent-child relationships, verify that every referenced object belongs to the active tenant. A valid `invoice_id` from another tenant must never be accepted merely because it exists.

## 5. Database Design and Integrity Rules

Use InnoDB, foreign keys, explicit indexes, `utf8mb4`, UTC timestamps where practical, and migrations. Use `DECIMAL` for money and fixed-precision decimal quantities. Avoid relying on a JSON permissions blob as the only source of authorization truth; normalized roles/permissions or a carefully validated permission model is preferable.

### Core entities
- `tenants`: business account, status, locale, timezone, business settings.
- `plans`, `subscriptions`, `tenant_entitlements`: plan limits, effective dates, subscription status and feature flags.
- `users`, `roles`, `permissions`, `role_permissions`, `user_roles` or equivalent tenant-scoped assignment tables.
- `products`, `units_of_measure`, `tax_categories` / `product_tax_categories`, and effective-dated `tax_rates` or equivalent rules where needed.
- `tenant_tax_profiles` (registration mode, GSTIN if applicable, registered address/state, effective dates, and invoice configuration).
- `customers`, `suppliers`.
- `invoices`, `invoice_items`, `payments`, `payment_allocations` if split/partial payments are supported.
- `purchase_bills`, `purchase_bill_items`, `supplier_payments` for purchase receiving and supplier balances.
- `stock_movements`, optional `stock_balances`, `stock_adjustments`, `sales_returns`, `sales_return_items`.
- `accounts`, `journal_entries`, `journal_items`, `account_mappings`, optional `accounting_periods`.
- `audit_logs`, `login_events`, `idempotency_keys`.

### Important schema rules
- Tenant-owned tables carry `tenant_id` directly where practical. For child tables, enforce tenant consistency through service checks and, where feasible, composite unique keys/foreign keys.
- Unique indexes: `(tenant_id, invoice_number)`, `(tenant_id, sku)` when SKU is not null, and tenant-scoped usernames/emails according to the login design.
- Do not make a tenant's contact email globally unique unless the product requirement explicitly requires one email to belong to only one business.
- Store invoice line snapshots: product name/SKU, unit, quantity, unit price, discount, tax classification, tax rate, taxable value, each tax component/amount, and line total. Store invoice-level registration/document-type and place-of-supply details needed to explain the historical tax calculation.
- Do not rely on current tenant/product tax settings to render old invoices; posted invoice tax snapshots are the source of truth.
- Invoice/payment status transitions must be explicit and validated.
- Stock movement records are append-only after posting; corrections are compensating movements.
- Journal items reference tenant-owned accounts, and each posted journal balances.
- Prefer restrictive foreign keys for historical financial records; avoid cascading deletion of posted invoices or journals.

## 6. GST / Tax Engine Design

GST configuration is a first-class domain module. It must not be implemented as a single `gst_enabled` boolean or a blanket zero-rate toggle.

### Tenant registration profile
- Model registration mode explicitly: `regular`, `composition`, or `unregistered`, with effective dates and an audit trail for changes.
- Store GSTIN and registered business details only where applicable. Validate input format, but do not claim active registration verification without a separately implemented trusted integration.
- Changes to registration mode apply prospectively by default. Existing posted documents retain their original document type and tax snapshots.

### Product and transaction tax treatment
- Keep distinct classifications for taxable supplies, nil-rated supplies, exempt supplies, zero-rated supplies, and non-GST supplies, subject to the approved product/tax matrix.
- The tax engine receives tenant tax profile, product classification, configured tax rate, supplier location, customer/place-of-supply data, supply context, price mode (inclusive/exclusive), discounts, and rounding policy.
- Calculate applicable CGST + SGST/UTGST or IGST based on approved place-of-supply rules. Do not decide solely from a client-side state comparison without validating the transaction context.
- Centralize precision and rounding. Store taxable value, tax component rates/amounts, line totals, invoice totals, classification, and document type as immutable invoice snapshots.
- Unregistered tenants must not collect GST as a separately identified tax or issue a GST tax invoice. Composition workflows must use the approved bill-of-supply/document flow and prevent separate GST collection. The UI must not permit a generic override that bypasses these restrictions.
- Any exceptional tax override must be permission-controlled, reason-required, logged, and constrained by the approved rules. Prefer correction/credit-note workflows over editing a posted invoice.
- Tax reports derive from posted invoice snapshots and must reconcile to tax ledger mappings when accounting posting is enabled.
- Tax rules, classifications, and document templates require review by a qualified Indian GST practitioner before production release. GST portal filing, e-invoicing, e-way bills, and live GSTIN verification are separate integrations and are not implied by this architecture.

## 7. Transaction Boundaries

### Complete sale
Within one database transaction:
1. Validate idempotency key and request payload.
2. Lock/read relevant stock rows or perform a conditional stock decrement to prevent overselling.
3. Allocate a tenant-scoped invoice number safely.
4. Recalculate prices, discounts, tax, and totals on the server.
5. Insert invoice header and immutable line snapshots.
6. Insert payment records and validate the amount/status.
7. Insert stock movements and update balance projection if used.
8. Create balanced journal entry/items through configured account mappings.
9. Mark the idempotency key as completed and commit.

Any failure rolls back the complete sale. Do not rely on client-side calculations or database triggers alone for business orchestration.

### Returns and voids
- Preserve the original invoice and payment history.
- Create return/credit records and compensating stock/accounting entries.
- Require permission and reason; audit actor/time.
- Define refund method and whether returned goods are restockable.

## 8. Accounting Design

- A journal header owns one or more journal items.
- Every posted journal must satisfy `SUM(debit) = SUM(credit)` at the required currency precision.
- Draft entries may be edited; posted entries may not be silently changed.
- Sales account mapping must explicitly define cash/UPI/card/receivable, sales revenue, output tax where applicable, inventory, and cost-of-goods-sold accounts.
- The inventory valuation method (for example, weighted average or FIFO) must be chosen before cost-of-goods-sold reports are treated as reliable.
- Manual journals require validation, permission checks, and audit trail.
- P&L and trial balance must be derived from posted journal items, not ad hoc sums of invoice totals.
- Accounting setup, opening balances, period close, and tax handling require accounting review before production.

## 9. API / Route Conventions

A server-rendered PHP application can use conventional routes and form posts; JSON endpoints may be added for POS interactions. Suggested route groups:

- `/auth/*` — login, logout, password reset.
- `/admin/tenants/*` — platform admin only.
- `/app/settings/*` — tenant settings.
- `/app/products/*`, `/app/customers/*`, `/app/suppliers/*`.
- `/app/pos/*`, `/app/sales/*`, `/app/invoices/*`, `/app/returns/*`.
- `/app/purchases/*`, `/app/inventory/*`.
- `/app/accounting/*`, `/app/reports/*`.

Every route declares authentication, tenant requirement, permission, validation, and audit behavior. Do not expose arbitrary SQL/query execution to tenant users.

## 10. Printing and Localization

- Browser thermal printing is the baseline; verify supported paper width, browser, printer driver, margins, and Odia font rendering on real devices.
- PDF generation must embed or otherwise reliably access a font supporting Odia glyphs. Validate line wrapping and glyph shaping; a PDF library that works for Latin text may not render Odia correctly by default.
- Keep invoice data immutable and render output from stored invoice snapshots.
- Store translation dictionaries separately from business data; use `utf8mb4` throughout.
- Do not promise direct Bluetooth/USB printing from a standard browser without testing a bridge/native component.

## 11. Shared Hosting Constraints

Before committing to shared hosting, verify:
- Supported PHP version/extensions and Composer deployment approach.
- MySQL version, connection limits, database size limits, and backup/restore access.
- Cron availability for scheduled jobs and cleanup.
- Memory, CPU, execution time, upload limits, and PDF rendering capacity.
- Email sending limits and reliable password-reset delivery.
- Document-root control and ability to keep configuration/uploads private.
- SSL/HTTPS, staging environment, logs, and recovery access.

If usage grows beyond measured limits, move the same modular monolith to a VPS/managed PHP host rather than prematurely introducing microservices.

## 12. Security Controls

- PDO prepared statements; never interpolate untrusted values into SQL.
- Output escaping for HTML and PDF templates.
- CSRF tokens for state-changing requests; SameSite cookies are additional protection, not a substitute.
- Session ID rotation on login/privilege changes; secure cookie flags; idle timeout and throttling.
- Role and tenant authorization on every request and object access.
- Validate uploads by content, extension, size, and dimensions; randomize filenames; disallow script execution in upload directories.
- Keep `.env`, logs, database dumps, source maps, and dependency files out of public access.
- Rate-limit login and password reset; avoid account enumeration.
- Audit tenant suspension, role changes, stock adjustments, invoice returns, and journal posting/reversal.
- Store secrets outside version control; least-privilege database account.

## 13. Observability, Backup, and Deployment

- Structured application logs with request/correlation ID; no passwords, session tokens, or unnecessary personal data in logs.
- Staging environment and versioned migration scripts.
- Pre-release checks: tests, migration dry run, permissions review, and rollback plan.
- Automated backups where supported plus periodic off-host encrypted copies if contract/policy allows.
- Perform restoration drills and document recovery point/recovery time expectations with the client.
- Deploy dependencies with Composer lockfile; pin frontend assets; avoid runtime dependency downloads from public CDNs.
- Provide an operational runbook for restore, tenant suspension, password reset, and incident response.

## 14. Testing Strategy

- Unit tests: money/rounding, invoice totals, permission policies, journal balance, stock valuation.
- Integration tests: complete sale, failed sale rollback, return/refund, purchase receipt, journal posting, report reconciliation.
- Security tests: cross-tenant IDOR, privilege escalation, CSRF, SQL injection, XSS, upload abuse, session fixation, suspended-tenant access.
- Concurrency tests: simultaneous invoice allocation and simultaneous sales against limited stock.
- Regression tests: invoice snapshots remain unchanged after product edits; reversals preserve audit history.
- Device tests: mobile/tablet POS, thermal print widths, PDF Odia font rendering.
- Recovery test: restore backup and verify counts and sample financial reconciliations.


### GST-specific test matrix
- Regular taxpayer: taxable intrastate sale with applicable CGST + SGST/UTGST.
- Regular taxpayer: taxable interstate sale with applicable IGST, when the scenario is legally applicable.
- Regular taxpayer: nil-rated, exempt, zero-rated, and non-GST product classifications kept distinct.
- Tax-inclusive and tax-exclusive prices, line and invoice discounts, decimal quantities, and rounding boundaries.
- Unregistered tenant cannot separately charge GST or issue a GST tax invoice.
- Composition tenant follows approved bill-of-supply workflow and cannot separately collect GST as tax.
- Changing a tenant's tax profile does not alter historical invoices or their rendered PDFs.
- Tax override permissions, reason capture, audit logs, returns/credit notes, and tax report reconciliation.
- Cross-tenant tests for GST profiles, invoices, PDF downloads, tax reports, and exports.


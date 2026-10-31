# Artha-Desk — Product Requirements Document (PRD)

**Product:** Artha-Desk (ଅର୍ଥ-Desk)  
**Document status:** Revised baseline for client review — GST configuration included in MVP  
**Target market:** Small and medium Indian businesses, initially retail shops  
**Primary languages:** Odia and English  
**Initial deployment target:** PHP/MySQL-compatible shared hosting, subject to host capability validation

## 1. Product Summary

Artha-Desk is a browser-based billing, inventory, and basic accounting SaaS designed for business owners and non-technical shop staff. The interface prioritizes fast touch-based billing, clear stock status, printable receipts, and bilingual UI/product names. The platform operator manages business tenants, subscription entitlements, and account status.

The MVP is a **retail operations and basic bookkeeping product**, not a claim of complete parity with Tally or a substitute for professional tax/accounting advice. GST, e-invoicing, e-way bills, and statutory filing integrations must be separately specified and validated before being advertised as compliant.

## 2. Goals and Success Criteria

### Goals
- Create and manage multiple business tenants from a platform-admin area.
- Prevent users in one tenant from accessing another tenant's data.
- Let a cashier create a sale quickly on mobile, tablet, or desktop.
- Maintain a traceable stock movement history.
- Print thermal receipts and generate downloadable PDF invoices.
- Provide essential sales, inventory, cashbook, and basic financial reports.
- Support Odia and English interface labels and bilingual product names.
- Make core operations understandable to staff with limited software experience.
- Support different business GST registration statuses and correct item-level tax treatments without treating every zero-tax transaction as the same legal category.

### Initial success measures
- A trained cashier can complete a typical sale without administrator assistance.
- A completed sale, its payment record, stock movements, and accounting entries are committed atomically or not committed at all.
- Tenant-isolation tests pass for all protected modules and object IDs.
- Reports reconcile to source transactions for a defined test dataset.
- Backup restoration is tested before production launch.
- Receipt/PDF output is verified on the actual printer models used by the client.

## 3. Users and Roles

### Platform Super Admin
- Create, activate, suspend, and reactivate tenant accounts.
- Assign subscription plans and limits.
- View tenant-level operational status and audit events.
- Cannot casually edit tenant financial transactions without a separately audited support workflow.

### Business Owner / Tenant Admin
- Manage business profile, invoice settings, users, roles, products, customers, suppliers, and reports.
- Configure opening stock and accounting mappings through controlled setup flows.
- View business-wide financial and inventory summaries.

### Cashier
- Search/select products, maintain a cart, accept permitted payment methods, complete sales, and print receipts.
- Access only the customer and sales functions granted by their role.
- Cannot change historical posted invoices or access accounting administration unless explicitly authorized.

### Inventory / Store Manager
- Maintain product catalogue, record purchases/stock receipts, review stock movements, and approve adjustments according to permissions.

### Accountant
- Review ledger and reports, record permitted expenses/journals, and manage accounting-period workflows if enabled.
- Cannot silently alter posted transactions; corrections use reversal/credit-note workflows.

Roles and permissions must be enforced server-side. Hiding a menu item is not authorization.

## 4. MVP Scope

### 4.1 Platform administration and tenant lifecycle
- Create tenant with business name, owner contact, locale, timezone, currency (INR for initial market), and business profile.
- Activate/suspend/reactivate a tenant.
- Configure plan, user limit, and feature entitlements.
- Suspended tenants cannot perform business operations. Define a safe read-only/export/support policy before suspension; do not rely solely on a stale session flag.
- Keep platform-admin access separate from tenant user access.

### 4.2 Authentication and authorization
- Login/logout, password hashing with PHP `password_hash()` using a supported secure algorithm, password reset flow, session rotation, idle timeout, and login throttling.
- Tenant membership is explicit; user identity and role assignment must be tenant-scoped.
- Enforce authorization and tenant scope on every server request, including exports, PDF downloads, uploaded files, and object-ID lookups.
- Protect state-changing requests against CSRF. Use prepared SQL statements and output escaping.

### 4.3 Business setup
- Business name, address, phone, logo, invoice prefix, receipt footer, language default, financial-year start, and GST/tax configuration. GST configuration is a first-class onboarding step, not a generic on/off switch.
- Customer and supplier records with optional contact and tax-registration fields.
- Initial chart of accounts and account mappings created through a guided setup process.
- Import/export products via validated CSV as a post-MVP enhancement unless the client requires it at launch.

### 4.4 GST configuration and tax rules
- Configure each tenant's GST registration status: `regular`, `composition`, or `unregistered`. Keep the status effective-dated and audit changes; do not infer registration status from whether a GSTIN field is blank.
- For regular taxpayers, capture and validate the format of GSTIN where provided, registered business details, supplier state, place-of-supply inputs, and invoice settings. Format validation is not proof that a GSTIN is active; verification integrations are a separate scope.
- Configure tax treatment at the product/service line level: taxable with an applicable rate, nil-rated, exempt, zero-rated, or non-GST supply, as applicable to the product and current rules. Do not collapse these classifications into a single “GST = 0%” value.
- Support intrastate CGST + SGST/UTGST and interstate IGST calculations where applicable. The tax decision must use the relevant supplier location, place-of-supply/customer details, supply type, and configured tax classification; do not guess based only on a generic state dropdown.
- Define whether prices are tax-inclusive or tax-exclusive, discount allocation, decimal precision, rounding, and line/invoice totals centrally. Snapshot the chosen tax treatment, rate, taxable value, tax components, and totals on the posted invoice so later settings changes do not rewrite history.
- For unregistered businesses, do not charge or separately collect GST as tax and do not label output as a GST tax invoice. Use an appropriate ordinary commercial invoice format.
- For composition taxpayers, support the applicable bill-of-supply workflow and prevent separate GST collection on sales. Enforce relevant restrictions agreed with the client's tax adviser.
- Allow zero tax only when supported by the item's configured treatment and transaction circumstances. Authorized overrides require a reason and audit event; do not offer a casual “turn GST off for this bill” control that bypasses rules.
- Provide tax summary reports and exports based on posted invoice snapshots. GST portal filing, e-invoice, e-way bill, and live GSTIN verification integrations remain out of scope unless separately approved.
- Tax classifications, invoice wording, and rules must be reviewed by a qualified Indian GST practitioner before production use. The application is a calculation and recordkeeping aid, not a guarantee of legal compliance.

### 4.5 Product catalogue
- SKU/barcode (optional but unique within a tenant when provided), English name, Odia name, unit of measure, selling price, cost basis, tax category/rate where applicable, active status, reorder threshold, and optional image.
- Support fractional quantities for units that require them; use fixed-precision decimal quantities rather than assuming every item is an integer count.
- Validate prices and quantities on the server. Client-side totals are for convenience only.
- Product deactivation must not delete historical invoice or stock records.

### 4.6 POS and sales
- Search by product name/SKU/barcode; add/remove cart items; adjust quantity; apply permitted discount; calculate subtotal, tax, and total using server-validated rules.
- Customer selection is optional for walk-in sales, subject to the business's invoice/tax requirements.
- Support configured payment methods such as cash, UPI, card, and credit/receivable. Store payment records separately from invoice headers so split payments can be supported when required.
- Complete sale in a database transaction: validate stock policy, allocate invoice number, persist invoice and items, record payment(s), post stock movements, and post accounting entries.
- Prevent duplicate submissions with an idempotency key or equivalent server-side duplicate protection.
- Posted invoices are immutable except through controlled void/return/credit-note workflows.
- Define whether negative stock is prohibited or permitted per tenant; default to prohibited for the MVP.

### 4.7 Invoice and printing
- Thermal receipt layout for supported 58 mm/80 mm printer/browser combinations.
- PDF invoice for A4/A5 where appropriate.
- Store a stable invoice snapshot of product descriptions, quantities, unit prices, discount, tax, and totals so later catalogue changes do not rewrite history.
- Use tenant-specific, concurrency-safe invoice numbering. A unique constraint must protect `(tenant_id, invoice_number)`.
- Print layouts must escape content and must not expose another tenant's documents.
- Browser print support is the baseline. Direct Bluetooth/USB ESC/POS control is not guaranteed by a normal web page and may require a compatible print bridge or native helper; validate actual hardware before promising it.

### 4.8 Inventory
- Record stock receipts, sales, customer returns, supplier returns, and approved adjustments as stock movements.
- Derive current stock from a maintained balance plus movement ledger, or compute from movements where volume allows. Reconciliation must be possible.
- Use database transactions and row-level locking/conditional updates to avoid overselling under concurrent sales.
- Low-stock and out-of-stock indicators; stock valuation report with a clearly defined valuation method.
- Purchase receiving and supplier purchase bills are required for a useful inventory workflow; detailed purchase-order approvals can be post-MVP.

### 4.9 Basic accounting
- Chart of accounts with tenant-owned accounts and controlled account types.
- Every posted journal must balance: total debits equal total credits.
- Sales posting should use configured payment/receivable accounts and sales/tax accounts. Inventory and cost-of-goods-sold postings depend on the chosen inventory valuation and must be specified and tested before launch.
- Record business expenses and other permitted journal vouchers.
- Posted journals are not edited in place; corrections use reversal and replacement entries with an audit trail.
- Reports: daybook, cashbook, general ledger, trial balance, basic profit and loss, and stock valuation only after underlying data and accounting rules are validated.
- Opening balances, accounting period locks, and tax treatments require explicit setup rules. Do not market these reports as audited financial statements.

### 4.10 Reports
- Sales summary by date range, payment method, cashier, and product.
- Invoice list and invoice details.
- Stock on hand, low stock, movement history, and stock valuation.
- Cashbook/daybook, general ledger, trial balance, and basic P&L when accounting posting is enabled.
- Tenant and date filters must be enforced server-side. Exports must use the same authorization rules as on-screen reports.

### 4.11 Localization and accessibility
- Translate UI labels, validation messages, navigation, and print labels into Odia and English.
- Product names may have both language variants; a missing translation falls back to the available language.
- Store text in `utf8mb4`; verify Odia font rendering in browsers and generated PDFs.
- Use large touch targets, visible focus states, clear error messages, and responsive layouts.

## 5. Out of Scope for MVP
- Full Tally feature parity.
- GST portal filing, live GSTIN verification, e-invoice/e-way bill integrations, or statutory compliance guarantees without a separate requirements and integration project.
- Payroll, manufacturing, multi-warehouse transfers, advanced procurement, bank-feed reconciliation, franchise consolidation, offline-first synchronization, and native mobile apps.
- Direct printer hardware control beyond validated browser-print workflows.
- AI forecasting or automated accounting recommendations.

## 6. Business Rules and Data Integrity
- Every tenant-owned record must have a tenant scope, directly or through a validated parent relationship.
- Use `DECIMAL`, never floating-point types, for monetary values. Use an agreed decimal precision for quantities.
- Monetary calculations, tax determination, discount allocation, and rounding rules must be centralized and tested.
- GST registration status is tenant-configured; tax treatment is configured per product/service and snapshotted per posted invoice line.
- Unregistered and composition taxpayers must not be allowed to collect GST separately as tax; their document types and wording must follow the approved workflow.
- Nil-rated, exempt, zero-rated, and non-GST supplies remain distinct classifications. A 0% amount alone is not a sufficient tax classification.
- Invoice, payment, stock, and journal changes must be transactionally consistent.
- Posted records are immutable; reversals/returns preserve history.
- Use UTC timestamps internally where practical and render using the tenant timezone; document the chosen policy.
- Soft deletion/deactivation is preferred for records referenced by historical transactions.
- Every financial or stock adjustment records actor, timestamp, reason, and before/after values where applicable.

## 7. Security, Privacy, and Operations
- HTTPS required in production.
- Secure session cookie flags (`Secure`, `HttpOnly`, and appropriate `SameSite`), session ID rotation, CSRF tokens, input validation, output encoding, rate limiting, and least-privilege database credentials.
- Store uploads outside executable paths where possible; validate MIME/type, extension, size, and image dimensions; generate server-side filenames.
- Keep secrets out of source control and public web root.
- Prevent direct access to configuration, dependency, backup, and log files using server configuration and deployment checks.
- Maintain audit logs for login events, user/role changes, tenant status changes, stock adjustments, invoice reversals, and journal operations.
- Define retention, export, account closure, and backup policy with the client.
- Test database backups and restoration, not just backup creation.
- Shared hosting limits (CPU, memory, request timeout, cron, outbound email, storage, backup access) must be confirmed with the actual plan before launch.

## 8. Non-Functional Requirements
- Responsive on current mainstream mobile and desktop browsers.
- Core POS screens should remain usable on intermittent but connected networks; the MVP does not promise offline transaction synchronization.
- Paginate large tables and cap export sizes.
- Use indexes for tenant-scoped list queries and common report filters.
- Errors must be logged server-side with a correlation/reference ID; do not show stack traces or SQL errors to users.
- Establish performance targets with representative product/invoice volumes before production sign-off.

## 9. Acceptance Criteria
1. Tenant A cannot read, edit, export, or print Tenant B records by changing a URL, ID, or request payload.
2. Two simultaneous sale requests cannot allocate the same tenant invoice number.
3. A sale either saves invoice, items, payment, stock movement, and journals together, or saves none of them.
4. Duplicate form/API submissions do not create duplicate sales.
5. Posted journal entries balance and a failed posting rolls back the sale.
6. Returns and reversals preserve the original transaction and produce traceable compensating records.
7. Stock on hand reconciles with opening stock plus receipts minus sales/returns/adjustments according to documented rules.
8. Invoice/PDF values match stored invoice snapshots, including tax treatment, taxable value, each tax component, and total, and support Odia text in the tested environment.
9. GST tests cover regular, composition, and unregistered tenants; taxable and non-taxable classifications; intra/inter-state scenarios where applicable; inclusive/exclusive pricing; discounts; rounding; and prohibited GST collection.
10. Tax classification and invoice templates have documented review/sign-off by the client’s qualified GST adviser.
11. Suspended tenant requests are denied server-side, including requests from existing sessions.
12. A backup has been restored into a test environment and the recovery procedure documented.

## 10. Decisions Required Before Build
- Which business types are in the first client rollout (retail only, or multiple sectors)?
- Confirm the client’s GST registration status (regular, composition, or unregistered), GSTIN if applicable, and the GST practitioner-approved tax classification/invoice matrix.
- Confirm product tax rates/classifications, tax-inclusive versus tax-exclusive pricing, discount and rounding policy, intra/inter-state scenarios, and whether any live GST portal integrations are separately required.
- Is credit sales/receivables required at launch? Are split payments required?
- What units need fractional quantities?
- Is negative stock allowed?
- Which thermal printer models, browsers, and devices will be supported?
- What is the accounting basis and inventory valuation method?
- What hosting plan, backup access, email delivery, and expected tenant/transaction volume are available?
- What subscription/payment collection workflow will be used for SaaS plans?

## 11. Suggested Delivery Milestones
1. Requirements confirmation and accounting rules.
2. Secure tenant/auth foundation and schema migrations.
3. Product/customer/supplier setup and stock ledger.
4. POS, invoices, payments, printing, and return workflows.
5. Accounting posting and reconciled reports.
6. Security, concurrency, tenant-isolation, backup/restore, and device testing.
7. Pilot with one tenant, reconcile real sample transactions, then controlled rollout.

# Artha-Desk — Implementation Plan

**Status:** Revised delivery plan  
**Assumption:** One developer or a small team; timeline depends on client decisions, accounting validation, and shared-hosting constraints. Estimates are planning ranges, not a delivery guarantee.

## 1. Delivery Strategy

Build Artha-Desk as a modular PHP monolith with MySQL/MariaDB. Keep the first release focused on retail billing, traceable inventory, printing, and basic accounting. Validate the accounting model and tenant isolation before polishing the UI or onboarding multiple paying businesses.

The original eight-day plan is too aggressive for a production SaaS. An eight-day build can produce a clickable prototype or limited proof of concept, but not a defensible multi-tenant billing/accounting product with adequate tests and operational readiness.

## 2. Phase 0 — Requirements and Decisions (2–4 working days)

### Tasks
- Confirm first customer/business type and whether launch is retail-only.
- Confirm invoice format, GST registration modes (regular/composition/unregistered), tax classifications, invoice wording, payment methods, credit sales, split payments, returns, and cancellation rules. Obtain a GST practitioner-approved tax matrix before coding tax calculations.
- Agree negative-stock policy, units of measure, stock valuation method, opening balances, and accounting account mappings with a qualified accountant/client representative.
- Confirm supported browsers/devices and actual 58 mm/80 mm printer models.
- Confirm hosting plan, PHP extensions, database limits, backup access, document-root layout, email delivery, cron, and staging capability.
- Define tenant plan limits and suspension/export policy.
- Create acceptance scenarios using sample transactions, including GST and non-GST cases.
- Document tax-inclusive/exclusive pricing, discount allocation, rounding, intra/inter-state treatment, zero-tax classifications, and prohibited tax collection by registration mode.

### Deliverables
- Approved PRD and decision log.
- Sample invoice/receipt and report mockups.
- Accounting posting matrix.
- Hosting capability checklist and risk register.

### Exit criteria
No unresolved decisions that affect core schema, GST registration modes, tax calculation/invoice wording, or accounting postings; the tax matrix has named client/adviser approval.

## 3. Phase 1 — Project Foundation and Secure Tenant Model (4–6 working days)

### Tasks
- Set up repository, Composer, environment configuration, migrations, seeds, test harness, and deployment notes.
- Implement front controller/router, PDO connection, error handling, logging, and base layout.
- Implement login/logout, secure session handling, CSRF, password reset approach, and login throttling.
- Create tenant, user, role, permission, plan, subscription, and entitlement schema.
- Implement tenant context resolution and tenant status enforcement.
- Build platform-admin tenant create/suspend/reactivate screens.
- Add audit events for tenant status and user/role changes.

### Deliverables
- Working staging deployment.
- Tenant administration and login.
- Cross-tenant security test suite.

### Exit criteria
Cross-tenant access tests pass, and suspended tenants are blocked server-side even with an existing session.

## 4. Phase 2 — Business Setup and Catalogue (4–6 working days)

### Tasks
- Business profile and invoice configuration, including effective-dated GST registration status and GSTIN/business details where applicable.
- Configure tax categories and product-level tax treatment; distinguish taxable, nil-rated, exempt, zero-rated, and non-GST supplies.
- Implement settings validation and an audited workflow for changing registration status; preserve historic invoice snapshots.
- Products, SKU/barcode, bilingual names, units, price/cost, tax category, stock threshold, active status, image upload.
- Customers and suppliers.
- Initial chart of accounts and account mapping setup.
- Search, pagination, validation, and permission checks.
- CSV import/export only if required for pilot; otherwise defer.

### Deliverables
- Tenant setup flow including GST setup.
- Product/customer/supplier management with tax classification fields.
- Secure image handling.

### Exit criteria
Tenant-scoped CRUD tests pass; historical references cannot be broken by deleting products/customers; tax settings and product classifications are validated and audit logged.

## 5. Phase 3 — Inventory Ledger and Purchasing (4–7 working days)

### Tasks
- Stock movement schema and service.
- Opening stock and approved adjustment workflows with reason/audit fields.
- Purchase bill and receiving workflow for supplier deliveries.
- Stock balance projection if needed, reconciliation query/report, and low-stock alerts.
- Concurrency-safe stock decrement/receipt handling.
- Choose and implement the agreed inventory valuation method before publishing valuation/COGS reports.

### Deliverables
- Stock movement history and on-hand report.
- Purchase receiving and adjustment workflows.
- Inventory reconciliation tests.

### Exit criteria
Opening stock + receipts − sales + restockable returns ± adjustments reconciles with stock on hand for test cases.

## 6. Phase 4 — POS, Sales, Payments, Invoices, and Returns (6–9 working days)

### Tasks
- Touch-friendly POS, product search, cart, quantity controls, discounts, totals, and language switch.
- Central server-side tax engine: determine tax from tenant registration mode, product classification, configured rates, supplier/customer/place-of-supply data, and transaction context.
- Support applicable CGST+SGST/UTGST or IGST calculation, tax-inclusive/exclusive pricing, discount allocation, and documented rounding.
- Block separate GST collection and GST tax-invoice labels for unregistered/composition flows as applicable; use the approved document type.
- Persist immutable tax snapshots on invoice header/lines; audit any permitted override with reason.
- Automated tax test matrix covering all supported registration modes and tax treatments.
- Server-side price/tax/total calculation.
- Concurrency-safe tenant invoice numbering.
- Atomic sale transaction covering invoice, line snapshots, payment records, stock movements, and accounting posting.
- Idempotency protection for double-click/retry submissions.
- Invoice list/detail, receipt print, PDF generation, and controlled return/void workflow.
- Validate Odia font support in PDF and test the actual printer/browser combination.

### Deliverables
- End-to-end sale flow.
- Receipt/PDF templates.
- Return/refund flow.

### Exit criteria
Sale rollback, duplicate submission, simultaneous sale, invoice snapshot, return, and print tests pass. Tax test cases pass and sample invoice formats are reviewed by the client’s GST adviser.

## 7. Phase 5 — Accounting and Reconciled Reports (5–8 working days)

### Tasks
- Journal entry/item model, posting service, and balance validation.
- Account mapping for payment methods, sales, output tax by applicable component, inventory, and COGS.
- Reconcile tax summaries to immutable invoice snapshots and tax-related ledger postings; ensure no GST output tax is posted for non-GST collection scenarios.
- Expense vouchers and permitted manual journals.
- Reversal entries and audit trail.
- Cashbook/daybook, general ledger, trial balance, basic P&L, and stock valuation report as supported by finalized accounting rules.
- Reconcile reports to a known test ledger and transaction set.

### Deliverables
- Accounting posting matrix implemented.
- Report screens and controlled exports.
- Reconciliation workbook/test fixtures.

### Exit criteria
Every posted journal balances; reports reconcile to source journals and invoice tax snapshots; accounting representative signs off on sample cases.

## 8. Phase 6 — SaaS Operations, Security, and Release Readiness (4–7 working days)

### Tasks
- Subscription status and entitlement checks; plan limits enforced on the server.
- Final authorization matrix review for all routes and exports.
- Security testing: IDOR, tenant isolation, CSRF, XSS, SQL injection, upload validation, session fixation, rate limiting.
- Backup/restore drill, migration rollback plan, error logging, operational runbook.
- Responsive/mobile tests, browser/printer tests, PDF/Odia rendering tests.
- Performance test with agreed representative tenant/product/invoice volumes.
- Pilot onboarding and issue triage.

### Deliverables
- Release candidate.
- Security and acceptance checklist.
- Backup/restore evidence and runbook.
- Pilot sign-off.

### Exit criteria
All critical acceptance criteria pass; no unresolved critical/high security defects; recovery procedure is tested.

## 9. Indicative Schedule

| Phase | Estimate | Dependency |
|---|---:|---|
| Phase 0: requirements | 2–4 days | Client decisions and accountant input |
| Phase 1: foundation | 4–6 days | Phase 0 |
| Phase 2: catalogue + GST setup | 5–7 days | Phase 1; approved tax matrix |
| Phase 3: inventory/purchasing | 4–7 days | Phases 1–2 |
| Phase 4: POS/sales + tax engine | 8–12 days | Phases 2–3; approved tax rules and invoice wording |
| Phase 5: accounting/reports | 5–8 days | Phases 0, 3–4; accounting sign-off |
| Phase 6: hardening/pilot | 4–7 days | Feature-complete release candidate |

**Indicative total: 32–51 working days**, excluding substantial client-side delays, complex GST integrations, data migration, or changes in scope. The estimate explicitly includes basic configurable GST modes and a tested tax calculation workflow; it does not include GST portal filing, e-invoice/e-way bill integrations, or live GSTIN verification. A single developer may need longer. Keep the eight-day target only for a non-production prototype with clearly excluded accounting, security, and operational guarantees.

## 10. Suggested Sprint Backlog

### Sprint A — Foundation
- [ ] Repository and deployment baseline
- [ ] Migrations and seed data
- [ ] Secure authentication/session handling
- [ ] Tenant context and authorization middleware
- [ ] Platform-admin tenant lifecycle
- [ ] Tenant-isolation integration tests

### Sprint B — Master Data
- [ ] Business profile/settings and GST registration mode
- [ ] GST tax classification matrix and tax calculation test fixtures
- [ ] Products, tax classifications, and bilingual fields
- [ ] Units and tax categories
- [ ] Customers and suppliers
- [ ] Upload validation and pagination

### Sprint C — Inventory
- [ ] Stock movements and opening stock
- [ ] Purchase receiving
- [ ] Adjustments with reasons and audit events
- [ ] Stock balance and reconciliation report
- [ ] Concurrency tests

### Sprint D — Sales and Printing
- [ ] POS cart UI
- [ ] Server-side totals and validation
- [ ] Invoice numbering and idempotency
- [ ] Atomic sale workflow
- [ ] Payments and invoice snapshots
- [ ] Thermal browser print and PDF
- [ ] Returns/voids and compensating movements

### Sprint E — Accounting, GST Reports, and Reconciliation
- [ ] Chart of accounts and mappings
- [ ] Balanced journal posting
- [ ] Expenses/manual journals
- [ ] Reversals and audit trail
- [ ] Cashbook/daybook/ledger/trial balance
- [ ] Basic P&L and valuation only after rules validated
- [ ] Reconciliation fixtures and review

### Sprint F — Launch
- [ ] Plan/entitlement enforcement
- [ ] Security review and regression suite
- [ ] Backup restore drill
- [ ] Device, printer, and Odia PDF tests
- [ ] Performance and export limits
- [ ] Pilot with one business
- [ ] Fix pilot defects and sign-off

## 11. Definition of Done

A feature is not done merely because the screen works. It must include:
- Server-side validation and authorization.
- Tenant-scoped queries and object ownership checks.
- Correct transaction boundaries and failure handling.
- Audit events for sensitive operations.
- Automated tests for expected and failure cases.
- Responsive and localization checks where relevant.
- Updated migrations/documentation.
- No secrets or sensitive logs exposed.

## 12. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Accounting rules are underspecified | Approve posting matrix and sample ledgers before coding reports |
| Shared hosting restricts runtime or backups | Validate actual plan early; stage and test restore before production |
| Tenant data leakage | Central tenant context, scoped repositories, IDOR tests, composite constraints where feasible |
| Overselling or duplicate bills | Transactions, locking/conditional updates, unique invoice numbers, idempotency |
| Thermal printing varies by device | Validate named printer/browser combinations; do not promise universal direct printing |
| Odia PDF rendering fails | Embed/test a suitable font and verify glyph shaping on actual PDFs |
| Scope expands toward full accounting suite | Keep statutory integrations and advanced modules outside MVP unless separately estimated |
| Product is marketed as tax-compliant without verification | Obtain qualified review and implement only explicitly agreed tax workflows |

## 13. Release Gate

Do not onboard multiple paying tenants until tenant-isolation tests, sale atomicity, stock reconciliation, journal balancing, invoice/return behavior, print validation, backup restoration, and client acceptance tests pass. Start with one pilot tenant and reconcile its sample transactions before general rollout.

# Artha-Desk — Shop Onboarding Guide

**Audience:** platform administrators operating `admin.arthadesk.in`.
**Purpose:** bring a new shop from first contact to a working, tested store.

> Companion documents: `docs/DATABASE_SCHEMA.md` (what gets created), `docs/SHOP_SETUP_GUIDE.md` (send this one to the shop).

---

## 0. Prerequisites checklist

Confirm these **before** creating the tenant. Most failed onboards are one of these:

| # | Check | Why |
|---|---|---|
| 1 | Wildcard DNS `*.{base_domain}` → server IP is live | Shop subdomains will not resolve otherwise |
| 2 | Wildcard SSL covers `*.{base_domain}` (Cloudflare Universal SSL or per-shop certs) | Browsers will block the login page |
| 3 | The platform is healthy: `php bin/health.php` | DB reachable, storage writable, migrations applied |
| 4 | `TENANCY_ENFORCE_HOSTS=true` and `TENANCY_BASE_DOMAIN` are correct | Wrong base domain → every shop 404s |
| 5 | You can log in to `admin.arthadesk.in` as `super_admin` | You need it for the next step |
| 6 | No maintenance mode active | |

Collect from the shop before you start:

- **Business legal name** (as it appears on GSTIN)
- **Shop/store display name**
- **Owner name, mobile number, email**
- **GST registration mode**: `regular` / `composition` / `unregistered`
- **GSTIN** (if registered — 15 characters)
- **Registered state** (default Odisha = `21`)
- **Preferred store address (slug)**, e.g. `maatarini`, `cuttack-kirana`
- **Owner password** you will hand over (or generate one for a forced first change)

---

## 1. Create the shop

1. Browse to `https://admin.arthadesk.in/admin/tenants`.
2. Click **Add New Business**.
3. Fill the form:

| Field | Required | Notes |
|---|---|---|
| Business name | ✅ | Legal/registered name; used for GST `legal_business_name` |
| Slug (store address) | optional | Auto-generated from the name if blank. 3–50 chars, `a-z0-9-` only |
| Owner name | recommended | |
| Owner phone | recommended | |
| Business email | optional | |
| **Owner email** | ⚠️ **strongly recommended** | **If you leave this blank, no login is created and the shop cannot log in** |
| **Owner password** | ⚠️ with owner email | Minimum **8 characters** |
| Registration mode | ✅ | `regular`, `composition`, `unregistered` |
| GSTIN | if registered | Format enforced: `21AAAAA0000A1Z5` |
| State | ✅ | Default `21` (Odisha) |

4. Submit.

### What happens (single transaction)

- `tenants` row created with `status = active`, `currency = INR`, locale `en`
- `tenant_tax_profiles` row created with the GST mode, GSTIN and state
- **16 accounts** seeded into `accounts` (chart of accounts — see schema doc)
- `users` row created with role **`tenant_admin`**, if owner email was supplied
- Action written to `audit_logs` as `tenant.create`

If anything fails the transaction rolls back completely and you get a generic error; the detail is in `storage/logs/admin-YYYY-MM-DD.log`.

### After creation

You are returned to the tenant list with a success flash showing the store URL:

```
https://{slug}.{base_domain}/
```

**If the slug was auto-generated, read it off the flash message or the tenant list — this is the shop's permanent address.**

---

## 2. Verify the slug and DNS

```bash
# resolve the new subdomain
nslookup {slug}.arthadesk.in

# confirm HTTPS works
curl -sI https://{slug}.arthadesk.in/auth/login | head -1
```

Expected: an IP (not `NXDOMAIN`) and `HTTP/2 200` (or `30x` to HTTPS).

If DNS does not resolve:

- Add the record in your panel/Cloudflare. A wildcard `*` should already cover it; if not, add `{slug}` explicitly.
- SSL certificates for a brand-new subdomain can take 1–15 minutes (Cloudflare) or longer on shared-hosting auto-SSL. Re-check before alarming the shop.

---

## 3. First login (do this before handing over)

Log in as the owner on the shop's own subdomain — **not** the admin host:

1. `https://{slug}.{base_domain}/auth/login`
2. Owner email + the password you set.
3. Confirm you land on `/app/pos`.

Verify the login is host-scoped: the same email used on a *different* shop must **not** authenticate here.

---

## 4. Seed the shop's baseline data

The shop cannot sell until these four exist. Walk the owner through them, or set them up yourself and hand over a working store (recommended for the first shops).

### 4.1 Business & GST settings — **Settings**
- Legal business name, GSTIN, address, phone
- Invoice prefix (default `INV`) → produces `INV-2026-0001`
- Default payment mode (cash / UPI / card) — POS preselects this
- Place-of-supply state (drives CGST+SGST vs IGST)

### 4.2 Units of measure — built in
`PCS`, `KG`, `L`, etc. already exist; add more under **Settings** if needed.

### 4.3 Tax categories — built in
Standard slabs exist. Confirm the slabs the shop actually uses (5%, 12%, 18%, 28%, nil-rated, exempt).

### 4.4 Products — **Products**
For each product:
- English name (required), Odia name (optional)
- SKU or barcode (unique per tenant)
- Unit + GST tax category
- **Sale price** (GST-inclusive by default — see `price_includes_tax`)
- **Cost price** + **initial stock** on first creation

Use **+ Add New Product**, then verify it appears in the POS grid.

### 4.5 Suppliers — **Dealers/Suppliers** *(if the shop buys stock)*
Name, phone, GSTIN, address. Link products to a preferred supplier so purchase orders prefill.

### 4.6 Customers — **Customers** *(only for credit/khata sales)*
Name, phone, **credit limit**, state, GSTIN for B2B.
A credit (khata) sale is blocked if the customer is missing or the limit would be exceeded.

---

## 5. Add the shop's staff — **Settings → Users**

The owner account is `tenant_admin` (full access). Add the rest with least privilege:

| Role | Give to | Access |
|---|---|---|
| `tenant_admin` | Owner / manager | Everything in the shop |
| `cashier` | Billing counter | POS, invoices, returns, customers, profile |
| `inventory_manager` | Storekeeper | + products, suppliers, inventory, purchases |
| `accountant` | Bookkeeper / CA | invoices, returns, accounting, reports, suppliers, purchases |

Notes:
- Emails are unique **per tenant** — the same person can hold accounts in several shops.
- Deactivating a user cuts access on their **next request**, not just at next login.
- Passwords are bcrypt-hashed; never store or transmit them in plaintext.

---

## 6. Test sale (mandatory)

Run one real end-to-end transaction yourself before handover:

1. **POS** → add 2–3 products → confirm the tax preview matches expectation.
2. Try a **UPI + cash split**; confirm the tender totals exactly match.
3. Try a **credit sale** with a registered customer under their limit — then over the limit and confirm it is **blocked**.
4. **Checkout** → confirm invoice number follows the configured prefix.
5. **Thermal print** (`/app/invoices/{id}/thermal`) on the actual 58/80 mm printer.
6. **A4/PDF view** on `/app/invoices/{id}` — confirm totals and GST split.
7. **Return** part of the sale → confirm stock is restored and a credit note is issued.
8. **Reports** → confirm the sale shows in today's totals and the GST summary.
9. **Accounting** → confirm the sale posted a balanced journal.
10. **Log out**, log back in as a `cashier` → confirm they *cannot* reach Settings or Accounting.

---

## 7. Handover pack

Send the shop owner (use a secure channel — **the platform does not send email yet**):

```
Store address : https://{slug}.arthadesk.in
Login page    : https://{slug}.arthadesk.in/auth/login
Owner email   : {owner_email}
Initial password : {the password you set}   ← ask them to change it on first login
```

Also send them `docs/SHOP_SETUP_GUIDE.md`.

**Ask them to change the password immediately** (Profile → Change password; you must know the current one to change it).

---

## 8. Post-onboard monitoring

In the first days, check:

- `storage/logs/error-*.log` for 5xx / `checkout_failed`
- `storage/logs/auth-*.log` for repeated failed logins (throttle lockouts)
- `audit_logs` for `tenant.status`, `invoice.voided`, `settings.updated`
- Inventory: products showing **low stock** in Reports
- Receivables: customer `balance` growing against `credit_limit`

---

## 9. Suspending a shop

**Admin portal → tenant list → toggle status.**

- `active` ↔ `suspended`, audited as `tenant.status`.
- Suspension is enforced **server-side on every request** — the shop is locked out immediately, not at next login.
- Data is preserved; reactivating restores access.

Use for non-payment or at the shop's request. It does **not** delete anything.

---

## 10. Troubleshooting

| Symptom | Likely cause | Fix |
|---|---|---|
| `This store address was not found.` (404) | Slug mismatch, unknown subdomain, or `TENANCY_ENFORCE_HOSTS` with no tenant row | Confirm `tenants.slug` exactly matches the host prefix |
| Blank/fatal page | Production boot guard refused to start | Run `php bin/check-env.php` — usually missing `APP_KEY`, non-HTTPS `APP_URL`, default `DB_PASS`, or missing Turnstile keys |
| `Service temporarily unavailable` | Same boot guard | Generic page by design; reason is in `storage/logs/security-*.log` |
| Login always fails on the shop subdomain | Host-scoped query: email exists in another tenant, not this one | Verify `users.tenant_id` matches the tenant resolved from the host |
| No owner can log in | Owner email was left blank at creation | Create the user in **Settings → Users** |
| Subdomain won't load over HTTPS | Wildcard SSL missing/propagating | Add via Cloudflare/panel; wait for issuance |
| 419 / "Page expired" | Stale CSRF token | Refresh the page and resubmit |
| Sale blocked: "Credit limit exceeded" | Customer balance + new sale > limit | Raise the limit or take part payment |
| PDF shows blank boxes for Odia | Odia font not bundled with Dompdf | Use thermal/browser print, which renders Odia correctly |

---

## 11. Onboarding quick card

```
[ ] Wildcard DNS + SSL verified
[ ] php bin/health.php passes
[ ] Collect: name, slug, owner contact, GST mode, GSTIN, state
[ ] Admin → Add Business  (owner email + password = REQUIRED for login)
[ ] Read back https://{slug}.{base_domain}/
[ ] nslookup + curl the subdomain
[ ] Log in as owner on the shop subdomain
[ ] Settings → GST profile + invoice prefix + default payment
[ ] Products + initial stock
[ ] Suppliers / Customers as needed
[ ] Settings → Users → add staff with least privilege
[ ] Full test sale: split tender, credit, print, return, report, journal, role check
[ ] Hand over URL + credentials; force password change
[ ] Monitor logs for 48h
```

# Artha-Desk — Database Schema Reference

**Engine:** InnoDB · **Charset:** `utf8mb4` / `utf8mb4_unicode_ci` · **Money:** always `DECIMAL(12,2)` · **Quantities:** `DECIMAL(12,3)`

Schema lives in `database/migrations/*.sql`. Migrations run in filename order and are recorded in the `migrations` table, so each runs exactly once.

| Migration | Adds |
|---|---|
| `001_initial_schema.sql` | Core tenant, master data, sales, inventory, accounting, audit tables |
| `002_returns_expenses_enhancements.sql` | Walk-in fields, supplier link, returns, expenses |
| `003_settings_exchange_enhancements.sql` | Invoice prefix, default payment, exchange fields |
| `004_bill_template_designer.sql` | Bill template designer |
| `005_security_and_idempotency.sql` | Login attempts, idempotency, password resets, login counters |
| `006_purchases_voids.sql` | Purchase bills, supplier payments, invoice voiding |

## Multi-tenancy model

This is a **shared-schema, single-database** design: every tenant-owned table carries `tenant_id`. Every query is expected to filter on it. Deleting a tenant cascades to its data, except invoices and purchase bills which use `ON DELETE RESTRICT` so financial history is never silently destroyed.

Tenant resolution is by **host**: `{slug}.arthadesk.in` → `tenants.slug`. See `app/Services/TenantResolver.php`.

---

## 1. Platform & Identity

### `tenants`
One row per shop/business. The anchor for all isolation.

| Column | Type | Notes |
|---|---|---|
| `id` | INT PK | |
| `slug` | VARCHAR(50) UNIQUE | Subdomain label, e.g. `maatarini` |
| `name` | VARCHAR(150) | Business display name |
| `owner_name`, `phone`, `email`, `address` | varchar/text | Owner contact |
| `status` | ENUM `active`,`suspended`,`trial` | Suspension blocks access immediately |
| `currency` | VARCHAR(10) | Default `INR` |
| `default_locale` | VARCHAR(10) | `en` or `or` (Odia) |
| `allow_negative_stock` | TINYINT(1) | Default `0` |
| `invoice_prefix` | VARCHAR(10) | *(003)* Default `INV` |
| `default_payment_mode` | ENUM `cash`,`upi`,`card` | *(003)* POS default tender |
| `created_at`, `updated_at` | DATETIME | |

**Indexes:** `status`

### `tenant_tax_profiles`
One row per tenant (latest by `id` wins). Drives GST calculation.

| Column | Type | Notes |
|---|---|---|
| `tenant_id` | INT FK → tenants | CASCADE |
| `registration_mode` | ENUM `regular`,`composition`,`unregistered` | Controls CGST/SGST vs IGST |
| `gstin` | VARCHAR(15) NULL | Nullable |
| `legal_business_name`, `trade_name` | VARCHAR(150) NULL | |
| `registered_state_code` | VARCHAR(5) | Default `21` (Odisha) |
| `registered_state_name` | VARCHAR(50) | |
| `effective_from`, `effective_to` | DATE NULL | |

**Indexes:** `(tenant_id, registration_mode)`

### `users`
Platform super-admins (`tenant_id IS NULL`) and shop users.

| Column | Type | Notes |
|---|---|---|
| `tenant_id` | INT FK NULL | `NULL` = platform super_admin |
| `name`, `email` | VARCHAR(100) | |
| `password_hash` | VARCHAR(255) | bcrypt |
| `role` | ENUM `super_admin`,`tenant_admin`,`cashier`,`inventory_manager`,`accountant` | |
| `is_active` | TINYINT(1) | Rechecked on **every** request |
| `last_login_at` | DATETIME NULL | *(005)* |
| `login_count` | INT | *(005)* |

**Keys:** `UNIQUE (tenant_id, email)` — same email may exist in different tenants.

### `login_attempts` *(005)*
Throttle/forensics for login. Never read by the UI directly.

| Column | Type |
|---|---|
| `tenant_id` INT NULL | |
| `email` VARCHAR(150), `ip` VARCHAR(45) | |
| `success` TINYINT(1), `reason` VARCHAR(100) NULL | |
| `created_at` DATETIME | |

**Indexes:** `(email, created_at)`, `(ip, created_at)`, `(tenant_id, created_at)`

### `password_resets` *(005)*
Token is stored **hashed** (`token_hash CHAR(64)`), never raw.

| Column | Type |
|---|---|
| `tenant_id` INT NULL, `user_id` INT FK | |
| `token_hash` CHAR(64) | |
| `expires_at` DATETIME, `used_at` DATETIME NULL | |
| `created_at` DATETIME | |

### `idempotency_keys` *(005)*
Makes POS checkout safe to retry.

| Column | Type | Notes |
|---|---|---|
| `tenant_id` INT FK | | CASCADE |
| `user_id` INT NULL | | |
| `idem_key` VARCHAR(100) | | From `Idempotency-Key` header |
| `request_hash` CHAR(64) | | SHA-256 of the cart payload |
| `status` ENUM `processing`,`completed`,`failed` | | |
| `response_json` LONGTEXT NULL | | Cached original response |
| `created_at`, `updated_at` | DATETIME | |

**Keys:** `UNIQUE (tenant_id, idem_key)`, `INDEX (updated_at)`

### `audit_logs`
Append-only business/admin trail. Written by `App\Services\AuditService`.

| Column | Type | Notes |
|---|---|---|
| `tenant_id` INT NULL | | `NULL` for platform events |
| `user_id` INT NULL | | |
| `action` VARCHAR(100) | | e.g. `invoice.voided` |
| `entity_type` VARCHAR(50) NULL | | e.g. `invoice` |
| `entity_id` INT NULL | | |
| `details` TEXT NULL | | JSON, redacted |
| `ip_address` VARCHAR(45) NULL | | |
| `created_at` DATETIME | | Retained 365 days (`AUDIT_RETENTION_DAYS`) |

### `migrations`
Managed by `App\Core\MigrationRunner`, not by a migration file.

| Column | Type |
|---|---|
| `id` INT PK | |
| `migration` VARCHAR(255) UNIQUE | Filename |
| `executed_at` DATETIME | |

---

## 2. Catalogue & Master Data

### `units_of_measure`
`code`, `name_en`, `name_or`, `allow_decimal` (1 = permits fractional qty like 0.5 kg).

### `tax_categories`
| Column | Notes |
|---|---|
| `tenant_id` INT NULL | |
| `name` | Display label |
| `category_code` ENUM `taxable`,`nil_rated`,`exempt`,`zero_rated`,`non_gst` | |
| `rate_percent` | Total rate, e.g. `18.00` |
| `cgst_percent`, `sgst_percent`, `igst_percent` | Split components |

### `customers`
| Column | Notes |
|---|---|
| `tenant_id` INT FK | CASCADE |
| `name`, `phone`, `email` | |
| `gstin` VARCHAR(15) NULL | B2B |
| `state_code` VARCHAR(5) | Default `21` |
| `address` TEXT NULL | |
| `credit_limit` DECIMAL(12,2) | Khata ceiling |
| `balance` DECIMAL(12,2) | **Owed to you**; positive = outstanding credit |

### `suppliers`
Same contact columns as customers, no balance column (payables live on `purchase_bills`).

### `products`
| Column | Notes |
|---|---|
| `tenant_id` INT FK | |
| `sku` VARCHAR(50) NULL, `barcode` VARCHAR(50) NULL | |
| `name_en` VARCHAR(150) NOT NULL | |
| `name_or` VARCHAR(150) NULL | Odia |
| `unit_id` INT FK → units_of_measure | SET NULL |
| `tax_category_id` INT FK → tax_categories | SET NULL |
| `supplier_id` INT FK → suppliers | *(002)* SET NULL |
| `sale_price`, `cost_price` DECIMAL(12,2) | |
| `price_includes_tax` TINYINT(1) | Default `1` |
| `current_stock` DECIMAL(12,3) | Updated by sales/receipts/adjustments |
| `reorder_level` DECIMAL(12,3) | Low-stock threshold, default `5` |
| `category` VARCHAR(50) | Default `General` |
| `image_url` VARCHAR(255) NULL | |
| `is_active` TINYINT(1) | |

**Keys:** `UNIQUE (tenant_id, sku)`, `INDEX (tenant_id, barcode)`, `INDEX (tenant_id, is_active)`

---

## 3. Sales

### `invoices`
| Column | Notes |
|---|---|
| `tenant_id` INT FK | **RESTRICT** |
| `invoice_number` VARCHAR(50) | `{prefix}-{year}-{0000}` |
| `document_type` ENUM `TAX_INVOICE`,`BILL_OF_SUPPLY`,`COMMERCIAL_INVOICE` | |
| `customer_id` INT FK NULL | SET NULL |
| `user_id` INT NULL | Cashier |
| `walkin_name`, `walkin_phone` | *(002)* |
| `place_of_supply_state_code`, `supplier_state_code` | |
| `is_interstate` TINYINT(1) | 1 → IGST, else CGST+SGST |
| `subtotal`, `discount_amount`, `taxable_amount` | |
| `cgst_amount`, `sgst_amount`, `igst_amount` | |
| `total_amount` | |
| `payment_status` ENUM `paid`,`partial`,`pending`,`cancelled` | `cancelled` = voided |
| `notes` TEXT NULL | |
| `void_reason`, `voided_at`, `voided_by` | *(006)* |
| `created_at` DATETIME | |

**Keys:** `UNIQUE (tenant_id, invoice_number)`, `INDEX (tenant_id, created_at)`

### `invoice_items`
**Immutable snapshots** — name, price and tax are copied at sale time so later product edits never rewrite history.

`item_name_en`, `item_name_or`, `unit_name`, `quantity`, `unit_price`, `discount`, `tax_classification`, `tax_rate_percent`, `taxable_value`, `cgst_rate`, `cgst_amount`, `sgst_rate`, `sgst_amount`, `igst_rate`, `igst_amount`, `line_total`, plus `invoice_id` / `product_id` / `tenant_id` FKs.

### `payments`
| Column | Notes |
|---|---|
| `payment_method` ENUM `cash`,`upi`,`card`,`credit` | No `bank` — matches POS tender set |
| `amount` DECIMAL(12,2) | |
| `transaction_ref` VARCHAR(100) NULL | UPI/card reference |
| `status` ENUM `completed`,`failed`,`refunded` | `refunded` set on void |

Split payments produce one row per tender.

### `sales_returns` *(002, 003)*
| Column | Notes |
|---|---|
| `return_number` VARCHAR(50) | Unique per tenant |
| `invoice_id` INT FK | RESTRICT |
| `customer_id` INT NULL | |
| `total_refund_amount` DECIMAL(12,2) | |
| `refund_method` ENUM `cash`,`upi`,`card`,`credit_note` | |
| `transaction_type` ENUM `return`,`exchange` | *(003)* |
| `exchange_invoice_id` INT NULL | *(003)* New sale in an exchange |
| `reason` TEXT NULL | |

### `sales_return_items` *(002)*
Links back to `invoice_item_id`, with `quantity`, `refund_price`, `line_total`, `is_restocked`.

---

## 4. Inventory

### `stock_movements`
Append-only ledger. Every stock change writes a row here; `products.current_stock` is the cached running total.

| Column | Notes |
|---|---|
| `movement_type` ENUM `OPENING`,`RECEIPT`,`SALE`,`RETURN`,`ADJUSTMENT` | |
| `quantity` DECIMAL(12,3) | Positive magnitude; direction implied by type |
| `reference_id` INT NULL | e.g. invoice or bill id |
| `reference_type` VARCHAR(50) NULL | `invoice`, `purchase_bill`, `invoice_void`, `manual` |
| `notes` TEXT NULL | |

**Indexes:** `(tenant_id, product_id, created_at)`

### `purchases` *(006)*

**`purchase_bills`**
| Column | Notes |
|---|---|
| `bill_number` VARCHAR(50) | `PUR-{year}-{0000}` |
| `supplier_id` INT FK NULL | |
| `supplier_bill_ref` VARCHAR(100) NULL | Dealer's own invoice no. |
| `bill_date` DATE | |
| `subtotal`, `tax_amount`, `total_amount`, `paid_amount` | |
| `status` ENUM `unpaid`,`partial`,`paid`,`cancelled` | |
| `notes` TEXT NULL, `created_by` INT NULL | |

**Keys:** `UNIQUE (tenant_id, bill_number)`, `INDEX (tenant_id, bill_date)`

**`purchase_bill_items`** — `item_name`, `quantity`, `unit_cost`, `tax_rate`, `taxable_value`, `tax_amount`, `line_total`, optional `product_id`.

**`supplier_payments`** — `amount`, `payment_method` ENUM `cash`,`upi`,`card`,`bank`, `reference`, `paid_at`, `created_by`, FKs to bill + supplier.

---

## 5. Accounting

Double-entry. Header + lines, always balanced (checked before insert).

### `accounts` — chart of accounts
`code` (unique per tenant), `name`, `type` ENUM `asset`,`liability`,`equity`,`revenue`,`expense`, `parent_id`, `is_active`.

**Seeded for every new tenant:**

| Code | Name | Type |
|---|---|---|
| 1010 | Cash in Hand | asset |
| 1020 | Bank / UPI Clearing | asset |
| 1030 | Card Clearing | asset |
| 1040 | Accounts Receivable (Khata) | asset |
| 1050 | Inventory Asset | asset |
| 2010 | Output CGST Payable | liability |
| 2020 | Output SGST Payable | liability |
| 2030 | Output IGST Payable | liability |
| 2050 | Accounts Payable (Suppliers) | liability |
| 3010 | Owner Equity | equity |
| 4010 | Sales Revenue | revenue |
| 4020 | Sales Returns & Allowances | revenue |
| 5010 | Cost of Goods Sold (COGS) | expense |
| 5020 | Store Rent Expense | expense |
| 5030 | Electricity & Utilities | expense |
| 5040 | Tea & Refreshment / General Expense | expense |

### `journal_entries`
`entry_number` (unique per tenant), `date`, `reference_id`, `reference_type` (`sale`, `purchase_bill`, `supplier_payment`, `expense`, `return`, `invoice_void`…), `description`, `total_amount`.

### `journal_items`
`journal_entry_id`, `account_id`, `debit`, `credit`, `narration`. Sum of debits must equal sum of credits.

### `expenses` *(002)*
`voucher_number`, `category`, `account_id`, `amount`, `payment_method` ENUM `cash`,`upi`,`bank`, `date`, `description`.

---

## 6. Presentation & Configuration

### `bill_templates` *(004)*
`name`, `type` ENUM `thermal`,`normal`,`a4`, `is_default`, `config` JSON, `css` TEXT, `html_template` TEXT. Backs the bill template designer under **Settings → Templates**.

**Indexes:** `(tenant_id, type)`

---

## Entity relationship overview

```
tenants ──┬── tenant_tax_profiles
          ├── users
          ├── units_of_measure ──┐
          ├── tax_categories ────┤
          ├── suppliers ─────────┼── products ──┬── stock_movements
          ├── customers ─────────┤              ├── invoice_items ── invoices ──┬── payments
          │                      │              │                               ├── sales_returns ── sales_return_items
          │                      │              │                               └── journal_entries ── journal_items ── accounts
          ├── expenses
          ├── purchase_bills ──┬── purchase_bill_items
          │                    └── supplier_payments
          ├── bill_templates
          └── audit_logs
```

## Conventions & invariants

1. **Tenant scoping** — every read/write on tenant data must filter `tenant_id`. Enforced by controllers, not the DB.
2. **Money is DECIMAL** — never float. All arithmetic is rounded to 2 dp in PHP before insert.
3. **Balanced journals** — `AccountingPostingService` and `PurchaseController` throw before insert if debits ≠ credits.
4. **Immutable history** — `invoice_items` and `journal_items` are never updated after creation; corrections are made by reversal/void.
5. **Stock = sum of movements** — `products.current_stock` is a cache; `stock_movements` is the source of truth.
6. **Restrict vs Cascade** — financial documents use `RESTRICT`; master data cascades.
7. **Retention** — logs 90 days (`LOG_RETENTION_DAYS`), audit 365 days (`AUDIT_RETENTION_DAYS`), cleaned by `bin/cleanup.php`.

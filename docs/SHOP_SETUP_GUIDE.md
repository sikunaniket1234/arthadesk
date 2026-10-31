# Artha-Desk — Shop Setup & User Guide

**This guide is for the shop.** It walks you from your first login to running daily billing, stock, khata and reports.

Your interface is available in **English** and **ଓଡ଼ିଆ (Odia)** — switch any time from the language option in the top bar.

---

## 1. What you get

| Module | What it does for you |
|---|---|
| **POS** | Fast billing counter with live tax, split payments and printed receipts |
| **Invoices** | Every bill you've issued — search, reprint, PDF, CSV export |
| **Returns / Exchange** | Refunds, credit notes and exchanges against an old bill |
| **Products** | Your catalogue with prices, GST rate and stock |
| **Customers** | Your khata (credit) list with balances and limits |
| **Dealers / Suppliers** | Your wholesalers and what you owe them |
| **Inventory** | Live stock, adjustments and a full movement history |
| **Purchases** | Record dealer bills; stock updates automatically |
| **Accounting** | Automatic double-entry books, expenses, trial balance |
| **Reports** | Sales, GST, stock, expenses — printable and exportable |
| **Settings** | Your GST details, invoice number, staff accounts, bill layout |

Everything is **GST-aware**: intra-state sales get CGST + SGST, inter-state sales get IGST, automatically from the place of supply.

---

## 2. Log in

1. Open your store address: `https://yourshop.arthadesk.in`
2. Click **Login**.
3. Enter the email and password you were given.
4. On first login, go to **Profile** and change your password.

> **Your store address is private to your shop.** Keep it bookmarked. If someone you don't know has it, contact support.

### Forgot your password?
Contact your platform administrator. Password resets are handled by support — the system never emails a temporary password automatically.

---

## 3. Set up your business (do this once)

Go to **Settings**.

### 3.1 GST & business details
- **Legal business name** — exactly as printed on your GSTIN
- **GSTIN** — leave blank if you are not registered
- **Address, phone**
- **Registration type**
  - **Regular** — you charge full GST and file monthly/quarterly
  - **Composition** — you pay a fixed turnover rate; you cannot charge GST to customers
  - **Unregistered** — no GSTIN; treated as unregistered supplier

### 3.2 Invoice settings
- **Invoice prefix** — e.g. `INV` gives `INV-2026-0001`. Use your initials if you prefer (`MK-2026-0001`).
- **Default payment method** — the tender your POS preselects (Cash, UPI or Card)
- **Place of supply (state)** — this decides whether a bill gets **CGST + SGST** (same state) or **IGST** (inter-state)

Save. The preview in Settings shows the resulting bill format.

### 3.3 Bill layout
Under **Templates**, pick or edit your receipt: thermal (58/80 mm), normal, or A4. Add your shop name, address and thank-you note.

---

## 4. Add your products

Go to **Products → + Add New Product**.

| Field | What to enter | Example |
|---|---|---|
| English name | Required | Tata Salt 1kg |
| Odia name | Optional | ଟାଟା ଲୁଣ ୧ କି.ଗ୍ରା. |
| SKU | Your own code, unique | SALT-TAT-01 |
| Barcode | Scan or type | 8901030865432 |
| Category | Grouping for the POS | Grocery |
| Unit | PCS, KG, L… | KG |
| GST rate | The slab the product falls under | 18% |
| **Sale price** | What the customer pays | 30.00 |
| **Cost price** | What you pay the dealer | 25.00 |
| **Initial stock** | How many you have now | 50 |
| Price includes GST | ✅ for normal retail pricing | ✅ |

**Tips**
- Tick **Price includes GST** if your shelf price already contains tax (the normal case for retail).
- Set a **Reorder level** (e.g. 10) — you'll see a low-stock alert in Reports when stock falls to it.
- Use **Edit** on any row to correct a price or link a supplier. **Disable** hides it from the POS without deleting history.

Products appear in the POS grid immediately.

---

## 5. Add customers (for khata / credit)

Go to **Customers → + Add New Customer**.

Only add people you sell on credit:

| Field | Why it matters |
|---|---|
| Name + phone | Identification at the counter |
| **Credit limit** | The system **blocks** a credit sale once the limit is crossed |
| GSTIN | Needed only for business (B2B) customers |
| State | Decides CGST+SGST vs IGST on their bills |

The **Khata Balance** column shows what each customer owes you (red = outstanding).

You do **not** need customers for cash/UPI sales — those are billed as walk-in.

---

## 6. Add your dealers

Go to **Dealers/Suppliers → + Add Dealer**.

Name, phone, GSTIN and address. Then link your main products to their preferred supplier (Products → Edit → Preferred Supplier) so purchase bills prefill.

---

## 7. Your first sale

Go to **POS**.

1. Tap products to add them, or search by English/Odia name or barcode.
2. Adjust quantities by tapping the line.
3. The bottom panel shows the **live tax breakdown** — taxable value, CGST/SGST or IGST, total.
4. Choose the tender: **Cash / UPI / Card / Credit (Khata)**
   - **Split payment:** select one tender, then add another — e.g. ₹500 cash + the rest on UPI. The system makes sure the parts add up to the total.
   - **Credit:** select Khata, pick the customer. Blocked if they have no account or would exceed their limit.
5. Press **Pay / Checkout**.
6. The bill number is generated and the receipt prints.

### Printed receipt
- **Thermal (58/80 mm)** — from the invoice's thermal view
- **A4 / PDF** — from the invoice detail page, for emailing or filing

> Tapping Pay twice, or losing connection mid-sale, will **not** create a duplicate bill — the system recognises a retry of the same cart.

---

## 8. Returns and exchanges

Go to **Returns**.

### Refund
1. Open **Create Return**, enter the bill number.
2. Select the lines and quantities being returned.
3. Choose how to refund: Cash, UPI, Card or **Credit note** (store credit against the bill).
4. Confirm.

Returned stock goes **back into inventory** automatically, and the ledger is reversed.

### Exchange
Use **Exchange** to return old items and bill new ones in a single step — the returned stock is restocked and the new stock deducted.

---

## 9. Recording purchases

Go to **Purchases → Create Purchase Bill**.

1. Choose the dealer, bill date and their bill reference.
2. Add lines: product, quantity, unit cost, GST rate.
3. Enter how much you're paying now (Cash/UPI/Card/Bank), or leave it unpaid.
4. Save.

**What happens automatically**
- Stock for those products **increases**
- Cost price is **updated** (so your margins stay accurate)
- The bill is marked Unpaid / Partial / Paid
- A proper accounting journal is posted (Inventory in, Cash or Accounts Payable out)

Settle later via **Pay** on the purchase list — partial payments are allowed and the balance is tracked.

---

## 10. Daily expenses

**Accounting → Expenses.** Record rent, electricity, tea, etc. against the right account (Store Rent, Electricity, General). Each expense posts a journal automatically, so your profit figures stay honest.

---

## 11. Reading your numbers

### Reports
Pick a date range, then export CSV for your accountant.

- **Sales** — total turnover, invoice count, returns
- **GST summary** — taxable value and tax by rate (5/12/18/28%) → what you owe or can claim
- **Stock** — current levels, value, low-stock items
- **Top selling products** — what actually moves
- **Expenses** — where the money went
- **Purchases** — what you bought and still owe
- **Net profit estimate** — Sales − Returns − Expenses

### Accounting
- **Trial balance** — debits equal credits (if not, something is wrong)
- **Daybook / cashbook** — every transaction by day
- **GST summary** — filing-ready numbers

### Invoices
Filter by date, status or search term; export CSV for bulk work.

---

## 12. Managing your staff

**Settings → Users → Add.**

| Role | Give it to | They can… |
|---|---|---|
| **Tenant admin (owner)** | You | Everything |
| **Cashier** | Billing counter | Bill, print, returns, add customers |
| **Inventory manager** | Storekeeper | All of the above + products, stock, dealers, purchases |
| **Accountant** | Bookkeeper / CA | Bills, returns, books, reports, dealers, purchases — **no POS selling** |

Give each person the **smallest** role that lets them work. You can **deactivate** an account the moment someone leaves — access stops immediately.

**You must know your current password to change it** (Profile → Change password).

---

## 13. Tips for smooth operation

- **Enter cost prices.** Selling price alone still works, but margin and inventory value need cost price.
- **Do a stock count weekly** and use **Inventory → Adjust** for the difference. The movement ledger keeps a permanent history.
- **Watch low-stock alerts** in Reports so you don't run out at the counter.
- **Issue credit carefully.** Raise limits only for customers you trust; watch the red Khata balances.
- **Back up regularly** — your platform runs automatic nightly database backups; ask for an off-site copy monthly.
- **Don't run heavy reports during peak billing hours.**

---

## 14. Troubleshooting

| Problem | What to do |
|---|---|
| Page won't open / "store address not found" | Check you're on your own store address, not someone else's |
| "Page expired" on save | Refresh the page and submit again (session timed out) |
| "Credit limit exceeded" | Take part payment in cash/UPI, or raise the customer's limit |
| "Insufficient stock" | The available quantity shown is what's really there; record a purchase or adjust stock |
| Wrong tax split (CGST/SGST vs IGST) | Check **Settings → Place of supply** and the customer's state |
| Receipt printed wrongly | Check **Settings → Templates** and the printer's paper width (58 vs 80 mm) |
| A bill needs cancelling | Ask your admin/accountant — voiding requires Tenant Admin or Accountant rights and restores stock + reverses the ledger |
| Odia text looks wrong in PDF | Odia renders correctly on screen and on thermal print; PDF export needs a font update — use browser print for now |
| Someone changed a price and old bills look wrong | They can't — old bills store a snapshot. History is never rewritten |

---

## 15. What the system protects for you

- Every action is **logged** — who did what, when, and from where.
- **Passwords are hashed**; nobody, including support, can read yours.
- Sales can't be submitted twice by accident.
- Voided bills keep their record, but stock and accounts are corrected.
- Old bills are **immutable** — your printed and filed history can't be altered after the fact.
- Your shop's data is **isolated** — no other shop can see it.

---

## 16. Getting help

When contacting support, include:

1. Your **store address** (shop URL)
2. The **bill number**, if it's about a specific bill
3. The **exact error message**, and the **reference ID** shown if the page fails

That reference ID lets support find the exact log entry — it's the fastest way to a fix.

---

*Quick start card*

```
Settings  → GST details, invoice prefix, place of supply
Products  → add items with price + cost + opening stock
Customers → only those you sell on credit (with limit)
Dealers   → your wholesalers
POS       → bill, split tender, print
Returns   → refund or exchange
Purchases → dealer bills; stock updates itself
Reports   → sales, GST, stock, profit (CSV export)
Settings  → add staff with the least privilege they need
```

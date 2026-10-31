-- Migration 002: Returns, Expenses, Walk-in Details, and Supplier Links
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Add walk-in customer fields to invoices
ALTER TABLE invoices 
ADD COLUMN walkin_name VARCHAR(100) NULL AFTER customer_id,
ADD COLUMN walkin_phone VARCHAR(20) NULL AFTER walkin_name;

-- 2. Add supplier link to products
ALTER TABLE products
ADD COLUMN supplier_id INT NULL AFTER tax_category_id,
ADD CONSTRAINT fk_product_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL;

-- 3. Sales Returns (Credit Notes / Exchanges)
CREATE TABLE IF NOT EXISTS sales_returns (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    return_number VARCHAR(50) NOT NULL,
    invoice_id INT NOT NULL,
    customer_id INT NULL,
    total_refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    refund_method ENUM('cash', 'upi', 'card', 'credit_note') NOT NULL DEFAULT 'cash',
    reason TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    UNIQUE KEY uk_tenant_ret_num (tenant_id, return_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sales_return_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    sales_return_id INT NOT NULL,
    invoice_item_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    refund_price DECIMAL(12,2) NOT NULL,
    line_total DECIMAL(12,2) NOT NULL,
    is_restocked TINYINT(1) DEFAULT 1,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (sales_return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Business Expenses
CREATE TABLE IF NOT EXISTS expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    voucher_number VARCHAR(50) NOT NULL,
    category VARCHAR(50) NOT NULL,
    account_id INT NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash', 'upi', 'bank') NOT NULL DEFAULT 'cash',
    date DATE NOT NULL,
    description TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Add default expense accounts if not exist
INSERT INTO accounts (tenant_id, code, name, type)
SELECT t.id, '5020', 'Store Rent Expense', 'expense' FROM tenants t
WHERE NOT EXISTS (SELECT 1 FROM accounts a WHERE a.tenant_id = t.id AND a.code = '5020');

INSERT INTO accounts (tenant_id, code, name, type)
SELECT t.id, '5030', 'Electricity & Utilities', 'expense' FROM tenants t
WHERE NOT EXISTS (SELECT 1 FROM accounts a WHERE a.tenant_id = t.id AND a.code = '5030');

INSERT INTO accounts (tenant_id, code, name, type)
SELECT t.id, '5040', 'Tea & Refreshment / General Expense', 'expense' FROM tenants t
WHERE NOT EXISTS (SELECT 1 FROM accounts a WHERE a.tenant_id = t.id AND a.code = '5040');

INSERT INTO accounts (tenant_id, code, name, type)
SELECT t.id, '4020', 'Sales Returns & Allowances', 'revenue' FROM tenants t
WHERE NOT EXISTS (SELECT 1 FROM accounts a WHERE a.tenant_id = t.id AND a.code = '4020');

-- 6. Demo dealers/suppliers are inserted by the demo seed after tenants exist.
--    Guard here so a fresh database can run migrations before seeding.
INSERT INTO suppliers (tenant_id, name, phone, email, gstin, state_code, address)
SELECT t.id, 'Kalinga Wholesale FMCG Distributors', '9861234567', 'kalinga.fmcg@odishadealers.in', '21AAACK5432D1Z9', '21', 'Unit-3 Commercial Complex, Bhubaneswar'
FROM tenants t
WHERE t.id = 1
  AND NOT EXISTS (SELECT 1 FROM suppliers s WHERE s.tenant_id = t.id AND s.phone = '9861234567');

INSERT INTO suppliers (tenant_id, name, phone, email, gstin, state_code, address)
SELECT t.id, 'Utkal Agro Foods & Edible Oils Ltd', '9437123987', 'utkalagro@odishaoils.com', '21BBBCU9876E1Z3', '21', 'Malgodown, Cuttack'
FROM tenants t
WHERE t.id = 1
  AND NOT EXISTS (SELECT 1 FROM suppliers s WHERE s.tenant_id = t.id AND s.phone = '9437123987');

SET FOREIGN_KEY_CHECKS = 1;

SET FOREIGN_KEY_CHECKS = 1;

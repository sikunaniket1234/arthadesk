-- Migration 006: Purchase bills, supplier payments, invoice voiding
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS purchase_bills (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    bill_number VARCHAR(50) NOT NULL,
    supplier_id INT NULL,
    supplier_bill_ref VARCHAR(100) NULL,
    bill_date DATE NOT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status ENUM('unpaid', 'partial', 'paid', 'cancelled') NOT NULL DEFAULT 'unpaid',
    notes TEXT NULL,
    created_by INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
    UNIQUE KEY uk_tenant_purchase_number (tenant_id, bill_number),
    INDEX idx_purchase_date (tenant_id, bill_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS purchase_bill_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    purchase_bill_id INT NOT NULL,
    product_id INT NULL,
    item_name VARCHAR(150) NOT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    taxable_value DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (purchase_bill_id) REFERENCES purchase_bills(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS supplier_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    purchase_bill_id INT NOT NULL,
    supplier_id INT NULL,
    amount DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash', 'upi', 'card', 'bank') NOT NULL DEFAULT 'cash',
    reference VARCHAR(100) NULL,
    paid_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_by INT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (purchase_bill_id) REFERENCES purchase_bills(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE invoices
    ADD COLUMN void_reason TEXT NULL AFTER notes,
    ADD COLUMN voided_at DATETIME NULL AFTER void_reason,
    ADD COLUMN voided_by INT NULL AFTER voided_at;

INSERT INTO accounts (tenant_id, code, name, type)
SELECT t.id, '1050', 'Inventory Asset', 'asset' FROM tenants t
WHERE NOT EXISTS (SELECT 1 FROM accounts a WHERE a.tenant_id = t.id AND a.code = '1050');

INSERT INTO accounts (tenant_id, code, name, type)
SELECT t.id, '2050', 'Accounts Payable (Suppliers)', 'liability' FROM tenants t
WHERE NOT EXISTS (SELECT 1 FROM accounts a WHERE a.tenant_id = t.id AND a.code = '2050');

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

SET FOREIGN_KEY_CHECKS = 1;

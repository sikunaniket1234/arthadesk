-- Artha-Desk Schema Migration 001
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Tenants
CREATE TABLE IF NOT EXISTS tenants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    owner_name VARCHAR(100) NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    address TEXT NULL,
    status ENUM('active', 'suspended', 'trial') DEFAULT 'active',
    currency VARCHAR(10) DEFAULT 'INR',
    default_locale VARCHAR(10) DEFAULT 'en',
    allow_negative_stock TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_tenant_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tenant Tax Profiles (GST)
CREATE TABLE IF NOT EXISTS tenant_tax_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    registration_mode ENUM('regular', 'composition', 'unregistered') NOT NULL DEFAULT 'unregistered',
    gstin VARCHAR(15) NULL,
    legal_business_name VARCHAR(150) NULL,
    trade_name VARCHAR(150) NULL,
    registered_state_code VARCHAR(5) NOT NULL DEFAULT '21', -- 21 = Odisha
    registered_state_name VARCHAR(50) NOT NULL DEFAULT 'Odisha',
    effective_from DATE NULL,
    effective_to DATE NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_tax_tenant (tenant_id, registration_mode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Users
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL, -- NULL for super_admin
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin', 'tenant_admin', 'cashier', 'inventory_manager', 'accountant') NOT NULL DEFAULT 'cashier',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_tenant_email (tenant_id, email),
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Units of Measure
CREATE TABLE IF NOT EXISTS units_of_measure (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    code VARCHAR(10) NOT NULL,
    name_en VARCHAR(50) NOT NULL,
    name_or VARCHAR(50) NULL,
    allow_decimal TINYINT(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tax Categories
CREATE TABLE IF NOT EXISTS tax_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    name VARCHAR(50) NOT NULL,
    category_code ENUM('taxable', 'nil_rated', 'exempt', 'zero_rated', 'non_gst') NOT NULL DEFAULT 'taxable',
    rate_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    cgst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    sgst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    igst_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    INDEX idx_tc_code (category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Customers
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    gstin VARCHAR(15) NULL,
    state_code VARCHAR(5) DEFAULT '21',
    address TEXT NULL,
    credit_limit DECIMAL(12,2) DEFAULT 0.00,
    balance DECIMAL(12,2) DEFAULT 0.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    INDEX idx_cust_tenant (tenant_id, phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Suppliers
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NULL,
    email VARCHAR(100) NULL,
    gstin VARCHAR(15) NULL,
    state_code VARCHAR(5) DEFAULT '21',
    address TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Products
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    sku VARCHAR(50) NULL,
    barcode VARCHAR(50) NULL,
    name_en VARCHAR(150) NOT NULL,
    name_or VARCHAR(150) NULL,
    unit_id INT NULL,
    tax_category_id INT NULL,
    sale_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    price_includes_tax TINYINT(1) DEFAULT 1,
    current_stock DECIMAL(12,3) NOT NULL DEFAULT 0.000,
    reorder_level DECIMAL(12,3) DEFAULT 5.000,
    category VARCHAR(50) DEFAULT 'General',
    image_url VARCHAR(255) NULL,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (unit_id) REFERENCES units_of_measure(id) ON DELETE SET NULL,
    FOREIGN KEY (tax_category_id) REFERENCES tax_categories(id) ON DELETE SET NULL,
    UNIQUE KEY uk_tenant_sku (tenant_id, sku),
    INDEX idx_product_barcode (tenant_id, barcode),
    INDEX idx_product_active (tenant_id, is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Invoices
CREATE TABLE IF NOT EXISTS invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    invoice_number VARCHAR(50) NOT NULL,
    document_type ENUM('TAX_INVOICE', 'BILL_OF_SUPPLY', 'COMMERCIAL_INVOICE') NOT NULL,
    customer_id INT NULL,
    user_id INT NULL,
    place_of_supply_state_code VARCHAR(5) DEFAULT '21',
    supplier_state_code VARCHAR(5) DEFAULT '21',
    is_interstate TINYINT(1) DEFAULT 0,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    taxable_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    cgst_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    sgst_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    igst_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_status ENUM('paid', 'partial', 'pending', 'cancelled') DEFAULT 'paid',
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
    UNIQUE KEY uk_tenant_invoice_num (tenant_id, invoice_number),
    INDEX idx_inv_date (tenant_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Invoice Items (Immutable Snapshots)
CREATE TABLE IF NOT EXISTS invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    invoice_id INT NOT NULL,
    product_id INT NULL,
    item_name_en VARCHAR(150) NOT NULL,
    item_name_or VARCHAR(150) NULL,
    unit_name VARCHAR(50) DEFAULT 'pcs',
    quantity DECIMAL(12,3) NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    discount DECIMAL(12,2) DEFAULT 0.00,
    tax_classification VARCHAR(30) DEFAULT 'taxable',
    tax_rate_percent DECIMAL(5,2) DEFAULT 0.00,
    taxable_value DECIMAL(12,2) NOT NULL,
    cgst_rate DECIMAL(5,2) DEFAULT 0.00,
    cgst_amount DECIMAL(12,2) DEFAULT 0.00,
    sgst_rate DECIMAL(5,2) DEFAULT 0.00,
    sgst_amount DECIMAL(12,2) DEFAULT 0.00,
    igst_rate DECIMAL(5,2) DEFAULT 0.00,
    igst_amount DECIMAL(12,2) DEFAULT 0.00,
    line_total DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Payments
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    invoice_id INT NOT NULL,
    payment_method ENUM('cash', 'upi', 'card', 'credit') NOT NULL DEFAULT 'cash',
    amount DECIMAL(12,2) NOT NULL,
    transaction_ref VARCHAR(100) NULL,
    status ENUM('completed', 'failed', 'refunded') DEFAULT 'completed',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Stock Movements
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    product_id INT NOT NULL,
    movement_type ENUM('OPENING', 'RECEIPT', 'SALE', 'RETURN', 'ADJUSTMENT') NOT NULL,
    quantity DECIMAL(12,3) NOT NULL,
    reference_id INT NULL,
    reference_type VARCHAR(50) NULL,
    notes TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_sm_product (tenant_id, product_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Accounts (Chart of Accounts)
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
    parent_id INT NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uk_tenant_account_code (tenant_id, code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Journal Entries & Items
CREATE TABLE IF NOT EXISTS journal_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    entry_number VARCHAR(50) NOT NULL,
    date DATE NOT NULL,
    reference_id INT NULL,
    reference_type VARCHAR(50) NULL,
    description TEXT NULL,
    total_amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    UNIQUE KEY uk_tenant_jrn_num (tenant_id, entry_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS journal_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NOT NULL,
    journal_entry_id INT NOT NULL,
    account_id INT NOT NULL,
    debit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    credit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    narration VARCHAR(255) NULL,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
    FOREIGN KEY (journal_entry_id) REFERENCES journal_entries(id) ON DELETE CASCADE,
    FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. Audit Logs
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tenant_id INT NULL,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

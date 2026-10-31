-- Migration 003: Settings enhancements — invoice prefix, default payment, exchange fields
SET FOREIGN_KEY_CHECKS = 0;

ALTER TABLE tenants
    ADD COLUMN invoice_prefix VARCHAR(10) NOT NULL DEFAULT 'INV' AFTER allow_negative_stock,
    ADD COLUMN default_payment_mode ENUM('cash','upi','card') NOT NULL DEFAULT 'cash' AFTER invoice_prefix;

ALTER TABLE tenant_tax_profiles
    MODIFY COLUMN gstin VARCHAR(15) NULL,
    MODIFY COLUMN legal_business_name VARCHAR(150) NULL;

ALTER TABLE sales_returns
    ADD COLUMN transaction_type ENUM('return','exchange') NOT NULL DEFAULT 'return' AFTER refund_method,
    ADD COLUMN exchange_invoice_id INT NULL AFTER transaction_type,
    ADD CONSTRAINT fk_exchange_invoice FOREIGN KEY (exchange_invoice_id) REFERENCES invoices(id) ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS = 1;

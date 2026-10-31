-- Artha-Desk Demo Seed Data
SET FOREIGN_KEY_CHECKS = 0;

-- 1. Standard Units of Measure
INSERT INTO units_of_measure (code, name_en, name_or, allow_decimal) VALUES
('PCS', 'Piece', 'ଖଣ୍ଡ', 0),
('KG', 'Kilogram', 'କିଲୋଗ୍ରାମ', 1),
('GM', 'Gram', 'ଗ୍ରାମ', 1),
('LTR', 'Liter', 'ଲିଟର', 1),
('PKT', 'Packet', 'ପ୍ୟାକେଟ', 0),
('BOX', 'Box', 'ବାକ୍ସ', 0);

-- 2. Standard GST Tax Categories
INSERT INTO tax_categories (name, category_code, rate_percent, cgst_percent, sgst_percent, igst_percent) VALUES
('Exempt / Nil Rated (0%)', 'exempt', 0.00, 0.00, 0.00, 0.00),
('GST 5% (Food/Essentials)', 'taxable', 5.00, 2.50, 2.50, 5.00),
('GST 12% (Standard Low)', 'taxable', 12.00, 6.00, 6.00, 12.00),
('GST 18% (Standard General)', 'taxable', 18.00, 9.00, 9.00, 18.00),
('GST 28% (Luxury/Sin)', 'taxable', 28.00, 14.00, 14.00, 28.00),
('Non-GST Supply', 'non_gst', 0.00, 0.00, 0.00, 0.00);

-- 3. Tenants
-- Password for all seed users is: password123 (hash below generated with password_hash('password123', PASSWORD_BCRYPT))
-- $2y$10$eO0V6j5o30c2o/y6u0H/a.Q62P8R8Xy9Gj4fXlQ4bUaL5j8D7n7qO
-- Super Admin
INSERT INTO users (tenant_id, name, email, password_hash, role, is_active) VALUES
(NULL, 'Platform Super Admin', 'admin@arthadesk.local', '$2y$10$H7.ZFmLHzrD2Ta2zqU8WEeO.8oGxaYDpOp2Vvtp5w3t/jxxHzYreC', 'super_admin', 1);

-- Tenant 1: Regular GST Retailer
INSERT INTO tenants (id, slug, name, owner_name, phone, email, address, status, currency, default_locale) VALUES
(1, 'maa-tarini-store', 'Maa Tarini General Store (ମା\' ତାରିଣୀ ଷ୍ଟୋର)', 'Bikram Keshari Sahoo', '9861012345', 'tarini@arthadesk.local', 'Shop No. 12, Cuttack Road, Bhubaneswar, Odisha', 'active', 'INR', 'or');

INSERT INTO tenant_tax_profiles (tenant_id, registration_mode, gstin, legal_business_name, trade_name, registered_state_code, registered_state_name) VALUES
(1, 'regular', '21AAACT1234F1Z5', 'Maa Tarini Enterprise Pvt Ltd', 'Maa Tarini General Store', '21', 'Odisha');

INSERT INTO users (tenant_id, name, email, password_hash, role, is_active) VALUES
(1, 'Bikram Sahoo (Owner)', 'owner@tarini.local', '$2y$10$H7.ZFmLHzrD2Ta2zqU8WEeO.8oGxaYDpOp2Vvtp5w3t/jxxHzYreC', 'tenant_admin', 1),
(1, 'Gopal Das (Cashier)', 'cashier@tarini.local', '$2y$10$H7.ZFmLHzrD2Ta2zqU8WEeO.8oGxaYDpOp2Vvtp5w3t/jxxHzYreC', 'cashier', 1);

-- Tenant 2: Composition Taxpayer
INSERT INTO tenants (id, slug, name, owner_name, phone, email, address, status, currency, default_locale) VALUES
(2, 'utkal-sweets', 'Utkal Sweet Stall (ଉତ୍କଳ ମିଠା ଭଣ୍ଡାର)', 'Prasanna Mohanty', '9437023456', 'utkal@arthadesk.local', 'Grand Road, Puri, Odisha', 'active', 'INR', 'or');

INSERT INTO tenant_tax_profiles (tenant_id, registration_mode, gstin, legal_business_name, trade_name, registered_state_code, registered_state_name) VALUES
(2, 'composition', '21BBBCS5678G2Z1', 'Utkal Sweets & Bakery', 'Utkal Sweet Stall', '21', 'Odisha');

INSERT INTO users (tenant_id, name, email, password_hash, role, is_active) VALUES
(2, 'Prasanna Mohanty', 'owner@utkal.local', '$2y$10$H7.ZFmLHzrD2Ta2zqU8WEeO.8oGxaYDpOp2Vvtp5w3t/jxxHzYreC', 'tenant_admin', 1);

-- Tenant 3: Unregistered Small Shop
INSERT INTO tenants (id, slug, name, owner_name, phone, email, address, status, currency, default_locale) VALUES
(3, 'khurda-daily', 'Khurda Daily Corner', 'Ramesh Chandra Jena', '9123456780', 'khurda@arthadesk.local', 'Main Market, Khordha, Odisha', 'active', 'INR', 'en');

INSERT INTO tenant_tax_profiles (tenant_id, registration_mode, gstin, legal_business_name, trade_name, registered_state_code, registered_state_name) VALUES
(3, 'unregistered', NULL, 'Khurda Daily Corner', 'Khurda Daily Corner', '21', 'Odisha');

INSERT INTO users (tenant_id, name, email, password_hash, role, is_active) VALUES
(3, 'Ramesh Jena', 'owner@khurda.local', '$2y$10$H7.ZFmLHzrD2Ta2zqU8WEeO.8oGxaYDpOp2Vvtp5w3t/jxxHzYreC', 'tenant_admin', 1);

-- Chart of Accounts for Tenant 1
INSERT INTO accounts (tenant_id, code, name, type) VALUES
(1, '1010', 'Cash in Hand', 'asset'),
(1, '1020', 'Bank / UPI Clearing', 'asset'),
(1, '1030', 'Card Clearing', 'asset'),
(1, '1040', 'Accounts Receivable (Khata)', 'asset'),
(1, '1050', 'Inventory Asset', 'asset'),
(1, '2010', 'Output CGST Payable', 'liability'),
(1, '2020', 'Output SGST Payable', 'liability'),
(1, '2030', 'Output IGST Payable', 'liability'),
(1, '3010', 'Owner Equity', 'equity'),
(1, '4010', 'Sales Revenue', 'revenue'),
(1, '5010', 'Cost of Goods Sold (COGS)', 'expense');

-- Chart of Accounts for Tenant 2
INSERT INTO accounts (tenant_id, code, name, type) VALUES
(2, '1010', 'Cash in Hand', 'asset'),
(2, '1020', 'Bank / UPI Clearing', 'asset'),
(2, '1040', 'Accounts Receivable', 'asset'),
(2, '4010', 'Sales Revenue', 'revenue');

-- Chart of Accounts for Tenant 3
INSERT INTO accounts (tenant_id, code, name, type) VALUES
(3, '1010', 'Cash in Hand', 'asset'),
(3, '1020', 'Bank / UPI Clearing', 'asset'),
(3, '4010', 'Sales Revenue', 'revenue');

-- Additional accounts required by purchases, expenses, returns and inventory
INSERT INTO accounts (tenant_id, code, name, type) VALUES
(1, '1050', 'Inventory Asset', 'asset'),
(1, '2050', 'Accounts Payable (Suppliers)', 'liability'),
(1, '4020', 'Sales Returns & Allowances', 'revenue'),
(1, '5020', 'Store Rent Expense', 'expense'),
(1, '5030', 'Electricity & Utilities', 'expense'),
(1, '5040', 'Tea & Refreshment / General Expense', 'expense'),
(2, '1050', 'Inventory Asset', 'asset'),
(2, '2050', 'Accounts Payable (Suppliers)', 'liability'),
(2, '4020', 'Sales Returns & Allowances', 'revenue'),
(2, '5020', 'Store Rent Expense', 'expense'),
(2, '5030', 'Electricity & Utilities', 'expense'),
(2, '5040', 'Tea & Refreshment / General Expense', 'expense'),
(3, '1050', 'Inventory Asset', 'asset'),
(3, '2050', 'Accounts Payable (Suppliers)', 'liability'),
(3, '4020', 'Sales Returns & Allowances', 'revenue'),
(3, '5020', 'Store Rent Expense', 'expense'),
(3, '5030', 'Electricity & Utilities', 'expense'),
(3, '5040', 'Tea & Refreshment / General Expense', 'expense');

-- Demo suppliers for Tenant 1
INSERT INTO suppliers (tenant_id, name, phone, email, gstin, state_code, address) VALUES
(1, 'Kalinga Wholesale FMCG Distributors', '9861234567', 'kalinga.fmcg@odishadealers.in', '21AAACK5432D1Z9', '21', 'Unit-3 Commercial Complex, Bhubaneswar'),
(1, 'Utkal Agro Foods & Edible Oils Ltd', '9437123987', 'utkalagro@odishaoils.com', '21BBBCU9876E1Z3', '21', 'Malgodown, Cuttack');

-- Customers for Tenant 1
INSERT INTO customers (tenant_id, name, phone, gstin, state_code, address) VALUES
(1, 'Manoj Kumar Nayak', '9937123456', NULL, '21', 'Saheed Nagar, Bhubaneswar'),
(1, 'Saraswati Traders', '9861987654', '21ABCDE1234F1Z8', '21', 'Rasulgarh, Bhubaneswar'),
(1, 'Kolkata Sweet House', '9433123456', '19AAACK9876M1Z2', '19', 'Howrah, West Bengal'); -- Interstate test customer

-- Products for Tenant 1 (Maa Tarini General Store)
INSERT INTO products (tenant_id, sku, barcode, name_en, name_or, unit_id, tax_category_id, sale_price, cost_price, price_includes_tax, current_stock, reorder_level, category) VALUES
(1, 'RICE-BAS-01', '8901234001', 'Fortune Biryani Special Basmati Rice 1kg', 'ଫର୍ଚ୍ୟୁନ ବାସୁମତୀ ଚାଉଳ ୧ କି.ଗ୍ରା.', 2, 2, 145.00, 120.00, 1, 85.000, 10.000, 'Grains & Pulses'),
(1, 'OIL-MUS-01', '8901234002', 'Engine Brand Mustard Oil 1 Ltr Pouch', 'ଇଞ୍ଜିନ ଖାଣ୍ଟି ସୋରିଷ ତେଲ ୧ ଲିଟର', 4, 2, 160.00, 140.00, 1, 42.000, 12.000, 'Edible Oils'),
(1, 'TEA-TATA-01', '8901234003', 'Tata Tea Gold Leaf 500g', 'ଟାଟା ଟି ଗୋଲ୍ଡ ଚା’ ପତି ୫୦୦ ଗ୍ରାମ', 3, 2, 280.00, 240.00, 1, 28.000, 8.000, 'Beverages'),
(1, 'ATTA-AASH-01', '8901234004', 'Aashirvaad Shudh Chakki Atta 5kg', 'ଆଶୀର୍ବାଦ ଚକି ଅଟା ୫ କି.ଗ୍ରା.', 2, 2, 235.00, 205.00, 1, 50.000, 15.000, 'Grains & Pulses'),
(1, 'MILK-OMF-01', '8901234005', 'Omfed Premium Pasteurized Milk 500ml', 'ଓମଫେଡ୍ ତାଜା ତରଳ କ୍ଷୀର ୫୦୦ ମି.ଲି.', 4, 1, 26.00, 23.50, 1, 60.000, 15.000, 'Dairy'),
(1, 'BUTTER-AMUL-01', '8901234006', 'Amul Pasteurized Butter 100g', 'ଅମୁଲ ଲହୁଣୀ (ବଟର) ୧୦୦ ଗ୍ରାମ', 1, 3, 56.00, 50.00, 1, 35.000, 10.000, 'Dairy'),
(1, 'BISCUIT-PARLE-01', '8901234007', 'Parle-G Glucose Biscuit Family Pack', 'ପାର୍ଲେ-ଜି ଗ୍ଲୁକୋଜ ବିସ୍କୁଟ', 1, 4, 30.00, 25.00, 1, 110.000, 20.000, 'Snacks'),
(1, 'NOODLE-MAG-01', '8901234008', 'Maggi 2-Minute Masala Noodles 4-Pack', 'ମ୍ୟାଗି ମସଲା ନୁଡଲ୍ସ', 1, 4, 55.00, 48.00, 1, 75.000, 15.000, 'Instant Food'),
(1, 'SUGAR-REG-01', '8901234009', 'Refined Crystal Sugar 1kg', 'ରିଫାଇଣ୍ଡ ଧଳା ଚିନି ୧ କି.ଗ୍ରା.', 2, 2, 44.00, 39.00, 1, 150.000, 25.000, 'Essentials'),
(1, 'SOAP-DET-01', '8901234010', 'Dettol Original Germ Protection Bathing Soap', 'ଡେଟଲ ସାବୁନ', 1, 4, 40.00, 33.00, 1, 90.000, 20.000, 'Personal Care');

-- Initial Stock Movements for Tenant 1
INSERT INTO stock_movements (tenant_id, product_id, movement_type, quantity, reference_type, notes) VALUES
(1, 1, 'OPENING', 85.000, 'setup', 'Initial opening stock'),
(1, 2, 'OPENING', 42.000, 'setup', 'Initial opening stock'),
(1, 3, 'OPENING', 28.000, 'setup', 'Initial opening stock'),
(1, 4, 'OPENING', 50.000, 'setup', 'Initial opening stock'),
(1, 5, 'OPENING', 60.000, 'setup', 'Initial opening stock'),
(1, 6, 'OPENING', 35.000, 'setup', 'Initial opening stock'),
(1, 7, 'OPENING', 110.000, 'setup', 'Initial opening stock'),
(1, 8, 'OPENING', 75.000, 'setup', 'Initial opening stock'),
(1, 9, 'OPENING', 150.000, 'setup', 'Initial opening stock'),
(1, 10, 'OPENING', 90.000, 'setup', 'Initial opening stock');

-- Products for Tenant 2 (Utkal Sweets - Composition)
INSERT INTO products (tenant_id, sku, barcode, name_en, name_or, unit_id, tax_category_id, sale_price, cost_price, price_includes_tax, current_stock, reorder_level, category) VALUES
(2, 'SWT-RAS-01', '8902001', 'Puri Famous Rasagola (10 pcs)', 'ପୁରୀ ପ୍ରସିଦ୍ଧ ରସଗୋଲା (୧୦ ଟି)', 1, 1, 200.00, 130.00, 1, 40.000, 5.000, 'Sweets'),
(2, 'SWT-CHE-01', '8902002', 'Baked Chhena Poda 500g', 'ବେକ୍ଡ଼ ଛେନାପୋଡ଼ ୫୦୦ ଗ୍ରାମ', 2, 1, 180.00, 110.00, 1, 25.000, 5.000, 'Sweets');

SET FOREIGN_KEY_CHECKS = 1;

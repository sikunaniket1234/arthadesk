<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;

class ProductController {
    public function index(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = $tenant['id'];
        $filterLowStock = $request->get('filter') === 'low_stock';

        $sql = "
            SELECT p.*, tc.name as tax_cat_name, tc.rate_percent, u.code as unit_code, u.name_en as unit_name,
                   s.name as supplier_name, s.phone as supplier_phone
            FROM products p
            LEFT JOIN tax_categories tc ON p.tax_category_id = tc.id
            LEFT JOIN units_of_measure u ON p.unit_id = u.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.tenant_id = :tid
        ";

        if ($filterLowStock) {
            $sql .= " AND p.current_stock <= p.reorder_level ";
        }

        $sql .= " ORDER BY p.name_en ASC";

        $products = Database::fetchAll($sql, ['tid' => $tenantId]);
        $units = Database::fetchAll("SELECT * FROM units_of_measure ORDER BY id ASC");
        $taxCategories = Database::fetchAll("SELECT * FROM tax_categories ORDER BY rate_percent ASC");
        $suppliers = Database::fetchAll("SELECT id, name, phone FROM suppliers WHERE tenant_id = :tid ORDER BY name ASC", ['tid' => $tenantId]);

        View::render('products/index', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'products' => $products,
            'units' => $units,
            'taxCategories' => $taxCategories,
            'suppliers' => $suppliers,
            'filterLowStock' => $filterLowStock,
        ]);
    }

    public function store(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = $tenant['id'];
        $user = $request->user();

        $data = $this->validated($request, $tenantId);
        if (is_string($data)) {
            flash('error', $data);
            Response::redirect('/app/products');
            return;
        }

        $initialStock = max(0, (float)$request->input('initial_stock', 0));

        try {
            Database::transaction(function (\PDO $pdo) use ($tenantId, $data, $initialStock) {
                $stmt = $pdo->prepare("
                    INSERT INTO products (
                        tenant_id, sku, barcode, name_en, name_or, unit_id, tax_category_id, supplier_id,
                        sale_price, cost_price, price_includes_tax, current_stock, reorder_level, category, is_active
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $tenantId, $data['sku'], $data['barcode'], $data['name_en'], $data['name_or'],
                    $data['unit_id'], $data['tax_category_id'], $data['supplier_id'],
                    $data['sale_price'], $data['cost_price'], $data['price_includes_tax'],
                    $initialStock, $data['reorder_level'], $data['category'],
                ]);
                $productId = (int)$pdo->lastInsertId();

                if ($initialStock > 0) {
                    $pdo->prepare("
                        INSERT INTO stock_movements (tenant_id, product_id, movement_type, quantity, reference_type, notes)
                        VALUES (?, ?, 'OPENING', ?, 'manual', 'Initial product stock')
                    ")->execute([$tenantId, $productId, $initialStock]);
                }

                AuditService::log('product.created', 'product', $productId, [
                    'name' => $data['name_en'],
                    'sku' => $data['sku'],
                    'sale_price' => $data['sale_price'],
                ], $tenantId, (int)($user['id'] ?? 0));
            });
        } catch (\PDOException $e) {
            $message = str_contains($e->getMessage(), 'Duplicate') ? 'SKU or barcode already exists for this store.' : 'Could not save product.';
            flash('error', $message);
            Response::redirect('/app/products');
            return;
        }

        flash('success', "Product '{$data['name_en']}' added successfully.");
        Response::redirect('/app/products');
    }

    public function update(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $productId = (int)$request->param('id');
        $user = $request->user();

        $existing = Database::fetchOne("SELECT id, name_en FROM products WHERE id = :id AND tenant_id = :tid", [
            'id' => $productId,
            'tid' => $tenantId,
        ]);
        if (!$existing) {
            flash('error', 'Product not found.');
            Response::redirect('/app/products');
            return;
        }

        $data = $this->validated($request, $tenantId);
        if (is_string($data)) {
            flash('error', $data);
            Response::redirect('/app/products');
            return;
        }

        try {
            Database::query("
                UPDATE products
                SET sku = ?, barcode = ?, name_en = ?, name_or = ?, unit_id = ?, tax_category_id = ?,
                    supplier_id = ?, sale_price = ?, cost_price = ?, price_includes_tax = ?,
                    reorder_level = ?, category = ?
                WHERE id = ? AND tenant_id = ?
            ", [
                $data['sku'], $data['barcode'], $data['name_en'], $data['name_or'], $data['unit_id'],
                $data['tax_category_id'], $data['supplier_id'], $data['sale_price'], $data['cost_price'],
                $data['price_includes_tax'], $data['reorder_level'], $data['category'], $productId, $tenantId,
            ]);
        } catch (\PDOException $e) {
            $message = str_contains($e->getMessage(), 'Duplicate') ? 'SKU or barcode already exists for another product.' : 'Could not update product.';
            flash('error', $message);
            Response::redirect('/app/products');
            return;
        }

        AuditService::log('product.updated', 'product', $productId, [
            'old_name' => $existing['name_en'],
            'name' => $data['name_en'],
            'sale_price' => $data['sale_price'],
            'cost_price' => $data['cost_price'],
        ], $tenantId, (int)($user['id'] ?? 0));

        flash('success', "Product '{$data['name_en']}' updated.");
        Response::redirect('/app/products');
    }

    public function toggle(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $productId = (int)$request->param('id');
        $user = $request->user();

        $product = Database::fetchOne("SELECT id, name_en, is_active FROM products WHERE id = :id AND tenant_id = :tid", [
            'id' => $productId,
            'tid' => $tenantId,
        ]);
        if (!$product) {
            flash('error', 'Product not found.');
            Response::redirect('/app/products');
            return;
        }

        $newState = (int)$product['is_active'] === 1 ? 0 : 1;
        Database::query("UPDATE products SET is_active = ? WHERE id = ? AND tenant_id = ?", [$newState, $productId, $tenantId]);

        AuditService::log('product.status_changed', 'product', $productId, [
            'name' => $product['name_en'],
            'is_active' => $newState,
        ], $tenantId, (int)($user['id'] ?? 0));

        flash('success', "Product '{$product['name_en']}' " . ($newState ? 'activated' : 'deactivated') . '.');
        Response::redirect('/app/products');
    }

    protected function validated(Request $request, int $tenantId) {
        $nameEn = trim((string)$request->input('name_en', ''));
        if ($nameEn === '' || mb_strlen($nameEn) > 150) {
            return 'English Product Name is required (max 150 characters).';
        }

        $sku = trim((string)$request->input('sku', '')) ?: null;
        if ($sku !== null && mb_strlen($sku) > 50) {
            return 'SKU must be 50 characters or fewer.';
        }
        $barcode = trim((string)$request->input('barcode', '')) ?: null;
        if ($barcode !== null && mb_strlen($barcode) > 50) {
            return 'Barcode must be 50 characters or fewer.';
        }

        $unitId = (int)$request->input('unit_id') ?: null;
        $taxCatId = (int)$request->input('tax_category_id') ?: null;
        $supplierId = (int)$request->input('supplier_id') ?: null;

        if ($supplierId) {
            $supplier = Database::fetchOne("SELECT id FROM suppliers WHERE id = :id AND tenant_id = :tid", [
                'id' => $supplierId,
                'tid' => $tenantId,
            ]);
            if (!$supplier) {
                return 'Selected supplier was not found.';
            }
        }

        $salePrice = round((float)$request->input('sale_price', 0), 2);
        $costPrice = round((float)$request->input('cost_price', 0), 2);
        if ($salePrice < 0 || $costPrice < 0) {
            return 'Prices cannot be negative.';
        }

        $reorderLevel = max(0, (float)$request->input('reorder_level', 5));
        $category = trim((string)$request->input('category', 'General')) ?: 'General';

        return [
            'name_en' => $nameEn,
            'name_or' => trim((string)$request->input('name_or', '')) ?: null,
            'sku' => $sku,
            'barcode' => $barcode,
            'unit_id' => $unitId,
            'tax_category_id' => $taxCatId,
            'supplier_id' => $supplierId,
            'sale_price' => $salePrice,
            'cost_price' => $costPrice,
            'price_includes_tax' => $request->input('price_includes_tax') ? 1 : 0,
            'reorder_level' => $reorderLevel,
            'category' => substr($category, 0, 50),
        ];
    }
}

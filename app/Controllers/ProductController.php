<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;

class ProductController {
    /**
     * Builds the shared list WHERE clause for index + export.
     * @return array{0:string,1:array,2:array} [whereSql, params, meta]
     */
    protected function filterWhere(Request $request, int $tenantId): array {
        $filterLowStock = $request->get('filter') === 'low_stock';
        $q = trim((string)$request->get('q', ''));
        $categoryFilter = trim((string)$request->get('category', ''));
        $statusFilter = (string)$request->get('status', '');
        if (!in_array($statusFilter, ['active', 'inactive'], true)) {
            $statusFilter = '';
        }

        $where = ['p.tenant_id = :tid'];
        $params = ['tid' => $tenantId];

        if ($q !== '') {
            $where[] = '(p.name_en LIKE :q0 OR p.name_or LIKE :q1 OR p.sku LIKE :q2 OR p.barcode LIKE :q3)';
            $like = '%' . $q . '%';
            $params['q0'] = $like;
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        if ($categoryFilter !== '') {
            $where[] = 'p.category = :cat';
            $params['cat'] = $categoryFilter;
        }
        if ($statusFilter === 'active') {
            $where[] = 'p.is_active = 1';
        } elseif ($statusFilter === 'inactive') {
            $where[] = 'p.is_active = 0';
        }
        if ($filterLowStock) {
            $where[] = '(p.reorder_level IS NOT NULL AND p.current_stock <= p.reorder_level)';
        }

        return [
            implode(' AND ', $where),
            $params,
            ['q' => $q, 'category' => $categoryFilter, 'status' => $statusFilter, 'lowStock' => $filterLowStock],
        ];
    }

    public function index(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $filterLowStock = $request->get('filter') === 'low_stock';

        $q = trim((string)$request->get('q', ''));
        $categoryFilter = trim((string)$request->get('category', ''));
        $statusFilter = (string)$request->get('status', '');
        if (!in_array($statusFilter, ['active', 'inactive'], true)) {
            $statusFilter = '';
        }

        $sort = (string)$request->get('sort', 'name_en');
        $sortWhitelist = ['name_en', 'sku', 'category', 'sale_price', 'cost_price', 'current_stock', 'is_active', 'created_at'];
        if (!in_array($sort, $sortWhitelist, true)) {
            $sort = 'name_en';
        }
        $dir = strtolower((string)$request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $perPage = 50;
        $page = max(1, (int)$request->get('page', 1));

        [$whereSql, $params] = $this->filterWhere($request, $tenantId);

        $total = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM products p WHERE {$whereSql}", $params)['c'];
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $products = Database::fetchAll("
            SELECT p.*, tc.name as tax_cat_name, tc.rate_percent, u.code as unit_code, u.name_en as unit_name,
                   s.name as supplier_name, s.phone as supplier_phone
            FROM products p
            LEFT JOIN tax_categories tc ON p.tax_category_id = tc.id
            LEFT JOIN units_of_measure u ON p.unit_id = u.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE {$whereSql}
            ORDER BY p.{$sort} {$dir}, p.id ASC
            LIMIT {$perPage} OFFSET {$offset}
        ", $params);

        $units = Database::fetchAll("SELECT * FROM units_of_measure WHERE tenant_id IS NULL OR tenant_id = :tid ORDER BY id ASC", ['tid' => $tenantId]);
        $taxCategories = Database::fetchAll("SELECT * FROM tax_categories WHERE tenant_id IS NULL OR tenant_id = :tid ORDER BY rate_percent ASC", ['tid' => $tenantId]);
        $suppliers = Database::fetchAll("SELECT id, name, phone FROM suppliers WHERE tenant_id = :tid ORDER BY name ASC", ['tid' => $tenantId]);
        $categories = Database::fetchAll(
            "SELECT DISTINCT category FROM products WHERE tenant_id = :tid AND category IS NOT NULL AND category <> '' ORDER BY category ASC",
            ['tid' => $tenantId]
        );
        $categories = array_column($categories, 'category');

        $lowStockCount = (int)Database::fetchOne(
            "SELECT COUNT(*) AS c FROM products WHERE tenant_id = :tid AND is_active = 1 AND reorder_level IS NOT NULL AND current_stock <= reorder_level",
            ['tid' => $tenantId]
        )['c'];

        $kpiRow = Database::fetchOne("
            SELECT COUNT(*) AS total_all,
                   COALESCE(SUM(current_stock * cost_price), 0) AS valuation
            FROM products
            WHERE tenant_id = :tid AND is_active = 1
        ", ['tid' => $tenantId]);

        View::render('products/index', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'products' => $products,
            'units' => $units,
            'taxCategories' => $taxCategories,
            'suppliers' => $suppliers,
            'filterLowStock' => $filterLowStock,
            'q' => $q,
            'categoryFilter' => $categoryFilter,
            'statusFilter' => $statusFilter,
            'categories' => $categories,
            'lowStockCount' => $lowStockCount,
            'totalAll' => (int)$kpiRow['total_all'],
            'totalValuation' => (float)$kpiRow['valuation'],
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
        ]);
    }

    public function show(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $productId = (int)$request->param('id');

        $product = Database::fetchOne("
            SELECT p.*, tc.name as tax_cat_name, tc.rate_percent, u.code as unit_code, u.name_en as unit_name,
                   s.name as supplier_name, s.phone as supplier_phone
            FROM products p
            LEFT JOIN tax_categories tc ON p.tax_category_id = tc.id
            LEFT JOIN units_of_measure u ON p.unit_id = u.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE p.id = :id AND p.tenant_id = :tid
        ", ['id' => $productId, 'tid' => $tenantId]);
        if (!$product) {
            Response::error('Product not found or access denied.', 404);
            return;
        }

        // --- Movement history with filters (Phase 2) ---
        $movTypes = ['OPENING', 'RECEIPT', 'SALE', 'RETURN', 'ADJUSTMENT'];
        $type = (string)$request->get('type', '');
        if (!in_array($type, $movTypes, true)) {
            $type = '';
        }
        $from = (string)$request->get('from', '');
        $to = (string)$request->get('to', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = '';
        }
        $perPage = 50;
        $movPage = max(1, (int)$request->get('page', 1));

        $movWhere = ['sm.tenant_id = :tid', 'sm.product_id = :pid'];
        $movParams = ['tid' => $tenantId, 'pid' => $productId];
        if ($type !== '') {
            $movWhere[] = 'sm.movement_type = :type';
            $movParams['type'] = $type;
        }
        if ($from !== '') {
            $movWhere[] = 'sm.created_at >= :from';
            $movParams['from'] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $movWhere[] = 'sm.created_at <= :to';
            $movParams['to'] = $to . ' 23:59:59';
        }
        $movWhereSql = implode(' AND ', $movWhere);

        $movTotal = (int)Database::fetchOne(
            "SELECT COUNT(*) AS c FROM stock_movements sm WHERE {$movWhereSql}",
            $movParams
        )['c'];
        $movTotalPages = max(1, (int)ceil($movTotal / $perPage));
        if ($movPage > $movTotalPages) {
            $movPage = $movTotalPages;
        }
        $movOffset = ($movPage - 1) * $perPage;

        $movements = Database::fetchAll("
            SELECT sm.*
            FROM stock_movements sm
            WHERE {$movWhereSql}
            ORDER BY sm.id DESC
            LIMIT {$perPage} OFFSET {$movOffset}
        ", $movParams);

        // --- Related documents (last 10 each) ---
        $sales = Database::fetchAll("
            SELECT i.id, i.invoice_number, i.created_at, i.payment_status,
                   ii.quantity, ii.unit_price, ii.line_total
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            WHERE ii.product_id = :pid AND i.tenant_id = :tid
            ORDER BY i.created_at DESC, i.id DESC
            LIMIT 10
        ", ['pid' => $productId, 'tid' => $tenantId]);

        $purchases = Database::fetchAll("
            SELECT pb.id, pb.bill_number, pb.bill_date, pb.status, s.name as supplier_name,
                   pbi.quantity, pbi.unit_cost, pbi.line_total
            FROM purchase_bill_items pbi
            JOIN purchase_bills pb ON pb.id = pbi.purchase_bill_id
            LEFT JOIN suppliers s ON s.id = pb.supplier_id
            WHERE pbi.product_id = :pid AND pb.tenant_id = :tid
            ORDER BY pb.bill_date DESC, pb.id DESC
            LIMIT 10
        ", ['pid' => $productId, 'tid' => $tenantId]);

        $returns = Database::fetchAll("
            SELECT sr.id, sr.return_number, sr.created_at, sr.invoice_id, sr.transaction_type,
                   sri.quantity, sri.line_total
            FROM sales_return_items sri
            JOIN sales_returns sr ON sr.id = sri.sales_return_id
            WHERE sri.product_id = :pid AND sri.tenant_id = :tid
            ORDER BY sr.created_at DESC, sr.id DESC
            LIMIT 10
        ", ['pid' => $productId, 'tid' => $tenantId]);

        // --- KPI computations ---
        $sale = (float)$product['sale_price'];
        $cost = (float)$product['cost_price'];
        $stock = (float)$product['current_stock'];
        $reorder = $product['reorder_level'] !== null ? (float)$product['reorder_level'] : null;
        $marginPct = $sale > 0 ? round(($sale - $cost) / $sale * 100, 1) : null;

        View::render('products/show', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'product' => $product,
            'movements' => $movements,
            'movTotal' => $movTotal,
            'movPage' => $movPage,
            'movTotalPages' => $movTotalPages,
            'movType' => $type,
            'movFrom' => $from,
            'movTo' => $to,
            'sales' => $sales,
            'purchases' => $purchases,
            'returns' => $returns,
            'marginPct' => $marginPct,
            'valuation' => $stock * $cost,
            'isLow' => $reorder !== null && $stock <= $reorder,
            'isOut' => $stock <= 0,
        ]);
    }

    public function export(Request $request): void {
        $tenantId = (int)$request->tenant()['id'];
        $user = $request->user();

        [$whereSql, $params, $meta] = $this->filterWhere($request, $tenantId);

        $rows = Database::fetchAll("
            SELECT p.sku, p.barcode, p.name_en, p.name_or, p.category,
                   u.code as unit_code, p.sale_price, p.cost_price,
                   tc.name as tax_cat_name, p.current_stock, p.reorder_level,
                   s.name as supplier_name, p.price_includes_tax, p.is_active
            FROM products p
            LEFT JOIN units_of_measure u ON p.unit_id = u.id
            LEFT JOIN tax_categories tc ON p.tax_category_id = tc.id
            LEFT JOIN suppliers s ON p.supplier_id = s.id
            WHERE {$whereSql}
            ORDER BY p.name_en ASC
        ", $params);

        AuditService::log('product.exported', 'tenant', $tenantId, [
            'rows' => count($rows),
            'filters' => $meta,
        ], $tenantId, (int)($user['id'] ?? 0));

        $filename = 'products-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'SKU', 'Barcode', 'Name (English)', 'Name (Odia)', 'Category', 'Unit',
            'Sale Price', 'Cost Price', 'GST Classification', 'Stock', 'Reorder Level',
            'Supplier', 'Price Includes GST', 'Status',
        ]);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['sku'] ?? '',
                $row['barcode'] ?? '',
                $row['name_en'],
                $row['name_or'] ?? '',
                $row['category'],
                $row['unit_code'] ?? '',
                number_format((float)$row['sale_price'], 2, '.', ''),
                number_format((float)$row['cost_price'], 2, '.', ''),
                $row['tax_cat_name'] ?? '',
                number_format((float)$row['current_stock'], 3, '.', ''),
                $row['reorder_level'] !== null ? number_format((float)$row['reorder_level'], 3, '.', '') : '',
                $row['supplier_name'] ?? '',
                (int)$row['price_includes_tax'] === 1 ? 'yes' : 'no',
                (int)$row['is_active'] === 1 ? 'active' : 'inactive',
            ]);
        }
        fclose($out);
        exit;
    }

    public function importForm(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];

        if ($request->get('cancel')) {
            unset($_SESSION['_product_import']);
            Response::redirect('/app/products/import');
            return;
        }

        if ($request->get('template')) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="product-import-template.csv"');
            header('Cache-Control: no-store');
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['sku', 'name', 'category', 'unit', 'stock', 'cost_price', 'sale_price']);
            fputcsv($out, ['SKU-001', 'Example Product 1kg', 'Grocery', 'PCS', '10', '50.00', '75.00']);
            fputcsv($out, ['SKU-002', 'Example Product 500g', 'Grocery', 'PKT', '24', '25.00', '40.00']);
            fclose($out);
            exit;
        }

        $preview = $_SESSION['_product_import'] ?? null;

        View::render('products/import', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'preview' => $preview,
        ]);
    }

    public function importStore(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $user = $request->user();
        $step = (string)$request->input('step', 'preview');

        if ($step === 'commit') {
            $this->importCommit($request, $tenantId, $user);
            return;
        }

        // --- STEP 1: parse + validate (zero writes) ---
        $file = $request->files('csv_file');
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Please choose a CSV file to upload.');
            Response::redirect('/app/products/import');
            return;
        }
        if ((int)$file['size'] > 2 * 1024 * 1024) {
            flash('error', 'File is too large (max 2 MB).');
            Response::redirect('/app/products/import');
            return;
        }
        $content = file_get_contents($file['tmp_name']);
        if ($content === false || trim($content) === '') {
            flash('error', 'Could not read the uploaded file.');
            Response::redirect('/app/products/import');
            return;
        }
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        $expected = ['sku', 'name', 'category', 'unit', 'stock', 'cost_price', 'sale_price'];
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $header = str_getcsv((string)array_shift($lines));
        $header = array_map('trim', $header);
        if ($header !== $expected) {
            flash('error', 'Unexpected CSV header. Expected: ' . implode(',', $expected) . ' (download the template for the exact format).');
            Response::redirect('/app/products/import');
            return;
        }

        // Lookups for validation
        $unitRows = Database::fetchAll(
            "SELECT code FROM units_of_measure WHERE tenant_id IS NULL OR tenant_id = :tid",
            ['tid' => $tenantId]
        );
        $knownUnits = array_column($unitRows, 'code');
        $existingSkus = array_column(
            Database::fetchAll("SELECT sku FROM products WHERE tenant_id = :tid AND sku IS NOT NULL", ['tid' => $tenantId]),
            'sku'
        );
        $existingSkus = array_flip($existingSkus);

        $validRows = [];
        $errors = [];
        $creates = 0;
        $updates = 0;
        $unitsNeeded = [];
        $lineNo = 1;

        foreach ($lines as $line) {
            $lineNo++;
            if (trim((string)$line) === '') {
                continue;
            }
            if (count($validRows) >= 5000) {
                $errors[] = "Row limit exceeded — only the first 5,000 rows are imported.";
                break;
            }
            $row = str_getcsv($line);
            if (count($row) < 7) {
                $errors[] = "Line {$lineNo}: expected 7 columns, got " . count($row);
                continue;
            }
            [$sku, $name, $category, $unit, $stock, $cost, $sale] = array_map('trim', $row);

            if ($sku === '' || $name === '' || $category === '') {
                $errors[] = "Line {$lineNo}: sku, name and category are required";
                continue;
            }
            if (strlen($sku) > 50 || strlen($name) > 150 || strlen($category) > 50) {
                $errors[] = "Line {$lineNo}: field too long ({$sku})";
                continue;
            }
            if (!is_numeric($stock) || !is_numeric($cost) || !is_numeric($sale)) {
                $errors[] = "Line {$lineNo}: non-numeric stock/price ({$sku})";
                continue;
            }
            if ((float)$cost < 0 || (float)$sale < 0) {
                $errors[] = "Line {$lineNo}: negative price ({$sku})";
                continue;
            }
            if ($unit === '') {
                $errors[] = "Line {$lineNo}: unit is required ({$sku})";
                continue;
            }
            if (!in_array($unit, $knownUnits, true)) {
                $unitsNeeded[$unit] = true;
            }

            $isUpdate = isset($existingSkus[$sku]);
            if ($isUpdate) {
                $updates++;
            } else {
                $creates++;
            }

            $validRows[] = [
                'line' => $lineNo,
                'sku' => $sku,
                'name' => $name,
                'category' => substr($category, 0, 50),
                'unit' => $unit,
                'stock' => max(0, (float)$stock),
                'cost' => round((float)$cost, 2),
                'sale' => round((float)$sale, 2),
                'action' => $isUpdate ? 'update' : 'create',
            ];
        }

        $_SESSION['_product_import'] = [
            'hash' => hash('sha256', $content),
            'filename' => basename((string)$file['name']),
            'rows' => $validRows,
            'errors' => $errors,
            'creates' => $creates,
            'updates' => $updates,
            'units_to_create' => array_keys($unitsNeeded),
            'created_at' => date('Y-m-d H:i:s'),
        ];

        View::render('products/import', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'preview' => $_SESSION['_product_import'],
        ]);
    }

    protected function importCommit(Request $request, int $tenantId, ?array $user): void {
        $preview = $_SESSION['_product_import'] ?? null;
        if (!$preview || empty($preview['rows'])) {
            flash('error', 'No import preview found — please upload your CSV again.');
            Response::redirect('/app/products/import');
            return;
        }
        $hash = (string)$request->input('hash', '');
        if ($hash === '' || !hash_equals((string)$preview['hash'], $hash)) {
            flash('error', 'Import session expired — please upload your CSV again.');
            Response::redirect('/app/products/import');
            return;
        }
        $hasErrors = !empty($preview['errors']);
        $validOnly = (bool)$request->input('valid_only');
        if ($hasErrors && !$validOnly) {
            flash('error', 'Fix the errors (or tick "import valid rows only") and re-upload.');
            Response::redirect('/app/products/import');
            return;
        }

        $rows = $preview['rows'];
        $unitsToCreate = $preview['units_to_create'] ?? [];

        try {
            $summary = Database::transaction(function (\PDO $pdo) use ($tenantId, $rows, $unitsToCreate) {
                // Ensure missing global units exist
                $unitIds = $pdo->query(
                    "SELECT code, id FROM units_of_measure WHERE tenant_id IS NULL"
                )->fetchAll(\PDO::FETCH_KEY_PAIR);
                foreach ($unitsToCreate as $code) {
                    if (isset($unitIds[$code])) {
                        continue;
                    }
                    $pdo->prepare("INSERT INTO units_of_measure (tenant_id, code, name_en, allow_decimal) VALUES (NULL, ?, ?, 0)")
                        ->execute([$code, $code]);
                    $unitIds[$code] = (int)$pdo->lastInsertId();
                }
                // Tenant-scoped units too (in case only tenant has the code)
                foreach ($pdo->query(
                    "SELECT code, id FROM units_of_measure WHERE tenant_id = " . (int)$tenantId
                )->fetchAll(\PDO::FETCH_KEY_PAIR) as $code => $id) {
                    if (!isset($unitIds[$code])) {
                        $unitIds[$code] = (int)$id;
                    }
                }

                $insertProduct = $pdo->prepare("
                    INSERT INTO products (
                        tenant_id, sku, barcode, name_en, name_or, unit_id, tax_category_id, supplier_id,
                        sale_price, cost_price, price_includes_tax, current_stock, reorder_level, category, is_active
                    ) VALUES (?, ?, NULL, ?, NULL, ?, NULL, NULL, ?, ?, 1, ?, 5, ?, 1)
                ");
                $insertMovement = $pdo->prepare("
                    INSERT INTO stock_movements (tenant_id, product_id, movement_type, quantity, reference_type, notes)
                    VALUES (?, ?, 'OPENING', ?, 'manual', 'CSV import')
                ");
                $updateProduct = $pdo->prepare("
                    UPDATE products
                    SET name_en = ?, category = ?, cost_price = ?, sale_price = ?, unit_id = ?
                    WHERE tenant_id = ? AND sku = ?
                ");
                $checkSku = $pdo->prepare("SELECT id FROM products WHERE tenant_id = :tid AND sku = :sku LIMIT 1");

                $created = 0;
                $updated = 0;
                foreach ($rows as $row) {
                    $unitId = (int)($unitIds[$row['unit']] ?? 0);
                    if ($unitId === 0) {
                        // Should not happen — units were ensured above; skip defensively
                        continue;
                    }
                    $checkSku->execute(['tid' => $tenantId, 'sku' => $row['sku']]);
                    $existing = $checkSku->fetch(\PDO::FETCH_ASSOC);

                    if ($existing) {
                        $updateProduct->execute([
                            $row['name'], $row['category'], $row['cost'], $row['sale'], $unitId,
                            $tenantId, $row['sku'],
                        ]);
                        $updated++;
                    } else {
                        $insertProduct->execute([
                            $tenantId, $row['sku'], $row['name'], $unitId,
                            $row['sale'], $row['cost'], $row['stock'], $row['category'],
                        ]);
                        $productId = (int)$pdo->lastInsertId();
                        if ($row['stock'] > 0) {
                            $insertMovement->execute([$tenantId, $productId, $row['stock']]);
                        }
                        $created++;
                    }
                }

                return ['created' => $created, 'updated' => $updated];
            });
        } catch (\Throwable $e) {
            flash('error', 'Import failed: ' . $e->getMessage());
            Response::redirect('/app/products/import');
            return;
        }

        AuditService::log('product.imported', 'tenant', $tenantId, [
            'filename' => $preview['filename'],
            'created' => $summary['created'],
            'updated' => $summary['updated'],
            'errors' => count($preview['errors']),
        ], $tenantId, (int)($user['id'] ?? 0));

        unset($_SESSION['_product_import']);

        flash('success', sprintf(
            'Import complete: %d created, %d updated. Existing stock quantities were not changed.',
            $summary['created'],
            $summary['updated']
        ));
        Response::redirect('/app/products');
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

        if ($this->barcodeTaken($tenantId, $data['barcode'])) {
            flash('error', 'Barcode already exists for another product.');
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
            $message = str_contains($e->getMessage(), 'Duplicate') ? 'SKU already exists for this store.' : 'Could not save product.';
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

        if ($this->barcodeTaken($tenantId, $data['barcode'], $productId)) {
            flash('error', 'Barcode already exists for another product.');
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
            $message = str_contains($e->getMessage(), 'Duplicate') ? 'SKU already exists for another product.' : 'Could not update product.';
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

    public function quickEdit(Request $request): void {
        $tenantId = (int)$request->tenant()['id'];
        $productId = (int)$request->param('id');
        $user = $request->user();

        $field = (string)$request->input('field', '');
        $value = $request->input('value');

        $allowed = ['sale_price', 'cost_price', 'category', 'reorder_level'];
        if (!in_array($field, $allowed, true)) {
            Response::json(['ok' => false, 'error' => 'Field not editable.'], 422);
        }

        $product = Database::fetchOne(
            "SELECT id, name_en, sale_price, cost_price, category, reorder_level FROM products WHERE id = :id AND tenant_id = :tid",
            ['id' => $productId, 'tid' => $tenantId]
        );
        if (!$product) {
            Response::json(['ok' => false, 'error' => 'Product not found.'], 404);
        }

        $oldValue = $product[$field];

        switch ($field) {
            case 'sale_price':
            case 'cost_price':
                if (!is_numeric($value) || (float)$value < 0) {
                    Response::json(['ok' => false, 'error' => 'Price must be a number of 0 or more.'], 422);
                }
                $newValue = round((float)$value, 2);
                break;
            case 'reorder_level':
                if (!is_numeric($value) || (float)$value < 0) {
                    Response::json(['ok' => false, 'error' => 'Reorder level must be a number of 0 or more.'], 422);
                }
                $newValue = round((float)$value, 3);
                break;
            case 'category':
                $newValue = substr(trim((string)$value) ?: 'General', 0, 50);
                break;
            default:
                Response::json(['ok' => false, 'error' => 'Field not editable.'], 422);
        }

        try {
            Database::query(
                "UPDATE products SET {$field} = ? WHERE id = ? AND tenant_id = ?",
                [$newValue, $productId, $tenantId]
            );
        } catch (\PDOException $e) {
            Response::json(['ok' => false, 'error' => 'Could not save change.'], 500);
        }

        AuditService::log('product.quick_edit', 'product', $productId, [
            'name' => $product['name_en'],
            'field' => $field,
            'old' => $oldValue,
            'new' => $newValue,
        ], $tenantId, (int)($user['id'] ?? 0));

        $display = in_array($field, ['sale_price', 'cost_price'], true)
            ? money_format_inr($newValue)
            : (string)$newValue;

        Response::json(['ok' => true, 'field' => $field, 'value' => $newValue, 'display' => $display]);
    }

    public function bulk(Request $request): void {
        $tenantId = (int)$request->tenant()['id'];
        $user = $request->user();

        $action = (string)$request->input('action', '');
        $value = $request->input('value');
        $ids = $request->input('product_ids');
        if (!is_array($ids) || $ids === []) {
            flash('error', 'No products selected.');
            Response::redirect('/app/products');
            return;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if ($ids === []) {
            flash('error', 'No products selected.');
            Response::redirect('/app/products');
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $allowedActions = ['set_sale_price', 'increase_sale_price_pct', 'decrease_sale_price_pct', 'set_cost_price', 'set_category', 'set_reorder_level', 'enable', 'disable'];
        if (!in_array($action, $allowedActions, true)) {
            flash('error', 'Unknown bulk action.');
            Response::redirect('/app/products');
            return;
        }

        if ($action === 'set_sale_price' || $action === 'set_cost_price') {
            if (!is_numeric($value) || (float)$value < 0) {
                flash('error', 'Enter a valid price of 0 or more.');
                Response::redirect('/app/products');
                return;
            }
        } elseif ($action === 'increase_sale_price_pct' || $action === 'decrease_sale_price_pct') {
            if (!is_numeric($value) || (float)$value < 0 || (float)$value > 1000) {
                flash('error', 'Enter a percentage between 0 and 1000.');
                Response::redirect('/app/products');
                return;
            }
        } elseif ($action === 'set_reorder_level') {
            if (!is_numeric($value) || (float)$value < 0) {
                flash('error', 'Enter a reorder level of 0 or more.');
                Response::redirect('/app/products');
                return;
            }
        } elseif ($action === 'set_category') {
            $value = substr(trim((string)$value) ?: 'General', 0, 50);
        }

        try {
            $updated = Database::transaction(function (\PDO $pdo) use ($action, $value, $ids, $placeholders, $tenantId) {
                $params = [];
                switch ($action) {
                    case 'set_sale_price':
                        $sql = "UPDATE products SET sale_price = ? WHERE tenant_id = ? AND id IN ({$placeholders})";
                        $params = [round((float)$value, 2), $tenantId];
                        break;
                    case 'set_cost_price':
                        $sql = "UPDATE products SET cost_price = ? WHERE tenant_id = ? AND id IN ({$placeholders})";
                        $params = [round((float)$value, 2), $tenantId];
                        break;
                    case 'increase_sale_price_pct':
                        $sql = "UPDATE products SET sale_price = ROUND(sale_price * (1 + ? / 100), 2) WHERE tenant_id = ? AND id IN ({$placeholders})";
                        $params = [(float)$value, $tenantId];
                        break;
                    case 'decrease_sale_price_pct':
                        $sql = "UPDATE products SET sale_price = GREATEST(0, ROUND(sale_price * (1 - ? / 100), 2)) WHERE tenant_id = ? AND id IN ({$placeholders})";
                        $params = [(float)$value, $tenantId];
                        break;
                    case 'set_category':
                        $sql = "UPDATE products SET category = ? WHERE tenant_id = ? AND id IN ({$placeholders})";
                        $params = [$value, $tenantId];
                        break;
                    case 'set_reorder_level':
                        $sql = "UPDATE products SET reorder_level = ? WHERE tenant_id = ? AND id IN ({$placeholders})";
                        $params = [max(0, (float)$value), $tenantId];
                        break;
                    case 'enable':
                        $sql = "UPDATE products SET is_active = 1 WHERE tenant_id = ? AND id IN ({$placeholders})";
                        $params = [$tenantId];
                        break;
                    case 'disable':
                        $sql = "UPDATE products SET is_active = 0 WHERE tenant_id = ? AND id IN ({$placeholders})";
                        $params = [$tenantId];
                        break;
                    default:
                        throw new \RuntimeException('Unknown action');
                }
                $params = array_merge($params, $ids);
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                return $stmt->rowCount();
            });
        } catch (\Throwable $e) {
            flash('error', 'Bulk update failed.');
            Response::redirect('/app/products');
            return;
        }

        AuditService::log('product.bulk_update', 'tenant', $tenantId, [
            'action' => $action,
            'value' => is_scalar($value) ? $value : null,
            'selected' => count($ids),
            'updated' => $updated,
        ], $tenantId, (int)($user['id'] ?? 0));

        flash('success', "Bulk action \"{$action}\" applied to {$updated} product(s).");
        Response::redirect('/app/products');
    }

    protected function barcodeTaken(int $tenantId, ?string $barcode, ?int $excludeProductId = null): bool {
        if ($barcode === null || $barcode === '') {
            return false;
        }
        $sql = "SELECT id FROM products WHERE tenant_id = :tid AND barcode = :bc";
        $params = ['tid' => $tenantId, 'bc' => $barcode];
        if ($excludeProductId !== null) {
            $sql .= " AND id <> :pid";
            $params['pid'] = $excludeProductId;
        }
        $sql .= " LIMIT 1";
        return Database::fetchOne($sql, $params) !== null;
    }

    protected function validated(Request $request, int $tenantId) {
        $nameEn = trim((string)$request->input('name_en', ''));
        if ($nameEn === '' || mb_strlen($nameEn) > 150) {
            return 'English Product Name is required (max 150 characters).';
        }

        $nameOr = trim((string)$request->input('name_or', '')) ?: null;
        if ($nameOr !== null && mb_strlen($nameOr) > 150) {
            return 'Odia Product Name must be 150 characters or fewer.';
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

        if ($unitId) {
            $unit = Database::fetchOne(
                "SELECT id FROM units_of_measure WHERE id = :id AND (tenant_id IS NULL OR tenant_id = :tid)",
                ['id' => $unitId, 'tid' => $tenantId]
            );
            if (!$unit) {
                return 'Selected unit of measure was not found.';
            }
        }
        if ($taxCatId) {
            $taxCat = Database::fetchOne(
                "SELECT id FROM tax_categories WHERE id = :id AND (tenant_id IS NULL OR tenant_id = :tid)",
                ['id' => $taxCatId, 'tid' => $tenantId]
            );
            if (!$taxCat) {
                return 'Selected GST tax category was not found.';
            }
        }
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
            'name_or' => $nameOr,
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

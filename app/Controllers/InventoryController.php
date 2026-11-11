<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;

class InventoryController {
    /**
     * Builds the shared stock-list WHERE clause for index + export.
     * @return array{0:string,1:array,2:array} [whereSql, params, meta]
     */
    protected function filterWhere(Request $request, int $tenantId): array {
        $q = trim((string)$request->get('q', ''));
        $stockStatus = (string)$request->get('stock_status', 'all');
        if (!in_array($stockStatus, ['all', 'in', 'low', 'out'], true)) {
            $stockStatus = 'all';
        }

        $where = ['p.tenant_id = :tid', 'p.is_active = 1'];
        $params = ['tid' => $tenantId];

        if ($q !== '') {
            $where[] = '(p.name_en LIKE :q0 OR p.name_or LIKE :q1 OR p.sku LIKE :q2 OR p.barcode LIKE :q3)';
            $like = '%' . $q . '%';
            $params['q0'] = $like;
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }
        if ($stockStatus === 'out') {
            $where[] = 'p.current_stock <= 0';
        } elseif ($stockStatus === 'low') {
            $where[] = '(p.current_stock > 0 AND p.reorder_level IS NOT NULL AND p.current_stock <= p.reorder_level)';
        } elseif ($stockStatus === 'in') {
            $where[] = '(p.current_stock > 0 AND (p.reorder_level IS NULL OR p.current_stock > p.reorder_level))';
        }

        return [
            implode(' AND ', $where),
            $params,
            ['q' => $q, 'stock_status' => $stockStatus],
        ];
    }

    public function index(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];

        $q = trim((string)$request->get('q', ''));
        $stockStatus = (string)$request->get('stock_status', 'all');
        if (!in_array($stockStatus, ['all', 'in', 'low', 'out'], true)) {
            $stockStatus = 'all';
        }

        $sort = (string)$request->get('sort', 'current_stock');
        $sortWhitelist = ['name_en', 'sku', 'current_stock', 'cost_price', 'reorder_level'];
        if (!in_array($sort, $sortWhitelist, true)) {
            $sort = 'current_stock';
        }
        $dir = strtolower((string)$request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';

        $perPage = 50;
        $page = max(1, (int)$request->get('page', 1));

        // KPIs always cover the full active catalogue (not the filtered page)
        $kpiRow = Database::fetchOne("
            SELECT COUNT(*) AS sku_count,
                   COALESCE(SUM(p.current_stock * p.cost_price), 0) AS total_valuation,
                   COALESCE(SUM(CASE WHEN p.reorder_level IS NOT NULL AND p.current_stock <= p.reorder_level THEN 1 ELSE 0 END), 0) AS low_count
            FROM products p
            WHERE p.tenant_id = :tid AND p.is_active = 1
        ", ['tid' => $tenantId]);

        [$whereSql, $params] = $this->filterWhere($request, $tenantId);

        $total = (int)Database::fetchOne("SELECT COUNT(*) AS c FROM products p WHERE {$whereSql}", $params)['c'];
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $stockList = Database::fetchAll("
            SELECT p.*, u.name_en as unit_name,
                   (p.current_stock * p.cost_price) as stock_valuation
            FROM products p
            LEFT JOIN units_of_measure u ON p.unit_id = u.id
            WHERE {$whereSql}
            ORDER BY p.{$sort} {$dir}, p.id ASC
            LIMIT {$perPage} OFFSET {$offset}
        ", $params);

        $movTypes = ['OPENING', 'RECEIPT', 'SALE', 'RETURN', 'ADJUSTMENT'];
        $movType = (string)$request->get('mov_type', '');
        if (!in_array($movType, $movTypes, true)) {
            $movType = '';
        }
        $movFrom = (string)$request->get('mov_from', '');
        $movTo = (string)$request->get('mov_to', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $movFrom)) {
            $movFrom = '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $movTo)) {
            $movTo = '';
        }
        $movPerPage = 50;
        $movPage = max(1, (int)$request->get('mov_page', 1));

        $movWhere = ['sm.tenant_id = :tid'];
        $movParams = ['tid' => $tenantId];
        if ($movType !== '') {
            $movWhere[] = 'sm.movement_type = :mtype';
            $movParams['mtype'] = $movType;
        }
        if ($movFrom !== '') {
            $movWhere[] = 'sm.created_at >= :mfrom';
            $movParams['mfrom'] = $movFrom . ' 00:00:00';
        }
        if ($movTo !== '') {
            $movWhere[] = 'sm.created_at <= :mto';
            $movParams['mto'] = $movTo . ' 23:59:59';
        }
        $movWhereSql = implode(' AND ', $movWhere);

        $movTotal = (int)Database::fetchOne(
            "SELECT COUNT(*) AS c FROM stock_movements sm WHERE {$movWhereSql}",
            $movParams
        )['c'];
        $movTotalPages = max(1, (int)ceil($movTotal / $movPerPage));
        if ($movPage > $movTotalPages) {
            $movPage = $movTotalPages;
        }
        $movOffset = ($movPage - 1) * $movPerPage;

        $movements = Database::fetchAll("
            SELECT sm.*, p.name_en as product_name, p.sku
            FROM stock_movements sm
            JOIN products p ON sm.product_id = p.id AND p.tenant_id = sm.tenant_id
            WHERE {$movWhereSql}
            ORDER BY sm.id DESC
            LIMIT {$movPerPage} OFFSET {$movOffset}
        ", $movParams);

        View::render('inventory/index', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'stockList' => $stockList,
            'movements' => $movements,
            'totalValuation' => (float)$kpiRow['total_valuation'],
            'lowStockCount' => (int)$kpiRow['low_count'],
            'totalSkus' => (int)$kpiRow['sku_count'],
            'q' => $q,
            'stockStatus' => $stockStatus,
            'sort' => $sort,
            'dir' => $dir,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'totalPages' => $totalPages,
            'movType' => $movType,
            'movFrom' => $movFrom,
            'movTo' => $movTo,
            'movPage' => $movPage,
            'movTotalPages' => $movTotalPages,
            'movTotal' => $movTotal,
        ]);
    }

    public function export(Request $request): void {
        $tenantId = (int)$request->tenant()['id'];
        $user = $request->user();

        [$whereSql, $params, $meta] = $this->filterWhere($request, $tenantId);

        $rows = Database::fetchAll("
            SELECT p.sku, p.name_en, p.category, u.name_en as unit_name, u.code as unit_code,
                   p.cost_price, p.sale_price, p.current_stock, p.reorder_level,
                   (p.current_stock * p.cost_price) as stock_valuation, p.is_active
            FROM products p
            LEFT JOIN units_of_measure u ON p.unit_id = u.id
            WHERE {$whereSql}
            ORDER BY p.current_stock ASC, p.name_en ASC
        ", $params);

        AuditService::log('inventory.exported', 'tenant', $tenantId, [
            'rows' => count($rows),
            'filters' => $meta,
        ], $tenantId, (int)($user['id'] ?? 0));

        $filename = 'inventory-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'SKU', 'Product', 'Category', 'Unit', 'Cost Price', 'Sale Price',
            'Stock on Hand', 'Reorder Level', 'Valuation', 'Status',
        ]);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['sku'] ?? '',
                $row['name_en'],
                $row['category'],
                trim(($row['unit_name'] ?? '') . ' (' . ($row['unit_code'] ?? '') . ')'),
                number_format((float)$row['cost_price'], 2, '.', ''),
                number_format((float)$row['sale_price'], 2, '.', ''),
                number_format((float)$row['current_stock'], 3, '.', ''),
                $row['reorder_level'] !== null ? number_format((float)$row['reorder_level'], 3, '.', '') : '',
                number_format((float)$row['stock_valuation'], 2, '.', ''),
                (int)$row['is_active'] === 1 ? 'active' : 'inactive',
            ]);
        }
        fclose($out);
        exit;
    }

    public function adjust(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $user = $request->user();

        $productId = (int)$request->input('product_id');
        $mode = (string)$request->input('mode', 'delta') === 'counted' ? 'counted' : 'delta';
        $adjustmentQty = (float)$request->input('quantity', 0);
        $countedQty = $request->input('counted_quantity');
        $reason = trim((string)$request->input('reason', 'Stock count adjustment'));

        if ($productId <= 0) {
            flash('error', 'Please select a valid product.');
            Response::redirect('/app/inventory');
            return;
        }
        if ($mode === 'delta' && $adjustmentQty == 0) {
            flash('error', 'Please provide a non-zero adjustment quantity.');
            Response::redirect('/app/inventory');
            return;
        }
        if ($mode === 'counted' && ($countedQty === null || $countedQty === '' || !is_numeric($countedQty) || (float)$countedQty < 0)) {
            flash('error', 'Please provide the counted quantity (0 or more).');
            Response::redirect('/app/inventory');
            return;
        }
        if (mb_strlen($reason) > 500) {
            $reason = mb_substr($reason, 0, 500);
        }

        try {
            Database::transaction(function (\PDO $pdo) use ($tenantId, $productId, $mode, $adjustmentQty, $countedQty, $reason, $tenant, $user) {
                $stmt = $pdo->prepare("
                    SELECT id, name_en, current_stock, is_active
                    FROM products
                    WHERE id = ? AND tenant_id = ?
                    FOR UPDATE
                ");
                $stmt->execute([$productId, $tenantId]);
                $product = $stmt->fetch(\PDO::FETCH_ASSOC);

                if (!$product) {
                    throw new \RuntimeException('Product not found for this store.');
                }
                if ((int)$product['is_active'] !== 1) {
                    throw new \RuntimeException('Cannot adjust stock for an inactive product.');
                }

                $before = (float)$product['current_stock'];
                if ($mode === 'counted') {
                    $delta = (float)$countedQty - $before;
                    if ($delta == 0) {
                        throw new \RuntimeException('Counted quantity matches current stock — nothing to adjust.');
                    }
                } else {
                    $delta = $adjustmentQty;
                }
                $after = $before + $delta;

                if ($after < 0 && empty($tenant['allow_negative_stock'])) {
                    throw new \RuntimeException(sprintf(
                        'Adjustment would set stock to %s (below zero). Negative stock is not allowed for this store.',
                        $after
                    ));
                }

                $pdo->prepare("
                    UPDATE products SET current_stock = current_stock + ? WHERE id = ? AND tenant_id = ?
                ")->execute([$delta, $productId, $tenantId]);

                $notes = sprintf(
                    '%s | before: %s, after: %s%s',
                    $reason,
                    $before,
                    $after,
                    $mode === 'counted' ? ' (counted: ' . (float)$countedQty . ')' : ''
                );
                $pdo->prepare("
                    INSERT INTO stock_movements (tenant_id, product_id, movement_type, quantity, reference_type, notes)
                    VALUES (?, ?, 'ADJUSTMENT', ?, 'manual', ?)
                ")->execute([$tenantId, $productId, $delta, $notes]);

                AuditService::log('inventory.adjusted', 'product', $productId, [
                    'name' => $product['name_en'],
                    'mode' => $mode,
                    'quantity' => $delta,
                    'stock_before' => $before,
                    'stock_after' => $after,
                    'reason' => $reason,
                ], $tenantId, (int)($user['id'] ?? 0));
            });
        } catch (\RuntimeException $e) {
            flash('error', $e->getMessage());
            Response::redirect('/app/inventory');
            return;
        } catch (\Throwable $e) {
            flash('error', 'Could not record stock adjustment.');
            Response::redirect('/app/inventory');
            return;
        }

        flash('success', 'Stock adjustment recorded successfully.');
        Response::redirect('/app/inventory');
    }
}

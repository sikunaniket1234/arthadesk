<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;

class InventoryController {
    public function index(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = $tenant['id'];

        $stockList = Database::fetchAll("
            SELECT p.*, u.name_en as unit_name,
                   (p.current_stock * p.cost_price) as stock_valuation
            FROM products p
            LEFT JOIN units_of_measure u ON p.unit_id = u.id
            WHERE p.tenant_id = :tid
            ORDER BY p.current_stock ASC
        ", ['tid' => $tenantId]);

        $movements = Database::fetchAll("
            SELECT sm.*, p.name_en as product_name, p.sku
            FROM stock_movements sm
            JOIN products p ON sm.product_id = p.id
            WHERE sm.tenant_id = :tid
            ORDER BY sm.id DESC
            LIMIT 50
        ", ['tid' => $tenantId]);

        $totalValuation = array_sum(array_column($stockList, 'stock_valuation'));
        $lowStockCount = count(array_filter($stockList, fn($p) => (float)$p['current_stock'] <= (float)$p['reorder_level']));

        View::render('inventory/index', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'stockList' => $stockList,
            'movements' => $movements,
            'totalValuation' => $totalValuation,
            'lowStockCount' => $lowStockCount,
        ]);
    }

    public function adjust(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = $tenant['id'];

        $productId = (int)$request->input('product_id');
        $adjustmentQty = (float)$request->input('quantity', 0);
        $reason = trim($request->input('reason', 'Stock count adjustment'));

        if ($productId <= 0 || $adjustmentQty == 0) {
            flash('error', 'Please provide a valid product and non-zero adjustment quantity.');
            Response::redirect('/app/inventory');
            return;
        }

        Database::transaction(function(\PDO $pdo) use ($tenantId, $productId, $adjustmentQty, $reason) {
            $pdo->prepare("
                UPDATE products SET current_stock = current_stock + ? WHERE id = ? AND tenant_id = ?
            ")->execute([$adjustmentQty, $productId, $tenantId]);

            $pdo->prepare("
                INSERT INTO stock_movements (tenant_id, product_id, movement_type, quantity, reference_type, notes)
                VALUES (?, ?, 'ADJUSTMENT', ?, 'manual', ?)
            ")->execute([$tenantId, $productId, $adjustmentQty, $reason]);
        });

        flash('success', 'Stock adjustment recorded successfully.');
        Response::redirect('/app/inventory');
    }
}

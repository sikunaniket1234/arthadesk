<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Core\Logger;
use App\Services\ReturnTransactionService;
use Exception;

class ReturnController {
    public function index(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = $tenant['id'];

        $returns = Database::fetchAll("
            SELECT r.*, i.invoice_number, COALESCE(c.name, i.walkin_name, 'Walk-in Customer') as customer_name
            FROM sales_returns r
            JOIN invoices i ON r.invoice_id = i.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE r.tenant_id = :tid
            ORDER BY r.id DESC
        ", ['tid' => $tenantId]);

        View::render('returns/index', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'returns' => $returns,
        ]);
    }

    public function create(Request $request): void {
        $tenant = $request->tenant();
        $invoiceId = (int)$request->get('invoice_id');

        $invoice = Database::fetchOne("
            SELECT i.*, COALESCE(c.name, i.walkin_name, 'Walk-in Customer') as customer_name
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE i.id = :id AND i.tenant_id = :tid
        ", ['id' => $invoiceId, 'tid' => $tenant['id']]);

        if (!$invoice) {
            flash('error', 'Invoice not found.');
            Response::redirect('/app/invoices');
            return;
        }

        $items = Database::fetchAll("
            SELECT * FROM invoice_items WHERE invoice_id = :id AND tenant_id = :tid
        ", ['id' => $invoiceId, 'tid' => $tenant['id']]);

        View::render('returns/create', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'invoice' => $invoice,
            'items' => $items,
        ]);
    }

    public function store(Request $request): void {
        $tenant = $request->tenant();
        $taxProfile = $request->taxProfile();

        $invoiceId = (int)$request->input('invoice_id');
        $refundMethod = $request->input('refund_method', 'cash');
        $reason = trim($request->input('reason', 'Customer requested return/exchange'));
        $rawItems = $request->input('items', []);

        $returnItems = [];
        foreach ($rawItems as $item) {
            $qty = (float)($item['quantity'] ?? 0);
            if ($qty > 0) {
                $returnItems[] = [
                    'invoice_item_id' => (int)$item['invoice_item_id'],
                    'quantity' => $qty,
                    'is_restocked' => !empty($item['is_restocked']),
                ];
            }
        }

        if (empty($returnItems)) {
            flash('error', 'Please enter a return quantity greater than 0 for at least one item.');
            Response::redirect("/app/returns/create?invoice_id={$invoiceId}");
            return;
        }

        try {
            $res = ReturnTransactionService::processReturn($tenant, $taxProfile, $invoiceId, $returnItems, $refundMethod, $reason);
            flash('success', "Return processed successfully! Credit Note #{$res['return_number']} generated. Amount refunded: ₹ " . number_format($res['total_refund'], 2));
            Response::redirect('/app/returns');
        } catch (\PDOException $e) {
            Logger::error('return_failed', ['tenant_id' => (int)$tenant['id'], 'error' => $e->getMessage()], 'returns');
            flash('error', 'Return could not be processed. Please try again.');
            Response::redirect("/app/returns/create?invoice_id={$invoiceId}");
        } catch (Exception $e) {
            flash('error', "Return failed: " . $e->getMessage());
            Response::redirect("/app/returns/create?invoice_id={$invoiceId}");
        }
    }

    /**
     * Exchange Step 1: Search invoice by number.
     */
    public function exchange(Request $request): void {
        $tenant   = $request->tenant();
        $invoiceNo = trim($request->query('invoice', ''));
        $invoice   = null;
        $invoiceItems = [];
        $allProducts = [];
        $searchError = null;

        if ($invoiceNo !== '') {
            $invoice = Database::fetchOne("
                SELECT i.*, COALESCE(c.name, i.walkin_name, 'Walk-in Customer') as customer_name
                FROM invoices i
                LEFT JOIN customers c ON i.customer_id = c.id
                WHERE i.invoice_number = :num AND i.tenant_id = :tid AND i.payment_status != 'cancelled'
            ", ['num' => $invoiceNo, 'tid' => $tenant['id']]);

            if (!$invoice) {
                $searchError = "Invoice '{$invoiceNo}' not found or already cancelled.";
            } else {
                $invoiceItems = Database::fetchAll("
                    SELECT * FROM invoice_items WHERE invoice_id = :id AND tenant_id = :tid
                ", ['id' => $invoice['id'], 'tid' => $tenant['id']]);

                $allProducts = Database::fetchAll("
                    SELECT id, name_en, sale_price, current_stock, unit_id
                    FROM products
                    WHERE tenant_id = :tid AND is_active = 1
                    ORDER BY name_en ASC
                ", ['tid' => $tenant['id']]);
            }
        }

        View::render('returns/exchange', [
            'tenant'       => $tenant,
            'taxProfile'   => $request->taxProfile(),
            'invoice'      => $invoice,
            'invoiceItems' => $invoiceItems,
            'allProducts'  => $allProducts,
            'searchError'  => $searchError,
        ]);
    }

    /**
     * Exchange Step 2: Process return + new sale atomically.
     */
    public function storeExchange(Request $request): void {
        $tenant    = $request->tenant();
        $taxProfile = $request->taxProfile();
        $tenantId  = $tenant['id'];

        $invoiceId        = (int)$request->input('invoice_id');
        $returnItemsRaw   = $request->input('return_items', []);
        $exchangeItemsRaw = $request->input('exchange_items', []);
        $settlementMethod = $request->input('settlement_method', 'cash');
        $reason           = trim($request->input('reason', 'Exchange')) ?: 'Exchange';

        // Build return items
        $returnItems = [];
        foreach ($returnItemsRaw as $itemId => $data) {
            $qty = (float)($data['qty'] ?? 0);
            if ($qty > 0) {
                $returnItems[] = [
                    'invoice_item_id' => (int)$itemId,
                    'quantity'        => $qty,
                    'is_restocked'    => true,
                ];
            }
        }

        if (empty($returnItems)) {
            flash('error', 'Please select at least one item to return.');
            Response::redirect("/app/returns/exchange?invoice={$request->input('_invoice_num', '')}");
            return;
        }

        try {
            Database::transaction(function(\PDO $pdo) use (
                $tenant, $taxProfile, $invoiceId, $returnItems,
                $exchangeItemsRaw, $settlementMethod, $reason, $tenantId
            ) {
                // Process return
                $returnResult = ReturnTransactionService::processReturn(
                    $tenant, $taxProfile, $invoiceId, $returnItems, $settlementMethod, $reason
                );

                // Deduct exchange items stock
                foreach ($exchangeItemsRaw as $productId => $data) {
                    $qty = (float)($data['qty'] ?? 0);
                    if ($qty <= 0) continue;

                    $product = $pdo->prepare("SELECT current_stock FROM products WHERE id = ? AND tenant_id = ? FOR UPDATE");
                    $product->execute([(int)$productId, $tenantId]);
                    $prod = $product->fetch(\PDO::FETCH_ASSOC);
                    if (!$prod) continue;

                    $pdo->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$qty, (int)$productId, $tenantId]);

                    $pdo->prepare("INSERT INTO stock_movements (tenant_id, product_id, movement_type, quantity, reference_type, notes, created_at)
                        VALUES (?, ?, 'SALE', ?, 'exchange', ?, NOW())")
                        ->execute([$tenantId, (int)$productId, -$qty, "Exchange from return #{$returnResult['return_number']}"]);
                }
            });

            flash('success', '✅ Exchange processed successfully! Stock restocked for returned items and deducted for new items.');
            Response::redirect('/app/returns');
        } catch (\PDOException $e) {
            Logger::error('exchange_failed', ['tenant_id' => (int)$tenant['id'], 'error' => $e->getMessage()], 'returns');
            flash('error', 'Exchange could not be processed. Please try again.');
            Response::redirect('/app/returns/exchange');
        } catch (Exception $e) {
            flash('error', 'Exchange failed: ' . $e->getMessage());
            Response::redirect('/app/returns/exchange');
        }
    }
}

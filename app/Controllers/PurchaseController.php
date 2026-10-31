<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Core\Logger;
use App\Services\AuditService;
use Exception;

class PurchaseController {
    public function index(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];

        $bills = Database::fetchAll("
            SELECT pb.*, s.name AS supplier_name, s.phone AS supplier_phone,
                   (SELECT COUNT(*) FROM purchase_bill_items pbi WHERE pbi.purchase_bill_id = pb.id) AS item_count
            FROM purchase_bills pb
            LEFT JOIN suppliers s ON s.id = pb.supplier_id
            WHERE pb.tenant_id = :tid
            ORDER BY pb.bill_date DESC, pb.id DESC
            LIMIT 200
        ", ['tid' => $tenantId]);

        $stats = Database::fetchOne("
            SELECT COUNT(*) AS total_bills,
                   COALESCE(SUM(total_amount), 0) AS total_purchases,
                   COALESCE(SUM(total_amount - paid_amount), 0) AS total_payable
            FROM purchase_bills
            WHERE tenant_id = :tid AND status != 'cancelled'
        ", ['tid' => $tenantId]);

        View::render('purchases/index', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'bills' => $bills,
            'stats' => $stats,
        ]);
    }

    public function create(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];

        $suppliers = Database::fetchAll("SELECT id, name, gstin, phone FROM suppliers WHERE tenant_id = :tid ORDER BY name ASC", ['tid' => $tenantId]);
        $products = Database::fetchAll("
            SELECT p.id, p.name_en, p.sku, p.cost_price, p.current_stock,
                   COALESCE(tc.rate_percent, 0) AS tax_rate
            FROM products p
            LEFT JOIN tax_categories tc ON tc.id = p.tax_category_id
            WHERE p.tenant_id = :tid AND p.is_active = 1
            ORDER BY p.name_en ASC
        ", ['tid' => $tenantId]);

        View::render('purchases/create', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    public function store(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $user = $request->user();

        $supplierId = (int)$request->input('supplier_id') ?: null;
        $supplierRef = trim((string)$request->input('supplier_bill_ref', '')) ?: null;
        $billDate = (string)$request->input('bill_date', date('Y-m-d'));
        $notes = trim((string)$request->input('notes', '')) ?: null;
        $paidAmount = max(0, round((float)$request->input('paid_amount', 0), 2));
        $paymentMethod = (string)$request->input('payment_method', 'cash');
        $rawItems = $request->input('items', []);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $billDate)) {
            $billDate = date('Y-m-d');
        }
        if (!in_array($paymentMethod, ['cash', 'upi', 'card', 'bank'], true)) {
            $paymentMethod = 'cash';
        }

        if (!is_array($rawItems) || empty($rawItems)) {
            flash('error', 'Add at least one purchase item.');
            Response::redirect('/app/purchases/create');
            return;
        }

        try {
            $result = Database::transaction(function (\PDO $pdo) use (
                $tenantId, $user, $supplierId, $supplierRef, $billDate, $notes,
                $paidAmount, $paymentMethod, $rawItems
            ) {
                if ($supplierId) {
                    $check = $pdo->prepare("SELECT id, name FROM suppliers WHERE id = ? AND tenant_id = ?");
                    $check->execute([$supplierId, $tenantId]);
                    $supplier = $check->fetch();
                    if (!$supplier) {
                        throw new Exception('Selected supplier was not found.');
                    }
                }

                $normalized = [];
                $totalTaxable = 0.0;
                $totalTax = 0.0;
                foreach ($rawItems as $item) {
                    $productId = (int)($item['product_id'] ?? 0) ?: null;
                    $quantity = (float)($item['quantity'] ?? 0);
                    $unitCost = round((float)($item['unit_cost'] ?? 0), 2);
                    $taxRate = max(0, min(100, (float)($item['tax_rate'] ?? 0)));

                    if ($quantity <= 0 || $unitCost < 0) {
                        continue;
                    }

                    $product = null;
                    if ($productId) {
                        $pstmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND tenant_id = ? FOR UPDATE");
                        $pstmt->execute([$productId, $tenantId]);
                        $product = $pstmt->fetch();
                        if (!$product) {
                            throw new Exception('A selected product was not found.');
                        }
                    }

                    $itemName = trim((string)($item['item_name'] ?? ''))
                        ?: ($product['name_en'] ?? 'Purchase item');
                    $taxable = round($quantity * $unitCost, 2);
                    $tax = round($taxable * $taxRate / 100, 2);
                    $lineTotal = round($taxable + $tax, 2);

                    $totalTaxable += $taxable;
                    $totalTax += $tax;

                    $normalized[] = [
                        'product_id' => $productId,
                        'product' => $product,
                        'item_name' => substr($itemName, 0, 150),
                        'quantity' => $quantity,
                        'unit_cost' => $unitCost,
                        'tax_rate' => $taxRate,
                        'taxable' => $taxable,
                        'tax' => $tax,
                        'line_total' => $lineTotal,
                    ];
                }

                if (empty($normalized)) {
                    throw new Exception('No valid purchase items were supplied.');
                }

                $totalAmount = round($totalTaxable + $totalTax, 2);
                $paidAmount = min($paidAmount, $totalAmount);

                $year = date('Y', strtotime($billDate));
                $seqStmt = $pdo->prepare("
                    SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(bill_number, '-', -1) AS UNSIGNED)), 0) + 1
                    FROM purchase_bills
                    WHERE tenant_id = ? AND bill_number LIKE ?
                    FOR UPDATE
                ");
                $seqStmt->execute([$tenantId, "PUR-{$year}-%"]);
                $seq = (int)$seqStmt->fetchColumn();
                $billNumber = sprintf('PUR-%s-%04d', $year, $seq);

                $status = $paidAmount >= $totalAmount - 0.001 ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid');

                $billStmt = $pdo->prepare("
                    INSERT INTO purchase_bills (
                        tenant_id, bill_number, supplier_id, supplier_bill_ref, bill_date,
                        subtotal, tax_amount, total_amount, paid_amount, status, notes, created_by, created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $billStmt->execute([
                    $tenantId, $billNumber, $supplierId, $supplierRef, $billDate,
                    round($totalTaxable, 2), round($totalTax, 2), $totalAmount, $paidAmount,
                    $status, $notes, (int)$user['id'],
                ]);
                $billId = (int)$pdo->lastInsertId();

                $itemStmt = $pdo->prepare("
                    INSERT INTO purchase_bill_items (
                        tenant_id, purchase_bill_id, product_id, item_name, quantity, unit_cost,
                        tax_rate, taxable_value, tax_amount, line_total
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stockStmt = $pdo->prepare("UPDATE products SET current_stock = current_stock + ?, cost_price = ? WHERE id = ? AND tenant_id = ?");
                $movementStmt = $pdo->prepare("
                    INSERT INTO stock_movements (tenant_id, product_id, movement_type, quantity, reference_id, reference_type, notes, created_at)
                    VALUES (?, ?, 'RECEIPT', ?, ?, 'purchase_bill', ?, NOW())
                ");

                foreach ($normalized as $line) {
                    $itemStmt->execute([
                        $tenantId, $billId, $line['product_id'], $line['item_name'], $line['quantity'],
                        $line['unit_cost'], $line['tax_rate'], $line['taxable'], $line['tax'], $line['line_total'],
                    ]);

                    if ($line['product_id']) {
                        $stockStmt->execute([$line['quantity'], $line['unit_cost'], $line['product_id'], $tenantId]);
                        $movementStmt->execute([
                            $tenantId,
                            $line['product_id'],
                            $line['quantity'],
                            $billId,
                            'Purchase ' . $billNumber,
                        ]);
                    }
                }

                if ($paidAmount > 0) {
                    $pdo->prepare("
                        INSERT INTO supplier_payments (tenant_id, purchase_bill_id, supplier_id, amount, payment_method, reference, paid_at, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                    ")->execute([
                        $tenantId, $billId, $supplierId, $paidAmount, $paymentMethod,
                        'Initial payment', (int)$user['id'],
                    ]);
                }

                self::postPurchaseJournal($pdo, $tenantId, $billId, $billNumber, $totalAmount, $paidAmount, $paymentMethod);

                return ['bill_id' => $billId, 'bill_number' => $billNumber, 'total' => $totalAmount, 'status' => $status];
            });

            AuditService::log('purchase.created', 'purchase_bill', (int)$result['bill_id'], [
                'bill_number' => $result['bill_number'],
                'total_amount' => $result['total'],
                'status' => $result['status'],
            ], $tenantId, (int)$user['id']);

            flash('success', "Purchase bill {$result['bill_number']} recorded. Stock updated.");
        } catch (\PDOException $e) {
            Logger::error('purchase_store_failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()], 'purchases');
            flash('error', 'Could not save purchase. Please check the details and try again.');
            Response::redirect('/app/purchases/create');
            return;
        } catch (Exception $e) {
            flash('error', 'Could not save purchase: ' . $e->getMessage());
            Response::redirect('/app/purchases/create');
            return;
        }

        Response::redirect('/app/purchases');
    }

    public function pay(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $user = $request->user();
        $billId = (int)$request->param('id');

        $amount = round((float)$request->input('amount', 0), 2);
        $method = (string)$request->input('payment_method', 'cash');
        $reference = trim((string)$request->input('reference', '')) ?: null;

        if (!in_array($method, ['cash', 'upi', 'card', 'bank'], true)) {
            $method = 'cash';
        }
        if ($amount <= 0) {
            flash('error', 'Payment amount must be greater than zero.');
            Response::redirect('/app/purchases');
            return;
        }

        try {
            $result = Database::transaction(function (\PDO $pdo) use ($tenantId, $billId, $amount, $method, $reference, $user) {
                $stmt = $pdo->prepare("SELECT * FROM purchase_bills WHERE id = ? AND tenant_id = ? FOR UPDATE");
                $stmt->execute([$billId, $tenantId]);
                $bill = $stmt->fetch();
                if (!$bill) {
                    throw new Exception('Purchase bill not found.');
                }
                if ($bill['status'] === 'cancelled') {
                    throw new Exception('This purchase bill was cancelled.');
                }

                $outstanding = round((float)$bill['total_amount'] - (float)$bill['paid_amount'], 2);
                if ($amount > $outstanding + 0.001) {
                    throw new Exception('Payment exceeds the outstanding balance of ₹ ' . number_format($outstanding, 2));
                }

                $pdo->prepare("
                    INSERT INTO supplier_payments (tenant_id, purchase_bill_id, supplier_id, amount, payment_method, reference, paid_at, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
                ")->execute([
                    $tenantId, $billId, $bill['supplier_id'], $amount, $method, $reference, (int)$user['id'],
                ]);

                $newPaid = round((float)$bill['paid_amount'] + $amount, 2);
                $status = $newPaid >= (float)$bill['total_amount'] - 0.001 ? 'paid' : 'partial';
                $pdo->prepare("UPDATE purchase_bills SET paid_amount = ?, status = ? WHERE id = ? AND tenant_id = ?")
                    ->execute([$newPaid, $status, $billId, $tenantId]);

                self::postSupplierPaymentJournal($pdo, $tenantId, $billId, (string)$bill['bill_number'], $amount, $method);

                return ['bill_number' => $bill['bill_number'], 'amount' => $amount, 'status' => $status];
            });

            AuditService::log('purchase.payment_recorded', 'purchase_bill', $billId, [
                'bill_number' => $result['bill_number'],
                'amount' => $result['amount'],
                'status' => $result['status'],
            ], $tenantId, (int)$user['id']);

            flash('success', 'Supplier payment recorded for ' . $result['bill_number'] . '.');
        } catch (\PDOException $e) {
            Logger::error('purchase_payment_failed', ['tenant_id' => $tenantId, 'bill_id' => $billId, 'error' => $e->getMessage()], 'purchases');
            flash('error', 'Payment could not be recorded. Please try again.');
        } catch (Exception $e) {
            flash('error', 'Payment failed: ' . $e->getMessage());
        }

        Response::redirect('/app/purchases');
    }

    protected static function postPurchaseJournal(\PDO $pdo, int $tenantId, int $billId, string $billNumber, float $total, float $paid, string $method): void {
        $accounts = self::accounts($pdo, $tenantId);
        $inventory = $accounts['1050'] ?? null;
        $ap = $accounts['2050'] ?? null;
        $cash = $accounts['1010'] ?? null;
        $bank = $accounts['1020'] ?? null;
        $card = $accounts['1030'] ?? null;

        if (!$inventory) {
            return;
        }

        $creditAccount = match ($method) {
            'upi', 'bank' => $bank ?: $cash,
            'card' => $card ?: $bank ?: $cash,
            default => $cash ?: $bank,
        };

        $items = [
            ['account_id' => $inventory, 'debit' => round($total, 2), 'credit' => 0.0, 'narration' => "Purchase {$billNumber}"],
        ];
        if ($paid > 0) {
            if (!$creditAccount) {
                throw new Exception('Payment account missing from chart of accounts.');
            }
            $items[] = ['account_id' => $creditAccount, 'debit' => 0.0, 'credit' => round($paid, 2), 'narration' => "Purchase payment {$billNumber}"];
        }
        $unpaid = round($total - $paid, 2);
        if ($unpaid > 0) {
            if (!$ap) {
                throw new Exception('Accounts Payable account missing from chart of accounts.');
            }
            $items[] = ['account_id' => $ap, 'debit' => 0.0, 'credit' => $unpaid, 'narration' => "Supplier payable {$billNumber}"];
        }

        self::insertJournal($pdo, $tenantId, $billId, 'purchase_bill', 'JRN-PUR', "Purchase bill {$billNumber}", $total, $items);
    }

    protected static function postSupplierPaymentJournal(\PDO $pdo, int $tenantId, int $billId, string $billNumber, float $amount, string $method): void {
        $accounts = self::accounts($pdo, $tenantId);
        $ap = $accounts['2050'] ?? null;
        $cash = $accounts['1010'] ?? null;
        $bank = $accounts['1020'] ?? null;
        $card = $accounts['1030'] ?? null;

        if (!$ap) {
            return;
        }

        $creditAccount = match ($method) {
            'upi', 'bank' => $bank ?: $cash,
            'card' => $card ?: $bank ?: $cash,
            default => $cash ?: $bank,
        };
        if (!$creditAccount) {
            throw new Exception('Payment account missing from chart of accounts.');
        }

        $items = [
            ['account_id' => $ap, 'debit' => round($amount, 2), 'credit' => 0.0, 'narration' => "Supplier payment {$billNumber}"],
            ['account_id' => $creditAccount, 'debit' => 0.0, 'credit' => round($amount, 2), 'narration' => "Supplier payment {$billNumber}"],
        ];

        self::insertJournal($pdo, $tenantId, $billId, 'supplier_payment', 'JRN-PAY', "Supplier payment {$billNumber}", $amount, $items);
    }

    protected static function insertJournal(\PDO $pdo, int $tenantId, int $referenceId, string $referenceType, string $prefix, string $description, float $total, array $items): void {
        $debit = round(array_sum(array_column($items, 'debit')), 2);
        $credit = round(array_sum(array_column($items, 'credit')), 2);
        if (abs($debit - $credit) > 0.01) {
            throw new Exception("Unbalanced purchase journal. Debits: {$debit}, Credits: {$credit}.");
        }

        $entryNumber = $prefix . '-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $pdo->prepare("
            INSERT INTO journal_entries (tenant_id, entry_number, date, reference_id, reference_type, description, total_amount, created_at)
            VALUES (?, ?, CURDATE(), ?, ?, ?, ?, NOW())
        ")->execute([$tenantId, $entryNumber, $referenceId, $referenceType, $description, $total]);
        $journalId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("
            INSERT INTO journal_items (tenant_id, journal_entry_id, account_id, debit, credit, narration)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($items as $item) {
            $stmt->execute([
                $tenantId, $journalId, $item['account_id'], $item['debit'], $item['credit'], $item['narration'],
            ]);
        }
    }

    protected static function accounts(\PDO $pdo, int $tenantId): array {
        $stmt = $pdo->prepare("SELECT id, code FROM accounts WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        $accounts = [];
        foreach ($stmt->fetchAll() as $row) {
            $accounts[(string)$row['code']] = (int)$row['id'];
        }
        return $accounts;
    }
}

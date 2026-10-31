<?php
namespace App\Services;

use App\Core\Database;
use Exception;

class SalesTransactionService {
    public static function createSale(array $tenant, ?array $taxProfile, array $cartData, int $userId): array {
        return Database::transaction(function (\PDO $pdo) use ($tenant, $taxProfile, $cartData, $userId) {
            $tenantId = (int)$tenant['id'];
            $items = $cartData['items'] ?? [];
            if (empty($items)) {
                throw new Exception('Cannot process sale: cart is empty.');
            }

            $placeOfSupply = $cartData['place_of_supply'] ?? ($taxProfile['registered_state_code'] ?? '21');
            $customerId = !empty($cartData['customer_id']) ? (int)$cartData['customer_id'] : null;
            $paymentMethod = (string)($cartData['payment_method'] ?? 'cash');

            $productIds = array_values(array_unique(array_map(static fn($item) => (int)($item['product_id'] ?? 0), $items)));
            $productIds = array_values(array_filter($productIds, static fn($id) => $id > 0));
            if (empty($productIds)) {
                throw new Exception('Cannot process sale: no valid products in cart.');
            }

            $inPlaceholders = implode(',', array_fill(0, count($productIds), '?'));
            $stmt = $pdo->prepare("
                SELECT p.*, tc.category_code as tax_classification, tc.rate_percent as tax_rate_percent, u.name_en as unit_name
                FROM products p
                LEFT JOIN tax_categories tc ON p.tax_category_id = tc.id
                LEFT JOIN units_of_measure u ON p.unit_id = u.id
                WHERE p.id IN ($inPlaceholders) AND p.tenant_id = ? AND p.is_active = 1
                FOR UPDATE
            ");
            $stmt->execute(array_merge($productIds, [$tenantId]));
            $dbProducts = [];
            foreach ($stmt->fetchAll() as $row) {
                $dbProducts[(int)$row['id']] = $row;
            }

            $sanitizedItems = [];
            foreach ($items as $item) {
                $pId = (int)($item['product_id'] ?? 0);
                if (!isset($dbProducts[$pId])) {
                    throw new Exception("Product ID {$pId} not found or unauthorized for this tenant.");
                }

                $dbP = $dbProducts[$pId];
                $qty = (float)($item['quantity'] ?? 0);
                if ($qty <= 0) {
                    throw new Exception("Invalid quantity for '{$dbP['name_en']}'.");
                }

                $currentStock = (float)$dbP['current_stock'];
                if ($currentStock < $qty && empty($tenant['allow_negative_stock'])) {
                    throw new Exception("Insufficient stock for '{$dbP['name_en']}'. Available: {$currentStock}, Requested: {$qty}.");
                }

                $sanitizedItems[] = [
                    'product_id' => $pId,
                    'name_en' => $dbP['name_en'],
                    'name_or' => $dbP['name_or'],
                    'unit' => $dbP['unit_name'] ?? 'pcs',
                    'quantity' => $qty,
                    'unit_price' => (float)$dbP['sale_price'],
                    'discount' => (float)($item['discount'] ?? 0),
                    'tax_classification' => $dbP['tax_classification'] ?? 'taxable',
                    'tax_rate_percent' => (float)($dbP['tax_rate_percent'] ?? 0),
                    'price_includes_tax' => !empty($dbP['price_includes_tax']),
                ];
            }

            $taxCalc = TaxEngine::calculate($taxProfile ?: [], $sanitizedItems, $placeOfSupply);
            $total = round((float)$taxCalc['total_amount'], 2);

            [$payments, $creditAmount, $paidAmount, $paymentStatus] = self::normalizePayments(
                $cartData['payments'] ?? null,
                $paymentMethod,
                $total,
                $customerId
            );

            $customer = null;
            if ($creditAmount > 0 && $customerId) {
                $custStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? AND tenant_id = ? FOR UPDATE");
                $custStmt->execute([$customerId, $tenantId]);
                $customer = $custStmt->fetch();
                if (!$customer) {
                    throw new Exception('Selected customer was not found.');
                }

                $limit = (float)$customer['credit_limit'];
                $newBalance = (float)$customer['balance'] + $creditAmount;
                if ($limit > 0 && $newBalance > $limit + 0.001) {
                    throw new Exception('Credit limit exceeded for this customer. Available credit: ' . number_format(max(0, $limit - (float)$customer['balance']), 2));
                }
            }

            if (!empty($cartData['customer_id'])) {
                $customerCheck = $pdo->prepare("SELECT id FROM customers WHERE id = ? AND tenant_id = ?");
                $customerCheck->execute([$customerId, $tenantId]);
                if (!$customerCheck->fetchColumn()) {
                    throw new Exception('Selected customer was not found.');
                }
            }

            $prefix = strtoupper((string)($tenant['invoice_prefix'] ?? 'INV'));
            $prefix = preg_replace('/[^A-Z0-9-]/', '', $prefix) ?: 'INV';
            $prefix = substr($prefix, 0, 10);
            $year = date('Y');

            $seqStmt = $pdo->prepare("
                SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(invoice_number, '-', -1) AS UNSIGNED)), 0) + 1 as next_seq
                FROM invoices
                WHERE tenant_id = ? AND invoice_number LIKE ?
                FOR UPDATE
            ");
            $seqStmt->execute([$tenantId, $prefix . '-' . $year . '-%']);
            $nextSeq = (int)$seqStmt->fetchColumn();
            $invoiceNumber = sprintf('%s-%s-%04d', $prefix, $year, $nextSeq);

            $walkinName = !empty($cartData['walkin_name']) ? trim((string)$cartData['walkin_name']) : null;
            $walkinPhone = !empty($cartData['walkin_phone']) ? trim((string)$cartData['walkin_phone']) : null;

            $invStmt = $pdo->prepare("
                INSERT INTO invoices (
                    tenant_id, invoice_number, document_type, customer_id, walkin_name, walkin_phone, user_id,
                    place_of_supply_state_code, supplier_state_code, is_interstate,
                    subtotal, discount_amount, taxable_amount, cgst_amount, sgst_amount,
                    igst_amount, total_amount, payment_status, created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, NOW()
                )
            ");
            $invStmt->execute([
                $tenantId,
                $invoiceNumber,
                $taxCalc['document_type'],
                $customerId,
                $walkinName,
                $walkinPhone,
                $userId,
                $taxCalc['place_of_supply_state_code'],
                $taxCalc['supplier_state_code'],
                $taxCalc['is_interstate'] ? 1 : 0,
                $taxCalc['subtotal'],
                $taxCalc['discount_amount'],
                $taxCalc['taxable_amount'],
                $taxCalc['cgst_amount'],
                $taxCalc['sgst_amount'],
                $taxCalc['igst_amount'],
                $taxCalc['total_amount'],
                $paymentStatus,
            ]);
            $invoiceId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare("
                INSERT INTO invoice_items (
                    tenant_id, invoice_id, product_id, item_name_en, item_name_or,
                    unit_name, quantity, unit_price, discount, tax_classification,
                    tax_rate_percent, taxable_value, cgst_rate, cgst_amount,
                    sgst_rate, sgst_amount, igst_rate, igst_amount, line_total
                ) VALUES (
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?, ?, ?
                )
            ");
            $stockStmt = $pdo->prepare("UPDATE products SET current_stock = current_stock - ? WHERE id = ? AND tenant_id = ?");
            $movementStmt = $pdo->prepare("
                INSERT INTO stock_movements (tenant_id, product_id, movement_type, quantity, reference_id, reference_type, notes, created_at)
                VALUES (?, ?, 'SALE', ?, ?, 'invoice', ?, NOW())
            ");

            foreach ($taxCalc['items'] as $line) {
                $itemStmt->execute([
                    $tenantId,
                    $invoiceId,
                    $line['product_id'],
                    $line['name_en'],
                    $line['name_or'],
                    $line['unit'],
                    $line['quantity'],
                    $line['unit_price'],
                    $line['discount'],
                    $line['tax_classification'],
                    $line['tax_rate_percent'],
                    $line['taxable_value'],
                    $line['cgst_rate'],
                    $line['cgst_amount'],
                    $line['sgst_rate'],
                    $line['sgst_amount'],
                    $line['igst_rate'],
                    $line['igst_amount'],
                    $line['line_total'],
                ]);

                $stockStmt->execute([$line['quantity'], $line['product_id'], $tenantId]);
                $movementStmt->execute([
                    $tenantId,
                    $line['product_id'],
                    -$line['quantity'],
                    $invoiceId,
                    "Sale #{$invoiceNumber}",
                ]);
            }

            $payStmt = $pdo->prepare("
                INSERT INTO payments (tenant_id, invoice_id, payment_method, amount, transaction_ref, status, created_at)
                VALUES (?, ?, ?, ?, ?, 'completed', NOW())
            ");
            foreach ($payments as $payment) {
                $payStmt->execute([
                    $tenantId,
                    $invoiceId,
                    $payment['method'],
                    $payment['amount'],
                    $payment['reference'] ?? null,
                ]);
            }

            if ($creditAmount > 0 && $customerId) {
                $pdo->prepare("UPDATE customers SET balance = balance + ? WHERE id = ? AND tenant_id = ?")
                    ->execute([$creditAmount, $customerId, $tenantId]);
            }

            $accountingPayments = $payments;
            if ($creditAmount > 0) {
                $accountingPayments[] = ['method' => 'credit', 'amount' => $creditAmount];
            }
            AccountingPostingService::postSaleJournal($pdo, $tenantId, $invoiceId, $invoiceNumber, $accountingPayments, $taxCalc);

            return [
                'success' => true,
                'invoice_id' => $invoiceId,
                'invoice_number' => $invoiceNumber,
                'total_amount' => $total,
                'paid_amount' => round($paidAmount, 2),
                'credit_amount' => round($creditAmount, 2),
                'payment_status' => $paymentStatus,
                'document_type' => $taxCalc['document_type'],
            ];
        });
    }

    protected static function normalizePayments($paymentsInput, string $fallbackMethod, float $total, ?int $customerId): array {
        $allowed = ['cash', 'upi', 'card', 'credit'];
        $payments = [];
        $creditAmount = 0.0;

        if (is_array($paymentsInput) && !empty($paymentsInput)) {
            foreach ($paymentsInput as $payment) {
                if (!is_array($payment)) {
                    continue;
                }
                $method = strtolower((string)($payment['method'] ?? 'cash'));
                $amount = round((float)($payment['amount'] ?? 0), 2);
                if (!in_array($method, $allowed, true) || $amount <= 0) {
                    continue;
                }
                $reference = trim((string)($payment['reference'] ?? $payment['transaction_ref'] ?? ''));
                if ($method === 'credit') {
                    $creditAmount += $amount;
                } else {
                    $payments[] = ['method' => $method, 'amount' => $amount, 'reference' => $reference ?: null];
                }
            }
        } else {
            $method = in_array($fallbackMethod, $allowed, true) ? $fallbackMethod : 'cash';
            if ($method === 'credit') {
                $creditAmount = $total;
            } else {
                $payments[] = ['method' => $method, 'amount' => $total, 'reference' => null];
            }
        }

        $paidAmount = round(array_sum(array_column($payments, 'amount')), 2);
        $settled = round($paidAmount + $creditAmount, 2);

        if (abs($settled - $total) > 0.01) {
            throw new Exception('Payment total must match the invoice total.');
        }

        if ($creditAmount > 0 && empty($customerId)) {
            throw new Exception('Credit sales require a registered customer.');
        }

        if ($creditAmount <= 0.001) {
            $paymentStatus = 'paid';
        } elseif ($paidAmount <= 0.001) {
            $paymentStatus = 'pending';
        } else {
            $paymentStatus = 'partial';
        }

        return [$payments, round($creditAmount, 2), $paidAmount, $paymentStatus];
    }
}

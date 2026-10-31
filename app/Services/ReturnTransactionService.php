<?php
namespace App\Services;

use App\Core\Database;
use Exception;
use PDO;

class ReturnTransactionService {
    public static function processReturn(array $tenant, ?array $taxProfile, int $invoiceId, array $returnItems, string $refundMethod, string $reason): array {
        return Database::transaction(function(PDO $pdo) use ($tenant, $taxProfile, $invoiceId, $returnItems, $refundMethod, $reason) {
            $tenantId = (int)$tenant['id'];

            // 1. Fetch original invoice
            $invStmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND tenant_id = ? FOR UPDATE");
            $invStmt->execute([$invoiceId, $tenantId]);
            $invoice = $invStmt->fetch();

            if (!$invoice) {
                throw new Exception("Original invoice #{$invoiceId} not found or access denied.");
            }

            // Fetch invoice items
            $itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? AND tenant_id = ?");
            $itemStmt->execute([$invoiceId, $tenantId]);
            $dbItems = [];
            foreach ($itemStmt->fetchAll() as $row) {
                $dbItems[$row['id']] = $row;
            }

            // 2. Validate return items
            $totalRefund = 0.0;
            $totalTaxRefund = 0.0;
            $totalNetRevenueDebit = 0.0;
            $sanitizedLines = [];

            foreach ($returnItems as $ret) {
                $invItemId = (int)$ret['invoice_item_id'];
                $retQty = (float)$ret['quantity'];

                if ($retQty <= 0) continue;

                if (!isset($dbItems[$invItemId])) {
                    throw new Exception("Item ID {$invItemId} does not belong to invoice #{$invoice['invoice_number']}.");
                }

                $origLine = $dbItems[$invItemId];
                if ($retQty > (float)$origLine['quantity']) {
                    throw new Exception("Return quantity ({$retQty}) exceeds originally sold quantity ({$origLine['quantity']}) for {$origLine['item_name_en']}.");
                }

                $unitPrice = (float)$origLine['unit_price'];
                $lineRefund = round($retQty * $unitPrice, 2);
                $isRestocked = !empty($ret['is_restocked']);

                // Tax component reversal calculation
                $ratePercent = (float)$origLine['tax_rate_percent'];
                $taxPortion = 0.0;
                $netPortion = $lineRefund;

                if (($taxProfile['registration_mode'] ?? '') === 'regular' && $ratePercent > 0) {
                    $taxablePart = round($lineRefund / (1 + ($ratePercent / 100)), 2);
                    $taxPortion = round($lineRefund - $taxablePart, 2);
                    $netPortion = $taxablePart;
                }

                $sanitizedLines[] = [
                    'invoice_item_id' => $invItemId,
                    'product_id' => $origLine['product_id'],
                    'item_name' => $origLine['item_name_en'],
                    'quantity' => $retQty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineRefund,
                    'tax_refund' => $taxPortion,
                    'net_portion' => $netPortion,
                    'is_restocked' => $isRestocked,
                ];

                $totalRefund += $lineRefund;
                $totalTaxRefund += $taxPortion;
                $totalNetRevenueDebit += $netPortion;
            }

            if (empty($sanitizedLines)) {
                throw new Exception("No valid items selected for return.");
            }

            // 3. Generate Return / Credit Note Number
            $year = date('Y');
            $seqStmt = $pdo->prepare("
                SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(return_number, '-', -1) AS UNSIGNED)), 0) + 1 as next_seq
                FROM sales_returns
                WHERE tenant_id = ? AND return_number LIKE ?
                FOR UPDATE
            ");
            $seqStmt->execute([$tenantId, "RET-{$year}-%"]);
            $nextSeq = (int)$seqStmt->fetchColumn();
            $returnNumber = sprintf("RET-%s-%04d", $year, $nextSeq);

            // 4. Insert Sales Return Header
            $retHeaderStmt = $pdo->prepare("
                INSERT INTO sales_returns (
                    tenant_id, return_number, invoice_id, customer_id, total_refund_amount,
                    refund_method, reason, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $retHeaderStmt->execute([
                $tenantId,
                $returnNumber,
                $invoiceId,
                $invoice['customer_id'],
                $totalRefund,
                $refundMethod,
                $reason,
            ]);
            $returnId = (int)$pdo->lastInsertId();

            // 5. Insert Return Items & Restock inventory
            $retItemStmt = $pdo->prepare("
                INSERT INTO sales_return_items (
                    tenant_id, sales_return_id, invoice_item_id, product_id,
                    quantity, refund_price, line_total, is_restocked
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stockStmt = $pdo->prepare("
                UPDATE products SET current_stock = current_stock + ? WHERE id = ? AND tenant_id = ?
            ");

            $movementStmt = $pdo->prepare("
                INSERT INTO stock_movements (
                    tenant_id, product_id, movement_type, quantity, reference_id, reference_type, notes, created_at
                ) VALUES (?, ?, 'RETURN', ?, ?, 'sales_return', ?, NOW())
            ");

            foreach ($sanitizedLines as $line) {
                $retItemStmt->execute([
                    $tenantId,
                    $returnId,
                    $line['invoice_item_id'],
                    $line['product_id'],
                    $line['quantity'],
                    $line['unit_price'],
                    $line['line_total'],
                    $line['is_restocked'] ? 1 : 0,
                ]);

                if ($line['is_restocked'] && !empty($line['product_id'])) {
                    // Restock inventory
                    $stockStmt->execute([$line['quantity'], $line['product_id'], $tenantId]);

                    $movementStmt->execute([
                        $tenantId,
                        $line['product_id'],
                        $line['quantity'],
                        $returnId,
                        "Return #{$returnNumber} for Invoice #{$invoice['invoice_number']}"
                    ]);
                }
            }

            // 6. Post Compensating Accounting Journals
            $accounts = [];
            $accStmt = $pdo->prepare("SELECT id, code FROM accounts WHERE tenant_id = ?");
            $accStmt->execute([$tenantId]);
            foreach ($accStmt->fetchAll() as $acc) {
                $accounts[$acc['code']] = (int)$acc['id'];
            }

            $returnsAccId = $accounts['4020'] ?? ($accounts['4010'] ?? null); // Sales Returns
            $cashAccId    = $accounts['1010'] ?? null;
            $bankAccId    = $accounts['1020'] ?? null;
            $arAccId      = $accounts['1040'] ?? null;
            $cgstAccId    = $accounts['2010'] ?? null;
            $sgstAccId    = $accounts['2020'] ?? null;
            $igstAccId    = $accounts['2030'] ?? null;

            if ($returnsAccId && $cashAccId) {
                $refundAccId = match($refundMethod) {
                    'upi', 'bank' => $bankAccId ?: $cashAccId,
                    'credit_note' => $arAccId ?: $cashAccId,
                    default => $cashAccId,
                };

                $journalItems = [];

                // DR: Sales Returns
                $journalItems[] = [
                    'account_id' => $returnsAccId,
                    'debit' => $totalNetRevenueDebit,
                    'credit' => 0.0,
                    'narration' => "Sales Return #{$returnNumber} for Invoice #{$invoice['invoice_number']}",
                ];

                // DR: Output Tax Reversal (if applicable)
                if ($totalTaxRefund > 0 && ($taxProfile['registration_mode'] ?? '') === 'regular') {
                    $splitTax = round($totalTaxRefund / 2, 2);
                    if ($cgstAccId) {
                        $journalItems[] = [
                            'account_id' => $cgstAccId,
                            'debit' => $splitTax,
                            'credit' => 0.0,
                            'narration' => "CGST Reversal for Return #{$returnNumber}",
                        ];
                    }
                    if ($sgstAccId) {
                        $journalItems[] = [
                            'account_id' => $sgstAccId,
                            'debit' => round($totalTaxRefund - $splitTax, 2),
                            'credit' => 0.0,
                            'narration' => "SGST Reversal for Return #{$returnNumber}",
                        ];
                    }
                }

                // CR: Refund Asset (Cash / Bank / Khata Credit Note)
                $journalItems[] = [
                    'account_id' => $refundAccId,
                    'debit' => 0.0,
                    'credit' => $totalRefund,
                    'narration' => "Refund payout for Return #{$returnNumber}",
                ];

                // Validate double-entry balance
                $sumDr = round(array_sum(array_column($journalItems, 'debit')), 2);
                $sumCr = round(array_sum(array_column($journalItems, 'credit')), 2);

                if (abs($sumDr - $sumCr) > 0.02) {
                    throw new Exception("Unbalanced return journal. Debits: {$sumDr}, Credits: {$sumCr}.");
                }

                // Insert Journal Header
                $jEntryStmt = $pdo->prepare("
                    INSERT INTO journal_entries (tenant_id, entry_number, date, reference_id, reference_type, description, total_amount, created_at)
                    VALUES (?, ?, CURDATE(), ?, 'sales_return', ?, ?, NOW())
                ");
                $jNum = "JRN-RET-" . date('Ymd') . "-" . rand(1000, 9999);
                $jEntryStmt->execute([
                    $tenantId,
                    $jNum,
                    $returnId,
                    "Journal for Return #{$returnNumber}",
                    $totalRefund,
                ]);
                $journalId = (int)$pdo->lastInsertId();

                $jiStmt = $pdo->prepare("
                    INSERT INTO journal_items (tenant_id, journal_entry_id, account_id, debit, credit, narration)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                foreach ($journalItems as $ji) {
                    $jiStmt->execute([
                        $tenantId,
                        $journalId,
                        $ji['account_id'],
                        $ji['debit'],
                        $ji['credit'],
                        $ji['narration'],
                    ]);
                }
            }

            return [
                'success' => true,
                'return_id' => $returnId,
                'return_number' => $returnNumber,
                'total_refund' => $totalRefund,
            ];
        });
    }
}

<?php
namespace App\Services;

use Exception;
use PDO;

class AccountingPostingService {
    public static function postSaleJournal(PDO $pdo, int $tenantId, int $invoiceId, string $invoiceNumber, array $payments, array $taxCalc): void {
        $accounts = [];
        $stmt = $pdo->prepare("SELECT id, code FROM accounts WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        foreach ($stmt->fetchAll() as $acc) {
            $accounts[(string)$acc['code']] = (int)$acc['id'];
        }

        $cashAccId = $accounts['1010'] ?? null;
        $bankAccId = $accounts['1020'] ?? null;
        $cardAccId = $accounts['1030'] ?? null;
        $arAccId = $accounts['1040'] ?? null;
        $salesAccId = $accounts['4010'] ?? null;
        $cgstAccId = $accounts['2010'] ?? null;
        $sgstAccId = $accounts['2020'] ?? null;
        $igstAccId = $accounts['2030'] ?? null;

        if (!$salesAccId) {
            return;
        }

        $methodAccount = static function (string $method) use ($cashAccId, $bankAccId, $cardAccId, $arAccId): ?int {
            return match ($method) {
                'upi' => $bankAccId ?: $cashAccId,
                'card' => $cardAccId ?: $bankAccId ?: $cashAccId,
                'credit' => $arAccId ?: $cashAccId,
                default => $cashAccId ?: $bankAccId,
            };
        };

        $journalItems = [];
        $grouped = [];
        foreach ($payments as $payment) {
            $method = (string)($payment['method'] ?? 'cash');
            $amount = round((float)($payment['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $grouped[$method] = ($grouped[$method] ?? 0) + $amount;
        }

        foreach ($grouped as $method => $amount) {
            $accountId = $methodAccount($method);
            if (!$accountId) {
                throw new Exception('A required payment account is missing from the chart of accounts.');
            }
            $journalItems[] = [
                'account_id' => $accountId,
                'debit' => round($amount, 2),
                'credit' => 0.0,
                'narration' => "Receipt ({$method}) for Invoice #{$invoiceNumber}",
            ];
        }

        $totalAmount = round((float)$taxCalc['total_amount'], 2);
        $taxableAmount = (float)$taxCalc['taxable_amount'];
        $cgst = (float)$taxCalc['cgst_amount'];
        $sgst = (float)$taxCalc['sgst_amount'];
        $igst = (float)$taxCalc['igst_amount'];

        $revenueCredit = (($taxCalc['registration_mode'] ?? '') === 'regular') ? $taxableAmount : $totalAmount;
        $journalItems[] = [
            'account_id' => $salesAccId,
            'debit' => 0.0,
            'credit' => round($revenueCredit, 2),
            'narration' => "Sales revenue for #{$invoiceNumber}",
        ];

        if (($taxCalc['registration_mode'] ?? '') === 'regular') {
            if ($cgst > 0 && $cgstAccId) {
                $journalItems[] = [
                    'account_id' => $cgstAccId,
                    'debit' => 0.0,
                    'credit' => round($cgst, 2),
                    'narration' => "Output CGST for #{$invoiceNumber}",
                ];
            }
            if ($sgst > 0 && $sgstAccId) {
                $journalItems[] = [
                    'account_id' => $sgstAccId,
                    'debit' => 0.0,
                    'credit' => round($sgst, 2),
                    'narration' => "Output SGST for #{$invoiceNumber}",
                ];
            }
            if ($igst > 0 && $igstAccId) {
                $journalItems[] = [
                    'account_id' => $igstAccId,
                    'debit' => 0.0,
                    'credit' => round($igst, 2),
                    'narration' => "Output IGST for #{$invoiceNumber}",
                ];
            }
        }

        $totalDebit = round(array_sum(array_column($journalItems, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($journalItems, 'credit')), 2);

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new Exception("Accounting validation error: Unbalanced journal entry. Debits: {$totalDebit}, Credits: {$totalCredit}.");
        }

        $entryNumber = 'JRN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $jStmt = $pdo->prepare("
            INSERT INTO journal_entries (tenant_id, entry_number, date, reference_id, reference_type, description, total_amount, created_at)
            VALUES (?, ?, CURDATE(), ?, 'invoice', ?, ?, NOW())
        ");
        $jStmt->execute([
            $tenantId,
            $entryNumber,
            $invoiceId,
            "Journal for Invoice #{$invoiceNumber}",
            $totalAmount,
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

    public static function postReversalJournal(PDO $pdo, int $tenantId, int $invoiceId, string $invoiceNumber, array $payments, array $taxCalc, string $reason = ''): void {
        $accounts = [];
        $stmt = $pdo->prepare("SELECT id, code FROM accounts WHERE tenant_id = ?");
        $stmt->execute([$tenantId]);
        foreach ($stmt->fetchAll() as $acc) {
            $accounts[(string)$acc['code']] = (int)$acc['id'];
        }

        $cashAccId = $accounts['1010'] ?? null;
        $bankAccId = $accounts['1020'] ?? null;
        $cardAccId = $accounts['1030'] ?? null;
        $arAccId = $accounts['1040'] ?? null;
        $salesAccId = $accounts['4010'] ?? null;
        $cgstAccId = $accounts['2010'] ?? null;
        $sgstAccId = $accounts['2020'] ?? null;
        $igstAccId = $accounts['2030'] ?? null;

        if (!$salesAccId) {
            return;
        }

        $methodAccount = static function (string $method) use ($cashAccId, $bankAccId, $cardAccId, $arAccId): ?int {
            return match ($method) {
                'upi' => $bankAccId ?: $cashAccId,
                'card' => $cardAccId ?: $bankAccId ?: $cashAccId,
                'credit' => $arAccId ?: $cashAccId,
                default => $cashAccId ?: $bankAccId,
            };
        };

        $journalItems = [];
        $grouped = [];
        foreach ($payments as $payment) {
            $method = (string)($payment['method'] ?? 'cash');
            $amount = round((float)($payment['amount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $grouped[$method] = ($grouped[$method] ?? 0) + $amount;
        }

        foreach ($grouped as $method => $amount) {
            $accountId = $methodAccount($method);
            if (!$accountId) {
                throw new Exception('A required payment account is missing from the chart of accounts.');
            }
            $journalItems[] = [
                'account_id' => $accountId,
                'debit' => 0.0,
                'credit' => round($amount, 2),
                'narration' => "Void reversal ({$method}) for Invoice #{$invoiceNumber}",
            ];
        }

        $totalAmount = round((float)$taxCalc['total_amount'], 2);
        $taxableAmount = (float)$taxCalc['taxable_amount'];
        $revenueCredit = (($taxCalc['registration_mode'] ?? '') === 'regular') ? $taxableAmount : $totalAmount;

        $journalItems[] = [
            'account_id' => $salesAccId,
            'debit' => round($revenueCredit, 2),
            'credit' => 0.0,
            'narration' => "Void sales reversal for #{$invoiceNumber}",
        ];

        if (($taxCalc['registration_mode'] ?? '') === 'regular') {
            foreach ([
                [$cgstAccId, (float)$taxCalc['cgst_amount'], 'CGST'],
                [$sgstAccId, (float)$taxCalc['sgst_amount'], 'SGST'],
                [$igstAccId, (float)$taxCalc['igst_amount'], 'IGST'],
            ] as [$accountId, $amount, $label]) {
                if ($amount > 0 && $accountId) {
                    $journalItems[] = [
                        'account_id' => $accountId,
                        'debit' => round($amount, 2),
                        'credit' => 0.0,
                        'narration' => "Void {$label} reversal for #{$invoiceNumber}",
                    ];
                }
            }
        }

        $totalDebit = round(array_sum(array_column($journalItems, 'debit')), 2);
        $totalCredit = round(array_sum(array_column($journalItems, 'credit')), 2);
        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new Exception("Accounting validation error: Unbalanced void journal. Debits: {$totalDebit}, Credits: {$totalCredit}.");
        }

        $entryNumber = 'VOID-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $jStmt = $pdo->prepare("
            INSERT INTO journal_entries (tenant_id, entry_number, date, reference_id, reference_type, description, total_amount, created_at)
            VALUES (?, ?, CURDATE(), ?, 'invoice_void', ?, ?, NOW())
        ");
        $jStmt->execute([
            $tenantId,
            $entryNumber,
            $invoiceId,
            "Void of Invoice #{$invoiceNumber}" . ($reason !== '' ? " — {$reason}" : ''),
            $totalAmount,
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
}

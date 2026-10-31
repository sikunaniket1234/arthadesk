<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use Exception;

class AccountingController {
    public function index(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = $tenant['id'];

        // Daybook / Cashbook (Daily cash and UPI movements)
        $cashbook = Database::fetchAll("
            SELECT p.id, p.created_at, p.payment_method, p.amount, i.invoice_number,
                   COALESCE(c.name, i.walkin_name, 'Walk-in Customer') as customer_name
            FROM payments p
            JOIN invoices i ON p.invoice_id = i.id
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE p.tenant_id = :tid
            ORDER BY p.id DESC
            LIMIT 50
        ", ['tid' => $tenantId]);

        // Customer Khata (Receivables)
        $khata = Database::fetchAll("
            SELECT c.*,
                   COALESCE(SUM(CASE WHEN p.payment_method = 'credit' THEN p.amount ELSE 0 END), 0) as total_credit_sales
            FROM customers c
            LEFT JOIN invoices i ON i.customer_id = c.id
            LEFT JOIN payments p ON p.invoice_id = i.id
            WHERE c.tenant_id = :tid
            GROUP BY c.id
        ", ['tid' => $tenantId]);

        // Expenses
        $expenses = Database::fetchAll("
            SELECT e.*, a.name as account_name
            FROM expenses e
            JOIN accounts a ON e.account_id = a.id
            WHERE e.tenant_id = :tid
            ORDER BY e.id DESC
            LIMIT 50
        ", ['tid' => $tenantId]);

        $expenseAccounts = Database::fetchAll("
            SELECT id, code, name FROM accounts WHERE tenant_id = :tid AND type = 'expense' ORDER BY code ASC
        ", ['tid' => $tenantId]);

        // Returns & Credit Notes
        $returns = Database::fetchAll("
            SELECT r.*, i.invoice_number, COALESCE(c.name, i.walkin_name, 'Walk-in Customer') as customer_name
            FROM sales_returns r
            JOIN invoices i ON r.invoice_id = i.id
            LEFT JOIN customers c ON r.customer_id = c.id
            WHERE r.tenant_id = :tid
            ORDER BY r.id DESC
            LIMIT 50
        ", ['tid' => $tenantId]);

        // General Ledger / Trial Balance
        $trialBalance = Database::fetchAll("
            SELECT a.code, a.name, a.type,
                   COALESCE(SUM(ji.debit), 0) as total_debit,
                   COALESCE(SUM(ji.credit), 0) as total_credit
            FROM accounts a
            LEFT JOIN journal_items ji ON ji.account_id = a.id
            WHERE a.tenant_id = :tid
            GROUP BY a.id, a.code, a.name, a.type
            ORDER BY a.code ASC
        ", ['tid' => $tenantId]);

        // GST Sales Summary
        $gstSummary = Database::fetchOne("
            SELECT
                COALESCE(SUM(subtotal), 0) as total_gross,
                COALESCE(SUM(discount_amount), 0) as total_discount,
                COALESCE(SUM(taxable_amount), 0) as total_taxable,
                COALESCE(SUM(cgst_amount), 0) as total_cgst,
                COALESCE(SUM(sgst_amount), 0) as total_sgst,
                COALESCE(SUM(igst_amount), 0) as total_igst,
                COALESCE(SUM(total_amount), 0) as total_billed
            FROM invoices
            WHERE tenant_id = :tid
        ", ['tid' => $tenantId]);

        $totalExpenses = array_sum(array_column($expenses, 'amount'));
        $totalReturns = array_sum(array_column($returns, 'total_refund_amount'));

        View::render('accounting/index', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'cashbook' => $cashbook,
            'khata' => $khata,
            'expenses' => $expenses,
            'expenseAccounts' => $expenseAccounts,
            'returns' => $returns,
            'trialBalance' => $trialBalance,
            'gstSummary' => $gstSummary,
            'totalExpenses' => $totalExpenses,
            'totalReturns' => $totalReturns,
        ]);
    }

    public function addExpense(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = $tenant['id'];

        $category = trim($request->input('category', 'General Expense'));
        $accountId = (int)$request->input('account_id');
        $amount = (float)$request->input('amount', 0);
        $paymentMethod = $request->input('payment_method', 'cash');
        $date = $request->input('date', date('Y-m-d'));
        $description = trim($request->input('description', 'Store expense voucher'));

        if ($amount <= 0 || $accountId <= 0) {
            flash('error', 'Please enter a valid expense account and positive amount.');
            Response::redirect('/app/accounting');
            return;
        }

        Database::transaction(function(\PDO $pdo) use ($tenantId, $category, $accountId, $amount, $paymentMethod, $date, $description) {
            $voucherNum = "EXP-" . date('Ymd') . "-" . rand(1000, 9999);

            $stmt = $pdo->prepare("
                INSERT INTO expenses (tenant_id, voucher_number, category, account_id, amount, payment_method, date, description, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$tenantId, $voucherNum, $category, $accountId, $amount, $paymentMethod, $date, $description]);
            $expenseId = (int)$pdo->lastInsertId();

            // Find payment account (Cash 1010 or Bank 1020)
            $payAccCode = ($paymentMethod === 'bank' || $paymentMethod === 'upi') ? '1020' : '1010';
            $payAccStmt = $pdo->prepare("SELECT id FROM accounts WHERE tenant_id = ? AND code = ?");
            $payAccStmt->execute([$tenantId, $payAccCode]);
            $payAccId = (int)$payAccStmt->fetchColumn();

            if (!$payAccId) {
                $payAccStmt = $pdo->prepare("SELECT id FROM accounts WHERE tenant_id = ? AND code = '1010'");
                $payAccStmt->execute([$tenantId]);
                $payAccId = (int)$payAccStmt->fetchColumn();
            }

            // Balanced Double-Entry: DR Expense Account, CR Cash/Bank Asset
            $jStmt = $pdo->prepare("
                INSERT INTO journal_entries (tenant_id, entry_number, date, reference_id, reference_type, description, total_amount, created_at)
                VALUES (?, ?, ?, ?, 'expense', ?, ?, NOW())
            ");
            $jEntryNum = "JRN-EXP-" . date('Ymd') . "-" . rand(1000, 9999);
            $jStmt->execute([$tenantId, $jEntryNum, $date, $expenseId, "Expense Voucher #{$voucherNum}: {$category}", $amount]);
            $jId = (int)$pdo->lastInsertId();

            $jiStmt = $pdo->prepare("
                INSERT INTO journal_items (tenant_id, journal_entry_id, account_id, debit, credit, narration)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            // DR Expense
            $jiStmt->execute([$tenantId, $jId, $accountId, $amount, 0.0, "Expense: {$category} - {$description}"]);
            // CR Asset
            $jiStmt->execute([$tenantId, $jId, $payAccId, 0.0, $amount, "Payment for Voucher #{$voucherNum}"]);
        });

        flash('success', "Expense of ₹ " . number_format($amount, 2) . " recorded and journal posted.");
        Response::redirect('/app/accounting');
    }
}

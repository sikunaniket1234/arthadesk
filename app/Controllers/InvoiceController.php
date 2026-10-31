<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Core\Session;
use App\Core\Logger;
use App\Services\AccountingPostingService;
use App\Services\AuditService;
use App\Services\PdfService;
use Exception;

class InvoiceController {
    public function index(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = $tenant['id'];

        [$where, $params] = $this->filters($request, $tenantId);

        $invoices = Database::fetchAll("
            SELECT i.*, COALESCE(c.name, i.walkin_name, 'Walk-in Customer') as customer_name, c.phone as customer_phone
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE {$where}
            ORDER BY i.id DESC
            LIMIT 200
        ", $params);

        $stats = Database::fetchOne("
            SELECT
                COUNT(*) as total_invoices,
                COALESCE(SUM(CASE WHEN i.payment_status != 'cancelled' THEN i.total_amount ELSE 0 END), 0) as total_sales,
                COALESCE(SUM(CASE WHEN i.payment_status != 'cancelled' THEN i.cgst_amount + i.sgst_amount + i.igst_amount ELSE 0 END), 0) as total_tax,
                COALESCE(SUM(CASE WHEN i.payment_status = 'cancelled' THEN 1 ELSE 0 END), 0) as cancelled_invoices
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE {$where}
        ", $params);

        View::render('invoices/index', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'invoices' => $invoices,
            'stats' => $stats,
            'filters' => [
                'from' => (string)$request->query('from', ''),
                'to' => (string)$request->query('to', ''),
                'status' => (string)$request->query('status', ''),
                'q' => (string)$request->query('q', ''),
            ],
        ]);
    }

    public function show(Request $request): void {
        $tenant = $request->tenant();
        $invoiceId = (int)$request->param('id');

        $data = $this->loadInvoice($request, $invoiceId);
        if ($data === null) {
            Response::error('Invoice not found or access denied.', 404);
            return;
        }

        View::render('invoices/show', $data + [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
        ]);
    }

    public function thermal(Request $request): void {
        $tenant = $request->tenant();
        $invoiceId = (int)$request->param('id');

        $data = $this->loadInvoice($request, $invoiceId);
        if ($data === null) {
            Response::error('Invoice not found.', 404);
            return;
        }

        View::render('invoices/thermal', $data + [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'noLayout' => true,
        ]);
    }

    public function pdf(Request $request): void {
        $tenant = $request->tenant();
        $invoiceId = (int)$request->param('id');

        $data = $this->loadInvoice($request, $invoiceId);
        if ($data === null) {
            Response::error('Invoice not found.', 404);
            return;
        }

        $data['tenant'] = $tenant;
        $data['taxProfile'] = $request->taxProfile();
        $body = View::renderToString('invoices/show', $data);
        $html = PdfService::document('Invoice ' . $data['invoice']['invoice_number'], $body);

        AuditService::log('invoice.pdf_exported', 'invoice', $invoiceId, [
            'invoice_number' => $data['invoice']['invoice_number'],
        ], (int)$tenant['id']);

        PdfService::stream($html, 'Invoice-' . $data['invoice']['invoice_number'] . '.pdf');
    }

    public function export(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        [$where, $params] = $this->filters($request, $tenantId);

        $rows = Database::fetchAll("
            SELECT i.invoice_number, i.document_type, i.created_at,
                   COALESCE(c.name, i.walkin_name, 'Walk-in Customer') as customer_name,
                   i.taxable_amount, i.cgst_amount, i.sgst_amount, i.igst_amount,
                   i.total_amount, i.payment_status, i.void_reason
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            WHERE {$where}
            ORDER BY i.id DESC
        ", $params);

        AuditService::log('invoice.exported', 'invoice', null, [
            'rows' => count($rows),
            'from' => $request->query('from'),
            'to' => $request->query('to'),
        ], $tenantId);

        $filename = 'invoices-' . date('Ymd-His') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Invoice #', 'Document Type', 'Date', 'Customer', 'Taxable', 'CGST', 'SGST', 'IGST', 'Total', 'Status', 'Void Reason']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['invoice_number'],
                $row['document_type'],
                $row['created_at'],
                $row['customer_name'],
                number_format((float)$row['taxable_amount'], 2, '.', ''),
                number_format((float)$row['cgst_amount'], 2, '.', ''),
                number_format((float)$row['sgst_amount'], 2, '.', ''),
                number_format((float)$row['igst_amount'], 2, '.', ''),
                number_format((float)$row['total_amount'], 2, '.', ''),
                $row['payment_status'],
                $row['void_reason'],
            ]);
        }
        fclose($out);
        exit;
    }

    public function void(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $user = $request->user();
        $role = $user['role'] ?? '';

        if (!in_array($role, ['super_admin', 'tenant_admin', 'accountant'], true)) {
            flash('error', 'You do not have permission to void invoices.');
            Response::redirect('/app/invoices/' . (int)$request->param('id'));
            return;
        }

        $invoiceId = (int)$request->param('id');
        $reason = trim((string)$request->input('reason', ''));
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            flash('error', 'A void reason of 5–500 characters is required.');
            Response::redirect('/app/invoices/' . $invoiceId);
            return;
        }

        try {
            Database::transaction(function (\PDO $pdo) use ($tenantId, $invoiceId, $reason, $user) {
                $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ? AND tenant_id = ? FOR UPDATE");
                $stmt->execute([$invoiceId, $tenantId]);
                $invoice = $stmt->fetch();
                if (!$invoice) {
                    throw new Exception('Invoice not found.');
                }
                if (!empty($invoice['voided_at']) || $invoice['payment_status'] === 'cancelled') {
                    throw new Exception('This invoice has already been voided.');
                }

                $payStmt = $pdo->prepare("SELECT * FROM payments WHERE invoice_id = ? AND tenant_id = ?");
                $payStmt->execute([$invoiceId, $tenantId]);
                $payments = $payStmt->fetchAll();

                $paidAmount = 0.0;
                $accountingPayments = [];
                foreach ($payments as $payment) {
                    if (($payment['status'] ?? '') === 'completed') {
                        $paidAmount += (float)$payment['amount'];
                        $accountingPayments[] = [
                            'method' => $payment['payment_method'],
                            'amount' => (float)$payment['amount'],
                        ];
                    }
                }
                $creditAmount = round((float)$invoice['total_amount'] - $paidAmount, 2);
                if ($creditAmount > 0.001) {
                    $accountingPayments[] = ['method' => 'credit', 'amount' => $creditAmount];
                }

                $items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? AND tenant_id = ?");
                $items->execute([$invoiceId, $tenantId]);
                $stockRestore = $pdo->prepare("UPDATE products SET current_stock = current_stock + ? WHERE id = ? AND tenant_id = ?");
                $movement = $pdo->prepare("
                    INSERT INTO stock_movements (tenant_id, product_id, movement_type, quantity, reference_id, reference_type, notes, created_at)
                    VALUES (?, ?, 'RETURN', ?, ?, 'invoice_void', ?, NOW())
                ");
                foreach ($items->fetchAll() as $item) {
                    if (!empty($item['product_id'])) {
                        $stockRestore->execute([(float)$item['quantity'], (int)$item['product_id'], $tenantId]);
                        $movement->execute([
                            $tenantId,
                            (int)$item['product_id'],
                            (float)$item['quantity'],
                            $invoiceId,
                            'Void of ' . $invoice['invoice_number'] . ' — ' . $reason,
                        ]);
                    }
                }

                $pdo->prepare("UPDATE payments SET status = 'refunded' WHERE invoice_id = ? AND tenant_id = ?")
                    ->execute([$invoiceId, $tenantId]);

                if ($creditAmount > 0 && !empty($invoice['customer_id'])) {
                    $pdo->prepare("UPDATE customers SET balance = balance - ? WHERE id = ? AND tenant_id = ?")
                        ->execute([$creditAmount, (int)$invoice['customer_id'], $tenantId]);
                }

                $taxProfile = $pdo->prepare("SELECT * FROM tenant_tax_profiles WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
                $taxProfile->execute([$tenantId]);
                $profile = $taxProfile->fetch() ?: ['registration_mode' => 'unregistered'];

                try {
                    AccountingPostingService::postReversalJournal(
                        $pdo,
                        $tenantId,
                        $invoiceId,
                        (string)$invoice['invoice_number'],
                        $accountingPayments,
                        [
                            'total_amount' => (float)$invoice['total_amount'],
                            'taxable_amount' => (float)$invoice['taxable_amount'],
                            'cgst_amount' => (float)$invoice['cgst_amount'],
                            'sgst_amount' => (float)$invoice['sgst_amount'],
                            'igst_amount' => (float)$invoice['igst_amount'],
                            'registration_mode' => $profile['registration_mode'] ?? 'unregistered',
                        ],
                        $reason
                    );
                } catch (\Throwable $e) {
                    Logger::error('invoice_void_accounting_failed', [
                        'invoice_id' => $invoiceId,
                        'error' => $e->getMessage(),
                    ], 'accounting');
                    throw new Exception('Accounting reversal failed. No changes were saved.');
                }

                $pdo->prepare("
                    UPDATE invoices
                    SET payment_status = 'cancelled', void_reason = ?, voided_at = NOW(), voided_by = ?
                    WHERE id = ? AND tenant_id = ?
                ")->execute([$reason, (int)$user['id'], $invoiceId, $tenantId]);
            });

            AuditService::log('invoice.voided', 'invoice', $invoiceId, [
                'reason' => $reason,
            ], $tenantId, (int)$user['id']);

            flash('success', 'Invoice voided successfully. Stock and ledger have been reversed.');
        } catch (\PDOException $e) {
            Logger::error('invoice_void_failed', [
                'tenant_id' => $tenantId,
                'invoice_id' => $invoiceId,
                'error' => $e->getMessage(),
            ], 'invoices');
            flash('error', 'Could not void invoice. Please try again.');
        } catch (Exception $e) {
            flash('error', 'Could not void invoice: ' . $e->getMessage());
        }

        Response::redirect('/app/invoices/' . $invoiceId);
    }

    protected function loadInvoice(Request $request, int $invoiceId): ?array {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];

        $invoice = Database::fetchOne("
            SELECT i.*, c.name as customer_name, c.phone as customer_phone, c.gstin as customer_gstin,
                   c.address as customer_address, u.name as cashier_name
            FROM invoices i
            LEFT JOIN customers c ON i.customer_id = c.id
            LEFT JOIN users u ON i.user_id = u.id
            WHERE i.id = :id AND i.tenant_id = :tid
        ", ['id' => $invoiceId, 'tid' => $tenantId]);

        if (!$invoice) {
            return null;
        }

        $items = Database::fetchAll("
            SELECT * FROM invoice_items WHERE invoice_id = :id AND tenant_id = :tid
        ", ['id' => $invoiceId, 'tid' => $tenantId]);

        $payments = Database::fetchAll("
            SELECT * FROM payments WHERE invoice_id = :id AND tenant_id = :tid ORDER BY id ASC
        ", ['id' => $invoiceId, 'tid' => $tenantId]);

        $paidAmount = 0.0;
        $methods = [];
        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') === 'completed') {
                $paidAmount += (float)$payment['amount'];
                $methods[] = strtoupper((string)$payment['payment_method']);
            }
        }
        $creditAmount = max(0, round((float)$invoice['total_amount'] - $paidAmount, 2));
        if ($creditAmount > 0.001) {
            $methods[] = 'CREDIT';
        }
        $invoice['payment_method'] = implode(' + ', array_unique($methods)) ?: 'CREDIT';
        $invoice['paid_amount'] = $paidAmount;
        $invoice['credit_amount'] = $creditAmount;

        return [
            'invoice' => $invoice,
            'items' => $items,
            'payments' => $payments,
        ];
    }

    protected function filters(Request $request, int $tenantId): array {
        $where = ['i.tenant_id = :tid'];
        $params = ['tid' => $tenantId];

        $from = (string)$request->query('from', '');
        $to = (string)$request->query('to', '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $where[] = 'DATE(i.created_at) >= :from';
            $params['from'] = $from;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $where[] = 'DATE(i.created_at) <= :to';
            $params['to'] = $to;
        }

        $status = (string)$request->query('status', '');
        if (in_array($status, ['paid', 'partial', 'pending', 'cancelled'], true)) {
            $where[] = 'i.payment_status = :status';
            $params['status'] = $status;
        }

        $q = trim((string)$request->query('q', ''));
        if ($q !== '') {
            $where[] = '(i.invoice_number LIKE :q OR c.name LIKE :q OR i.walkin_name LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        return [implode(' AND ', $where), $params];
    }
}

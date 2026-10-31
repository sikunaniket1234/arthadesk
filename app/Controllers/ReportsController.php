<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Core\Session;
use App\Services\AuditService;

class ReportsController {
    public function index(Request $request): void {
        $tenant  = $request->tenant();
        $tenantId = $tenant['id'];

        $from = $request->query('from', date('Y-m-01'));
        $to   = $request->query('to', date('Y-m-d'));

        // Sales summary
        $summary = Database::fetchOne("
            SELECT
                COALESCE(SUM(i.total_amount), 0)  AS total_sales,
                COUNT(i.id)                        AS invoice_count,
                COALESCE(SUM(i.cgst_amount), 0)   AS total_cgst,
                COALESCE(SUM(i.sgst_amount), 0)   AS total_sgst,
                COALESCE(SUM(i.igst_amount), 0)   AS total_igst
            FROM invoices i
            WHERE i.tenant_id = :tid
              AND i.payment_status != 'cancelled'
              AND DATE(i.created_at) BETWEEN :from AND :to
        ", ['tid' => $tenantId, 'from' => $from, 'to' => $to]);

        // Returns summary
        $returnSum = Database::fetchOne("
            SELECT COALESCE(SUM(total_refund_amount), 0) AS total_returns, COUNT(id) AS return_count
            FROM sales_returns
            WHERE tenant_id = :tid AND DATE(created_at) BETWEEN :from AND :to
        ", ['tid' => $tenantId, 'from' => $from, 'to' => $to]);

        $summary['total_returns'] = $returnSum['total_returns'] ?? 0;
        $summary['return_count']  = $returnSum['return_count'] ?? 0;

        // Expenses
        $expSum = Database::fetchOne("
            SELECT COALESCE(SUM(amount), 0) AS total_expenses
            FROM expenses
            WHERE tenant_id = :tid AND date BETWEEN :from AND :to
        ", ['tid' => $tenantId, 'from' => $from, 'to' => $to]);
        $summary['total_expenses'] = $expSum['total_expenses'] ?? 0;

        // Low stock
        $lowStockItems = Database::fetchAll("
            SELECT id, name_en, current_stock, reorder_level
            FROM products
            WHERE tenant_id = :tid AND is_active = 1 AND current_stock <= reorder_level
            ORDER BY current_stock ASC
        ", ['tid' => $tenantId]);
        $summary['low_stock_count'] = count($lowStockItems);

        // Top selling products
        $topProducts = Database::fetchAll("
            SELECT p.name_en,
                   SUM(ii.quantity)   AS total_qty,
                   SUM(ii.line_total) AS total_revenue
            FROM invoice_items ii
            JOIN products p ON p.id = ii.product_id
            JOIN invoices  i ON i.id = ii.invoice_id
            WHERE i.tenant_id = :tid
              AND i.payment_status != 'cancelled'
              AND DATE(i.created_at) BETWEEN :from AND :to
            GROUP BY ii.product_id, p.name_en
            ORDER BY total_revenue DESC
            LIMIT 10
        ", ['tid' => $tenantId, 'from' => $from, 'to' => $to]);

        // GST summary by rate
        $gstSummary = Database::fetchAll("
            SELECT ii.tax_rate_percent,
                   SUM(ii.taxable_value) AS taxable,
                   SUM(ii.cgst_amount)   AS cgst,
                   SUM(ii.sgst_amount)   AS sgst,
                   SUM(ii.igst_amount)   AS igst
            FROM invoice_items ii
            JOIN invoices i ON i.id = ii.invoice_id
            WHERE i.tenant_id = :tid
              AND i.payment_status != 'cancelled'
              AND DATE(i.created_at) BETWEEN :from AND :to
            GROUP BY ii.tax_rate_percent
            ORDER BY ii.tax_rate_percent ASC
        ", ['tid' => $tenantId, 'from' => $from, 'to' => $to]);

        // Recent invoices (last 15)
        $recentInvoices = Database::fetchAll("
            SELECT i.*, c.name AS customer_name
            FROM invoices i
            LEFT JOIN customers c ON c.id = i.customer_id
            WHERE i.tenant_id = :tid
            ORDER BY i.created_at DESC
            LIMIT 15
        ", ['tid' => $tenantId]);

        View::render('reports/index', [
            'tenant'         => $tenant,
            'taxProfile'     => $request->taxProfile(),
            'summary'        => $summary,
            'topProducts'    => $topProducts,
            'lowStockItems'  => $lowStockItems,
            'gstSummary'     => $gstSummary,
            'recentInvoices' => $recentInvoices,
            'filters'        => ['from' => $from, 'to' => $to],
        ]);
    }

    public function export(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];

        $from = (string)$request->query('from', date('Y-m-01'));
        $to = (string)$request->query('to', date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = date('Y-m-01');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = date('Y-m-d');
        }

        $type = (string)$request->query('type', 'sales');
        $allowed = ['sales', 'gst', 'stock', 'expenses', 'returns', 'purchases'];
        if (!in_array($type, $allowed, true)) {
            $type = 'sales';
        }

        $params = ['tid' => $tenantId, 'from' => $from, 'to' => $to];
        $filename = 'report-' . $type . '-' . $from . '-to-' . $to . '.csv';

        switch ($type) {
            case 'gst':
                $headers = ['Tax Rate %', 'Taxable Value', 'CGST', 'SGST', 'IGST'];
                $rows = Database::fetchAll("
                    SELECT ii.tax_rate_percent, SUM(ii.taxable_value) AS taxable,
                           SUM(ii.cgst_amount) AS cgst, SUM(ii.sgst_amount) AS sgst, SUM(ii.igst_amount) AS igst
                    FROM invoice_items ii
                    JOIN invoices i ON i.id = ii.invoice_id
                    WHERE i.tenant_id = :tid AND i.payment_status != 'cancelled'
                      AND DATE(i.created_at) BETWEEN :from AND :to
                    GROUP BY ii.tax_rate_percent ORDER BY ii.tax_rate_percent
                ", $params);
                $data = array_map(static fn($r) => [
                    $r['tax_rate_percent'], $r['taxable'], $r['cgst'], $r['sgst'], $r['igst'],
                ], $rows);
                break;

            case 'stock':
                $headers = ['SKU', 'Product', 'Category', 'Stock', 'Reorder Level', 'Cost Price', 'Sale Price'];
                $rows = Database::fetchAll("
                    SELECT sku, name_en, category, current_stock, reorder_level, cost_price, sale_price
                    FROM products WHERE tenant_id = :tid AND is_active = 1 ORDER BY name_en
                ", ['tid' => $tenantId]);
                $data = array_map(static fn($r) => [
                    $r['sku'], $r['name_en'], $r['category'], $r['current_stock'], $r['reorder_level'],
                    $r['cost_price'], $r['sale_price'],
                ], $rows);
                break;

            case 'expenses':
                $headers = ['Date', 'Category', 'Description', 'Amount'];
                $rows = Database::fetchAll("
                    SELECT date, category, description, amount FROM expenses
                    WHERE tenant_id = :tid AND date BETWEEN :from AND :to ORDER BY date DESC
                ", $params);
                $data = array_map(static fn($r) => [$r['date'], $r['category'], $r['description'], $r['amount']], $rows);
                break;

            case 'returns':
                $headers = ['Return #', 'Date', 'Invoice #', 'Type', 'Refund Method', 'Refund Amount', 'Reason'];
                $rows = Database::fetchAll("
                    SELECT r.return_number, r.created_at, i.invoice_number, r.transaction_type,
                           r.refund_method, r.total_refund_amount, r.reason
                    FROM sales_returns r
                    JOIN invoices i ON i.id = r.invoice_id
                    WHERE r.tenant_id = :tid AND DATE(r.created_at) BETWEEN :from AND :to
                    ORDER BY r.id DESC
                ", $params);
                $data = array_map(static fn($r) => [
                    $r['return_number'], $r['created_at'], $r['invoice_number'], $r['transaction_type'],
                    $r['refund_method'], $r['total_refund_amount'], $r['reason'],
                ], $rows);
                break;

            case 'purchases':
                $headers = ['Bill #', 'Date', 'Supplier', 'Supplier Ref', 'Total', 'Paid', 'Balance', 'Status'];
                $rows = Database::fetchAll("
                    SELECT pb.bill_number, pb.bill_date, s.name AS supplier_name, pb.supplier_bill_ref,
                           pb.total_amount, pb.paid_amount, pb.status
                    FROM purchase_bills pb
                    LEFT JOIN suppliers s ON s.id = pb.supplier_id
                    WHERE pb.tenant_id = :tid AND pb.bill_date BETWEEN :from AND :to
                    ORDER BY pb.bill_date DESC
                ", $params);
                $data = array_map(static fn($r) => [
                    $r['bill_number'], $r['bill_date'], $r['supplier_name'], $r['supplier_bill_ref'],
                    $r['total_amount'], $r['paid_amount'],
                    round((float)$r['total_amount'] - (float)$r['paid_amount'], 2), $r['status'],
                ], $rows);
                break;

            default:
                $headers = ['Invoice #', 'Date', 'Document Type', 'Customer', 'Taxable', 'CGST', 'SGST', 'IGST', 'Total', 'Status'];
                $rows = Database::fetchAll("
                    SELECT i.invoice_number, i.created_at, i.document_type,
                           COALESCE(c.name, i.walkin_name, 'Walk-in Customer') AS customer_name,
                           i.taxable_amount, i.cgst_amount, i.sgst_amount, i.igst_amount,
                           i.total_amount, i.payment_status
                    FROM invoices i
                    LEFT JOIN customers c ON c.id = i.customer_id
                    WHERE i.tenant_id = :tid AND DATE(i.created_at) BETWEEN :from AND :to
                    ORDER BY i.id DESC
                ", $params);
                $data = array_map(static fn($r) => [
                    $r['invoice_number'], $r['created_at'], $r['document_type'], $r['customer_name'],
                    $r['taxable_amount'], $r['cgst_amount'], $r['sgst_amount'], $r['igst_amount'],
                    $r['total_amount'], $r['payment_status'],
                ], $rows);
        }

        AuditService::log('report.exported', 'report', null, [
            'type' => $type,
            'from' => $from,
            'to' => $to,
            'rows' => count($data),
        ], $tenantId);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $headers);
        foreach ($data as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }
}

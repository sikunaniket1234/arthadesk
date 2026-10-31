<?php
// Reports Dashboard: P&L, Sales Summary, Low Stock, GST Summary
$isOdia = (current_locale() === 'or');
?>

<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;flex-wrap:wrap;gap:1rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:#0f172a;">📊 Reports & Analytics</h1>
            <p style="font-size:0.85rem;color:#64748b;">Business performance insights — Sales, Inventory, GST, and Profit & Loss.</p>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
            <input type="date" id="fromDate" class="form-control" style="width:140px;" value="<?= e($filters['from'] ?? date('Y-m-01')) ?>">
            <span style="color:#64748b;">to</span>
            <input type="date" id="toDate" class="form-control" style="width:140px;" value="<?= e($filters['to'] ?? date('Y-m-d')) ?>">
            <button onclick="applyDateFilter()" class="btn btn-primary" style="font-size:0.82rem;">Filter</button>
            <select id="exportType" class="form-control" style="width:150px;">
                <option value="sales">Sales</option>
                <option value="gst">GST</option>
                <option value="stock">Stock</option>
                <option value="expenses">Expenses</option>
                <option value="returns">Returns</option>
                <option value="purchases">Purchases</option>
            </select>
            <button onclick="exportReport()" class="btn btn-secondary" style="font-size:0.82rem;">⬇️ Export CSV</button>
        </div>
    </div>

    <!-- KPI Summary Row -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;margin-bottom:1.5rem;">
        <div class="card kpi-card">
            <div class="kpi-icon">💰</div>
            <div class="kpi-label">Total Sales (This Month)</div>
            <div class="kpi-value"><?= money_format_inr($summary['total_sales'] ?? 0) ?></div>
            <div class="kpi-sub"><?= (int)($summary['invoice_count'] ?? 0) ?> invoices</div>
        </div>
        <div class="card kpi-card">
            <div class="kpi-icon">↩️</div>
            <div class="kpi-label">Total Returns</div>
            <div class="kpi-value" style="color:#ef4444;"><?= money_format_inr($summary['total_returns'] ?? 0) ?></div>
            <div class="kpi-sub"><?= (int)($summary['return_count'] ?? 0) ?> credit notes</div>
        </div>
        <div class="card kpi-card">
            <div class="kpi-icon">📦</div>
            <div class="kpi-label">Products (Low Stock)</div>
            <div class="kpi-value" style="color:#f59e0b;"><?= (int)($summary['low_stock_count'] ?? 0) ?></div>
            <div class="kpi-sub">Below reorder level</div>
        </div>
        <div class="card kpi-card">
            <div class="kpi-icon">📋</div>
            <div class="kpi-label">GST Collected (CGST+SGST)</div>
            <div class="kpi-value" style="color:#7c3aed;"><?= money_format_inr(($summary['total_cgst'] ?? 0) + ($summary['total_sgst'] ?? 0)) ?></div>
            <div class="kpi-sub">IGST: <?= money_format_inr($summary['total_igst'] ?? 0) ?></div>
        </div>
        <div class="card kpi-card">
            <div class="kpi-icon">💸</div>
            <div class="kpi-label">Total Expenses</div>
            <div class="kpi-value" style="color:#dc2626;"><?= money_format_inr($summary['total_expenses'] ?? 0) ?></div>
            <div class="kpi-sub">Operating expenses</div>
        </div>
        <div class="card kpi-card" style="background:linear-gradient(135deg,#ecfdf5,#d1fae5);border-color:#059669;">
            <div class="kpi-icon">📈</div>
            <div class="kpi-label">Net Profit (Estimate)</div>
            <div class="kpi-value" style="color:#059669;"><?= money_format_inr(($summary['total_sales'] ?? 0) - ($summary['total_returns'] ?? 0) - ($summary['total_expenses'] ?? 0)) ?></div>
            <div class="kpi-sub">Sales − Returns − Expenses</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;margin-bottom:1.5rem;">
        <!-- Top Selling Products -->
        <div class="card" style="padding:1.2rem;">
            <h3 style="font-size:0.95rem;font-weight:700;color:#1e293b;margin-bottom:0.8rem;">🏆 Top Selling Products</h3>
            <?php if (empty($topProducts)): ?>
                <p style="color:#94a3b8;font-size:0.82rem;text-align:center;padding:1.5rem 0;">No sales data yet.</p>
            <?php else: ?>
                <table class="data-table">
                    <thead><tr><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
                    <tbody>
                    <?php foreach ($topProducts as $i => $p): ?>
                        <tr>
                            <td style="font-size:0.82rem;">
                                <span style="font-weight:700;color:#2563eb;margin-right:0.4rem;">#<?= $i+1 ?></span>
                                <?= e($p['name_en']) ?>
                            </td>
                            <td style="font-weight:600;"><?= number_format((float)$p['total_qty'],2) ?></td>
                            <td style="font-weight:700;color:#059669;"><?= money_format_inr($p['total_revenue']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Low Stock Alert -->
        <div class="card" style="padding:1.2rem;">
            <h3 style="font-size:0.95rem;font-weight:700;color:#1e293b;margin-bottom:0.8rem;">⚠️ Low / Out-of-Stock Alerts</h3>
            <?php if (empty($lowStockItems)): ?>
                <p style="color:#059669;font-size:0.82rem;text-align:center;padding:1.5rem 0;">✅ All stock levels are healthy.</p>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:0.5rem;max-height:250px;overflow-y:auto;">
                <?php foreach ($lowStockItems as $item): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem;border-radius:6px;background:<?= (float)$item['current_stock'] <= 0 ? '#fef2f2' : '#fffbeb' ?>;border-left:3px solid <?= (float)$item['current_stock'] <= 0 ? '#ef4444' : '#f59e0b' ?>;">
                        <span style="font-size:0.8rem;font-weight:600;"><?= e($item['name_en']) ?></span>
                        <span style="font-size:0.78rem;font-weight:700;color:<?= (float)$item['current_stock'] <= 0 ? '#dc2626' : '#d97706' ?>;">
                            <?= (float)$item['current_stock'] <= 0 ? 'OUT OF STOCK' : number_format((float)$item['current_stock'],2).' left' ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- GST Summary Table -->
    <div class="card" style="padding:1.2rem;margin-bottom:1.5rem;">
        <h3 style="font-size:0.95rem;font-weight:700;color:#1e293b;margin-bottom:0.8rem;">📋 GST Tax Summary (This Month)</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>GST Rate</th>
                        <th>Taxable Amount</th>
                        <th>CGST Collected</th>
                        <th>SGST Collected</th>
                        <th>IGST Collected</th>
                        <th>Total Tax</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($gstSummary)): ?>
                    <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No GST transactions this period.</td></tr>
                <?php else: ?>
                    <?php $totalTaxable=$totalCGST=$totalSGST=$totalIGST=0; ?>
                    <?php foreach ($gstSummary as $row): ?>
                        <?php $totalTaxable+=$row['taxable']; $totalCGST+=$row['cgst']; $totalSGST+=$row['sgst']; $totalIGST+=$row['igst']; ?>
                        <tr>
                            <td><strong><?= $row['tax_rate_percent'] ?>%</strong></td>
                            <td><?= money_format_inr($row['taxable']) ?></td>
                            <td><?= money_format_inr($row['cgst']) ?></td>
                            <td><?= money_format_inr($row['sgst']) ?></td>
                            <td><?= money_format_inr($row['igst']) ?></td>
                            <td style="font-weight:700;color:#7c3aed;"><?= money_format_inr($row['cgst']+$row['sgst']+$row['igst']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr style="background:#f8fafc;font-weight:700;">
                        <td>TOTAL</td>
                        <td><?= money_format_inr($totalTaxable) ?></td>
                        <td style="color:#059669;"><?= money_format_inr($totalCGST) ?></td>
                        <td style="color:#059669;"><?= money_format_inr($totalSGST) ?></td>
                        <td style="color:#7c3aed;"><?= money_format_inr($totalIGST) ?></td>
                        <td style="color:#7c3aed;"><?= money_format_inr($totalCGST+$totalSGST+$totalIGST) ?></td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Recent Sales (last 10) -->
    <div class="card" style="padding:1.2rem;">
        <h3 style="font-size:0.95rem;font-weight:700;color:#1e293b;margin-bottom:0.8rem;">🧾 Recent Invoices</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead><tr><th>Invoice #</th><th>Customer</th><th>Date</th><th>Total</th><th>Payment</th><th></th></tr></thead>
                <tbody>
                <?php if (empty($recentInvoices)): ?>
                    <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No invoices yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($recentInvoices as $inv): ?>
                    <tr>
                        <td style="font-weight:700;color:#2563eb;"><?= e($inv['invoice_number']) ?></td>
                        <td><?= e($inv['customer_name'] ?? ($inv['walkin_name'] ?? 'Walk-in Customer')) ?></td>
                        <td><?= date('d M Y', strtotime($inv['created_at'])) ?></td>
                        <td style="font-weight:700;"><?= money_format_inr($inv['total_amount']) ?></td>
                        <td><span class="badge badge-<?= $inv['payment_status']==='paid'?'success':($inv['payment_status']==='partial'?'warning':'error') ?>"><?= ucfirst($inv['payment_status']) ?></span></td>
                        <td><a href="/app/invoices/<?= (int)$inv['id'] ?>" class="btn btn-secondary" style="font-size:0.72rem;padding:0.2rem 0.5rem;">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.kpi-card { padding:1.2rem;text-align:center; }
.kpi-icon { font-size:1.8rem;margin-bottom:0.4rem; }
.kpi-label { font-size:0.72rem;font-weight:600;color:#64748b;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:0.3rem; }
.kpi-value { font-size:1.4rem;font-weight:800;color:#0f172a; }
.kpi-sub { font-size:0.72rem;color:#94a3b8;margin-top:0.2rem; }
</style>
<script>
function applyDateFilter() {
    const from = document.getElementById('fromDate').value;
    const to = document.getElementById('toDate').value;
    window.location.href = '/app/reports?from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
}

function exportReport() {
    const from = document.getElementById('fromDate').value;
    const to = document.getElementById('toDate').value;
    const type = document.getElementById('exportType').value;
    window.location.href = '/app/reports/export?type=' + encodeURIComponent(type)
        + '&from=' + encodeURIComponent(from) + '&to=' + encodeURIComponent(to);
}
</script>

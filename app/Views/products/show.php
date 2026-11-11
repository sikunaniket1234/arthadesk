<?php
$movFilterUrl = function (array $overrides) use ($movType, $movFrom, $movTo) {
    $params = array_merge([
        'type' => $movType,
        'from' => $movFrom,
        'to' => $movTo,
    ], $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    $qs = http_build_query($params);
    return '/app/products/' . (int)$product['id'] . ($qs !== '' ? '?' . $qs : '');
};
$movPageUrl = function (int $p) use ($movFilterUrl, $movType, $movFrom, $movTo) {
    return $movFilterUrl(['type' => $movType, 'from' => $movFrom, 'to' => $movTo, 'page' => $p]);
};
$badgeClass = function (string $movementType, float $qty): string {
    if ($movementType === 'SALE') return 'badge-info';
    if ($qty < 0) return 'badge-error';
    if ($movementType === 'ADJUSTMENT') return 'badge-warning';
    return 'badge-success';
};
?>
<div class="container" style="max-width: 1100px;">
    <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <a href="/app/products" class="btn btn-secondary">← Back to Products</a>
        <button type="button" class="btn btn-primary"
            data-product="<?= e(json_encode([
                'id' => (int)$product['id'],
                'name_en' => $product['name_en'],
                'name_or' => $product['name_or'],
                'sku' => $product['sku'],
                'barcode' => $product['barcode'],
                'category' => $product['category'],
                'unit_id' => $product['unit_id'] !== null ? (int)$product['unit_id'] : '',
                'tax_category_id' => $product['tax_category_id'] !== null ? (int)$product['tax_category_id'] : '',
                'supplier_id' => $product['supplier_id'] !== null ? (int)$product['supplier_id'] : '',
                'sale_price' => $product['sale_price'],
                'cost_price' => $product['cost_price'],
                'price_includes_tax' => (int)$product['price_includes_tax'],
                'reorder_level' => $product['reorder_level'],
            ])) ?>"
            onclick="editFromDetail(this)">Edit Product</button>
    </div>

    <!-- Header card -->
    <div class="card" style="margin-bottom: 1.25rem;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; flex-wrap: wrap;">
            <div>
                <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
                    <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin: 0;"><?= e($product['name_en']) ?></h1>
                    <span class="badge <?= (int)$product['is_active'] === 1 ? 'badge-success' : 'badge-error' ?>">
                        <?= (int)$product['is_active'] === 1 ? 'ACTIVE' : 'INACTIVE' ?>
                    </span>
                    <?php if ($isOut): ?>
                        <span class="badge badge-error">OUT OF STOCK</span>
                    <?php elseif ($isLow): ?>
                        <span class="badge badge-warning">LOW STOCK</span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($product['name_or'])): ?>
                    <div style="font-size: 1rem; color: #0369a1; font-family: 'Noto Sans Oriya', sans-serif; margin-top: 0.25rem;">
                        <?= e($product['name_or']) ?>
                    </div>
                <?php endif; ?>
                <div style="font-size: 0.85rem; color: #64748b; margin-top: 0.5rem;">
                    SKU: <strong style="font-family: monospace;"><?= e($product['sku'] ?? '—') ?></strong>
                    &nbsp;·&nbsp; Barcode: <strong style="font-family: monospace;"><?= e($product['barcode'] ?? '—') ?></strong>
                    &nbsp;·&nbsp; Category: <strong><?= e($product['category']) ?></strong>
                </div>
            </div>
            <div style="font-size: 0.85rem; color: #64748b; text-align: right;">
                <div>Unit: <strong><?= e($product['unit_name'] ?? '—') ?> (<?= e($product['unit_code'] ?? '—') ?>)</strong></div>
                <div>GST: <span class="badge badge-warning"><?= e($product['tax_cat_name'] ?? 'None') ?><?= $product['rate_percent'] !== null ? ' (' . e($product['rate_percent']) . '%)' : '' ?></span></div>
                <div style="margin-top: 0.25rem;">Supplier: <strong><?= e($product['supplier_name'] ?? '—') ?></strong></div>
                <div>Price includes GST: <strong><?= (int)$product['price_includes_tax'] === 1 ? 'Yes' : 'No' ?></strong></div>
            </div>
        </div>
    </div>

    <!-- KPI strip -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Stock on Hand</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: <?= $isOut ? '#ef4444' : ($isLow ? '#d97706' : '#0f172a') ?>; margin-top: 0.25rem;">
                <?= $stock ?> <span style="font-size:0.85rem;font-weight:600;"><?= e($product['unit_code'] ?? '') ?></span>
            </div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Sale Price</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;"><?= money_format_inr($product['sale_price']) ?></div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Cost Price</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #64748b; margin-top: 0.25rem;"><?= money_format_inr($product['cost_price']) ?></div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Margin</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: <?= $marginPct !== null && $marginPct >= 0 ? '#059669' : '#ef4444' ?>; margin-top: 0.25rem;">
                <?= $marginPct !== null ? $marginPct . '%' : '—' ?>
            </div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Stock Valuation</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #059669; margin-top: 0.25rem;"><?= money_format_inr($valuation) ?></div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Reorder Level</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;"><?= $product['reorder_level'] !== null ? (float)$product['reorder_level'] : '—' ?></div>
        </div>
    </div>

    <!-- Stock Movement History -->
    <div class="card" style="margin-bottom: 1.5rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.75rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Stock Movement History</h3>
            <form method="GET" action="/app/products/<?= (int)$product['id'] ?>" style="display: flex; gap: 0.5rem; align-items: flex-end; flex-wrap: wrap;">
                <select name="type" class="form-input" style="width: auto; margin: 0; font-size: 0.85rem;">
                    <option value="">All types</option>
                    <?php foreach (['OPENING', 'RECEIPT', 'SALE', 'RETURN', 'ADJUSTMENT'] as $t): ?>
                        <option value="<?= $t ?>" <?= $movType === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="from" value="<?= e($movFrom) ?>" class="form-input" style="width: auto; margin: 0; font-size: 0.85rem;" title="From date">
                <input type="date" name="to" value="<?= e($movTo) ?>" class="form-input" style="width: auto; margin: 0; font-size: 0.85rem;" title="To date">
                <button type="submit" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.45rem 0.9rem;">Filter</button>
                <a href="/app/products/<?= (int)$product['id'] ?>" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.45rem 0.9rem;">Reset</a>
            </form>
        </div>
        <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.6rem;">
            <?= $movTotal ?> movement<?= $movTotal === 1 ? '' : 's' ?>
            <?= $movTotal > 0 ? '· showing ' . (($movPage - 1) * 50 + 1) . '–' . min($movPage * 50, $movTotal) : '' ?>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Qty</th>
                        <th>Reference</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($movements)): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #94a3b8;">No movements match these filters.</td></tr>
                    <?php else: foreach ($movements as $m): ?>
                        <?php $qty = (float)$m['quantity']; ?>
                        <tr>
                            <td style="font-size: 0.8rem; color: #64748b; white-space: nowrap;"><?= date('d-M-Y H:i', strtotime($m['created_at'])) ?></td>
                            <td><span class="badge <?= $badgeClass($m['movement_type'], $qty) ?>"><?= e($m['movement_type']) ?></span></td>
                            <td style="font-weight: 700; color: <?= $qty < 0 ? '#ef4444' : '#059669' ?>;">
                                <?= $qty > 0 ? '+' : '' ?><?= $qty ?>
                            </td>
                            <td style="font-size: 0.8rem;">
                                <?php if (($m['reference_type'] ?? '') === 'invoice' && !empty($m['reference_id'])): ?>
                                    <a href="/app/invoices/<?= (int)$m['reference_id'] ?>" style="color:#0369a1;">Invoice #<?= (int)$m['reference_id'] ?></a>
                                <?php elseif (($m['reference_type'] ?? '') === 'purchase_bill' && !empty($m['reference_id'])): ?>
                                    <a href="/app/purchases" style="color:#0369a1;">Purchase #<?= (int)$m['reference_id'] ?></a>
                                <?php else: ?>
                                    <span style="color:#94a3b8;"><?= e($m['reference_type'] ?? '—') ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size: 0.78rem; color: #64748b;"><?= e($m['notes'] ?? '-') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($movTotalPages > 1): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.75rem; font-size: 0.85rem;">
                <span style="color: #64748b;">Page <?= $movPage ?> of <?= $movTotalPages ?></span>
                <span style="display: flex; gap: 0.5rem;">
                    <?php if ($movPage > 1): ?>
                        <a href="<?= e($movPageUrl($movPage - 1)) ?>" class="btn btn-secondary" style="font-size: 0.78rem; padding: 0.3rem 0.7rem;">← Prev</a>
                    <?php endif; ?>
                    <?php if ($movPage < $movTotalPages): ?>
                        <a href="<?= e($movPageUrl($movPage + 1)) ?>" class="btn btn-secondary" style="font-size: 0.78rem; padding: 0.3rem 0.7rem;">Next →</a>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Sales / Purchases / Returns -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <div class="card">
            <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.9rem;">Recent Sales</h3>
            <?php if (empty($sales)): ?>
                <p style="color: #94a3b8; font-size: 0.85rem; padding: 1rem 0;">No sales recorded for this product yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Invoice</th><th>Date</th><th>Qty</th><th>Amount</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sales as $s): ?>
                                <tr>
                                    <td><a href="/app/invoices/<?= (int)$s['id'] ?>" style="color:#0369a1;font-weight:600;"><?= e($s['invoice_number']) ?></a></td>
                                    <td style="font-size:0.8rem;color:#64748b;"><?= date('d-M-Y', strtotime($s['created_at'])) ?></td>
                                    <td><?= (float)$s['quantity'] ?></td>
                                    <td style="font-weight:600;"><?= money_format_inr($s['line_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="card">
            <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.9rem;">Recent Purchases</h3>
            <?php if (empty($purchases)): ?>
                <p style="color: #94a3b8; font-size: 0.85rem; padding: 1rem 0;">No purchases recorded for this product yet.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr><th>Bill</th><th>Date</th><th>Qty</th><th>Amount</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($purchases as $pb): ?>
                                <tr>
                                    <td style="font-weight:600;"><?= e($pb['bill_number']) ?><br><span style="font-size:0.72rem;color:#94a3b8;"><?= e($pb['supplier_name'] ?? '—') ?></span></td>
                                    <td style="font-size:0.8rem;color:#64748b;"><?= date('d-M-Y', strtotime($pb['bill_date'])) ?></td>
                                    <td><?= (float)$pb['quantity'] ?></td>
                                    <td style="font-weight:600;"><?= money_format_inr($pb['line_total']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom: 2rem;">
        <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.9rem;">Recent Returns</h3>
        <?php if (empty($returns)): ?>
            <p style="color: #94a3b8; font-size: 0.85rem; padding: 0.5rem 0 1rem;">No returns recorded for this product yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Return #</th><th>Date</th><th>Type</th><th>Qty</th><th>Refund</th><th>Invoice</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($returns as $r): ?>
                            <tr>
                                <td style="font-weight:600;"><?= e($r['return_number']) ?></td>
                                <td style="font-size:0.8rem;color:#64748b;"><?= date('d-M-Y', strtotime($r['created_at'])) ?></td>
                                <td><span class="badge <?= $r['transaction_type'] === 'exchange' ? 'badge-info' : 'badge-warning' ?>"><?= e(ucfirst($r['transaction_type'])) ?></span></td>
                                <td><?= (float)$r['quantity'] ?></td>
                                <td style="font-weight:600;"><?= money_format_inr($r['line_total']) ?></td>
                                <td>
                                    <?php if (!empty($r['invoice_id'])): ?>
                                        <a href="/app/invoices/<?= (int)$r['invoice_id'] ?>" style="color:#0369a1;">View invoice</a>
                                    <?php else: ?>—<?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function editFromDetail(button) {
    try {
        sessionStorage.setItem('openProductEdit', button.dataset.product);
        window.location.href = '/app/products?edit=1';
    } catch (e) {
        window.location.href = '/app/products';
    }
}
</script>

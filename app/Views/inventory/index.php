<?php
$sortUrl = function (string $col) use ($q, $stockStatus, $sort, $dir) {
    $params = [
        'sort' => $col,
        'dir' => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
    ];
    if ($q !== '') $params['q'] = $q;
    if ($stockStatus !== 'all') $params['stock_status'] = $stockStatus;
    return '/app/inventory?' . http_build_query($params);
};
$pageUrl = function (int $p) use ($q, $stockStatus, $sort, $dir) {
    $params = ['page' => $p, 'sort' => $sort, 'dir' => $dir];
    if ($q !== '') $params['q'] = $q;
    if ($stockStatus !== 'all') $params['stock_status'] = $stockStatus;
    return '/app/inventory?' . http_build_query($params);
};
$sortArrow = function (string $col) use ($sort, $dir) {
    if ($sort !== $col) return '';
    return $dir === 'asc' ? ' ▲' : ' ▼';
};
$movPageUrl = function (int $p) use ($q, $stockStatus, $sort, $dir, $movType, $movFrom, $movTo) {
    $params = [
        'mov_page' => $p, 'sort' => $sort, 'dir' => $dir,
        'mov_type' => $movType, 'mov_from' => $movFrom, 'mov_to' => $movTo,
    ];
    if ($q !== '') $params['q'] = $q;
    if ($stockStatus !== 'all') $params['stock_status'] = $stockStatus;
    return '/app/inventory?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
};
$badgeClass = function (string $movementType, float $qty): string {
    if ($movementType === 'SALE') return 'badge-info';
    if ($qty < 0) return 'badge-error';
    if ($movementType === 'ADJUSTMENT') return 'badge-warning';
    return 'badge-success';
};
?>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">📊 <?= e(__('inventory')) ?></h1>
            <p style="font-size: 0.85rem; color: #64748b;">Traceable stock movement history, valuations, and adjustments</p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="/app/inventory/export?<?= e(http_build_query(array_filter([
                'q' => $q, 'stock_status' => $stockStatus !== 'all' ? $stockStatus : '',
            ], fn($v) => $v !== ''))) ?>" class="btn btn-secondary">⬇ Export CSV</a>
            <button onclick="openAdjustModal()" class="btn btn-secondary">
                ⚡ Quick Stock Adjustment
            </button>
        </div>
    </div>

    <!-- Inventory Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Total Stock Items</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                <?= $totalSkus ?> SKUs
            </div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Inventory Cost Valuation</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #059669; margin-top: 0.25rem;">
                <?= money_format_inr($totalValuation) ?>
            </div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Low Stock Warnings</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: <?= $lowStockCount > 0 ? '#d97706' : '#059669' ?>; margin-top: 0.25rem;">
                <?= $lowStockCount ?> Items
            </div>
        </div>
    </div>

    <!-- Filter bar -->
    <form method="GET" action="/app/inventory" style="display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: flex-end; margin-bottom: 1rem;">
        <div style="flex: 1 1 220px;">
            <label class="form-label" style="font-size:0.75rem;">Search</label>
            <input type="search" name="q" value="<?= e($q) ?>" class="form-input" placeholder="Name, Odia name, SKU or barcode">
        </div>
        <div style="flex: 0 1 170px;">
            <label class="form-label" style="font-size:0.75rem;">Stock status</label>
            <select name="stock_status" class="form-input">
                <option value="all" <?= $stockStatus === 'all' ? 'selected' : '' ?>>All</option>
                <option value="in" <?= $stockStatus === 'in' ? 'selected' : '' ?>>In stock</option>
                <option value="low" <?= $stockStatus === 'low' ? 'selected' : '' ?>>Low stock</option>
                <option value="out" <?= $stockStatus === 'out' ? 'selected' : '' ?>>Out of stock</option>
            </select>
        </div>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <input type="hidden" name="dir" value="<?= e($dir) ?>">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="/app/inventory" class="btn btn-secondary">Reset</a>
    </form>

    <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.75rem;">
        <?= $total ?> product<?= $total === 1 ? '' : 's' ?>
        <?= $total > 0 ? '· showing ' . (($page - 1) * $perPage + 1) . '–' . min($page * $perPage, $total) : '' ?>
        · KPIs above cover all <?= $totalSkus ?> active SKUs
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <!-- Stock On Hand -->
        <div class="card">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Stock on Hand</h3>
            <div class="table-responsive" style="max-height: 480px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th><a href="<?= e($sortUrl('name_en')) ?>" style="color:inherit;text-decoration:none;">Item Name<?= e($sortArrow('name_en')) ?></a></th>
                            <th><a href="<?= e($sortUrl('cost_price')) ?>" style="color:inherit;text-decoration:none;">Cost<?= e($sortArrow('cost_price')) ?></a></th>
                            <th><a href="<?= e($sortUrl('current_stock')) ?>" style="color:inherit;text-decoration:none;">On Hand<?= e($sortArrow('current_stock')) ?></a></th>
                            <th>Valuation</th>
                            <th style="text-align:right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stockList)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #94a3b8;">No products found.</td></tr>
                        <?php else: foreach ($stockList as $item): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?= e($item['name_en']) ?></div>
                                    <div style="font-size: 0.75rem; color: #94a3b8;"><?= e($item['sku'] ?? '') ?></div>
                                </td>
                                <td><?= money_format_inr($item['cost_price']) ?></td>
                                <td>
                                    <?php
                                    $stock = (float)$item['current_stock'];
                                    $isOut = $stock <= 0;
                                    $isLow = !$isOut && (float)$item['current_stock'] <= (float)$item['reorder_level'];
                                    ?>
                                    <span class="stock-tag <?= ($isLow || $isOut) ? 'low' : '' ?>">
                                        <?= $stock ?> <?= e($item['unit_name']) ?>
                                        <?php if ($isOut): ?><span style="color:#ef4444;font-weight:700;">· OUT</span><?php endif; ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600;"><?= money_format_inr($item['stock_valuation']) ?></td>
                                <td style="text-align:right;">
                                    <button type="button" class="btn btn-secondary" style="font-size:0.72rem;padding:0.25rem 0.5rem;"
                                        onclick="openAdjustModal(<?= (int)$item['id'] ?>, <?= e(json_encode($item['name_en'])) ?>)">Adjust</button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem 0;border-top:1px solid #e2e8f0;font-size:0.85rem;">
                    <span style="color:#64748b;">Page <?= $page ?> of <?= $totalPages ?></span>
                    <span style="display:flex;gap:0.5rem;">
                        <?php if ($page > 1): ?>
                            <a href="<?= e($pageUrl($page - 1)) ?>" class="btn btn-secondary" style="font-size:0.78rem;padding:0.3rem 0.7rem;">← Prev</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= e($pageUrl($page + 1)) ?>" class="btn btn-secondary" style="font-size:0.78rem;padding:0.3rem 0.7rem;">Next →</a>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Stock Movements Ledger -->
        <div class="card">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 0.75rem;">Movement Ledger</h3>
            <form method="GET" action="/app/inventory" style="display: flex; gap: 0.45rem; flex-wrap: wrap; align-items: flex-end; margin-bottom: 0.75rem;">
                <?php if ($q !== ''): ?><input type="hidden" name="q" value="<?= e($q) ?>"><?php endif; ?>
                <?php if ($stockStatus !== 'all'): ?><input type="hidden" name="stock_status" value="<?= e($stockStatus) ?>"><?php endif; ?>
                <input type="hidden" name="sort" value="<?= e($sort) ?>">
                <input type="hidden" name="dir" value="<?= e($dir) ?>">
                <select name="mov_type" class="form-input" style="width: auto; margin: 0; font-size: 0.8rem;">
                    <option value="">All types</option>
                    <?php foreach (['OPENING', 'RECEIPT', 'SALE', 'RETURN', 'ADJUSTMENT'] as $t): ?>
                        <option value="<?= $t ?>" <?= $movType === $t ? 'selected' : '' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="date" name="mov_from" value="<?= e($movFrom) ?>" class="form-input" style="width: auto; margin: 0; font-size: 0.8rem;" title="From">
                <input type="date" name="mov_to" value="<?= e($movTo) ?>" class="form-input" style="width: auto; margin: 0; font-size: 0.8rem;" title="To">
                <button type="submit" class="btn btn-primary" style="font-size: 0.78rem; padding: 0.4rem 0.8rem;">Filter</button>
            </form>
            <div style="font-size: 0.78rem; color: #64748b; margin-bottom: 0.5rem;">
                <?= $movTotal ?> movement<?= $movTotal === 1 ? '' : 's' ?>
                <?= $movTotal > 0 ? '· showing ' . (($movPage - 1) * 50 + 1) . '–' . min($movPage * 50, $movTotal) : '' ?>
            </div>
            <div class="table-responsive" style="max-height: 430px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>Product</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($movements)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #94a3b8;">No movements recorded.</td></tr>
                        <?php else: ?>
                            <?php foreach ($movements as $m): ?>
                                <?php $qty = (float)$m['quantity']; ?>
                                <tr>
                                    <td style="font-size: 0.75rem; color: #64748b;"><?= date('d-M-Y H:i', strtotime($m['created_at'])) ?></td>
                                    <td style="font-weight: 600;"><?= e($m['product_name']) ?></td>
                                    <td>
                                        <span class="badge <?= $badgeClass($m['movement_type'], $qty) ?>">
                                            <?= e($m['movement_type']) ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 700; color: <?= $qty < 0 ? '#ef4444' : '#059669' ?>;">
                                        <?= $qty > 0 ? '+' : '' ?><?= $qty ?>
                                    </td>
                                    <td style="font-size: 0.75rem; color: #64748b;"><?= e($m['notes'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($movTotalPages > 1): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 0.6rem; font-size: 0.8rem;">
                    <span style="color: #64748b;">Page <?= $movPage ?> of <?= $movTotalPages ?></span>
                    <span style="display: flex; gap: 0.4rem;">
                        <?php if ($movPage > 1): ?>
                            <a href="<?= e($movPageUrl($movPage - 1)) ?>" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">← Prev</a>
                        <?php endif; ?>
                        <?php if ($movPage < $movTotalPages): ?>
                            <a href="<?= e($movPageUrl($movPage + 1)) ?>" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.25rem 0.6rem;">Next →</a>
                        <?php endif; ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Stock Adjustment Modal -->
<div id="adjustStockModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; padding: 2rem 1rem;">
    <div style="max-width: 480px; margin: 4rem auto; background: #fff; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; background: #0f172a; color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700;">Stock Adjustment</h3>
            <button onclick="document.getElementById('adjustStockModal').style.display='none'" style="background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form action="/app/inventory/adjust" method="POST" style="padding: 1.5rem;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Select Product *</label>
                <input type="text" id="adjustProductSearch" class="form-input" placeholder="Type to search products…" autocomplete="off" style="margin-bottom:0.5rem;">
                <select name="product_id" id="adjustProductSelect" class="form-input" size="6" required style="height:auto;">
                    <?php foreach ($stockList as $p): ?>
                        <option value="<?= (int)$p['id'] ?>" data-search="<?= e(strtolower($p['name_en'] . ' ' . ($p['sku'] ?? '') . ' ' . ($p['barcode'] ?? ''))) ?>">
                            <?= e($p['name_en']) ?> (Cur: <?= (float)$p['current_stock'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <div id="adjustSelectedInfo" style="font-size:0.78rem;color:#64748b;margin-top:0.35rem;"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Adjustment Mode</label>
                <div style="display:flex;gap:1rem;font-size:0.85rem;">
                    <label style="display:flex;align-items:center;gap:0.35rem;cursor:pointer;">
                        <input type="radio" name="mode" value="delta" checked onchange="toggleAdjustMode()"> Adjust by +/− qty
                    </label>
                    <label style="display:flex;align-items:center;gap:0.35rem;cursor:pointer;">
                        <input type="radio" name="mode" value="counted" onchange="toggleAdjustMode()"> Physical count
                    </label>
                </div>
            </div>
            <div class="form-group" id="deltaGroup">
                <label class="form-label">Quantity to Add/Subtract (+/-) *</label>
                <input type="number" step="0.001" name="quantity" id="adjustQtyInput" class="form-input" placeholder="e.g. +10 or -2">
            </div>
            <div class="form-group" id="countGroup" style="display:none;">
                <label class="form-label">Counted Quantity *</label>
                <input type="number" step="0.001" min="0" name="counted_quantity" id="adjustCountInput" class="form-input" placeholder="Physical count">
                <div id="adjustDeltaInfo" style="font-size:0.78rem;color:#64748b;margin-top:0.35rem;"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Adjustment Reason *</label>
                <input type="text" name="reason" class="form-input" placeholder="e.g. Physical inventory audit discrepancy" required>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('adjustStockModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Commit Adjustment</button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const map = {};
    <?php foreach ($stockList as $p): ?>
    map[<?= (int)$p['id'] ?>] = { stock: <?= (float)$p['current_stock'] ?>, name: <?= json_encode($p['name_en']) ?> };
    <?php endforeach; ?>
    window.__stockMap = map;
})();

function openAdjustModal(productId, productName) {
    const modal = document.getElementById('adjustStockModal');
    const select = document.getElementById('adjustProductSelect');
    const search = document.getElementById('adjustProductSearch');
    search.value = '';
    filterAdjustOptions('');
    if (productId) {
        select.value = String(productId);
        updateAdjustInfo();
    } else {
        select.selectedIndex = -1;
        document.getElementById('adjustSelectedInfo').textContent = '';
    }
    modal.style.display = 'block';
    search.focus();
}

function filterAdjustOptions(term) {
    const select = document.getElementById('adjustProductSelect');
    const t = term.toLowerCase();
    let firstVisible = null;
    Array.from(select.options).forEach(function (opt) {
        const match = !t || (opt.dataset.search || '').indexOf(t) !== -1;
        opt.hidden = !match;
        opt.disabled = !match;
        if (match && firstVisible === null) firstVisible = opt;
    });
    if (t && select.selectedOptions.length === 0 && firstVisible) {
        select.value = firstVisible.value;
        updateAdjustInfo();
    }
}

function updateAdjustInfo() {
    const select = document.getElementById('adjustProductSelect');
    const id = select.value;
    const info = document.getElementById('adjustSelectedInfo');
    if (id && window.__stockMap[id]) {
        info.textContent = 'Current stock: ' + window.__stockMap[id].stock + ' — ' + window.__stockMap[id].name;
    } else {
        info.textContent = '';
    }
    updateDeltaInfo();
}

function toggleAdjustMode() {
    const mode = document.querySelector('input[name="mode"]:checked').value;
    document.getElementById('deltaGroup').style.display = mode === 'delta' ? '' : 'none';
    document.getElementById('countGroup').style.display = mode === 'count' ? '' : 'none';
    updateDeltaInfo();
}

function updateDeltaInfo() {
    const mode = document.querySelector('input[name="mode"]:checked').value;
    if (mode !== 'count') return;
    const select = document.getElementById('adjustProductSelect');
    const current = window.__stockMap[select.value] ? window.__stockMap[select.value].stock : null;
    const counted = parseFloat(document.getElementById('adjustCountInput').value);
    const el = document.getElementById('adjustDeltaInfo');
    if (current === null || isNaN(counted)) {
        el.textContent = '';
        return;
    }
    const delta = counted - current;
    el.textContent = 'Will adjust ' + (delta >= 0 ? '+' : '') + delta + ' (from ' + current + ' to ' + counted + ')';
    document.getElementById('adjustQtyInput').value = delta !== 0 ? delta : '';
}

document.getElementById('adjustProductSearch').addEventListener('input', function () {
    filterAdjustOptions(this.value);
});
document.getElementById('adjustProductSelect').addEventListener('change', updateAdjustInfo);
document.getElementById('adjustCountInput').addEventListener('input', updateDeltaInfo);

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('[id$="Modal"]').forEach(function (m) { m.style.display = 'none'; });
    }
});
document.querySelectorAll('[id$="Modal"]').forEach(function (modal) {
    modal.addEventListener('mousedown', function (e) {
        if (e.target === modal) modal.style.display = 'none';
    });
});
</script>

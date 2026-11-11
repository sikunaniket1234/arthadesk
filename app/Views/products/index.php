<?php
$sortUrl = function (string $col) use ($q, $categoryFilter, $statusFilter, $filterLowStock, $sort, $dir) {
    $params = [
        'sort' => $col,
        'dir' => ($sort === $col && $dir === 'asc') ? 'desc' : 'asc',
    ];
    if ($q !== '') $params['q'] = $q;
    if ($categoryFilter !== '') $params['category'] = $categoryFilter;
    if ($statusFilter !== '') $params['status'] = $statusFilter;
    if ($filterLowStock) $params['filter'] = 'low_stock';
    return '/app/products?' . http_build_query($params);
};
$pageUrl = function (int $p) use ($q, $categoryFilter, $statusFilter, $filterLowStock, $sort, $dir) {
    $params = ['page' => $p, 'sort' => $sort, 'dir' => $dir];
    if ($q !== '') $params['q'] = $q;
    if ($categoryFilter !== '') $params['category'] = $categoryFilter;
    if ($statusFilter !== '') $params['status'] = $statusFilter;
    if ($filterLowStock) $params['filter'] = 'low_stock';
    return '/app/products?' . http_build_query($params);
};
$sortArrow = function (string $col) use ($sort, $dir) {
    if ($sort !== $col) return '';
    return $dir === 'asc' ? ' ▲' : ' ▼';
};
?>
<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">📦 <?= e(__('products')) ?></h1>
            <p style="font-size: 0.85rem; color: #64748b;">Manage bilingual product catalogue, units, and GST tax classifications</p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="/app/products/export?<?= e(http_build_query(array_filter([
                'q' => $q, 'category' => $categoryFilter, 'status' => $statusFilter,
                'filter' => $filterLowStock ? 'low_stock' : '',
            ], fn($v) => $v !== ''))) ?>" class="btn btn-secondary">⬇ Export CSV</a>
            <a href="/app/products/import" class="btn btn-secondary">⬆ Import CSV</a>
            <button onclick="document.getElementById('addProductModal').style.display='block'" class="btn btn-primary">
                + Add New Product
            </button>
        </div>
    </div>

    <!-- KPI cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Total Products</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;"><?= $totalAll ?> SKUs</div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Low Stock</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: <?= $lowStockCount > 0 ? '#d97706' : '#059669' ?>; margin-top: 0.25rem;"><?= $lowStockCount ?> items</div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Inventory Value (cost)</div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #059669; margin-top: 0.25rem;"><?= money_format_inr($totalValuation) ?></div>
        </div>
    </div>

    <!-- Filter bar -->
    <datalist id="categoryList">
        <?php foreach ($categories as $c): ?>
            <option value="<?= e($c) ?>"></option>
        <?php endforeach; ?>
    </datalist>
    <form method="GET" action="/app/products" style="display: flex; flex-wrap: wrap; gap: 0.6rem; align-items: flex-end; margin-bottom: 1rem;">
        <div style="flex: 1 1 220px;">
            <label class="form-label" style="font-size:0.75rem;">Search</label>
            <input type="search" name="q" value="<?= e($q) ?>" class="form-input" placeholder="Name, Odia name, SKU or barcode">
        </div>
        <div style="flex: 0 1 170px;">
            <label class="form-label" style="font-size:0.75rem;">Category</label>
            <select name="category" class="form-input">
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c) ?>" <?= $categoryFilter === $c ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="flex: 0 1 140px;">
            <label class="form-label" style="font-size:0.75rem;">Status</label>
            <select name="status" class="form-input">
                <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>All</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
        </div>
        <?php if ($filterLowStock): ?>
            <input type="hidden" name="filter" value="low_stock">
        <?php endif; ?>
        <input type="hidden" name="sort" value="<?= e($sort) ?>">
        <input type="hidden" name="dir" value="<?= e($dir) ?>">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="/app/products" class="btn btn-secondary">Reset</a>
        <a href="/app/products?filter=low_stock" class="btn btn-secondary" style="<?= $filterLowStock ? 'outline: 2px solid #d97706;' : '' ?>">
            ⚠ Low stock (<?= $lowStockCount ?>)
        </a>
    </form>

    <div style="font-size: 0.8rem; color: #64748b; margin-bottom: 0.75rem;">
        <?= $total ?> product<?= $total === 1 ? '' : 's' ?>
        <?= $total > 0 ? '· showing ' . (($page - 1) * $perPage + 1) . '–' . min($page * $perPage, $total) : '' ?>
    </div>

    <!-- Products Table + Bulk form -->
    <form id="bulkForm" method="POST" action="/app/products/bulk">
        <?= csrf_field() ?>
        <input type="hidden" name="action" id="bulkActionField" value="">
        <input type="hidden" name="value" id="bulkValueField" value="">

        <div id="bulkToolbar" style="display:none; align-items:center; gap:0.6rem; flex-wrap:wrap; background:#0f172a; color:#fff; padding:0.6rem 0.9rem; border-radius:10px 10px 0 0; font-size:0.85rem;">
            <strong id="bulkCount">0 selected</strong>
            <select id="bulkAction" class="form-input" style="width:auto; min-width:190px; margin:0;">
                <option value="set_sale_price">Set sale price (₹)</option>
                <option value="decrease_sale_price_pct">Decrease sale price by %</option>
                <option value="increase_sale_price_pct">Increase sale price by %</option>
                <option value="set_cost_price">Set cost price (₹)</option>
                <option value="set_category">Set category</option>
                <option value="set_reorder_level">Set reorder level</option>
                <option value="enable">Enable (activate)</option>
                <option value="disable">Disable (deactivate)</option>
            </select>
            <input type="text" id="bulkValue" class="form-input" style="width:120px; margin:0;" placeholder="Value">
            <button type="button" class="btn btn-primary" style="font-size:0.8rem;" onclick="applyBulk()">Apply</button>
            <button type="button" class="btn btn-secondary" style="font-size:0.8rem;" onclick="clearBulkSelection()">Clear</button>
            <span style="opacity:0.7;font-size:0.78rem;">Tip: click price / cost / category cells to edit inline</span>
        </div>

    <div class="card" id="bulkCard">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:28px;"><input type="checkbox" id="bulkSelectAll" onchange="toggleSelectAll(this)" title="Select all on this page"></th>
                        <th><a href="<?= e($sortUrl('sku')) ?>" style="color:inherit;text-decoration:none;">SKU / Barcode<?= e($sortArrow('sku')) ?></a></th>
                        <th><a href="<?= e($sortUrl('name_en')) ?>" style="color:inherit;text-decoration:none;">Product Name (English / ଓଡ଼ିଆ)<?= e($sortArrow('name_en')) ?></a></th>
                        <th><a href="<?= e($sortUrl('category')) ?>" style="color:inherit;text-decoration:none;">Category<?= e($sortArrow('category')) ?></a></th>
                        <th>Unit</th>
                        <th><a href="<?= e($sortUrl('sale_price')) ?>" style="color:inherit;text-decoration:none;">Sale Price<?= e($sortArrow('sale_price')) ?></a></th>
                        <th><a href="<?= e($sortUrl('cost_price')) ?>" style="color:inherit;text-decoration:none;">Cost Price<?= e($sortArrow('cost_price')) ?></a></th>
                        <th>GST Classification</th>
                        <th><a href="<?= e($sortUrl('current_stock')) ?>" style="color:inherit;text-decoration:none;">Stock on Hand<?= e($sortArrow('current_stock')) ?></a></th>
                        <th><a href="<?= e($sortUrl('is_active')) ?>" style="color:inherit;text-decoration:none;">Status<?= e($sortArrow('is_active')) ?></a></th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="11" style="text-align: center; padding: 2rem; color: #94a3b8;">No products found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><input type="checkbox" class="bulk-check" name="product_ids[]" value="<?= (int)$p['id'] ?>" onchange="updateBulkToolbar()"></td>
                                <td style="font-family: monospace; font-size: 0.8rem;">
                                    <?= e($p['sku'] ?? '-') ?><br>
                                    <span style="color: #94a3b8;"><?= e($p['barcode'] ?? '') ?></span>
                                </td>
                                <td>
                                    <a href="/app/products/<?= (int)$p['id'] ?>" style="font-weight: 600; color: #0f172a; text-decoration: none;"><?= e($p['name_en']) ?></a>
                                    <?php if (!empty($p['name_or'])): ?>
                                        <div style="font-size: 0.8rem; color: #0369a1; font-family: 'Noto Sans Oriya', sans-serif;">
                                            <?= e($p['name_or']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-edit" data-id="<?= (int)$p['id'] ?>" data-field="category" data-value="<?= e($p['category']) ?>" onclick="startCellEdit(this)" title="Click to edit category"><span class="badge badge-info"><?= e($p['category']) ?></span></td>
                                <td><?= e($p['unit_code'] ?? 'PCS') ?></td>
                                <td class="cell-edit" data-id="<?= (int)$p['id'] ?>" data-field="sale_price" data-value="<?= e($p['sale_price']) ?>" onclick="startCellEdit(this)" title="Click to edit sale price" style="font-weight: 700; color: #0f172a; cursor:pointer;"><?= money_format_inr($p['sale_price']) ?></td>
                                <td class="cell-edit" data-id="<?= (int)$p['id'] ?>" data-field="cost_price" data-value="<?= e($p['cost_price']) ?>" onclick="startCellEdit(this)" title="Click to edit cost price" style="color: #64748b; cursor:pointer;"><?= money_format_inr($p['cost_price']) ?></td>
                                <td>
                                    <span class="badge badge-warning"><?= e($p['tax_cat_name'] ?? 'None') ?></span>
                                </td>
                                <td>
                                    <?php $isLow = ((float)$p['current_stock'] <= (float)$p['reorder_level']); ?>
                                    <a href="/app/inventory?q=<?= urlencode($p['sku'] ?? $p['name_en']) ?>" style="text-decoration:none;color:inherit;" title="Adjust stock on Inventory screen">
                                        <span class="stock-tag <?= $isLow ? 'low' : '' ?>">
                                            <?= (float)$p['current_stock'] ?> <?= e($p['unit_code'] ?? '') ?>
                                        </span>
                                    </a>
                                </td>
                                <td>
                                    <span class="badge <?= (int)$p['is_active'] === 1 ? 'badge-success' : 'badge-error' ?>">
                                        <?= (int)$p['is_active'] === 1 ? 'ACTIVE' : 'INACTIVE' ?>
                                    </span>
                                </td>
                                <td style="text-align:right;white-space:nowrap;">
                                    <button type="button" class="btn btn-secondary" style="font-size:0.72rem;padding:0.25rem 0.5rem;"
                                        data-product="<?= e(json_encode([
                                            'id' => (int)$p['id'],
                                            'name_en' => $p['name_en'],
                                            'name_or' => $p['name_or'],
                                            'sku' => $p['sku'],
                                            'barcode' => $p['barcode'],
                                            'category' => $p['category'],
                                            'unit_id' => (int)$p['unit_id'],
                                            'tax_category_id' => (int)$p['tax_category_id'],
                                            'supplier_id' => $p['supplier_id'] ? (int)$p['supplier_id'] : '',
                                            'sale_price' => $p['sale_price'],
                                            'cost_price' => $p['cost_price'],
                                            'price_includes_tax' => (int)$p['price_includes_tax'],
                                            'reorder_level' => $p['reorder_level'],
                                        ])) ?>"
                                        onclick="openEditProduct(this)">Edit</button>
                                    <form action="/app/products/<?= (int)$p['id'] ?>/toggle" method="POST" style="display:inline;" onsubmit="return confirm('<?= (int)$p['is_active'] === 1 ? 'Disable' : 'Enable' ?> this product?');">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-secondary" style="font-size:0.72rem;padding:0.25rem 0.5rem;">
                                            <?= (int)$p['is_active'] === 1 ? 'Disable' : 'Enable' ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php if ($totalPages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.75rem 1rem;border-top:1px solid #e2e8f0;font-size:0.85rem;">
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
    </form>
</div>

<!-- Add Product Modal Dialog -->
<div id="addProductModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; overflow-y: auto; padding: 2rem 1rem;">
    <div style="max-width: 600px; margin: 2rem auto; background: #fff; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; background: #0f172a; color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700;">Add Product to Catalogue</h3>
            <button onclick="document.getElementById('addProductModal').style.display='none'" style="background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>

        <form action="/app/products" method="POST" style="padding: 1.5rem;">
            <?= csrf_field() ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">English Name *</label>
                    <input type="text" name="name_en" class="form-input" placeholder="e.g. Tata Salt 1kg" required maxlength="150">
                </div>
                <div>
                    <label class="form-label">Odia Name (ଓଡ଼ିଆ ନାମ)</label>
                    <input type="text" name="name_or" class="form-input" placeholder="e.g. ଟାଟା ଲୁଣ ୧ କି.ଗ୍ରା.">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" class="form-input" placeholder="e.g. SALT-TAT-01">
                </div>
                <div>
                    <label class="form-label">Barcode</label>
                    <input type="text" name="barcode" class="form-input" placeholder="Scan or enter barcode">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Category</label>
                    <input type="text" name="category" class="form-input" value="General" list="categoryList" autocomplete="off">
                </div>
                <div>
                    <label class="form-label">Unit of Measure</label>
                    <select name="unit_id" class="form-input">
                        <option value="">— None —</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= e($u['id']) ?>"><?= e($u['name_en']) ?> (<?= e($u['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">GST Tax Category</label>
                    <select name="tax_category_id" class="form-input">
                        <option value="">— None —</option>
                        <?php foreach ($taxCategories as $tc): ?>
                            <option value="<?= e($tc['id']) ?>"><?= e($tc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Sale Price (₹) *</label>
                    <input type="number" step="0.01" min="0" name="sale_price" class="form-input" placeholder="0.00" required>
                </div>
                <div>
                    <label class="form-label">Cost Price (₹)</label>
                    <input type="number" step="0.01" min="0" name="cost_price" class="form-input" placeholder="0.00">
                </div>
                <div>
                    <label class="form-label">Initial Stock</label>
                    <input type="number" step="0.001" min="0" name="initial_stock" class="form-input" value="0">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Preferred Supplier</label>
                    <select name="supplier_id" class="form-input">
                        <option value="">— None —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Reorder Level</label>
                    <input type="number" step="0.001" min="0" name="reorder_level" class="form-input" value="5">
                </div>
            </div>

            <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem;font-size:0.85rem;">
                <input type="checkbox" name="price_includes_tax" value="1">
                Sale price includes GST
            </label>

            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('addProductModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Product Modal -->
<div id="editProductModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; overflow-y: auto; padding: 2rem 1rem;">
    <div style="max-width: 640px; margin: 2rem auto; background: #fff; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; background: #0f172a; color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700;">Edit Product</h3>
            <button type="button" onclick="document.getElementById('editProductModal').style.display='none'" style="background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form id="editProductForm" action="/app/products/0/update" method="POST" style="padding: 1.5rem;">
            <?= csrf_field() ?>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">English Name *</label>
                    <input type="text" name="name_en" id="ep_name_en" class="form-input" required maxlength="150">
                </div>
                <div>
                    <label class="form-label">Odia Name</label>
                    <input type="text" name="name_or" id="ep_name_or" class="form-input">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">SKU</label>
                    <input type="text" name="sku" id="ep_sku" class="form-input" maxlength="50">
                </div>
                <div>
                    <label class="form-label">Barcode</label>
                    <input type="text" name="barcode" id="ep_barcode" class="form-input" maxlength="50">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Category</label>
                    <input type="text" name="category" id="ep_category" class="form-input" maxlength="50" list="categoryList" autocomplete="off">
                </div>
                <div>
                    <label class="form-label">Unit</label>
                    <select name="unit_id" id="ep_unit_id" class="form-input">
                        <option value="">— None —</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= e($u['name_en']) ?> (<?= e($u['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">GST Tax Category</label>
                    <select name="tax_category_id" id="ep_tax_category_id" class="form-input">
                        <option value="">— None —</option>
                        <?php foreach ($taxCategories as $tc): ?>
                            <option value="<?= (int)$tc['id'] ?>"><?= e($tc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Preferred Supplier</label>
                    <select name="supplier_id" id="ep_supplier_id" class="form-input">
                        <option value="">— None —</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Reorder Level</label>
                    <input type="number" step="0.001" min="0" name="reorder_level" id="ep_reorder_level" class="form-input">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label class="form-label">Sale Price (₹) *</label>
                    <input type="number" step="0.01" min="0" name="sale_price" id="ep_sale_price" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Cost Price (₹)</label>
                    <input type="number" step="0.01" min="0" name="cost_price" id="ep_cost_price" class="form-input">
                </div>
            </div>
            <label style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem;font-size:0.85rem;">
                <input type="checkbox" name="price_includes_tax" id="ep_price_includes_tax" value="1">
                Sale price includes GST
            </label>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('editProductModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Product</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditProduct(button) {
    const p = JSON.parse(button.dataset.product);
    document.getElementById('editProductForm').action = '/app/products/' + p.id + '/update';
    document.getElementById('ep_name_en').value = p.name_en || '';
    document.getElementById('ep_name_or').value = p.name_or || '';
    document.getElementById('ep_sku').value = p.sku || '';
    document.getElementById('ep_barcode').value = p.barcode || '';
    document.getElementById('ep_category').value = p.category || '';
    document.getElementById('ep_unit_id').value = p.unit_id ? String(p.unit_id) : '';
    document.getElementById('ep_tax_category_id').value = p.tax_category_id ? String(p.tax_category_id) : '';
    document.getElementById('ep_supplier_id').value = p.supplier_id ? String(p.supplier_id) : '';
    document.getElementById('ep_sale_price').value = p.sale_price;
    document.getElementById('ep_cost_price').value = p.cost_price;
    document.getElementById('ep_reorder_level').value = p.reorder_level;
    document.getElementById('ep_price_includes_tax').checked = Number(p.price_includes_tax) === 1;
    document.getElementById('editProductModal').style.display = 'block';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        document.querySelectorAll('[id$="Modal"]').forEach(function (m) { m.style.display = 'none'; });
        cancelCellEdit();
    }
});
document.querySelectorAll('[id$="Modal"]').forEach(function (modal) {
    modal.addEventListener('mousedown', function (e) {
        if (e.target === modal) modal.style.display = 'none';
    });
});

/* ===== Bulk actions ===== */
const NO_VALUE_ACTIONS = ['enable', 'disable'];

function selectedIds() {
    return Array.from(document.querySelectorAll('.bulk-check:checked')).map(function (c) { return c.value; });
}

function toggleSelectAll(master) {
    document.querySelectorAll('.bulk-check').forEach(function (c) { c.checked = master.checked; });
    updateBulkToolbar();
}

function clearBulkSelection() {
    document.querySelectorAll('.bulk-check').forEach(function (c) { c.checked = false; });
    const all = document.getElementById('bulkSelectAll');
    if (all) all.checked = false;
    updateBulkToolbar();
}

function updateBulkToolbar() {
    const n = selectedIds().length;
    const toolbar = document.getElementById('bulkToolbar');
    toolbar.style.display = n > 0 ? 'flex' : 'none';
    document.getElementById('bulkCount').textContent = n + ' selected';
    const card = document.getElementById('bulkCard');
    if (card) {
        card.style.borderTopLeftRadius = n > 0 ? '0' : '12px';
        card.style.borderTopRightRadius = n > 0 ? '0' : '12px';
        card.style.marginTop = n > 0 ? '0' : '';
    }
    toggleBulkValue();
}

function toggleBulkValue() {
    const action = document.getElementById('bulkAction').value;
    const needsValue = NO_VALUE_ACTIONS.indexOf(action) === -1;
    document.getElementById('bulkValue').style.display = needsValue ? '' : 'none';
}

document.getElementById('bulkAction').addEventListener('change', toggleBulkValue);

function applyBulk() {
    const ids = selectedIds();
    if (ids.length === 0) {
        alert('Select at least one product.');
        return;
    }
    const action = document.getElementById('bulkAction').value;
    const value = document.getElementById('bulkValue').value.trim();
    if (NO_VALUE_ACTIONS.indexOf(action) === -1 && value === '') {
        alert('Enter a value for this action.');
        return;
    }
    const label = document.getElementById('bulkAction').selectedOptions[0].textContent;
    if (!confirm('Apply "' + label + (NO_VALUE_ACTIONS.indexOf(action) === -1 ? ' = ' + value : '') + '" to ' + ids.length + ' product(s)?')) {
        return;
    }
    document.getElementById('bulkActionField').value = action;
    document.getElementById('bulkValueField').value = value;
    document.getElementById('bulkForm').submit();
}

/* ===== Inline cell editing ===== */
let activeCell = null;
let activeCellHtml = '';

function cancelCellEdit() {
    if (!activeCell) return;
    activeCell.innerHTML = activeCellHtml;
    activeCell = null;
}

function startCellEdit(td) {
    if (activeCell === td) return;
    cancelCellEdit();
    const field = td.dataset.field;
    const isNumeric = field !== 'category';
    activeCell = td;
    activeCellHtml = td.innerHTML;
    td.innerHTML = '';
    const input = document.createElement('input');
    input.type = isNumeric ? 'number' : 'text';
    if (isNumeric) {
        input.step = field === 'reorder_level' ? '0.001' : '0.01';
        input.min = '0';
    } else {
        input.maxLength = 50;
    }
    input.value = td.dataset.value || '';
    input.style.width = '100%';
    input.style.padding = '0.2rem 0.4rem';
    input.style.border = '1px solid #0ea5e9';
    input.style.borderRadius = '6px';
    input.style.font = 'inherit';
    td.appendChild(input);
    input.focus();
    input.select();

    let finished = false;
    const finish = function (save) {
        if (finished) return;
        finished = true;
        if (!save) {
            td.innerHTML = activeCellHtml;
            activeCell = null;
            return;
        }
        saveCell(td, input);
    };
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); finish(true); }
        else if (e.key === 'Escape') { e.preventDefault(); finish(false); }
    });
    input.addEventListener('blur', function () { finish(true); });
}

function saveCell(td, input) {
    const field = td.dataset.field;
    const value = input.value;
    const originalHtml = activeCellHtml;
    td.innerHTML = '<span style="color:#94a3b8;font-size:0.78rem;">…</span>';
    activeCell = null;

    fetch('/app/products/' + td.dataset.id + '/quick-edit', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ field: field, value: value })
    }).then(function (res) {
        return res.json().then(function (data) { return { status: res.status, data: data }; });
    }).then(function (r) {
        if (r.data && r.data.ok) {
            td.dataset.value = r.data.value;
            if (field === 'category') {
                td.innerHTML = '<span class="badge badge-info">' + escapeHtml(r.data.display) + '</span>';
            } else {
                td.innerHTML = escapeHtml(r.data.display);
            }
        } else {
            td.innerHTML = originalHtml;
            alert((r.data && r.data.error) || 'Could not save change.');
        }
    }).catch(function () {
        td.innerHTML = originalHtml;
        alert('Network error — change not saved.');
    });
}

function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
}

/* Open edit modal when arriving from a product detail page (?edit=1) */
(function () {
    const params = new URLSearchParams(window.location.search);
    if (params.get('edit')) {
        try {
            const raw = sessionStorage.getItem('openProductEdit');
            sessionStorage.removeItem('openProductEdit');
            if (raw) {
                const btn = { dataset: { product: raw } };
                openEditProduct(btn);
                params.delete('edit');
                const qs = params.toString();
                window.history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
            }
        } catch (e) { /* ignore */ }
    }
})();
</script>

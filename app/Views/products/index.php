<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">📦 <?= e(__('products')) ?></h1>
            <p style="font-size: 0.85rem; color: #64748b;">Manage bilingual product catalogue, units, and GST tax classifications</p>
        </div>
        <button onclick="document.getElementById('addProductModal').style.display='block'" class="btn btn-primary">
            + Add New Product
        </button>
    </div>

    <!-- Products Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>SKU / Barcode</th>
                        <th>Product Name (English / ଓଡ଼ିଆ)</th>
                        <th>Category</th>
                        <th>Unit</th>
                        <th>Sale Price</th>
                        <th>Cost Price</th>
                        <th>GST Classification</th>
                        <th>Stock on Hand</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                        <tr><td colspan="10" style="text-align: center; padding: 2rem; color: #94a3b8;">No products found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td style="font-family: monospace; font-size: 0.8rem;">
                                    <?= e($p['sku'] ?? '-') ?><br>
                                    <span style="color: #94a3b8;"><?= e($p['barcode'] ?? '') ?></span>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?= e($p['name_en']) ?></div>
                                    <?php if (!empty($p['name_or'])): ?>
                                        <div style="font-size: 0.8rem; color: #0369a1; font-family: 'Noto Sans Oriya', sans-serif;">
                                            <?= e($p['name_or']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge badge-info"><?= e($p['category']) ?></span></td>
                                <td><?= e($p['unit_code'] ?? 'PCS') ?></td>
                                <td style="font-weight: 700; color: #0f172a;"><?= money_format_inr($p['sale_price']) ?></td>
                                <td style="color: #64748b;"><?= money_format_inr($p['cost_price']) ?></td>
                                <td>
                                    <span class="badge badge-warning"><?= e($p['tax_cat_name'] ?? 'None') ?></span>
                                </td>
                                <td>
                                    <?php $isLow = ((float)$p['current_stock'] <= (float)$p['reorder_level']); ?>
                                    <span class="stock-tag <?= $isLow ? 'low' : '' ?>">
                                        <?= (float)$p['current_stock'] ?> <?= e($p['unit_code'] ?? '') ?>
                                    </span>
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
                                    <form action="/app/products/<?= (int)$p['id'] ?>/toggle" method="POST" style="display:inline;">
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
    </div>
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
                    <input type="text" name="name_en" class="form-input" placeholder="e.g. Tata Salt 1kg" required>
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
                    <input type="text" name="category" class="form-input" value="Grocery">
                </div>
                <div>
                    <label class="form-label">Unit of Measure</label>
                    <select name="unit_id" class="form-input">
                        <?php foreach ($units as $u): ?>
                            <option value="<?= e($u['id']) ?>"><?= e($u['name_en']) ?> (<?= e($u['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">GST Tax Category</label>
                    <select name="tax_category_id" class="form-input">
                        <?php foreach ($taxCategories as $tc): ?>
                            <option value="<?= e($tc['id']) ?>"><?= e($tc['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label class="form-label">Sale Price (₹) *</label>
                    <input type="number" step="0.01" name="sale_price" class="form-input" placeholder="0.00" required>
                </div>
                <div>
                    <label class="form-label">Cost Price (₹)</label>
                    <input type="number" step="0.01" name="cost_price" class="form-input" placeholder="0.00">
                </div>
                <div>
                    <label class="form-label">Initial Stock</label>
                    <input type="number" step="0.001" name="initial_stock" class="form-input" value="50">
                </div>
            </div>

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
        <form id="editProductForm" method="POST" style="padding: 1.5rem;">
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
                    <input type="text" name="category" id="ep_category" class="form-input" maxlength="50">
                </div>
                <div>
                    <label class="form-label">Unit</label>
                    <select name="unit_id" id="ep_unit_id" class="form-input">
                        <?php foreach ($units as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= e($u['name_en']) ?> (<?= e($u['code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">GST Tax Category</label>
                    <select name="tax_category_id" id="ep_tax_category_id" class="form-input">
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
    document.getElementById('ep_unit_id').value = String(p.unit_id || '');
    document.getElementById('ep_tax_category_id').value = String(p.tax_category_id || '');
    document.getElementById('ep_supplier_id').value = String(p.supplier_id || '');
    document.getElementById('ep_sale_price').value = p.sale_price;
    document.getElementById('ep_cost_price').value = p.cost_price;
    document.getElementById('ep_reorder_level').value = p.reorder_level;
    document.getElementById('ep_price_includes_tax').checked = Number(p.price_includes_tax) === 1;
    document.getElementById('editProductModal').style.display = 'block';
}
</script>

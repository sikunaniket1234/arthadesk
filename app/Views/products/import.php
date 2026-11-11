<div class="container" style="max-width: 960px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">⬆ Import Products (CSV)</h1>
            <p style="font-size: 0.85rem; color: #64748b;">Upload a CSV, preview the changes, then confirm — nothing is written until you commit.</p>
        </div>
        <a href="/app/products" class="btn btn-secondary">← Back to Products</a>
    </div>

    <?php if (empty($preview)): ?>
        <!-- ========== STEP 1: UPLOAD ========== -->
        <div class="card" style="margin-bottom: 1.25rem;">
            <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem;">1. Choose your CSV file</h3>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;">
                Required columns (in this exact order):
                <code style="background:#f1f5f9;padding:0.15rem 0.4rem;border-radius:4px;">sku,name,category,unit,stock,cost_price,sale_price</code>
                — <a href="/app/products/import?template=1" style="color:#0369a1;">download the template CSV</a>.
            </p>
            <form action="/app/products/import" method="POST" enctype="multipart/form-data" style="display: flex; gap: 0.75rem; align-items: flex-end; flex-wrap: wrap;">
                <?= csrf_field() ?>
                <input type="hidden" name="step" value="preview">
                <div style="flex: 1 1 320px;">
                    <label class="form-label">CSV file (max 2 MB)</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv" class="form-input" required>
                </div>
                <button type="submit" class="btn btn-primary">Upload &amp; Preview</button>
            </form>
        </div>

        <div class="card">
            <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem;">How it works</h3>
            <ol style="font-size: 0.88rem; color: #475569; padding-left: 1.25rem; line-height: 1.8;">
                <li><strong>Upload</strong> — your file is parsed and validated. No data is changed yet.</li>
                <li><strong>Preview</strong> — see exactly which rows will create new products and which will update existing ones.</li>
                <li><strong>Commit</strong> — one transaction applies everything; a summary is shown after.</li>
            </ol>
            <p style="font-size: 0.85rem; color: #64748b; margin-top: 0.75rem;">
                <strong>Notes:</strong> Existing SKUs get their name/category/prices updated — <strong>stock quantities are never changed</strong> by imports
                (stock moves through purchases, sales and adjustments). Unknown unit codes are created automatically as global units.
            </p>
        </div>

    <?php else: ?>
        <!-- ========== STEP 2: PREVIEW / CONFIRM ========== -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
            <div class="card" style="margin-bottom: 0;">
                <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">File</div>
                <div style="font-size: 1rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem; word-break: break-all;">
                    <?= e($preview['filename']) ?>
                </div>
            </div>
            <div class="card" style="margin-bottom: 0;">
                <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Will create</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: #059669; margin-top: 0.25rem;"><?= (int)$preview['creates'] ?></div>
            </div>
            <div class="card" style="margin-bottom: 0;">
                <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Will update</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: #0369a1; margin-top: 0.25rem;"><?= (int)$preview['updates'] ?></div>
            </div>
            <div class="card" style="margin-bottom: 0;">
                <div style="font-size: 0.78rem; color: #64748b; font-weight: 600;">Errors</div>
                <div style="font-size: 1.6rem; font-weight: 800; color: <?= count($preview['errors']) > 0 ? '#ef4444' : '#059669' ?>; margin-top: 0.25rem;">
                    <?= count($preview['errors']) ?>
                </div>
            </div>
        </div>

        <?php if (!empty($preview['units_to_create'])): ?>
            <div class="alert alert-success" style="margin-bottom: 1rem;">
                New unit codes will be created: <strong><?= e(implode(', ', $preview['units_to_create'])) ?></strong>
            </div>
        <?php endif; ?>

        <?php if (!empty($preview['errors'])): ?>
            <div class="card" style="border-left: 4px solid #ef4444; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1rem; font-weight: 700; color: #ef4444; margin-bottom: 0.75rem;">
                    Errors (<?= count($preview['errors']) ?>) — these rows will be skipped
                </h3>
                <ul style="font-size: 0.85rem; color: #64748b; padding-left: 1.25rem; max-height: 220px; overflow-y: auto; line-height: 1.7;">
                    <?php foreach ($preview['errors'] as $err): ?>
                        <li><?= e($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($preview['rows'])): ?>
            <div class="card" style="margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.05rem; font-weight: 700; margin-bottom: 0.75rem;">
                    Preview — first <?= min(50, count($preview['rows'])) ?> of <?= count($preview['rows']) ?> valid row<?= count($preview['rows']) === 1 ? '' : 's' ?>
                </h3>
                <div class="table-responsive" style="max-height: 420px;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Line</th>
                                <th>Action</th>
                                <th>SKU</th>
                                <th>Name</th>
                                <th>Category</th>
                                <th>Unit</th>
                                <th>Stock</th>
                                <th>Cost</th>
                                <th>Sale</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($preview['rows'], 0, 50) as $row): ?>
                                <tr>
                                    <td style="color:#94a3b8;"><?= (int)$row['line'] ?></td>
                                    <td>
                                        <span class="badge <?= $row['action'] === 'create' ? 'badge-success' : 'badge-info' ?>">
                                            <?= $row['action'] === 'create' ? 'CREATE' : 'UPDATE' ?>
                                        </span>
                                    </td>
                                    <td style="font-family:monospace;font-size:0.8rem;"><?= e($row['sku']) ?></td>
                                    <td><?= e($row['name']) ?></td>
                                    <td><?= e($row['category']) ?></td>
                                    <td><?= e($row['unit']) ?></td>
                                    <td><?= $row['stock'] ?></td>
                                    <td><?= money_format_inr($row['cost']) ?></td>
                                    <td style="font-weight:600;"><?= money_format_inr($row['sale']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p style="font-size: 0.8rem; color: #64748b; margin-top: 0.75rem;">
                    ⚠ Updates change <strong>name, category, cost price and sale price only</strong> — existing stock quantities are preserved.
                </p>
            </div>
        <?php endif; ?>

        <form action="/app/products/import" method="POST" style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
            <?= csrf_field() ?>
            <input type="hidden" name="step" value="commit">
            <input type="hidden" name="hash" value="<?= e($preview['hash']) ?>">
            <?php if (!empty($preview['errors'])): ?>
                <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.85rem;">
                    <input type="checkbox" name="valid_only" value="1">
                    Import valid rows only (skip the <?= count($preview['errors']) ?> error rows)
                </label>
            <?php endif; ?>
            <button type="submit" class="btn btn-primary"
                <?= empty($preview['rows']) ? 'disabled' : '' ?>
                onclick="return confirm('Import <?= (int)$preview['creates'] ?> new and <?= (int)$preview['updates'] ?> updated product(s)?');">
                ✓ Confirm Import
            </button>
            <a href="/app/products/import?cancel=1" class="btn btn-secondary">← Start over</a>
        </form>
    <?php endif; ?>
</div>

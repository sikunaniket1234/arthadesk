<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">📊 <?= e(__('inventory')) ?></h1>
            <p style="font-size: 0.85rem; color: #64748b;">Traceable stock movement history, valuations, and adjustments</p>
        </div>
        <button onclick="document.getElementById('adjustStockModal').style.display='block'" class="btn btn-secondary">
            ⚡ Quick Stock Adjustment
        </button>
    </div>

    <!-- Inventory Metric Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Total Stock Items</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                <?= count($stockList) ?> SKUs
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

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <!-- Stock On Hand -->
        <div class="card">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Stock on Hand</h3>
            <div class="table-responsive" style="max-height: 480px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Item Name</th>
                            <th>Cost</th>
                            <th>On Hand</th>
                            <th>Valuation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stockList as $item): ?>
                            <tr>
                                <td>
                                    <div style="font-weight: 600;"><?= e($item['name_en']) ?></div>
                                    <div style="font-size: 0.75rem; color: #94a3b8;"><?= e($item['sku'] ?? '') ?></div>
                                </td>
                                <td><?= money_format_inr($item['cost_price']) ?></td>
                                <td>
                                    <?php $isLow = ((float)$item['current_stock'] <= (float)$item['reorder_level']); ?>
                                    <span class="stock-tag <?= $isLow ? 'low' : '' ?>">
                                        <?= (float)$item['current_stock'] ?> <?= e($item['unit_name']) ?>
                                    </span>
                                </td>
                                <td style="font-weight: 600;"><?= money_format_inr($item['stock_valuation']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Stock Movements Ledger -->
        <div class="card">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Recent Movement Ledger</h3>
            <div class="table-responsive" style="max-height: 480px;">
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
                                <tr>
                                    <td style="font-size: 0.75rem; color: #64748b;"><?= date('d-M H:i', strtotime($m['created_at'])) ?></td>
                                    <td style="font-weight: 600;"><?= e($m['product_name']) ?></td>
                                    <td>
                                        <span class="badge <?= $m['movement_type'] === 'SALE' ? 'badge-info' : 'badge-success' ?>">
                                            <?= e($m['movement_type']) ?>
                                        </span>
                                    </td>
                                    <td style="font-weight: 700; color: <?= (float)$m['quantity'] < 0 ? '#ef4444' : '#059669' ?>;">
                                        <?= (float)$m['quantity'] > 0 ? '+' : '' ?><?= (float)$m['quantity'] ?>
                                    </td>
                                    <td style="font-size: 0.75rem; color: #64748b;"><?= e($m['notes'] ?? '-') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
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
                <select name="product_id" class="form-input" required>
                    <?php foreach ($stockList as $p): ?>
                        <option value="<?= e($p['id']) ?>"><?= e($p['name_en']) ?> (Cur: <?= (float)$p['current_stock'] ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Quantity to Add/Subtract (+/-) *</label>
                <input type="number" step="0.001" name="quantity" class="form-input" placeholder="e.g. +10 or -2" required>
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

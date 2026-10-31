<?php
// Exchange View: Search original invoice, pick returned items, select replacement items
?>
<div class="container" style="max-width:900px;">
    <div style="margin-bottom:1.5rem;">
        <a href="/app/returns" style="color:#64748b;font-size:0.82rem;text-decoration:none;">← Back to Returns</a>
        <h1 style="font-size:1.5rem;font-weight:800;color:#0f172a;margin-top:0.3rem;">🔄 Process Exchange</h1>
        <p style="font-size:0.85rem;color:#64748b;">Customer returns item(s) and picks replacement — stock is restocked and re-deducted accordingly.</p>
    </div>

    <?php if (isset($invoice)): ?>
    <!-- Step 2: Invoice found — select returned and exchange items -->
    <form method="POST" action="/app/returns/exchange" id="exchangeForm">
        <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="invoice_id" value="<?= (int)$invoice['id'] ?>">

        <div class="card" style="padding:1.2rem;margin-bottom:1rem;">
            <h3 style="font-size:0.95rem;font-weight:700;color:#1e293b;margin-bottom:0.5rem;">📄 Invoice: <?= e($invoice['invoice_number']) ?></h3>
            <div style="font-size:0.82rem;color:#64748b;">Customer: <?= e($invoice['customer_name'] ?? $invoice['walkin_name'] ?? 'Walk-in') ?> &nbsp;|&nbsp; Date: <?= date('d M Y', strtotime($invoice['created_at'])) ?> &nbsp;|&nbsp; Total: <?= money_format_inr($invoice['total_amount']) ?></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
            <!-- Items being returned -->
            <div class="card" style="padding:1.2rem;">
                <h3 style="font-size:0.9rem;font-weight:700;color:#ef4444;margin-bottom:0.75rem;">↩️ Items Returned by Customer</h3>
                <?php foreach ($invoiceItems as $item): ?>
                <div style="padding:0.6rem;border:1.5px solid #e2e8f0;border-radius:8px;margin-bottom:0.5rem;">
                    <div style="font-size:0.85rem;font-weight:600;"><?= e($item['item_name_en']) ?></div>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-top:0.3rem;">
                        <label style="font-size:0.75rem;color:#64748b;">Qty to return (max <?= $item['quantity'] ?>):</label>
                        <input type="number" name="return_items[<?= $item['id'] ?>][qty]"
                               min="0" max="<?= $item['quantity'] ?>" step="1" value="0"
                               class="form-control" style="width:80px;"
                               onchange="calcDiff()">
                        <input type="hidden" name="return_items[<?= $item['id'] ?>][price]" value="<?= $item['unit_price'] ?>">
                        <input type="hidden" name="return_items[<?= $item['id'] ?>][product_id]" value="<?= $item['product_id'] ?>">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Replacement products from catalog -->
            <div class="card" style="padding:1.2rem;">
                <h3 style="font-size:0.9rem;font-weight:700;color:#059669;margin-bottom:0.75rem;">🛒 Replacement Products</h3>
                <input type="text" id="exchSearch" class="form-control" placeholder="🔍 Search product to exchange for..." oninput="filterExchange(this.value)" style="margin-bottom:0.75rem;">
                <div id="exchProductList" style="max-height:280px;overflow-y:auto;display:flex;flex-direction:column;gap:0.4rem;">
                    <?php foreach ($allProducts as $p): ?>
                    <div class="exch-item" data-name="<?= strtolower(e($p['name_en'])) ?>" style="display:flex;justify-content:space-between;align-items:center;padding:0.5rem;border:1.5px solid #e2e8f0;border-radius:6px;">
                        <div>
                            <div style="font-size:0.82rem;font-weight:600;"><?= e($p['name_en']) ?></div>
                            <div style="font-size:0.72rem;color:#64748b;">₹<?= number_format((float)$p['sale_price'],2) ?> &nbsp;|&nbsp; Stock: <?= number_format((float)$p['current_stock'],2) ?></div>
                        </div>
                        <input type="number" name="exchange_items[<?= $p['id'] ?>][qty]"
                               min="0" max="<?= max(0, (float)$p['current_stock']) ?>" step="1" value="0"
                               class="form-control" style="width:70px;" onchange="calcDiff()">
                        <input type="hidden" name="exchange_items[<?= $p['id'] ?>][price]" value="<?= $p['sale_price'] ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Value Diff & Settlement -->
        <div class="card" style="padding:1.2rem;margin-top:1rem;">
            <div style="display:flex;gap:2rem;align-items:center;flex-wrap:wrap;">
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;">Return Value</div>
                    <div style="font-size:1.2rem;font-weight:800;color:#ef4444;" id="returnValue">₹0.00</div>
                </div>
                <div style="font-size:1.2rem;color:#64748b;">→</div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;">Exchange Value</div>
                    <div style="font-size:1.2rem;font-weight:800;color:#059669;" id="exchangeValue">₹0.00</div>
                </div>
                <div style="font-size:1.2rem;color:#64748b;">=</div>
                <div>
                    <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;font-weight:600;" id="diffLabel">Balance Due</div>
                    <div style="font-size:1.3rem;font-weight:800;" id="diffValue" style="color:#0f172a;">₹0.00</div>
                </div>
                <div style="margin-left:auto;">
                    <div class="form-group" style="margin:0;">
                        <label class="form-label">Settlement Method</label>
                        <select name="settlement_method" class="form-control">
                            <option value="cash">Cash</option>
                            <option value="upi">UPI</option>
                            <option value="credit_note">Credit Note</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-group" style="margin-top:0.75rem;">
                <label class="form-label">Reason / Notes</label>
                <input type="text" name="reason" class="form-control" placeholder="e.g. Defective, size mismatch, customer preference...">
            </div>
        </div>

        <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
            <a href="/app/returns" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">✅ Process Exchange</button>
        </div>
    </form>

    <?php else: ?>
    <!-- Step 1: Search by invoice number -->
    <div class="card" style="padding:2rem;max-width:500px;margin:0 auto;">
        <form method="GET" action="/app/returns/exchange">
            <div class="form-group">
                <label class="form-label" style="font-size:1rem;font-weight:700;">🔍 Search Original Invoice</label>
                <input type="text" name="invoice" class="form-control" placeholder="e.g. INV-2024-0001" autofocus style="font-size:1.1rem;padding:0.75rem;">
                <?php if (!empty($searchError)): ?>
                    <p style="color:#ef4444;font-size:0.82rem;margin-top:0.4rem;"><?= e($searchError) ?></p>
                <?php endif; ?>
            </div>
            <button type="submit" class="btn btn-primary" style="width:100%;">Search Invoice</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
<?php if (isset($invoiceItems)): ?>
const returnPrices  = <?= json_encode(array_column($invoiceItems, 'unit_price', 'id')) ?>;
const exchangePrices = <?= json_encode(array_column($allProducts, 'sale_price', 'id')) ?>;

function calcDiff() {
    let retVal = 0, exchVal = 0;
    document.querySelectorAll('input[name^="return_items"]').forEach(inp => {
        if (inp.name.includes('[qty]')) {
            const id = inp.name.match(/\[(\d+)\]/)[1];
            const price = parseFloat(document.querySelector(`input[name="return_items[${id}][price]"]`).value) || 0;
            retVal += (parseFloat(inp.value) || 0) * price;
        }
    });
    document.querySelectorAll('input[name^="exchange_items"]').forEach(inp => {
        if (inp.name.includes('[qty]')) {
            const id = inp.name.match(/\[(\d+)\]/)[1];
            const price = parseFloat(document.querySelector(`input[name="exchange_items[${id}][price]"]`).value) || 0;
            exchVal += (parseFloat(inp.value) || 0) * price;
        }
    });
    const diff = exchVal - retVal;
    document.getElementById('returnValue').textContent  = '₹' + retVal.toFixed(2);
    document.getElementById('exchangeValue').textContent = '₹' + exchVal.toFixed(2);
    document.getElementById('diffLabel').textContent    = diff >= 0 ? 'Balance Due from Customer' : 'Refund to Customer';
    document.getElementById('diffValue').textContent    = '₹' + Math.abs(diff).toFixed(2);
    document.getElementById('diffValue').style.color    = diff > 0 ? '#dc2626' : (diff < 0 ? '#059669' : '#0f172a');
}
<?php endif; ?>

function filterExchange(q) {
    document.querySelectorAll('.exch-item').forEach(el => {
        el.style.display = el.dataset.name.includes(q.toLowerCase()) ? '' : 'none';
    });
}
</script>

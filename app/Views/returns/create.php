<div class="container" style="max-width: 900px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">↩️ Process Sales Return / Credit Note</h1>
            <p style="font-size: 0.85rem; color: #64748b;">
                Original Invoice: <strong><?= e($invoice['invoice_number']) ?></strong> • Customer: <?= e($invoice['customer_name']) ?>
            </p>
        </div>
        <a href="/app/invoices/<?= e($invoice['id']) ?>" class="btn btn-secondary">← Back to Invoice</a>
    </div>

    <form action="/app/returns" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="invoice_id" value="<?= e($invoice['id']) ?>">

        <div class="card" style="padding: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Select Items & Quantities to Return</h3>
            
            <table class="data-table" style="margin-bottom: 1.5rem;">
                <thead>
                    <tr>
                        <th>Item Description</th>
                        <th>Sold Qty</th>
                        <th>Unit Price</th>
                        <th style="width: 140px;">Return Qty</th>
                        <th>Restock Item?</th>
                        <th style="text-align: right;">Line Refund (₹)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $idx => $item): ?>
                        <tr>
                            <td>
                                <input type="hidden" name="items[<?= $idx ?>][invoice_item_id]" value="<?= e($item['id']) ?>">
                                <div style="font-weight: 600;"><?= e($item['item_name_en']) ?></div>
                                <?php if (!empty($item['item_name_or'])): ?>
                                    <div style="font-size: 0.8rem; color: #0369a1;"><?= e($item['item_name_or']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= (float)$item['quantity'] ?> <?= e($item['unit_name']) ?></td>
                            <td><?= money_format_inr($item['unit_price']) ?></td>
                            <td>
                                <input type="number" step="0.001" min="0" max="<?= (float)$item['quantity'] ?>"
                                       name="items[<?= $idx ?>][quantity]" value="0"
                                       class="form-input ret-qty"
                                       data-price="<?= (float)$item['unit_price'] ?>"
                                       oninput="calculateReturnTotal()"
                                       style="padding: 0.35rem 0.5rem;">
                            </td>
                            <td style="text-align: center;">
                                <label style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.8rem; cursor: pointer;">
                                    <input type="checkbox" name="items[<?= $idx ?>][is_restocked]" value="1" checked>
                                    Restock
                                </label>
                            </td>
                            <td style="text-align: right; font-weight: 700;" class="line-refund-val">₹ 0.00</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                <div>
                    <div class="form-group">
                        <label class="form-label">Refund Payout Method *</label>
                        <select name="refund_method" class="form-input">
                            <option value="cash">Cash Refund</option>
                            <option value="upi">UPI / Bank Transfer</option>
                            <option value="credit_note">Credit Note / Adjust Khata</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Reason for Return *</label>
                        <input type="text" name="reason" class="form-input" placeholder="e.g. Defective item, customer exchanged size/variant" required>
                    </div>
                </div>

                <div style="background: #f8fafc; padding: 1.25rem; border-radius: 8px; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.85rem; color: #64748b; font-weight: 600;">Total Net Refund</div>
                        <div style="font-size: 1.8rem; font-weight: 800; color: #ef4444; margin-top: 0.25rem;" id="totalRefundDisplay">
                            ₹ 0.00
                        </div>
                        <p style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">
                            Will generate a balanced journal entry and compensating stock movement.
                        </p>
                    </div>

                    <button type="submit" class="btn btn-danger" style="width: 100%; justify-content: center; padding: 0.75rem; font-size: 1rem;">
                        Confirm Return & Issue Credit Note
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>

<script>
function calculateReturnTotal() {
    let total = 0;
    document.querySelectorAll('.ret-qty').forEach((input, index) => {
        const qty = parseFloat(input.value) || 0;
        const price = parseFloat(input.getAttribute('data-price')) || 0;
        const lineTotal = qty * price;
        total += lineTotal;
        document.querySelectorAll('.line-refund-val')[index].innerText = '₹ ' + lineTotal.toFixed(2);
    });
    document.getElementById('totalRefundDisplay').innerText = '₹ ' + total.toFixed(2);
}
</script>

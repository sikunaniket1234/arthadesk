<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">📥 Purchases & Supplier Bills</h1>
            <p style="font-size: 0.85rem; color: #64748b;">Record stock receipts from dealers and track supplier payables</p>
        </div>
        <a href="/app/purchases/create" class="btn btn-primary">+ New Purchase Bill</a>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Purchase Bills</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;"><?= (int)($stats['total_bills'] ?? 0) ?></div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Total Purchases</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #2563eb; margin-top: 0.25rem;"><?= money_format_inr($stats['total_purchases'] ?? 0) ?></div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Outstanding Payable</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #ef4444; margin-top: 0.25rem;"><?= money_format_inr($stats['total_payable'] ?? 0) ?></div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Bill #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th>Supplier Ref</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($bills)): ?>
                        <tr><td colspan="10" style="text-align:center;padding:2rem;color:#94a3b8;">No purchase bills recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($bills as $bill): ?>
                            <?php $balance = max(0, (float)$bill['total_amount'] - (float)$bill['paid_amount']); ?>
                            <tr>
                                <td style="font-weight:700;"><?= e($bill['bill_number']) ?></td>
                                <td style="font-size:0.85rem;color:#64748b;"><?= e($bill['bill_date']) ?></td>
                                <td><?= e($bill['supplier_name'] ?? 'Unspecified') ?></td>
                                <td style="font-size:0.8rem;color:#64748b;"><?= e($bill['supplier_bill_ref'] ?? '-') ?></td>
                                <td><?= (int)$bill['item_count'] ?></td>
                                <td style="font-weight:600;"><?= money_format_inr($bill['total_amount']) ?></td>
                                <td style="color:#059669;"><?= money_format_inr($bill['paid_amount']) ?></td>
                                <td style="font-weight:700;color:<?= $balance > 0 ? '#ef4444' : '#059669' ?>;"><?= money_format_inr($balance) ?></td>
                                <td>
                                    <?php if ($bill['status'] === 'paid'): ?>
                                        <span class="badge badge-success">PAID</span>
                                    <?php elseif ($bill['status'] === 'partial'): ?>
                                        <span class="badge badge-warning">PARTIAL</span>
                                    <?php elseif ($bill['status'] === 'cancelled'): ?>
                                        <span class="badge badge-danger">CANCELLED</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">UNPAID</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align:right;">
                                    <?php if ($balance > 0 && $bill['status'] !== 'cancelled'): ?>
                                        <button type="button" class="btn btn-primary" style="padding:0.25rem 0.5rem;font-size:0.75rem;"
                                            onclick="openPayModal(<?= (int)$bill['id'] ?>, <?= json_encode($bill['bill_number']) ?>, <?= json_encode(round($balance, 2)) ?>)">
                                            Record Payment
                                        </button>
                                    <?php else: ?>
                                        <span style="font-size:0.75rem;color:#94a3b8;">Settled</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div id="payBillModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);z-index:1000;padding:2rem 1rem;">
    <div style="max-width:520px;margin:3rem auto;background:#fff;border-radius:12px;box-shadow:var(--shadow-lg);overflow:hidden;">
        <div style="padding:1.25rem 1.5rem;background:#0f172a;color:#fff;display:flex;justify-content:space-between;align-items:center;">
            <h3 id="payModalTitle" style="font-size:1.1rem;font-weight:700;">Record Supplier Payment</h3>
            <button type="button" onclick="document.getElementById('payBillModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:1.5rem;cursor:pointer;">&times;</button>
        </div>
        <form id="payBillForm" method="POST" style="padding:1.5rem;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Amount (₹) *</label>
                <input type="number" step="0.01" min="0.01" name="amount" id="payAmount" class="form-input" required>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;margin-bottom:1rem;">
                <div>
                    <label class="form-label">Method</label>
                    <select name="payment_method" class="form-input">
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="card">Card</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Reference</label>
                    <input type="text" name="reference" class="form-input" placeholder="UTR / cheque no.">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:0.75rem;">
                <button type="button" onclick="document.getElementById('payBillModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Payment</button>
            </div>
        </form>
    </div>
</div>

<script>
function openPayModal(id, billNumber, balance) {
    const form = document.getElementById('payBillForm');
    form.action = '/app/purchases/' + id + '/pay';
    document.getElementById('payModalTitle').textContent = 'Pay ' + billNumber + ' — Balance ₹ ' + Number(balance).toFixed(2);
    document.getElementById('payAmount').value = Number(balance).toFixed(2);
    document.getElementById('payBillModal').style.display = 'block';
}
</script>

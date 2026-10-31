<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">↩️ <?= e(__('returns')) ?> / Credit Notes</h1>
            <p style="font-size: 0.85rem; color: #64748b;">Compensating sales returns, restock logs, and accounting credit notes</p>
        </div>
        <a href="/app/invoices" class="btn btn-secondary">
            Select Invoice to Return
        </a>
    </div>

    <!-- Returns Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Credit Note #</th>
                        <th>Original Invoice</th>
                        <th>Customer</th>
                        <th>Date & Time</th>
                        <th>Refund Method</th>
                        <th>Reason</th>
                        <th style="text-align: right;">Refund Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="7" style="text-align: center; padding: 2rem; color: #94a3b8;">No returns recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($returns as $ret): ?>
                            <tr>
                                <td style="font-weight: 700; color: #ef4444;"><?= e($ret['return_number']) ?></td>
                                <td style="font-weight: 600;">
                                    <a href="/app/invoices/<?= e($ret['invoice_id']) ?>" style="color: #2563eb; text-decoration: none;">
                                        <?= e($ret['invoice_number']) ?>
                                    </a>
                                </td>
                                <td><?= e($ret['customer_name']) ?></td>
                                <td style="font-size: 0.8rem; color: #64748b;"><?= e($ret['created_at']) ?></td>
                                <td><span class="badge badge-warning"><?= strtoupper($ret['refund_method']) ?></span></td>
                                <td style="font-size: 0.85rem; color: #475569;"><?= e($ret['reason'] ?? '-') ?></td>
                                <td style="font-weight: 800; text-align: right; color: #ef4444;">
                                    - <?= money_format_inr($ret['total_refund_amount']) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

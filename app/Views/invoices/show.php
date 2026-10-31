<div class="container" style="max-width: 900px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;" class="no-print">
        <a href="/app/invoices" class="btn btn-secondary">← Back to Invoices</a>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="/app/invoices/<?= e($invoice['id']) ?>/thermal" target="_blank" class="btn btn-secondary">
                🖨️ Thermal Receipt (58/80mm)
            </a>
            <a href="/app/invoices/<?= e($invoice['id']) ?>/pdf" class="btn btn-secondary">
                📄 PDF
            </a>
            <button onclick="window.print()" class="btn btn-primary">
                🖨️ Print Detailed Invoice (A4)
            </button>
            <?php if (empty($invoice['voided_at']) && in_array(auth_user()['role'] ?? '', ['super_admin', 'tenant_admin', 'accountant'], true)): ?>
                <button type="button" onclick="document.getElementById('voidInvoiceModal').style.display='block'" class="btn btn-danger">
                    ⛔ Void Invoice
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($invoice['voided_at']) || $invoice['payment_status'] === 'cancelled'): ?>
        <div class="alert alert-error" style="margin-bottom: 1rem;">
            <strong>VOIDED INVOICE</strong>
            <?php if (!empty($invoice['voided_at'])): ?>
                — voided on <?= e(date('d-M-Y H:i', strtotime($invoice['voided_at']))) ?>
            <?php endif; ?>
            <?php if (!empty($invoice['void_reason'])): ?>
                <div style="margin-top: 0.35rem; font-size: 0.85rem;">Reason: <?= e($invoice['void_reason']) ?></div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card" style="padding: 2.5rem; background: #fff;">
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; border-bottom: 2px solid #0f172a; padding-bottom: 1.5rem; margin-bottom: 1.5rem;">
            <div>
                <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;"><?= e($tenant['name']) ?></h1>
                <p style="font-size: 0.85rem; color: #475569; margin-top: 0.25rem;"><?= e($tenant['address'] ?? 'Odisha, India') ?></p>
                <p style="font-size: 0.85rem; color: #475569;">Phone: <?= e($tenant['phone'] ?? '-') ?></p>
                <?php if (!empty($taxProfile['gstin'])): ?>
                    <p style="font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-top: 0.25rem;">
                        GSTIN: <?= e($taxProfile['gstin']) ?> (State Code: <?= e($taxProfile['registered_state_code']) ?> - Odisha)
                    </p>
                <?php endif; ?>
            </div>

            <div style="text-align: right;">
                <div style="font-size: 1.25rem; font-weight: 800; color: #2563eb; letter-spacing: 0.5px;">
                    <?php if ($invoice['document_type'] === 'TAX_INVOICE'): ?>
                        TAX INVOICE
                    <?php elseif ($invoice['document_type'] === 'BILL_OF_SUPPLY'): ?>
                        BILL OF SUPPLY
                    <?php else: ?>
                        RETAIL INVOICE
                    <?php endif; ?>
                </div>
                <div style="font-size: 1rem; font-weight: 700; margin-top: 0.5rem;"><?= e($invoice['invoice_number']) ?></div>
                <div style="font-size: 0.85rem; color: #64748b;">Date: <?= date('d-M-Y H:i', strtotime($invoice['created_at'])) ?></div>
                <div style="font-size: 0.85rem; color: #64748b;">Payment: <?= strtoupper($invoice['payment_method'] ?? 'CASH') ?></div>
            </div>
        </div>

        <!-- Bill To -->
        <div style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; justify-content: space-between;">
            <div>
                <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Billed To / Customer</div>
                <div style="font-size: 1rem; font-weight: 700; color: #0f172a; margin-top: 0.2rem;">
                    <?= e($invoice['customer_name'] ?? 'Walk-in Customer') ?>
                </div>
                <?php if (!empty($invoice['customer_phone'])): ?>
                    <div style="font-size: 0.85rem; color: #475569;">Phone: <?= e($invoice['customer_phone']) ?></div>
                <?php endif; ?>
                <?php if (!empty($invoice['customer_gstin'])): ?>
                    <div style="font-size: 0.85rem; font-weight: 600; color: #1e293b;">Buyer GSTIN: <?= e($invoice['customer_gstin']) ?></div>
                <?php endif; ?>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 0.75rem; font-weight: 700; color: #94a3b8; text-transform: uppercase;">Place of Supply</div>
                <div style="font-size: 0.9rem; font-weight: 600; color: #0f172a; margin-top: 0.2rem;">
                    State Code: <?= e($invoice['place_of_supply_state_code']) ?>
                    (<?= $invoice['is_interstate'] ? 'Inter-state / IGST' : 'Intra-state / CGST+SGST' ?>)
                </div>
            </div>
        </div>

        <!-- Line Items Table -->
        <table class="data-table" style="margin-bottom: 1.5rem;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Item Description</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Discount</th>
                    <th>Taxable Val</th>
                    <?php if ($invoice['document_type'] === 'TAX_INVOICE'): ?>
                        <th>Tax Rate</th>
                        <th>Tax Amt</th>
                    <?php endif; ?>
                    <th style="text-align: right;">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $idx => $line): ?>
                    <tr>
                        <td><?= $idx + 1 ?></td>
                        <td>
                            <div style="font-weight: 600;"><?= e($line['item_name_en']) ?></div>
                            <?php if (!empty($line['item_name_or'])): ?>
                                <div style="font-size: 0.8rem; color: #0369a1; font-family: 'Noto Sans Oriya', sans-serif;">
                                    <?= e($line['item_name_or']) ?>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><?= (float)$line['quantity'] ?> <?= e($line['unit_name']) ?></td>
                        <td><?= money_format_inr($line['unit_price']) ?></td>
                        <td><?= money_format_inr($line['discount']) ?></td>
                        <td><?= money_format_inr($line['taxable_value']) ?></td>
                        <?php if ($invoice['document_type'] === 'TAX_INVOICE'): ?>
                            <td><?= (float)$line['tax_rate_percent'] ?>%</td>
                            <td><?= money_format_inr($line['cgst_amount'] + $line['sgst_amount'] + $line['igst_amount']) ?></td>
                        <?php endif; ?>
                        <td style="font-weight: 700; text-align: right;"><?= money_format_inr($line['line_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals & Tax Breakdown -->
        <div style="display: flex; justify-content: flex-end;">
            <div style="width: 320px;">
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; color: #475569; font-size: 0.9rem;">
                    <span>Subtotal (Gross):</span>
                    <span><?= money_format_inr($invoice['subtotal']) ?></span>
                </div>
                <?php if ((float)$invoice['discount_amount'] > 0): ?>
                    <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; color: #ef4444; font-size: 0.9rem;">
                        <span>Discount:</span>
                        <span>- <?= money_format_inr($invoice['discount_amount']) ?></span>
                    </div>
                <?php endif; ?>
                <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; color: #475569; font-size: 0.9rem;">
                    <span>Taxable Amount:</span>
                    <span><?= money_format_inr($invoice['taxable_amount']) ?></span>
                </div>

                <?php if ($invoice['document_type'] === 'TAX_INVOICE'): ?>
                    <?php if ((float)$invoice['cgst_amount'] > 0): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; color: #2563eb; font-size: 0.9rem;">
                            <span>Central GST (CGST):</span>
                            <span><?= money_format_inr($invoice['cgst_amount']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ((float)$invoice['sgst_amount'] > 0): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; color: #2563eb; font-size: 0.9rem;">
                            <span>State GST (SGST):</span>
                            <span><?= money_format_inr($invoice['sgst_amount']) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ((float)$invoice['igst_amount'] > 0): ?>
                        <div style="display: flex; justify-content: space-between; padding: 0.4rem 0; color: #2563eb; font-size: 0.9rem;">
                            <span>Integrated GST (IGST):</span>
                            <span><?= money_format_inr($invoice['igst_amount']) ?></span>
                        </div>
                    <?php endif; ?>
                <?php elseif ($invoice['document_type'] === 'BILL_OF_SUPPLY'): ?>
                    <div style="font-size: 0.75rem; color: #d97706; padding: 0.5rem 0; font-style: italic;">
                        Composition taxable person, not eligible to collect tax on supplies.
                    </div>
                <?php else: ?>
                    <div style="font-size: 0.75rem; color: #64748b; padding: 0.5rem 0; font-style: italic;">
                        Unregistered dealer. No tax charged.
                    </div>
                <?php endif; ?>

                <div style="display: flex; justify-content: space-between; padding: 0.75rem 0; border-top: 2px solid #0f172a; margin-top: 0.5rem; font-size: 1.25rem; font-weight: 800; color: #0f172a;">
                    <span>Total Amount:</span>
                    <span><?= money_format_inr($invoice['total_amount']) ?></span>
                </div>
                <?php if (($invoice['paid_amount'] ?? 0) > 0): ?>
                    <div style="display: flex; justify-content: space-between; padding: 0.3rem 0; color: #059669; font-size: 0.9rem;">
                        <span>Paid:</span>
                        <span><?= money_format_inr($invoice['paid_amount']) ?></span>
                    </div>
                <?php endif; ?>
                <?php if (($invoice['credit_amount'] ?? 0) > 0.001): ?>
                    <div style="display: flex; justify-content: space-between; padding: 0.3rem 0; color: #ef4444; font-size: 0.9rem; font-weight: 700;">
                        <span>Balance Due (Khata):</span>
                        <span><?= money_format_inr($invoice['credit_amount']) ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($payments)): ?>
            <div style="margin-top: 1.5rem; border-top: 1px dashed #e2e8f0; padding-top: 1rem;">
                <div style="font-size: 0.8rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 0.5rem;">Payments</div>
                <table class="data-table" style="font-size: 0.85rem;">
                    <thead>
                        <tr><th>Method</th><th>Reference</th><th>Status</th><th style="text-align:right;">Amount</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?= e(strtoupper($payment['payment_method'])) ?></td>
                                <td><?= e($payment['transaction_ref'] ?? '-') ?></td>
                                <td><?= e(strtoupper($payment['status'])) ?></td>
                                <td style="text-align:right;"><?= money_format_inr($payment['amount']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

        <div style="margin-top: 2.5rem; text-align: center; font-size: 0.85rem; color: #94a3b8; border-top: 1px dashed #e2e8f0; padding-top: 1.5rem;">
            <?= e(__('thank_you')) ?> • Generated by Artha-Desk (ଅର୍ଥ-ଡେସ୍କ)
        </div>
    </div>
</div>

<?php if (empty($invoice['voided_at']) && in_array(auth_user()['role'] ?? '', ['super_admin', 'tenant_admin', 'accountant'], true)): ?>
<div id="voidInvoiceModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; padding: 2rem 1rem;" class="no-print">
    <div style="max-width: 520px; margin: 3rem auto; background: #fff; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; background: #7f1d1d; color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700;">Void Invoice <?= e($invoice['invoice_number']) ?></h3>
            <button type="button" onclick="document.getElementById('voidInvoiceModal').style.display='none'" style="background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form action="/app/invoices/<?= e($invoice['id']) ?>/void" method="POST" style="padding: 1.5rem;">
            <?= csrf_field() ?>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1rem;">
                Voiding restores stock, reverses the accounting journal and marks any payment as refunded. This cannot be undone.
            </p>
            <div class="form-group">
                <label class="form-label">Reason *</label>
                <textarea name="reason" class="form-input" rows="3" required minlength="5" maxlength="500" placeholder="e.g. Wrong items billed / customer cancelled"></textarea>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('voidInvoiceModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-danger">Void Invoice</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">📄 <?= e(__('invoices')) ?></h1>
            <p style="font-size: 0.85rem; color: #64748b;">Posted sales transactions and historical receipts</p>
        </div>
        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
            <a href="/app/invoices/export?<?= e(http_build_query($filters ?? [])) ?>" class="btn btn-secondary">⬇️ Export CSV</a>
            <a href="/app/pos" class="btn btn-primary">+ New Sale (POS)</a>
        </div>
    </div>

    <form method="GET" action="/app/invoices" class="card" style="padding: 1rem; margin-bottom: 1.5rem;">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 0.75rem; align-items: end;">
            <div>
                <label class="form-label">From</label>
                <input type="date" name="from" class="form-input" value="<?= e($filters['from'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="to" class="form-input" value="<?= e($filters['to'] ?? '') ?>">
            </div>
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-input">
                    <option value="">All</option>
                    <?php foreach (['paid' => 'Paid', 'partial' => 'Partial', 'pending' => 'Pending', 'cancelled' => 'Voided'] as $value => $label): ?>
                        <option value="<?= e($value) ?>" <?= ($filters['status'] ?? '') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="form-label">Search</label>
                <input type="search" name="q" class="form-input" placeholder="Invoice # or customer" value="<?= e($filters['q'] ?? '') ?>">
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="/app/invoices" class="btn btn-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Invoices (filtered)</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;"><?= (int)($stats['total_invoices'] ?? 0) ?></div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Net Sales Volume</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #059669; margin-top: 0.25rem;"><?= money_format_inr($stats['total_sales'] ?? 0) ?></div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">GST Collected</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #2563eb; margin-top: 0.25rem;"><?= money_format_inr($stats['total_tax'] ?? 0) ?></div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.8rem; color: #64748b; font-weight: 600;">Voided Invoices</div>
            <div style="font-size: 1.6rem; font-weight: 800; color: #ef4444; margin-top: 0.25rem;"><?= (int)($stats['cancelled_invoices'] ?? 0) ?></div>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Document Type</th>
                        <th>Date & Time</th>
                        <th>Customer</th>
                        <th>Taxable Amt</th>
                        <th>GST (Tax)</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="9" style="text-align: center; padding: 2.5rem; color: #94a3b8;">
                                No invoices found for the selected filters.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($invoices as $inv): ?>
                            <?php $cancelled = $inv['payment_status'] === 'cancelled'; ?>
                            <tr style="<?= $cancelled ? 'opacity: 0.65;' : '' ?>">
                                <td style="font-weight: 700;">
                                    <a href="/app/invoices/<?= e($inv['id']) ?>" style="color: #2563eb; text-decoration: none;">
                                        <?= e($inv['invoice_number']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($inv['document_type'] === 'TAX_INVOICE'): ?>
                                        <span class="badge badge-success">TAX INVOICE</span>
                                    <?php elseif ($inv['document_type'] === 'BILL_OF_SUPPLY'): ?>
                                        <span class="badge badge-warning">BILL OF SUPPLY</span>
                                    <?php else: ?>
                                        <span class="badge badge-info">RETAIL INVOICE</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.8rem; color: #64748b;"><?= e($inv['created_at']) ?></td>
                                <td><?= e($inv['customer_name']) ?></td>
                                <td><?= money_format_inr($inv['taxable_amount']) ?></td>
                                <td style="color: #2563eb; font-weight: 600;">
                                    <?= money_format_inr($inv['cgst_amount'] + $inv['sgst_amount'] + $inv['igst_amount']) ?>
                                </td>
                                <td style="font-weight: 700; color: #0f172a;"><?= money_format_inr($inv['total_amount']) ?></td>
                                <td>
                                    <?php if ($cancelled): ?>
                                        <span class="badge badge-danger">VOIDED</span>
                                    <?php elseif ($inv['payment_status'] === 'partial'): ?>
                                        <span class="badge badge-warning">PARTIAL</span>
                                    <?php elseif ($inv['payment_status'] === 'pending'): ?>
                                        <span class="badge badge-warning">CREDIT</span>
                                    <?php else: ?>
                                        <span class="badge badge-success">PAID</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="/app/invoices/<?= e($inv['id']) ?>" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">View</a>
                                    <a href="/app/invoices/<?= e($inv['id']) ?>/thermal" target="_blank" class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">🖨️</a>
                                    <a href="/app/invoices/<?= e($inv['id']) ?>/pdf" class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.75rem;">PDF</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

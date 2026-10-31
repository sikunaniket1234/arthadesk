<div class="container">
    <div style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">📒 <?= e(__('accounting')) ?> & Reports</h1>
        <p style="font-size: 0.85rem; color: #64748b;">Double-entry financial daybook, customer khata, and GST tax ledger reconciliation</p>
    </div>

    <!-- GST Sales Tax Summary Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Gross Sales</div>
            <div style="font-size: 1.3rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem;">
                <?= money_format_inr($gstSummary['total_gross']) ?>
            </div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Taxable Value</div>
            <div style="font-size: 1.3rem; font-weight: 800; color: #0f172a; margin-top: 0.2rem;">
                <?= money_format_inr($gstSummary['total_taxable']) ?>
            </div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Output CGST</div>
            <div style="font-size: 1.3rem; font-weight: 800; color: #2563eb; margin-top: 0.2rem;">
                <?= money_format_inr($gstSummary['total_cgst']) ?>
            </div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Output SGST</div>
            <div style="font-size: 1.3rem; font-weight: 800; color: #2563eb; margin-top: 0.2rem;">
                <?= money_format_inr($gstSummary['total_sgst']) ?>
            </div>
        </div>
        <div class="card" style="margin-bottom: 0;">
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Output IGST</div>
            <div style="font-size: 1.3rem; font-weight: 800; color: #2563eb; margin-top: 0.2rem;">
                <?= money_format_inr($gstSummary['total_igst']) ?>
            </div>
        </div>
        <div class="card" style="margin-bottom: 0; background: #ecfdf5; border-color: #a7f3d0;">
            <div style="font-size: 0.75rem; color: #065f46; font-weight: 600;">Net Revenue Billed</div>
            <div style="font-size: 1.3rem; font-weight: 800; color: #047857; margin-top: 0.2rem;">
                <?= money_format_inr($gstSummary['total_billed']) ?>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
        <!-- Cashbook / Daybook -->
        <div class="card">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Daily Cashbook & Receipts</h3>
            <div class="table-responsive" style="max-height: 400px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Mode</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cashbook)): ?>
                            <tr><td colspan="5" style="text-align: center; padding: 2rem; color: #94a3b8;">No transactions today.</td></tr>
                        <?php else: ?>
                            <?php foreach ($cashbook as $entry): ?>
                                <tr>
                                    <td style="font-size: 0.75rem; color: #64748b;"><?= date('H:i', strtotime($entry['created_at'])) ?></td>
                                    <td style="font-weight: 600;"><?= e($entry['invoice_number']) ?></td>
                                    <td><?= e($entry['customer_name']) ?></td>
                                    <td><span class="badge badge-info"><?= strtoupper($entry['payment_method']) ?></span></td>
                                    <td style="font-weight: 700; text-align: right; color: #059669;"><?= money_format_inr($entry['amount']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Customer Khata (Receivables) -->
        <div class="card">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">Customer Khata (Credit Accounts)</h3>
            <div class="table-responsive" style="max-height: 400px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>State</th>
                            <th style="text-align: right;">Outstanding Khata</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($khata)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 2rem; color: #94a3b8;">No credit customers.</td></tr>
                        <?php else: ?>
                            <?php foreach ($khata as $c): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?= e($c['name']) ?></td>
                                    <td style="color: #64748b;"><?= e($c['phone'] ?? '-') ?></td>
                                    <td><?= e($c['state_code'] ?? '21') ?></td>
                                    <td style="font-weight: 700; text-align: right; color: <?= (float)$c['total_credit_sales'] > 0 ? '#ef4444' : '#64748b' ?>;">
                                        <?= money_format_inr($c['total_credit_sales']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Trial Balance & Ledger Postings -->
    <div class="card">
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem;">General Ledger & Trial Balance</h3>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Account Code</th>
                        <th>Account Name</th>
                        <th>Account Classification</th>
                        <th style="text-align: right;">Total Debits (₹)</th>
                        <th style="text-align: right;">Total Credits (₹)</th>
                        <th style="text-align: right;">Net Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $sumDebit = 0;
                    $sumCredit = 0;
                    ?>
                    <?php foreach ($trialBalance as $acc): ?>
                        <?php 
                        $d = (float)$acc['total_debit'];
                        $c = (float)$acc['total_credit'];
                        $sumDebit += $d;
                        $sumCredit += $c;
                        $net = $d - $c;
                        ?>
                        <tr>
                            <td style="font-family: monospace; font-weight: 700;"><?= e($acc['code']) ?></td>
                            <td style="font-weight: 600;"><?= e($acc['name']) ?></td>
                            <td><span class="badge badge-info"><?= strtoupper($acc['type']) ?></span></td>
                            <td style="text-align: right;"><?= money_format_inr($d) ?></td>
                            <td style="text-align: right;"><?= money_format_inr($c) ?></td>
                            <td style="font-weight: 700; text-align: right; color: <?= $net >= 0 ? '#0f172a' : '#d97706' ?>;">
                                <?= money_format_inr(abs($net)) ?> <?= $net >= 0 ? 'DR' : 'CR' ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background: #f8fafc; font-weight: 800; border-top: 2px solid #0f172a;">
                        <td colspan="3">BALANCED TRIAL BALANCE TOTALS</td>
                        <td style="text-align: right; color: #2563eb;"><?= money_format_inr($sumDebit) ?></td>
                        <td style="text-align: right; color: #2563eb;"><?= money_format_inr($sumCredit) ?></td>
                        <td style="text-align: right; color: #059669;">
                            <?= abs($sumDebit - $sumCredit) < 0.01 ? '✓ BALANCED' : 'UNBALANCED' ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

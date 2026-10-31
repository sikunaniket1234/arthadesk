<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= e($invoice['invoice_number']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&family=Noto+Sans+Oriya:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Inter', 'Noto Sans Oriya', monospace;
            background: #fff;
            color: #000;
            font-size: 12px;
            line-height: 1.35;
            padding: 10px;
        }
        .receipt-container {
            width: 78mm;
            max-width: 100%;
            margin: 0 auto;
        }
        .header {
            text-align: center;
            margin-bottom: 8px;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
        }
        .store-name {
            font-size: 15px;
            font-weight: 700;
        }
        .store-info {
            font-size: 11px;
            margin-top: 2px;
        }
        .doc-title {
            font-size: 13px;
            font-weight: 800;
            margin-top: 5px;
            letter-spacing: 0.5px;
        }
        .meta-table {
            width: 100%;
            font-size: 11px;
            margin-bottom: 6px;
        }
        .meta-table td {
            padding: 1px 0;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 6px;
        }
        .items-table th {
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
            padding: 4px 0;
            text-align: left;
        }
        .items-table td {
            padding: 3px 0;
            vertical-align: top;
        }
        .totals-section {
            border-top: 1px dashed #000;
            padding-top: 4px;
            font-size: 11px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
        }
        .grand-total {
            font-size: 14px;
            font-weight: 800;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0;
            margin-top: 4px;
        }
        .footer {
            text-align: center;
            font-size: 10px;
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px dashed #000;
        }
        /* No page margins => the browser prints NO header/footer (removes the
           stray date + URL above the store name) and gives the receipt the
           full paper height (stops the footer being trimmed off). */
        @page {
            size: auto;
            margin: 0;
        }
        @media print {
            .no-print { display: none !important; }
            html, body {
                margin: 0;
                padding: 0;
                width: 78mm;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .receipt-container {
                width: 100%;
                max-width: none;
                margin: 0;
            }
            /* Never split a line item or the totals block across paper pages. */
            .items-table tr,
            .totals-section,
            .footer,
            .grand-total {
                break-inside: avoid;
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="text-align: center; margin-bottom: 10px; padding: 10px; background: #f1f5f9; border-radius: 6px;">
        <button onclick="window.print()" style="padding: 6px 14px; background: #2563eb; color: #fff; border: none; border-radius: 4px; font-weight: 700; cursor: pointer;">
            🖨️ Print to Thermal Printer
        </button>
        <button onclick="window.close()" style="padding: 6px 14px; background: #e2e8f0; color: #000; border: none; border-radius: 4px; font-weight: 600; cursor: pointer; margin-left: 5px;">
            Close
        </button>
    </div>

    <div class="receipt-container">
        <!-- Store Header -->
        <div class="header">
            <div class="store-name"><?= e($tenant['name']) ?></div>
            <div class="store-info"><?= e($tenant['address'] ?? 'Odisha, India') ?></div>
            <div class="store-info">Ph: <?= e($tenant['phone'] ?? '-') ?></div>
            <?php if (!empty($taxProfile['gstin'])): ?>
                <div class="store-info" style="font-weight: 600;">GSTIN: <?= e($taxProfile['gstin']) ?></div>
            <?php endif; ?>

            <div class="doc-title">
                <?php if ($invoice['document_type'] === 'TAX_INVOICE'): ?>
                    TAX INVOICE
                <?php elseif ($invoice['document_type'] === 'BILL_OF_SUPPLY'): ?>
                    BILL OF SUPPLY
                <?php else: ?>
                    RETAIL INVOICE
                <?php endif; ?>
            </div>
        </div>

        <!-- Meta -->
        <table class="meta-table">
            <tr>
                <td><strong>Invoice:</strong> <?= e($invoice['invoice_number']) ?></td>
                <td style="text-align: right;"><strong>Date:</strong> <?= date('d/m/y H:i', strtotime($invoice['created_at'])) ?></td>
            </tr>
            <tr>
                <td><strong>Customer:</strong> <?= e($invoice['customer_name'] ?? 'Cash Customer') ?></td>
                <td style="text-align: right;"><strong>Pay:</strong> <?= strtoupper($invoice['payment_method'] ?? 'CASH') ?></td>
            </tr>
            <?php
            // Walk-in phone if given, otherwise the registered customer's phone.
            $receiptPhone = trim((string)($invoice['walkin_phone'] ?? ''));
            if ($receiptPhone === '') {
                $receiptPhone = trim((string)($invoice['customer_phone'] ?? ''));
            }
            if ($receiptPhone !== ''): ?>
                <tr>
                    <td colspan="2"><strong>Ph:</strong> <?= e($receiptPhone) ?></td>
                </tr>
            <?php endif; ?>
        </table>

        <!-- Items -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 48%;">Item</th>
                    <th style="width: 18%; text-align: center;">Qty</th>
                    <th style="width: 17%; text-align: right;">Rate</th>
                    <th style="width: 17%; text-align: right;">Amt</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items as $line): ?>
                    <tr>
                        <td>
                            <div><?= e($line['item_name_en']) ?></div>
                            <?php if (!empty($line['item_name_or'])): ?>
                                <div style="font-size: 10px;"><?= e($line['item_name_or']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: center;"><?= (float)$line['quantity'] ?></td>
                        <td style="text-align: right;"><?= number_format($line['unit_price'], 2) ?></td>
                        <td style="text-align: right; font-weight: 600;"><?= number_format($line['line_total'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <div class="totals-row">
                <span>Total Items:</span>
                <span><?= count($items) ?></span>
            </div>
            <div class="totals-row">
                <span>Subtotal:</span>
                <span>₹ <?= number_format($invoice['subtotal'], 2) ?></span>
            </div>
            <?php if ((float)$invoice['discount_amount'] > 0): ?>
                <div class="totals-row">
                    <span>Discount:</span>
                    <span>- ₹ <?= number_format($invoice['discount_amount'], 2) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($invoice['document_type'] === 'TAX_INVOICE'): ?>
                <div class="totals-row">
                    <span>Taxable Value:</span>
                    <span>₹ <?= number_format($invoice['taxable_amount'], 2) ?></span>
                </div>
                <?php if ((float)$invoice['cgst_amount'] > 0): ?>
                    <div class="totals-row">
                        <span>CGST:</span>
                        <span>₹ <?= number_format($invoice['cgst_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((float)$invoice['sgst_amount'] > 0): ?>
                    <div class="totals-row">
                        <span>SGST:</span>
                        <span>₹ <?= number_format($invoice['sgst_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
                <?php if ((float)$invoice['igst_amount'] > 0): ?>
                    <div class="totals-row">
                        <span>IGST:</span>
                        <span>₹ <?= number_format($invoice['igst_amount'], 2) ?></span>
                    </div>
                <?php endif; ?>
            <?php elseif ($invoice['document_type'] === 'BILL_OF_SUPPLY'): ?>
                <div style="font-size: 9px; text-align: center; margin: 3px 0;">
                    * Composition taxable person, no tax collected *
                </div>
            <?php endif; ?>

            <div class="totals-row grand-total">
                <span>NET PAYABLE:</span>
                <span>₹ <?= number_format($invoice['total_amount'], 2) ?></span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div><?= e(__('thank_you')) ?></div>
            <div style="font-size: 9px; margin-top: 2px;">Powered by Artha-Desk (ଅର୍ଥ-ଡେସ୍କ)</div>
        </div>
    </div>

    <script>
        // Auto-print once fonts are ready so the layout cannot shift mid-print.
        function autoPrint() {
            setTimeout(function () { window.print(); }, 250);
        }
        window.addEventListener('load', function () {
            if (document.fonts && document.fonts.ready) {
                document.fonts.ready.then(autoPrint).catch(autoPrint);
            } else {
                autoPrint();
            }
        });
    </script>
</body>
</html>

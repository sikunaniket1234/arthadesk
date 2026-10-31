<?php
$isOdia = (current_locale() === 'or');
$regMode = $taxProfile['registration_mode'] ?? 'unregistered';
$defaultPay = in_array(($defaultPaymentMode ?? 'cash'), ['cash', 'upi', 'card', 'credit'], true) ? $defaultPaymentMode : 'cash';
?>

<div class="pos-layout">
    <!-- Left Pane: Product Search & Grid -->
    <section class="product-pane">
        <div class="product-header">
            <input type="text" id="posSearchInput" class="search-input" placeholder="🔍 <?= e(__('search_product')) ?>" autofocus autocomplete="off">
            
            <div class="category-bar">
                <button type="button" class="cat-chip active" onclick="filterCategory('ALL')"><?= e(__('all_categories')) ?></button>
                <?php foreach ($categories as $cat): ?>
                    <button type="button" class="cat-chip" onclick="filterCategory('<?= e($cat) ?>')"><?= e($cat) ?></button>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="product-grid" id="productGrid">
            <?php foreach ($products as $p): ?>
                <?php 
                    $outOfStock = ((float)$p['current_stock'] <= 0 && empty($tenant['allow_negative_stock']));
                    $isLowStock = ((float)$p['current_stock'] <= (float)$p['reorder_level']);
                ?>
                <div class="product-card <?= $outOfStock ? 'out-of-stock' : '' ?>" 
                     data-id="<?= e($p['id']) ?>" 
                     data-name-en="<?= e($p['name_en']) ?>"
                     data-name-or="<?= e($p['name_or']) ?>"
                     data-price="<?= e($p['sale_price']) ?>"
                     data-tax-rate="<?= e($p['tax_rate_percent'] ?? 0) ?>"
                     data-tax-class="<?= e($p['tax_classification'] ?? 'taxable') ?>"
                     data-tax-inclusive="<?= e($p['price_includes_tax']) ?>"
                     data-unit="<?= e($p['unit_name']) ?>"
                     data-category="<?= e($p['category']) ?>"
                     data-stock="<?= e($p['current_stock']) ?>"
                     onclick="addToCart(<?= htmlspecialchars(json_encode([
                         'id' => (int)$p['id'],
                         'name_en' => $p['name_en'],
                         'name_or' => $p['name_or'] ?? '',
                         'unit' => $p['unit_name'] ?? 'pcs',
                         'price' => (float)$p['sale_price'],
                         'tax_rate' => (float)($p['tax_rate_percent'] ?? 0),
                         'tax_class' => $p['tax_classification'] ?? 'taxable',
                         'tax_inclusive' => !empty($p['price_includes_tax']),
                         'stock' => (float)$p['current_stock']
                     ])) ?>)">
                    <div>
                        <div class="product-name-en"><?= e($p['name_en']) ?></div>
                        <?php if (!empty($p['name_or'])): ?>
                            <div class="product-name-or"><?= e($p['name_or']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="product-meta">
                        <div class="product-price"><?= money_format_inr($p['sale_price']) ?></div>
                        <div class="stock-tag <?= $isLowStock ? 'low' : '' ?>">
                            <?= $outOfStock ? e(__('out_of_stock')) : ((float)$p['current_stock'] . ' ' . e($p['unit_name'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- Right Pane: Cart & Live Tax Breakdown -->
    <section class="cart-pane">
        <div class="cart-header">
            <h3>🛍️ <?= e(__('current_cart')) ?></h3>
            <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;" onclick="clearCart()">
                <?= e(__('clear_cart')) ?>
            </button>
        </div>

        <!-- Customer & Place of Supply Options -->
        <div style="padding: 0.75rem 1rem; background: #fff; border-bottom: 1px solid var(--border);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.35rem;">
                <label style="font-size: 0.72rem; font-weight: 700; color: var(--text-muted);"><?= e(__('customer')) ?></label>
                <button type="button" onclick="openQuickCustomerModal()" style="font-size: 0.72rem; color: #2563eb; background: none; border: none; cursor: pointer; font-weight: 700;">
                    + New Customer
                </button>
            </div>
            
            <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                <select id="cartCustomer" class="form-input" style="flex: 1.2; padding: 0.35rem; font-size: 0.8rem;" onchange="toggleWalkinFields()">
                    <option value="" selected>👤 <?= e(__('walk_in')) ?></option>
                    <?php foreach ($customers as $c): ?>
                        <option value="<?= e($c['id']) ?>" data-state="<?= e($c['state_code']) ?>"><?= e($c['name']) ?> (<?= e($c['phone']) ?>)</option>
                    <?php endforeach; ?>
                </select>

                <select id="cartPlaceOfSupply" class="form-input" style="flex: 1; padding: 0.35rem; font-size: 0.8rem;" onchange="recalculateCart()">
                    <option value="21" selected>Odisha (21) - Intra</option>
                    <option value="19">West Bengal (19) - Inter</option>
                    <option value="20">Jharkhand (20) - Inter</option>
                    <option value="27">Maharashtra (27) - Inter</option>
                </select>
            </div>

            <!-- Optional Walk-in Customer Name & Phone -->
            <div id="walkinFields" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; padding: 0.4rem; background: #f8fafc; border-radius: 6px; border: 1px dashed #cbd5e1;">
                <input type="text" id="walkinName" class="form-input" style="padding: 0.3rem 0.5rem; font-size: 0.75rem;" placeholder="Walk-in Name (Optional)" maxlength="100" autocomplete="off">
                <input type="tel" id="walkinPhone" class="form-input" style="padding: 0.3rem 0.5rem; font-size: 0.75rem;" placeholder="Phone — 10 digits (Optional)" maxlength="10" inputmode="numeric" pattern="[0-9]*" autocomplete="off" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
            </div>
        </div>

        <!-- Cart Items List -->
        <div class="cart-items" id="cartItemsContainer">
            <div class="cart-empty" id="cartEmptyState">
                <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">🛒</div>
                <div>Cart is empty</div>
                <div style="font-size: 0.75rem; color: #94a3b8; margin-top: 0.25rem;">Tap or scan products to start billing</div>
            </div>
            <div id="cartItemList"></div>
        </div>

        <!-- Cart Footer: Tax Details & Checkout CTA -->
        <div class="cart-footer">
            <div class="summary-row">
                <span><?= e(__('subtotal')) ?></span>
                <span id="summarySubtotal">₹ 0.00</span>
            </div>

            <?php if ($regMode === 'regular'): ?>
                <div class="summary-row">
                    <span><?= e(__('taxable_amount')) ?></span>
                    <span id="summaryTaxable">₹ 0.00</span>
                </div>
                <div class="summary-row" id="rowCgst">
                    <span><?= e(__('cgst')) ?></span>
                    <span id="summaryCgst">₹ 0.00</span>
                </div>
                <div class="summary-row" id="rowSgst">
                    <span><?= e(__('sgst')) ?></span>
                    <span id="summarySgst">₹ 0.00</span>
                </div>
                <div class="summary-row" id="rowIgst" style="display: none;">
                    <span><?= e(__('igst')) ?> (Interstate)</span>
                    <span id="summaryIgst">₹ 0.00</span>
                </div>
            <?php elseif ($regMode === 'composition'): ?>
                <div style="font-size: 0.72rem; color: #d97706; background: #fffbeb; padding: 0.4rem; border-radius: 4px; margin-bottom: 0.4rem;">
                    ℹ️ <?= e(__('composition_notice')) ?>
                </div>
            <?php else: ?>
                <div style="font-size: 0.72rem; color: #64748b; background: #f1f5f9; padding: 0.4rem; border-radius: 4px; margin-bottom: 0.4rem;">
                    ℹ️ <?= e(__('unregistered_notice')) ?>
                </div>
            <?php endif; ?>

            <div class="summary-row grand-total">
                <span><?= e(__('grand_total')) ?></span>
                <span id="summaryGrandTotal">₹ 0.00</span>
            </div>

            <!-- Payment Modes -->
            <div class="payment-buttons">
                <button type="button" class="pay-btn <?= $defaultPay === 'cash' ? 'active' : '' ?>" onclick="setPaymentMethod('cash', this)">💵 <?= e(__('cash')) ?></button>
                <button type="button" class="pay-btn <?= $defaultPay === 'upi' ? 'active' : '' ?>" onclick="setPaymentMethod('upi', this)">📱 <?= e(__('upi')) ?></button>
                <button type="button" class="pay-btn <?= $defaultPay === 'card' ? 'active' : '' ?>" onclick="setPaymentMethod('card', this)">💳 <?= e(__('card')) ?></button>
                <button type="button" class="pay-btn <?= $defaultPay === 'credit' ? 'active' : '' ?>" onclick="setPaymentMethod('credit', this)">📒 <?= e(__('credit_khata')) ?></button>
            </div>

            <button type="button" id="btnCheckout" class="btn-checkout" onclick="executeCheckout()" disabled>
                💳 <?= e(__('pay_checkout')) ?>
            </button>
        </div>
    </section>
</div>

<!-- Quick Customer Modal -->
<div id="quickCustomerModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; padding: 2rem 1rem;">
    <div style="max-width: 520px; margin: 3rem auto; background: #fff; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; background: #0f172a; color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700;">Quick Add Walk-in/Customer</h3>
            <button onclick="closeQuickCustomerModal()" style="background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form onsubmit="saveQuickCustomer(event)" style="padding: 1.5rem;">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group">
                <label class="form-label">Full Name *</label>
                <input type="text" id="qcName" class="form-input" required placeholder="Enter customer name">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Phone Number</label>
                    <input type="tel" id="qcPhone" class="form-input" placeholder="10-digit mobile number" maxlength="10" inputmode="numeric" pattern="[0-9]*" autocomplete="tel" oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,10)">
                </div>
                <div>
                    <label class="form-label">State</label>
                    <select id="qcState" class="form-input">
                        <option value="21" selected>Odisha (21)</option>
                        <option value="19">West Bengal (19)</option>
                        <option value="20">Jharkhand (20)</option>
                        <option value="27">Maharashtra (27)</option>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Email (optional)</label>
                    <input type="email" id="qcEmail" class="form-input" placeholder="customer@mail.com">
                </div>
                <div>
                    <label class="form-label">GSTIN (optional)</label>
                    <input type="text" id="qcGstin" class="form-input" placeholder="15-digit GSTIN">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Billing Address (optional)</label>
                <input type="text" id="qcAddress" class="form-input" placeholder="Street, City, PIN">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="closeQuickCustomerModal()" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save & Select</button>
            </div>
        </form>
    </div>
</div>

<!-- Embedded POS Script -->
<script>
let cart = [];
let selectedPaymentMethod = <?= json_encode($defaultPay) ?>;
let pendingIdempotencyKey = null;
const tenantRegistrationMode = '<?= e($regMode) ?>';
const supplierStateCode = '<?= e($taxProfile['registered_state_code'] ?? '21') ?>';
const isOdia = <?= $isOdia ? 'true' : 'false' ?>;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '<?= e(csrf_token()) ?>';
const apiSessionToken = document.querySelector('meta[name="api-token"]')?.content || '<?= e(api_token()) ?>';

function apiHeaders(extra = {}) {
    return Object.assign({
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken,
        'X-API-Token': apiSessionToken
    }, extra);
}

function newIdempotencyKey() {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID();
    }
    return 'idem-' + Date.now() + '-' + Math.random().toString(36).slice(2, 12);
}

function filterCategory(cat) {
    document.querySelectorAll('.cat-chip').forEach(el => el.classList.remove('active'));
    event.target.classList.add('active');

    document.querySelectorAll('.product-card').forEach(card => {
        if (cat === 'ALL' || card.dataset.category === cat) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Live Search
document.getElementById('posSearchInput').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase().trim();
    document.querySelectorAll('.product-card').forEach(card => {
        const en = (card.dataset.nameEn || '').toLowerCase();
        const or = (card.dataset.nameOr || '').toLowerCase();
        const match = en.includes(term) || or.includes(term);
        card.style.display = match ? 'flex' : 'none';
    });
});

function addToCart(p) {
    pendingIdempotencyKey = null;
    const existing = cart.find(item => item.product_id === p.id);
    if (existing) {
        existing.quantity += 1;
    } else {
        cart.push({
            product_id: p.id,
            name_en: p.name_en,
            name_or: p.name_or,
            unit: p.unit,
            unit_price: p.price,
            quantity: 1,
            discount: 0,
            tax_rate_percent: p.tax_rate,
            tax_classification: p.tax_class,
            price_includes_tax: p.tax_inclusive
        });
    }
    renderCart();
}

function updateQty(idx, delta) {
    pendingIdempotencyKey = null;
    if (cart[idx]) {
        cart[idx].quantity += delta;
        if (cart[idx].quantity <= 0) {
            cart.splice(idx, 1);
        }
        renderCart();
    }
}

function clearCart() {
    cart = [];
    pendingIdempotencyKey = null;
    renderCart();
}

function setPaymentMethod(method, btn) {
    selectedPaymentMethod = method;
    document.querySelectorAll('.pay-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

function recalculateCart() {
    pendingIdempotencyKey = null;
    renderCart();
}

function renderCart() {
    const list = document.getElementById('cartItemList');
    const emptyState = document.getElementById('cartEmptyState');
    const checkoutBtn = document.getElementById('btnCheckout');

    list.innerHTML = '';

    if (cart.length === 0) {
        emptyState.style.display = 'block';
        checkoutBtn.disabled = true;
        updateTotals({ subtotal: 0, taxable_amount: 0, cgst_amount: 0, sgst_amount: 0, igst_amount: 0, total_amount: 0 });
        return;
    }

    emptyState.style.display = 'none';
    checkoutBtn.disabled = false;

    // Render line items
    cart.forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'cart-item-row';
        const displayName = (isOdia && item.name_or) ? item.name_or : item.name_en;
        const lineTotal = (item.quantity * item.unit_price) - item.discount;

        row.innerHTML = `
            <div class="cart-item-info">
                <div class="cart-item-title">${displayName}</div>
                <div class="cart-item-sub">₹ ${item.unit_price.toFixed(2)} / ${item.unit}</div>
            </div>
            <div class="cart-item-controls">
                <button type="button" class="qty-btn" onclick="updateQty(${index}, -1)">-</button>
                <span class="qty-val">${item.quantity}</span>
                <button type="button" class="qty-btn" onclick="updateQty(${index}, 1)">+</button>
            </div>
            <div class="cart-item-total">₹ ${lineTotal.toFixed(2)}</div>
        `;
        list.appendChild(row);
    });

    // Request server-side calculation preview
    const placeOfSupply = document.getElementById('cartPlaceOfSupply').value;
    fetch(<?= json_encode(api_url('pos/calculate')) ?>, {
        method: 'POST',
        headers: apiHeaders(),
        body: JSON.stringify({
            items: cart,
            place_of_supply: placeOfSupply
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) {
            console.warn(data.error);
            return;
        }
        updateTotals(data);
    })
    .catch(err => console.error(err));
}

function updateTotals(data) {
    document.getElementById('summarySubtotal').innerText = '₹ ' + (data.subtotal || 0).toFixed(2);
    document.getElementById('summaryGrandTotal').innerText = '₹ ' + (data.total_amount || 0).toFixed(2);

    if (document.getElementById('summaryTaxable')) {
        document.getElementById('summaryTaxable').innerText = '₹ ' + (data.taxable_amount || 0).toFixed(2);
        document.getElementById('summaryCgst').innerText = '₹ ' + (data.cgst_amount || 0).toFixed(2);
        document.getElementById('summarySgst').innerText = '₹ ' + (data.sgst_amount || 0).toFixed(2);
        document.getElementById('summaryIgst').innerText = '₹ ' + (data.igst_amount || 0).toFixed(2);

        if (data.is_interstate) {
            document.getElementById('rowCgst').style.display = 'none';
            document.getElementById('rowSgst').style.display = 'none';
            document.getElementById('rowIgst').style.display = 'flex';
        } else {
            document.getElementById('rowCgst').style.display = 'flex';
            document.getElementById('rowSgst').style.display = 'flex';
            document.getElementById('rowIgst').style.display = 'none';
        }
    }
}

function toggleWalkinFields() {
    pendingIdempotencyKey = null;
    const custSelect = document.getElementById('cartCustomer');
    const walkinDiv = document.getElementById('walkinFields');
    if (!custSelect.value) {
        walkinDiv.style.display = 'grid';
    } else {
        walkinDiv.style.display = 'none';
        // Auto select customer's state if present
        const selectedOpt = custSelect.options[custSelect.selectedIndex];
        const stateCode = selectedOpt.getAttribute('data-state');
        if (stateCode) {
            document.getElementById('cartPlaceOfSupply').value = stateCode;
            recalculateCart();
        }
    }
}

function openQuickCustomerModal() {
    document.getElementById('quickCustomerModal').style.display = 'block';
}

function closeQuickCustomerModal() {
    document.getElementById('quickCustomerModal').style.display = 'none';
}

function saveQuickCustomer(e) {
    e.preventDefault();
    const name = document.getElementById('qcName').value.trim();
    const phone = document.getElementById('qcPhone').value.trim();
    const gstin = document.getElementById('qcGstin').value.trim();
    const stateCode = document.getElementById('qcState').value;
    const address = document.getElementById('qcAddress').value.trim();

    if (!name) {
        alert('Customer name is required');
        return;
    }

    fetch(<?= json_encode(api_url('pos/quick-customer')) ?>, {
        method: 'POST',
        headers: apiHeaders(),
        body: JSON.stringify({
            name: name,
            phone: phone,
            gstin: gstin,
            state_code: stateCode,
            address: address
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success && res.customer) {
            const select = document.getElementById('cartCustomer');
            const opt = document.createElement('option');
            opt.value = res.customer.id;
            opt.text = res.customer.name + (res.customer.phone ? ' (' + res.customer.phone + ')' : '');
            opt.setAttribute('data-state', res.customer.state_code);
            select.appendChild(opt);
            select.value = res.customer.id;
            toggleWalkinFields();
            closeQuickCustomerModal();
            alert('Customer added and selected!');
        } else {
            alert('Error adding customer: ' + (res.error || 'Failed'));
        }
    })
    .catch(err => alert('Network error saving customer.'));
}

function downloadInvoicePdf(invoiceId, invoiceNumber) {
    // Saves the invoice PDF to the user's downloads on every completed sale.
    try {
        const a = document.createElement('a');
        a.href = '/app/invoices/' + invoiceId + '/pdf?download=1';
        a.download = invoiceNumber + '.pdf';
        a.rel = 'noopener';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    } catch (e) {
        // Download is best-effort; the invoice remains available under Invoices → PDF.
    }
}

function executeCheckout() {
    if (cart.length === 0) return;

    const btn = document.getElementById('btnCheckout');
    btn.disabled = true;
    btn.innerText = 'Processing Sale...';

    const customerId = document.getElementById('cartCustomer').value;
    const walkinName = !customerId ? document.getElementById('walkinName').value.trim() : null;
    const walkinPhoneRaw = !customerId ? document.getElementById('walkinPhone').value : null;
    const walkinPhone = walkinPhoneRaw ? walkinPhoneRaw.replace(/[^0-9]/g, '').slice(0, 10) : null;

    // Phone is optional, but when given it must be exactly 10 digits (server enforces the same rule).
    if (walkinPhone && !/^[0-9]{10}$/.test(walkinPhone)) {
        alert('Phone must be exactly 10 digits.');
        document.getElementById('walkinPhone').focus();
        btn.disabled = false;
        btn.innerText = '💳 Complete Sale & Print';
        return;
    }

    const payload = {
        items: cart,
        customer_id: customerId || null,
        walkin_name: walkinName || null,
        walkin_phone: walkinPhone || null,
        place_of_supply: document.getElementById('cartPlaceOfSupply').value,
        payment_method: selectedPaymentMethod
    };

    if (selectedPaymentMethod === 'credit' && !customerId) {
        alert('Credit (Khata) sale requires a registered customer.');
        btn.disabled = false;
        return;
    }

    if (!pendingIdempotencyKey) {
        pendingIdempotencyKey = newIdempotencyKey();
    }

    const printWindow = window.open('', '_blank', 'width=450,height=700');

    fetch(<?= json_encode(api_url('pos/checkout')) ?>, {
        method: 'POST',
        headers: apiHeaders({ 'Idempotency-Key': pendingIdempotencyKey }),
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            pendingIdempotencyKey = null;
            // 1) save the PDF receipt to the user's downloads
            downloadInvoicePdf(res.invoice_id, res.invoice_number);
            // 2) open the thermal receipt for printing
            if (printWindow && !printWindow.closed) {
                printWindow.location.href = '/app/invoices/' + res.invoice_id + '/thermal';
            } else {
                window.open('/app/invoices/' + res.invoice_id + '/thermal', '_blank', 'width=450,height=700');
            }
            clearCart();
            alert('Sale Completed! Invoice #' + res.invoice_number + ' generated.' + (res.replayed ? ' (Recovered previous result)' : ''));
            // Reload after the download has been handed off to the browser.
            setTimeout(function () { window.location.reload(); }, 800);
        } else {
            if (printWindow && !printWindow.closed) {
                printWindow.close();
            }
            alert('Checkout Error: ' + (res.error || 'Failed to complete sale.'));
            btn.disabled = false;
            btn.innerText = '💳 Complete Sale & Print';
        }
    })
    .catch(err => {
        if (printWindow && !printWindow.closed) {
            printWindow.close();
        }
        alert('Network/Server Error during checkout. You can retry safely.');
        btn.disabled = false;
        btn.innerText = '💳 Complete Sale & Print';
    });
}
</script>


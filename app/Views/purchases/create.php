<div class="container" style="max-width: 1100px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">📥 New Purchase Bill</h1>
            <p style="font-size: 0.85rem; color: #64748b;">Record a dealer bill. Stock is increased immediately.</p>
        </div>
        <a href="/app/purchases" class="btn btn-secondary">← Back</a>
    </div>

    <form action="/app/purchases" method="POST" id="purchaseForm">
        <?= csrf_field() ?>
        <div class="card" style="padding: 1.25rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Supplier</label>
                    <select name="supplier_id" class="form-input">
                        <option value="">-- Unspecified --</option>
                        <?php foreach ($suppliers as $supplier): ?>
                            <option value="<?= (int)$supplier['id'] ?>"><?= e($supplier['name']) ?><?= $supplier['gstin'] ? ' — ' . e($supplier['gstin']) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="form-label">Bill Date *</label>
                    <input type="date" name="bill_date" class="form-input" value="<?= e(date('Y-m-d')) ?>" required>
                </div>
                <div>
                    <label class="form-label">Supplier Bill Ref</label>
                    <input type="text" name="supplier_bill_ref" class="form-input" placeholder="Dealer invoice no." maxlength="100">
                </div>
                <div>
                    <label class="form-label">Paid Now (₹)</label>
                    <input type="number" step="0.01" min="0" name="paid_amount" id="paidAmount" class="form-input" value="0">
                </div>
                <div>
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-input">
                        <option value="cash">Cash</option>
                        <option value="upi">UPI</option>
                        <option value="card">Card</option>
                        <option value="bank">Bank Transfer</option>
                    </select>
                </div>
            </div>

            <div class="table-responsive">
                <table class="data-table" id="itemsTable">
                    <thead>
                        <tr>
                            <th style="min-width: 220px;">Product / Item</th>
                            <th style="width: 110px;">Quantity</th>
                            <th style="width: 120px;">Unit Cost</th>
                            <th style="width: 100px;">Tax %</th>
                            <th style="width: 120px;">Taxable</th>
                            <th style="width: 110px;">Tax</th>
                            <th style="width: 120px;">Line Total</th>
                            <th style="width: 50px;"></th>
                        </tr>
                    </thead>
                    <tbody id="itemsBody"></tbody>
                </table>
            </div>

            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-top: 1rem; gap: 1rem; flex-wrap: wrap;">
                <button type="button" class="btn btn-secondary" onclick="addItemRow()">+ Add Line</button>
                <div style="min-width: 280px;">
                    <div style="display:flex;justify-content:space-between;padding:0.3rem 0;font-size:0.9rem;color:#475569;">
                        <span>Taxable Total</span><span id="sumTaxable">₹ 0.00</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:0.3rem 0;font-size:0.9rem;color:#475569;">
                        <span>Tax Total</span><span id="sumTax">₹ 0.00</span>
                    </div>
                    <div style="display:flex;justify-content:space-between;padding:0.6rem 0;border-top:2px solid #0f172a;font-size:1.15rem;font-weight:800;">
                        <span>Grand Total</span><span id="sumGrand">₹ 0.00</span>
                    </div>
                </div>
            </div>

            <div class="form-group" style="margin-top: 1rem;">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-input" rows="2" maxlength="1000" placeholder="Optional remarks"></textarea>
            </div>

            <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
                <a href="/app/purchases" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Purchase Bill</button>
            </div>
        </div>
    </form>
</div>

<script>
const purchaseProducts = <?= json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
let rowSeq = 0;

function addItemRow() {
    const tbody = document.getElementById('itemsBody');
    const idx = rowSeq++;
    const tr = document.createElement('tr');
    tr.dataset.row = idx;

    const options = ['<option value="">-- Custom item --</option>']
        .concat(purchaseProducts.map(p => `<option value="${p.id}" data-cost="${p.cost_price}" data-tax="${p.tax_rate}" data-name="${escapeAttr(p.name_en)}">${escapeHtml(p.name_en)}${p.sku ? ' (' + escapeHtml(p.sku) + ')' : ''}</option>`))
        .join('');

    tr.innerHTML = `
        <td>
            <select class="form-input product-select" style="margin-bottom:0.3rem;">
                ${options}
            </select>
            <input type="text" class="form-input item-name" placeholder="Item name (required for custom)" style="font-size:0.8rem;">
        </td>
        <td><input type="number" step="0.001" min="0.001" class="form-input qty" value="1"></td>
        <td><input type="number" step="0.01" min="0" class="form-input cost" value="0"></td>
        <td><input type="number" step="0.01" min="0" max="100" class="form-input tax" value="0"></td>
        <td class="taxable" style="font-weight:600;">₹ 0.00</td>
        <td class="taxamt" style="color:#2563eb;">₹ 0.00</td>
        <td class="linetotal" style="font-weight:700;">₹ 0.00</td>
        <td><button type="button" class="btn btn-secondary" style="padding:0.2rem 0.45rem;" onclick="removeItemRow(this)">✕</button></td>
    `;

    tbody.appendChild(tr);

    const select = tr.querySelector('.product-select');
    select.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const nameInput = tr.querySelector('.item-name');
        if (this.value) {
            tr.querySelector('.cost').value = Number(opt.dataset.cost || 0).toFixed(2);
            tr.querySelector('.tax').value = Number(opt.dataset.tax || 0);
            nameInput.value = opt.dataset.name || '';
            nameInput.readOnly = true;
        } else {
            nameInput.readOnly = false;
        }
        updateRow(tr);
    });

    tr.querySelectorAll('.qty, .cost, .tax').forEach(input => {
        input.addEventListener('input', () => updateRow(tr));
    });

    updateRow(tr);
}

function removeItemRow(btn) {
    const tbody = document.getElementById('itemsBody');
    btn.closest('tr').remove();
    if (!tbody.children.length) {
        addItemRow();
    }
    recalcTotals();
}

function updateRow(tr) {
    const qty = Number(tr.querySelector('.qty').value || 0);
    const cost = Number(tr.querySelector('.cost').value || 0);
    const taxRate = Number(tr.querySelector('.tax').value || 0);
    const taxable = qty * cost;
    const tax = taxable * taxRate / 100;
    tr.querySelector('.taxable').textContent = '₹ ' + taxable.toFixed(2);
    tr.querySelector('.taxamt').textContent = '₹ ' + tax.toFixed(2);
    tr.querySelector('.linetotal').textContent = '₹ ' + (taxable + tax).toFixed(2);
    recalcTotals();
}

function recalcTotals() {
    let taxable = 0, tax = 0;
    document.querySelectorAll('#itemsBody tr').forEach(tr => {
        const qty = Number(tr.querySelector('.qty').value || 0);
        const cost = Number(tr.querySelector('.cost').value || 0);
        const rate = Number(tr.querySelector('.tax').value || 0);
        const t = qty * cost;
        taxable += t;
        tax += t * rate / 100;
    });
    document.getElementById('sumTaxable').textContent = '₹ ' + taxable.toFixed(2);
    document.getElementById('sumTax').textContent = '₹ ' + tax.toFixed(2);
    document.getElementById('sumGrand').textContent = '₹ ' + (taxable + tax).toFixed(2);
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, ch => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[ch]));
}
function escapeAttr(value) {
    return escapeHtml(value);
}

document.getElementById('purchaseForm').addEventListener('submit', function (e) {
    const rows = Array.from(document.querySelectorAll('#itemsBody tr'));
    if (!rows.length) {
        e.preventDefault();
        alert('Add at least one purchase line.');
        return;
    }

    const cleaned = [];
    let invalid = false;
    rows.forEach((tr, idx) => {
        const select = tr.querySelector('.product-select');
        const name = tr.querySelector('.item-name').value.trim();
        const qty = Number(tr.querySelector('.qty').value || 0);
        const cost = Number(tr.querySelector('.cost').value || 0);
        const tax = Number(tr.querySelector('.tax').value || 0);

        if (!select.value && !name) {
            invalid = true;
            tr.style.background = '#fef2f2';
            return;
        }
        if (qty <= 0) {
            invalid = true;
            tr.style.background = '#fef2f2';
            return;
        }

        cleaned.push({ product_id: select.value || '', item_name: name, quantity: qty, unit_cost: cost, tax_rate: tax });
    });

    if (invalid) {
        e.preventDefault();
        alert('Every line needs a product/name and a quantity greater than zero.');
        return;
    }

    document.querySelectorAll('#itemsBody input, #itemsBody select').forEach(el => el.disabled = true);
    cleaned.forEach((line, i) => {
        Object.keys(line).forEach(key => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = `items[${i}][${key}]`;
            input.value = line[key];
            this.appendChild(input);
        });
    });
});

addItemRow();
</script>

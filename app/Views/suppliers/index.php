<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">🏭 <?= e(__('suppliers')) ?> / Dealers</h1>
            <p style="font-size: 0.85rem; color: #64748b;">Dealers, wholesale distributors, GSTIN, and linked product catalogue</p>
        </div>
        <button onclick="document.getElementById('addSupplierModal').style.display='block'" class="btn btn-primary">
            + Add Dealer / Supplier
        </button>
    </div>

    <!-- Suppliers Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Dealer / Company Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>GSTIN / State</th>
                        <th>Address</th>
                        <th>Supplied Products</th>
                        <th>Payable Balance</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="8" style="text-align: center; padding: 2rem; color: #94a3b8;">No suppliers/dealers configured yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td style="font-weight: 700; color: #0f172a;"><?= e($s['name']) ?></td>
                                <td style="color: #475569; font-weight: 600;"><?= e($s['phone'] ?? '-') ?></td>
                                <td style="color: #64748b;"><?= e($s['email'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($s['gstin'])): ?>
                                        <span class="badge badge-info"><?= e($s['gstin']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.8rem;">Unregistered (State <?= e($s['state_code'] ?? '21') ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.8rem; color: #64748b;"><?= e($s['address'] ?? '-') ?></td>
                                <td>
                                    <span class="badge badge-success"><?= (int)$s['product_count'] ?> products linked</span>
                                </td>
                                <td>
                                    <span style="font-weight:700;color:<?= (float)$s['payable_balance'] > 0 ? '#ef4444' : '#059669' ?>;">
                                        <?= money_format_inr($s['payable_balance']) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <button type="button" class="btn btn-secondary" style="font-size:0.72rem;padding:0.25rem 0.5rem;"
                                        data-supplier="<?= e(json_encode([
                                            'id' => (int)$s['id'],
                                            'name' => $s['name'],
                                            'phone' => $s['phone'],
                                            'email' => $s['email'],
                                            'gstin' => $s['gstin'],
                                            'state_code' => $s['state_code'] ?? '21',
                                            'address' => $s['address'],
                                        ])) ?>"
                                        onclick="openEditSupplier(this)">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Supplier Modal -->
<div id="addSupplierModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; padding: 2rem 1rem;">
    <div style="max-width: 520px; margin: 3rem auto; background: #fff; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; background: #0f172a; color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700;">Add Dealer / Supplier</h3>
            <button onclick="document.getElementById('addSupplierModal').style.display='none'" style="background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form action="/app/suppliers" method="POST" style="padding: 1.5rem;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Dealer / Company Name *</label>
                <input type="text" name="name" class="form-input" placeholder="e.g. Utkal Agro Foods & Edible Oils" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-input" placeholder="e.g. 9437000000">
                </div>
                <div>
                    <label class="form-label">State</label>
                    <select name="state_code" class="form-input">
                        <option value="21" selected>Odisha (21)</option>
                        <option value="19">West Bengal (19)</option>
                        <option value="20">Jharkhand (20)</option>
                        <option value="27">Maharashtra (27)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-input" placeholder="dealer@distributor.in">
            </div>
            <div class="form-group">
                <label class="form-label">Supplier GSTIN</label>
                <input type="text" name="gstin" class="form-input" placeholder="15-digit GSTIN">
            </div>
            <div class="form-group">
                <label class="form-label">Godown / Office Address</label>
                <input type="text" name="address" class="form-input" placeholder="Commercial Area, City">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('addSupplierModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Dealer</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Supplier Modal -->
<div id="editSupplierModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; padding: 2rem 1rem;">
    <div style="max-width: 520px; margin: 3rem auto; background: #fff; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden; max-height: 90vh; overflow-y: auto;">
        <div style="padding: 1.25rem 1.5rem; background: #0f172a; color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700;">Edit Dealer / Supplier</h3>
            <button type="button" onclick="document.getElementById('editSupplierModal').style.display='none'" style="background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form id="editSupplierForm" method="POST" style="padding: 1.5rem;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Dealer / Company Name *</label>
                <input type="text" name="name" id="es_name" class="form-input" required maxlength="100">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" id="es_phone" class="form-input">
                </div>
                <div>
                    <label class="form-label">State</label>
                    <select name="state_code" id="es_state_code" class="form-input">
                        <option value="21">Odisha (21)</option>
                        <option value="19">West Bengal (19)</option>
                        <option value="20">Jharkhand (20)</option>
                        <option value="27">Maharashtra (27)</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" id="es_email" class="form-input">
            </div>
            <div class="form-group">
                <label class="form-label">Supplier GSTIN</label>
                <input type="text" name="gstin" id="es_gstin" class="form-input" maxlength="15" style="text-transform:uppercase;">
            </div>
            <div class="form-group">
                <label class="form-label">Godown / Office Address</label>
                <input type="text" name="address" id="es_address" class="form-input" maxlength="500">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('editSupplierModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Dealer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditSupplier(button) {
    const s = JSON.parse(button.dataset.supplier);
    document.getElementById('editSupplierForm').action = '/app/suppliers/' + s.id + '/update';
    document.getElementById('es_name').value = s.name || '';
    document.getElementById('es_phone').value = s.phone || '';
    document.getElementById('es_email').value = s.email || '';
    document.getElementById('es_gstin').value = s.gstin || '';
    document.getElementById('es_state_code').value = s.state_code || '21';
    document.getElementById('es_address').value = s.address || '';
    document.getElementById('editSupplierModal').style.display = 'block';
}
</script>

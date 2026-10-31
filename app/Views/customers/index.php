<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">👥 <?= e(__('customers')) ?></h1>
            <p style="font-size: 0.85rem; color: #64748b;">Customer master directory, Khata receivables, and tax registration details</p>
        </div>
        <button onclick="document.getElementById('addCustomerModal').style.display='block'" class="btn btn-primary">
            + Add New Customer
        </button>
    </div>

    <!-- Customers Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Customer Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>GSTIN / State</th>
                        <th>Address</th>
                        <th>Invoices Billed</th>
                        <th>Total Purchased</th>
                        <th>Credit Limit</th>
                        <th>Khata Balance</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr><td colspan="10" style="text-align: center; padding: 2rem; color: #94a3b8;">No registered customers yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td style="font-weight: 700; color: #0f172a;"><?= e($c['name']) ?></td>
                                <td style="color: #475569;"><?= e($c['phone'] ?? '-') ?></td>
                                <td style="color: #64748b;"><?= e($c['email'] ?? '-') ?></td>
                                <td>
                                    <?php if (!empty($c['gstin'])): ?>
                                        <span class="badge badge-info"><?= e($c['gstin']) ?></span>
                                    <?php else: ?>
                                        <span style="color: #94a3b8; font-size: 0.8rem;">Consumer (State <?= e($c['state_code'] ?? '21') ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 0.8rem; color: #64748b;"><?= e($c['address'] ?? '-') ?></td>
                                <td><?= (int)$c['invoice_count'] ?> bills</td>
                                <td style="font-weight: 600;"><?= money_format_inr($c['total_purchased']) ?></td>
                                <td><?= money_format_inr($c['credit_limit']) ?></td>
                                <td>
                                    <span style="font-weight: 700; color: <?= (float)$c['balance'] > 0 ? '#ef4444' : '#059669' ?>;">
                                        <?= money_format_inr($c['balance']) ?>
                                    </span>
                                </td>
                                <td style="text-align:right;">
                                    <button type="button" class="btn btn-secondary" style="font-size:0.72rem;padding:0.25rem 0.5rem;"
                                        data-customer="<?= e(json_encode([
                                            'id' => (int)$c['id'],
                                            'name' => $c['name'],
                                            'phone' => $c['phone'],
                                            'email' => $c['email'],
                                            'gstin' => $c['gstin'],
                                            'state_code' => $c['state_code'] ?? '21',
                                            'address' => $c['address'],
                                            'credit_limit' => $c['credit_limit'],
                                        ])) ?>"
                                        onclick="openEditCustomer(this)">Edit</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Customer Modal -->
<div id="addCustomerModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; padding: 2rem 1rem;">
    <div style="max-width: 560px; margin: 3rem auto; background: #fff; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden; max-height: 90vh; overflow-y: auto;">
        <div style="padding: 1.25rem 1.5rem; background: #0f172a; color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700;">Add Permanent Customer</h3>
            <button onclick="document.getElementById('addCustomerModal').style.display='none'" style="background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form action="/app/customers" method="POST" style="padding: 1.5rem;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Customer Name *</label>
                <input type="text" name="name" class="form-input" placeholder="e.g. Priyabrata Mohapatra" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" class="form-input" placeholder="e.g. 9861000000">
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
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" placeholder="customer@mail.com">
                </div>
                <div>
                    <label class="form-label">Credit Limit (₹)</label>
                    <input type="number" step="100" name="credit_limit" class="form-input" value="5000">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">GSTIN (for B2B Customers)</label>
                <input type="text" name="gstin" class="form-input" placeholder="15-digit GSTIN (e.g. 21AAAAA0000A1Z5)" maxlength="15" style="text-transform:uppercase;">
            </div>
            <div class="form-group">
                <label class="form-label">Billing Address</label>
                <input type="text" name="address" class="form-input" placeholder="Street, City, PIN">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('addCustomerModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Customer</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Customer Modal -->
<div id="editCustomerModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; padding: 2rem 1rem;">
    <div style="max-width: 560px; margin: 3rem auto; background: #fff; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden; max-height: 90vh; overflow-y: auto;">
        <div style="padding: 1.25rem 1.5rem; background: #0f172a; color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700;">Edit Customer</h3>
            <button type="button" onclick="document.getElementById('editCustomerModal').style.display='none'" style="background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form id="editCustomerForm" method="POST" style="padding: 1.5rem;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Customer Name *</label>
                <input type="text" name="name" id="ec_name" class="form-input" required maxlength="100">
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Phone Number</label>
                    <input type="tel" name="phone" id="ec_phone" class="form-input">
                </div>
                <div>
                    <label class="form-label">State</label>
                    <select name="state_code" id="ec_state_code" class="form-input">
                        <option value="21">Odisha (21)</option>
                        <option value="19">West Bengal (19)</option>
                        <option value="20">Jharkhand (20)</option>
                        <option value="27">Maharashtra (27)</option>
                    </select>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="ec_email" class="form-input">
                </div>
                <div>
                    <label class="form-label">Credit Limit (₹)</label>
                    <input type="number" step="0.01" min="0" name="credit_limit" id="ec_credit_limit" class="form-input">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">GSTIN</label>
                <input type="text" name="gstin" id="ec_gstin" class="form-input" maxlength="15" style="text-transform:uppercase;">
            </div>
            <div class="form-group">
                <label class="form-label">Billing Address</label>
                <input type="text" name="address" id="ec_address" class="form-input" maxlength="500">
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('editCustomerModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Customer</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditCustomer(button) {
    const c = JSON.parse(button.dataset.customer);
    document.getElementById('editCustomerForm').action = '/app/customers/' + c.id + '/update';
    document.getElementById('ec_name').value = c.name || '';
    document.getElementById('ec_phone').value = c.phone || '';
    document.getElementById('ec_email').value = c.email || '';
    document.getElementById('ec_gstin').value = c.gstin || '';
    document.getElementById('ec_state_code').value = c.state_code || '21';
    document.getElementById('ec_address').value = c.address || '';
    document.getElementById('ec_credit_limit').value = c.credit_limit || 0;
    document.getElementById('editCustomerModal').style.display = 'block';
}
</script>

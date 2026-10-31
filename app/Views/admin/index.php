<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.5rem; font-weight: 800; color: #0f172a;">🛡️ Platform Administration</h1>
            <p style="font-size: 0.85rem; color: #64748b;">Manage business tenants, GST tax modes, subscription status, and tenant isolation</p>
        </div>
        <button onclick="document.getElementById('newTenantModal').style.display='block'" class="btn btn-primary">
            + Onboard New Business Tenant
        </button>
    </div>

    <!-- Tenant List Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Business Name</th>
                        <th>Owner & Contact</th>
                        <th>GST Mode</th>
                        <th>GSTIN</th>
                        <th>Invoices</th>
                        <th>Total Sales</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenants as $t): ?>
                        <tr>
                            <td style="font-weight: 700;">#<?= e($t['id']) ?></td>
                            <td>
                                <div style="font-weight: 700; color: #0f172a;"><?= e($t['name']) ?></div>
                                <div style="font-size: 0.75rem; color: #94a3b8;"><?= e($t['slug']) ?>.<?= e($baseDomain ?? 'arthadesk.in') ?></div>
                            </td>
                            <td>
                                <div><?= e($t['owner_name'] ?? '-') ?></div>
                                <div style="font-size: 0.75rem; color: #64748b;"><?= e($t['phone'] ?? '') ?></div>
                            </td>
                            <td>
                                <?php if ($t['registration_mode'] === 'regular'): ?>
                                    <span class="mode-tag mode-regular">Regular GST</span>
                                <?php elseif ($t['registration_mode'] === 'composition'): ?>
                                    <span class="mode-tag mode-composition">Composition</span>
                                <?php else: ?>
                                    <span class="mode-tag mode-unregistered">Unregistered</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-family: monospace; font-size: 0.8rem;"><?= e($t['gstin'] ?? 'N/A') ?></td>
                            <td><?= (int)$t['invoice_count'] ?></td>
                            <td style="font-weight: 700;"><?= money_format_inr($t['total_sales']) ?></td>
                            <td>
                                <?php if ($t['status'] === 'active'): ?>
                                    <span class="badge badge-success">ACTIVE</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">SUSPENDED</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: flex; justify-content: flex-end; gap: 0.4rem;">
                                    <!-- Switch to this store -->
                                    <form action="/auth/switch-tenant" method="POST" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="tenant_id" value="<?= e($t['id']) ?>">
                                        <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;">
                                            🛒 Open POS
                                        </button>
                                    </form>

                                    <!-- Toggle Suspend / Activate -->
                                    <form action="/admin/tenants/status" method="POST" style="display: inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="tenant_id" value="<?= e($t['id']) ?>">
                                        <button type="submit" class="btn <?= $t['status'] === 'active' ? 'btn-danger' : 'btn-primary' ?>" style="padding: 0.25rem 0.6rem; font-size: 0.75rem;">
                                            <?= $t['status'] === 'active' ? 'Suspend' : 'Reactivate' ?>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Onboard New Tenant Modal -->
<div id="newTenantModal" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.6); z-index: 1000; padding: 2rem 1rem;">
    <div style="max-width: 550px; margin: 3rem auto; background: #fff; border-radius: 12px; box-shadow: var(--shadow-lg); overflow: hidden;">
        <div style="padding: 1.25rem 1.5rem; background: #0f172a; color: #fff; display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1.1rem; font-weight: 700;">Onboard Business Tenant</h3>
            <button onclick="document.getElementById('newTenantModal').style.display='none'" style="background: none; border: none; color: #fff; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form action="/admin/tenants" method="POST" style="padding: 1.5rem;">
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label">Business Name *</label>
                <input type="text" name="name" class="form-input" placeholder="e.g. Lingaraj Provision Store" required>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Store Address (slug)</label>
                    <input type="text" name="slug" class="form-input" placeholder="lingaraj-store" pattern="[a-z0-9-]{3,50}">
                    <div style="font-size:0.7rem;color:#94a3b8;margin-top:0.2rem;">Becomes slug.<?= e($baseDomain ?? 'arthadesk.in') ?>. Leave blank to auto-generate.</div>
                </div>
                <div>
                    <label class="form-label">Owner Name</label>
                    <input type="text" name="owner_name" class="form-input" placeholder="e.g. Subash Pradhan">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Store Phone</label>
                    <input type="text" name="phone" class="form-input" placeholder="e.g. 9861000000">
                </div>
                <div>
                    <label class="form-label">Store Email</label>
                    <input type="email" name="email" class="form-input" placeholder="store@example.com">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label class="form-label">Owner Login Email</label>
                    <input type="email" name="owner_email" class="form-input" placeholder="owner@example.com" required>
                </div>
                <div>
                    <label class="form-label">Owner Password *</label>
                    <input type="password" name="owner_password" class="form-input" minlength="8" placeholder="At least 8 characters" required>
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label class="form-label">GST Registration Mode *</label>
                    <select name="registration_mode" class="form-input" required>
                        <option value="regular">Regular GST Taxpayer</option>
                        <option value="composition">Composition (Bill of Supply)</option>
                        <option value="unregistered">Unregistered Small Retailer</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">GSTIN (if applicable)</label>
                    <input type="text" name="gstin" class="form-input" placeholder="15-digit GSTIN">
                </div>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                <button type="button" onclick="document.getElementById('newTenantModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Tenant Store</button>
            </div>
        </form>
    </div>
</div>

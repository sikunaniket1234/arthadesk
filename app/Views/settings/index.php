<?php
$regMode = $taxProfile['registration_mode'] ?? 'unregistered';
$indiaStates = [
    '01'=>'Jammu & Kashmir','02'=>'Himachal Pradesh','03'=>'Punjab','04'=>'Chandigarh',
    '05'=>'Uttarakhand','06'=>'Haryana','07'=>'Delhi','08'=>'Rajasthan','09'=>'Uttar Pradesh',
    '10'=>'Bihar','11'=>'Sikkim','12'=>'Arunachal Pradesh','13'=>'Nagaland','14'=>'Manipur',
    '15'=>'Mizoram','16'=>'Tripura','17'=>'Meghalaya','18'=>'Assam','19'=>'West Bengal',
    '20'=>'Jharkhand','21'=>'Odisha','22'=>'Chhattisgarh','23'=>'Madhya Pradesh',
    '24'=>'Gujarat','26'=>'Dadra & Nagar Haveli and Daman & Diu','27'=>'Maharashtra',
    '28'=>'Andhra Pradesh','29'=>'Karnataka','30'=>'Goa','31'=>'Lakshadweep',
    '32'=>'Kerala','33'=>'Tamil Nadu','34'=>'Puducherry','35'=>'Andaman & Nicobar',
    '36'=>'Telangana','37'=>'Andhra Pradesh (New)','38'=>'Ladakh'
];
?>

<div class="container" style="max-width:900px;">
    <div style="margin-bottom:2rem;">
        <h1 style="font-size:1.6rem;font-weight:800;color:#0f172a;">⚙️ Business Settings</h1>
        <p style="color:#64748b;font-size:0.85rem;">Configure your store profile, GST registration, and operational preferences.</p>
    </div>

    <!-- Tab Nav -->
    <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem;border-bottom:2px solid #e2e8f0;padding-bottom:0;flex-wrap:wrap;">
        <button class="settings-tab active" onclick="switchTab('business')" id="tab-business">dY?� Business Profile</button>
        <button class="settings-tab" onclick="switchTab('gst')" id="tab-gst">dY"< GST & Tax</button>
        <button class="settings-tab" onclick="switchTab('ops')" id="tab-ops">�sT�,? Operations</button>
        <button class="settings-tab" onclick="switchTab('users')" id="tab-users">dY` Users & Access</button>
        <button class="settings-tab" onclick="switchTab('templates')" id="tab-templates">🧾 Bill/Receipt Designer</button>
    </div>

    <form method="POST" action="/app/settings" id="settingsForm">
            <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">

        <!-- ── Business Profile Tab ── -->
        <div id="pane-business" class="settings-pane">
            <div class="card" style="padding:1.5rem;">
                <h2 style="font-size:1.05rem;font-weight:700;color:#1e293b;margin-bottom:1.2rem;">🏪 Store / Business Details</h2>
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Business / Store Name <span style="color:#ef4444">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= e($tenant['name'] ?? '') ?>" placeholder="Maa Tarini General Store">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Owner / Proprietor Name</label>
                        <input type="text" name="owner_name" class="form-control" value="<?= e($tenant['owner_name'] ?? '') ?>" placeholder="Full name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">📞 Business Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= e($tenant['phone'] ?? '') ?>" placeholder="+91 98610 12345">
                    </div>
                    <div class="form-group">
                        <label class="form-label">📧 Business Email</label>
                        <input type="email" name="email" class="form-control" value="<?= e($tenant['email'] ?? '') ?>" placeholder="store@example.com">
                    </div>
                    <div class="form-group" style="grid-column:1/-1">
                        <label class="form-label">🏠 Business Address</label>
                        <textarea name="address" class="form-control" rows="3" placeholder="Shop No., Street, City, State, PIN"><?= e($tenant['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">🌐 Default Language</label>
                        <select name="default_locale" class="form-control">
                            <option value="en" <?= ($tenant['default_locale'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                            <option value="or" <?= ($tenant['default_locale'] ?? 'en') === 'or' ? 'selected' : '' ?>>ଓଡ଼ିଆ (Odia)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">💰 Currency</label>
                        <input type="text" class="form-control" value="INR — Indian Rupee (₹)" disabled style="background:#f8fafc;color:#64748b;">
                    </div>
                </div>
                <!-- Logo Upload placeholder -->
                <div style="margin-top:1rem;padding:1rem;background:#f8fafc;border-radius:8px;border:1.5px dashed #cbd5e1;text-align:center;">
                    <p style="color:#64748b;font-size:0.82rem;margin:0;">🖼️ <strong>Business Logo</strong> — Logo upload coming soon. Will appear on invoices & thermal receipts.</p>
                </div>
            </div>
        </div>

        <!-- ── GST & Tax Tab ── -->
        <div id="pane-gst" class="settings-pane" style="display:none;">
            <div class="card" style="padding:1.5rem;">
                <h2 style="font-size:1.05rem;font-weight:700;color:#1e293b;margin-bottom:1.2rem;">📋 GST Registration & Tax Profile</h2>
                <div class="form-grid-2">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label class="form-label">GST Registration Type <span style="color:#ef4444">*</span></label>
                        <div style="display:flex;gap:0.75rem;flex-wrap:wrap;margin-top:0.4rem;">
                            <?php foreach (['regular'=>'Regular Taxpayer (CGST/SGST/IGST)','composition'=>'Composition Taxpayer (Turnover ≤ ₹1.5Cr)','unregistered'=>'Unregistered (No GST)'] as $mode=>$label): ?>
                            <label style="display:flex;align-items:center;gap:0.4rem;cursor:pointer;padding:0.5rem 0.9rem;border:1.5px solid <?= $regMode===$mode?'#2563eb':'#e2e8f0' ?>;border-radius:8px;background:<?= $regMode===$mode?'#eff6ff':'#fff' ?>;">
                                <input type="radio" name="registration_mode" value="<?= $mode ?>" <?= $regMode===$mode?'checked':'' ?> onchange="toggleGSTFields(this.value)">
                                <?= $label ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div id="gstFields" style="display:<?= $regMode!='unregistered'?'contents':'none' ?>">
                        <div class="form-group">
                            <label class="form-label">GSTIN <span id="gstinReqStar" style="color:#ef4444">*</span></label>
                            <input type="text" name="gstin" id="gstinInput" class="form-control" maxlength="15" value="<?= e($taxProfile['gstin'] ?? '') ?>" placeholder="21AAACT1234F1Z5" style="text-transform:uppercase;letter-spacing:1px;">
                            <small style="color:#64748b;">15-character GST Identification Number</small>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Legal Business Name</label>
                            <input type="text" name="legal_business_name" class="form-control" value="<?= e($taxProfile['legal_business_name'] ?? '') ?>" placeholder="As per GST registration">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Trade / Brand Name</label>
                            <input type="text" name="trade_name" class="form-control" value="<?= e($taxProfile['trade_name'] ?? '') ?>" placeholder="Popular shop name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Registered State</label>
                            <select name="registered_state_code" class="form-control">
                                <?php foreach ($indiaStates as $code=>$stateName): ?>
                                    <option value="<?= $code ?>" <?= ($taxProfile['registered_state_code']??'21')===$code?'selected':'' ?>><?= $code ?> — <?= $stateName ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">GST Effective From</label>
                            <input type="date" name="effective_from" class="form-control" value="<?= e($taxProfile['effective_from'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>
            <!-- Tax Summary -->
            <div class="card" style="padding:1.2rem;margin-top:1rem;">
                <h3 style="font-size:0.9rem;font-weight:700;color:#1e293b;margin-bottom:0.8rem;">📊 Active Tax Categories</h3>
                <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
                    <?php foreach ($taxCategories ?? [] as $tc): ?>
                        <div style="padding:0.4rem 0.75rem;background:#f1f5f9;border-radius:6px;font-size:0.78rem;font-weight:600;color:#475569;">
                            <?= e($tc['name']) ?> — <?= $tc['rate_percent'] ?>%
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── Operations Tab ── -->
        <div id="pane-ops" class="settings-pane" style="display:none;">
            <div class="card" style="padding:1.5rem;">
                <h2 style="font-size:1.05rem;font-weight:700;color:#1e293b;margin-bottom:1.2rem;">⚙️ Operational Preferences</h2>
                <div class="form-grid-2">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label style="display:flex;align-items:center;gap:0.6rem;cursor:pointer;">
                            <div style="position:relative;width:42px;height:24px;">
                                <input type="checkbox" name="allow_negative_stock" id="negStockToggle" value="1" <?= !empty($tenant['allow_negative_stock'])?'checked':'' ?> style="opacity:0;width:0;height:0;" onchange="this.parentElement.parentElement.querySelector('.toggle-label').textContent = this.checked?'Allowed':'Not Allowed'">
                                <span onclick="document.getElementById('negStockToggle').click()" style="position:absolute;cursor:pointer;inset:0;background:<?= !empty($tenant['allow_negative_stock'])?'#059669':'#cbd5e1' ?>;border-radius:24px;transition:0.3s;">
                                    <span style="position:absolute;left:<?= !empty($tenant['allow_negative_stock'])?'20':'4' ?>px;top:4px;width:16px;height:16px;background:#fff;border-radius:50%;transition:0.3s;"></span>
                                </span>
                            </div>
                            <div>
                                <strong style="font-size:0.9rem;">Allow Negative Stock</strong>
                                <span class="toggle-label" style="margin-left:0.5rem;font-size:0.8rem;color:#64748b;"><?= !empty($tenant['allow_negative_stock'])?'Allowed':'Not Allowed' ?></span>
                                <p style="margin:0;font-size:0.78rem;color:#94a3b8;">If enabled, sales can continue even when stock goes below zero. Useful for pre-orders or consignment items.</p>
                            </div>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" class="form-control" value="<?= e($tenant['invoice_prefix'] ?? 'INV') ?>" placeholder="INV" maxlength="10">
                        <small style="color:#64748b;">e.g. INV → INV-2024-0001</small>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Default Payment Mode</label>
                        <select name="default_payment_mode" class="form-control">
                            <?php foreach (['cash'=>'Cash','upi'=>'UPI / QR','card'=>'Card'] as $v=>$l): ?>
                                <option value="<?= $v ?>" <?= ($tenant['default_payment_mode']??'cash')===$v?'selected':'' ?>><?= $l ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Users Tab ── -->
        <div id="pane-users" class="settings-pane" style="display:none;">
            <div class="card" style="padding:1.5rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
                    <h2 style="font-size:1.05rem;font-weight:700;color:#1e293b;margin:0;">👤 Team Members & Access</h2>
                    <button type="button" onclick="document.getElementById('addUserModal').style.display='flex'" class="btn btn-primary" style="font-size:0.8rem;padding:0.4rem 0.9rem;">+ Add User</button>
                </div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php if (empty($teamUsers)): ?>
                            <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:2rem;">No team members yet.</td></tr>
                        <?php else: ?>
                            <?php foreach ($teamUsers as $u): ?>
                            <tr>
                                <td style="font-weight:600;"><?= e($u['name']) ?></td>
                                <td><?= e($u['email']) ?></td>
                                <td><span class="badge badge-info" style="text-transform:capitalize;"><?= str_replace('_',' ',e($u['role'])) ?></span></td>
                                <td><span class="badge <?= $u['is_active']?'badge-success':'badge-error' ?>"><?= $u['is_active']?'Active':'Inactive' ?></span></td>
                                <td><button type="button" class="btn btn-secondary" style="font-size:0.75rem;padding:0.25rem 0.6rem;" onclick="editUser(<?= (int)$u['id'] ?>, '<?= e($u['name']) ?>', '<?= e($u['role']) ?>')">Edit</button></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Templates Tab -->
        <div id="pane-templates" class="settings-pane" style="display:none;">
            <div class="card" style="padding:1.5rem;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
                    <div>
                        <h2 style="font-size:1.05rem;font-weight:700;color:#1e293b;margin:0;">Bill & Receipt Template Designer</h2>
                        <p style="color:#64748b;font-size:0.82rem;margin:0;">Customize thermal and normal bills with a visual editor</p>
                    </div>
                    <a href="/app/templates" class="btn btn-primary" style="font-size:0.8rem;padding:0.4rem 0.9rem;">Open Designer</a>
                </div>
                <p style="font-size:0.85rem;color:#475569;">Create and edit templates for 58mm/80mm thermal receipts and A4/normal bills. Add logo, store details, tax breakdown, and custom styling.</p>
            </div>
        </div>

        <!-- Save Button (shown on business/gst/ops tabs) -->
        <div id="saveRow" style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1.2rem;">
            <a href="/app/pos" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">dY'_ Save Settings</button>
        </div>
    </form>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay" style="display:none;">
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-header"><h3>Add Team Member</h3><button onclick="document.getElementById('addUserModal').style.display='none'" class="btn-close">✕</button></div>
        <form method="POST" action="/app/settings/users">
        <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
            <div class="form-group"><label class="form-label">Full Name *</label><input type="text" name="user_name" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Email *</label><input type="email" name="user_email" class="form-control" required></div>
            <div class="form-group"><label class="form-label">Temporary Password *</label><input type="password" name="user_password" class="form-control" required minlength="6"></div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select name="user_role" class="form-control">
                    <option value="cashier">Cashier</option>
                    <option value="inventory_manager">Inventory Manager</option>
                    <option value="accountant">Accountant</option>
                    <option value="tenant_admin">Store Admin (Full Access)</option>
                </select>
            </div>
            <div style="display:flex;gap:0.75rem;justify-content:flex-end;margin-top:1rem;">
                <button type="button" onclick="document.getElementById('addUserModal').style.display='none'" class="btn btn-secondary">Cancel</button>
                <button type="submit" class="btn btn-primary">Create User</button>
            </div>
        </form>
    </div>
</div>

<style>
.settings-tab { background:none;border:none;border-bottom:3px solid transparent;padding:0.6rem 1rem;font-size:0.85rem;font-weight:600;color:#64748b;cursor:pointer;transition:all 0.2s; }
.settings-tab.active { color:#2563eb;border-bottom-color:#2563eb; }
.settings-tab:hover { color:#1d4ed8; }
.form-grid-2 { display:grid;grid-template-columns:repeat(2,1fr);gap:1rem; }
@media(max-width:640px){ .form-grid-2{grid-template-columns:1fr;} }
</style>

<script>
function switchTab(name) {
    document.querySelectorAll('.settings-pane').forEach(p => p.style.display='none');
    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('pane-'+name).style.display = '';
    document.getElementById('tab-'+name).classList.add('active');
    document.getElementById('saveRow').style.display = (name === 'users' || name === 'templates') ? 'none' : 'flex';
}
const params = new URLSearchParams(window.location.search);
if (params.get('tab') === 'templates') { switchTab('templates'); }
function toggleGSTFields(mode) {
    const show = mode !== 'unregistered';
    document.getElementById('gstFields').style.display = show ? 'contents' : 'none';
}
function editUser(id, name, role) {
    // Simple inline edit placeholder
    alert('Edit user "' + name + '" — full user management coming in next update.');
}
</script>

<?php
$roleLabels = [
    'super_admin'       => '🛡️ Platform Super Admin',
    'tenant_admin'      => '👑 Store Admin',
    'cashier'           => '💳 Cashier',
    'inventory_manager' => '📦 Inventory Manager',
    'accountant'        => '📒 Accountant',
];
?>
<div class="container" style="max-width:800px;">
    <div style="margin-bottom:1.5rem;">
        <h1 style="font-size:1.6rem;font-weight:800;color:#0f172a;">👤 My Profile</h1>
        <p style="font-size:0.85rem;color:#64748b;">Update your display name and change your password.</p>
    </div>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.2rem;">
        <!-- Profile Card -->
        <div class="card" style="padding:1.5rem;">
            <div style="display:flex;align-items:center;gap:1rem;margin-bottom:1.5rem;">
                <div style="width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#2563eb,#7c3aed);display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:800;color:#fff;flex-shrink:0;">
                    <?= strtoupper(mb_substr($user['name'], 0, 1)) ?>
                </div>
                <div>
                    <div style="font-size:1.1rem;font-weight:800;color:#0f172a;"><?= e($user['name']) ?></div>
                    <div style="font-size:0.78rem;color:#64748b;"><?= e($user['email']) ?></div>
                    <div style="margin-top:0.25rem;">
                        <span class="badge badge-info"><?= $roleLabels[$user['role']] ?? ucfirst($user['role']) ?></span>
                    </div>
                </div>
            </div>

            <form method="POST" action="/app/profile">
                <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
                <div class="form-group">
                    <label class="form-label">Display Name *</label>
                    <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Email Address</label>
                    <input type="email" class="form-control" value="<?= e($user['email']) ?>" disabled style="background:#f8fafc;color:#64748b;">
                    <small style="color:#94a3b8;">Email cannot be changed. Contact your admin.</small>
                </div>

                <hr style="border:none;border-top:1px solid #e2e8f0;margin:1.2rem 0;">
                <h3 style="font-size:0.88rem;font-weight:700;color:#475569;margin-bottom:0.75rem;">🔒 Change Password (optional)</h3>
                <div class="form-group">
                    <label class="form-label">Current Password</label>
                    <input type="password" name="current_password" class="form-control" autocomplete="current-password" placeholder="Enter current password">
                </div>
                <div class="form-group">
                    <label class="form-label">New Password</label>
                    <input type="password" name="new_password" class="form-control" autocomplete="new-password" minlength="6" placeholder="Min 6 characters">
                </div>
                <div class="form-group">
                    <label class="form-label">Confirm New Password</label>
                    <input type="password" name="confirm_password" class="form-control" autocomplete="new-password" placeholder="Repeat new password">
                </div>

                <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
                    <a href="/app/pos" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                </div>
            </form>
        </div>

        <!-- Activity History -->
        <div class="card" style="padding:1.5rem;">
            <h3 style="font-size:0.95rem;font-weight:700;color:#1e293b;margin-bottom:1rem;">🕒 Recent Activity</h3>
            <?php if (empty($recentActivity)): ?>
                <p style="color:#94a3b8;font-size:0.82rem;text-align:center;padding:2rem 0;">No activity recorded yet.</p>
            <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:0.6rem;max-height:500px;overflow-y:auto;">
                <?php foreach ($recentActivity as $log): ?>
                    <div style="display:flex;gap:0.6rem;padding:0.6rem;border-radius:8px;background:#f8fafc;">
                        <div style="width:8px;height:8px;background:#2563eb;border-radius:50%;margin-top:0.35rem;flex-shrink:0;"></div>
                        <div>
                            <div style="font-size:0.8rem;font-weight:600;color:#0f172a;"><?= e($log['action']) ?></div>
                            <?php if (!empty($log['details'])): ?>
                                <div style="font-size:0.72rem;color:#64748b;"><?= e(mb_substr($log['details'], 0, 80)) ?></div>
                            <?php endif; ?>
                            <div style="font-size:0.68rem;color:#94a3b8;margin-top:0.15rem;"><?= date('d M Y, h:i A', strtotime($log['created_at'])) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media(max-width:640px) {
    .container > div[style*="grid-template-columns:1fr 1fr"] { grid-template-columns: 1fr !important; }
}
</style>

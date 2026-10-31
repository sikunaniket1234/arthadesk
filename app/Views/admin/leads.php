<?php
$counts = $counts ?? ['new' => 0, 'contacted' => 0, 'onboarded' => 0, 'closed' => 0];
$leads = $leads ?? [];
$status = $status ?? 'new';
$tabs = ['new' => 'New', 'contacted' => 'Contacted', 'onboarded' => 'Onboarded', 'closed' => 'Closed', 'all' => 'All'];
?>
<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:#0f172a;">📣 Marketing Leads</h1>
            <p style="font-size:0.85rem;color:#64748b;">
                Enquiries captured on <code>https://www.<?= e($baseDomain) ?></code>. Contact them, then onboard the shop.
            </p>
        </div>
        <a href="/admin/tenants" class="btn btn-secondary">← Platform Admin</a>
    </div>

    <div style="display:flex;gap:0.5rem;flex-wrap:wrap;margin-bottom:1rem;">
        <?php foreach ($tabs as $key => $label): ?>
            <?php $isActive = $status === $key; ?>
            <a href="/admin/leads?status=<?= e($key) ?>"
               class="btn <?= $isActive ? 'btn-primary' : 'btn-secondary' ?>"
               style="font-size:0.82rem;padding:0.4rem 0.9rem;">
                <?= e($label) ?><?= $key !== 'all' ? ' (' . (int)($counts[$key] ?? 0) . ')' : '' ?>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name / Shop</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>City</th>
                        <th>Message</th>
                        <th>Received</th>
                        <th style="text-align:right;">Update status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($leads)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center;padding:2.5rem;color:#94a3b8;">
                                No leads in this view yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($leads as $lead): ?>
                            <tr>
                                <td>
                                    <div style="font-weight:700;"><?= e($lead['name']) ?></div>
                                    <?php if (!empty($lead['shop_name'])): ?>
                                        <div style="font-size:0.8rem;color:#475569;"><?= e($lead['shop_name']) ?></div>
                                    <?php endif; ?>
                                    <?php
                                    $badge = match ($lead['status']) {
                                        'new' => 'badge-warning',
                                        'contacted' => 'badge-info',
                                        'onboarded' => 'badge-success',
                                        default => 'badge-error',
                                    };
                                    ?>
                                    <span class="badge <?= $badge ?>" style="margin-top:0.3rem;display:inline-block;"><?= e(strtoupper($lead['status'])) ?></span>
                                </td>
                                <td>
                                    <?php if (!empty($lead['phone'])): ?>
                                        <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $lead['phone'])) ?>"><?= e($lead['phone']) ?></a>
                                        <?php $digits = preg_replace('/[^0-9]/', '', (string)$lead['phone']); ?>
                                        <?php if ($digits !== ''): ?>
                                            · <a href="https://wa.me/<?= e($digits) ?>" target="_blank" rel="noopener">WhatsApp</a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($lead['email'])): ?>
                                        <a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a>
                                    <?php else: ?>
                                        <span style="color:#94a3b8;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($lead['city'] ?? '—') ?></td>
                                <td style="max-width:280px;">
                                    <span style="font-size:0.82rem;color:#475569;white-space:pre-wrap;"><?= e($lead['message'] ?? '—') ?></span>
                                </td>
                                <td style="font-size:0.78rem;color:#64748b;white-space:nowrap;">
                                    <?= e(date('d M Y', strtotime($lead['created_at']))) ?><br>
                                    <?= e(date('H:i', strtotime($lead['created_at']))) ?>
                                </td>
                                <td style="text-align:right;">
                                    <form action="/admin/leads/status" method="POST" style="display:inline-flex;gap:0.4rem;align-items:center;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="lead_id" value="<?= (int)$lead['id'] ?>">
                                        <select name="status" class="form-input" style="width:auto;padding:0.3rem 0.5rem;font-size:0.78rem;">
                                            <?php foreach (['new', 'contacted', 'onboarded', 'closed'] as $option): ?>
                                                <option value="<?= e($option) ?>"<?= $lead['status'] === $option ? ' selected' : '' ?>>
                                                    <?= e(ucfirst($option)) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" class="btn btn-secondary" style="font-size:0.72rem;padding:0.25rem 0.6rem;">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <p style="margin-top:1rem;font-size:0.8rem;color:#94a3b8;">
        Onboarding steps: contact the lead → agree pricing → <strong>Platform Admin → Add Business</strong> to create
        <code>yourshop.<?= e($baseDomain) ?></code> → hand over login details → mark as <em>Onboarded</em>.
    </p>
</div>

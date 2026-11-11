<?php
$report = $report ?? ['system' => [], 'probes' => [], 'ok' => false, 'checked_at' => ''];
$system = $report['system'];
$probes = $report['probes'];
$entries = $entries ?? [];

$badgeFor = fn(string $status): string => match ($status) {
    'ok' => 'badge-success',
    'warn' => 'badge-warning',
    'fail' => 'badge-error',
    default => 'badge-info',
};

$pageUrl = function (int $p) use ($q, $actionFilter, $tenantFilter, $from, $to) {
    $params = ['page' => $p];
    if ($q !== '') $params['q'] = $q;
    if ($actionFilter !== '') $params['action'] = $actionFilter;
    if ($tenantFilter !== '') $params['tenant'] = $tenantFilter;
    if ($from !== '') $params['from'] = $from;
    if ($to !== '') $params['to'] = $to;
    return '/admin/health?' . http_build_query($params);
};

$sysCounts = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'info' => 0];
foreach ($system as $rows) {
    foreach ($rows as $check) {
        $sysCounts[$check['status']] = ($sysCounts[$check['status']] ?? 0) + 1;
    }
}
$probeOk = 0;
foreach ($probes as $p) {
    if ($p['ok']) $probeOk++;
}
$allOk = ($report['ok'] ?? false);
?>
<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:1.25rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:#0f172a;">🩺 Platform Ops — Health &amp; Audit</h1>
            <p style="font-size:0.85rem;color:#64748b;">
                Super-admin only · probes run server-side against <code><?= e($_SERVER['HTTP_HOST'] ?? '') ?></code>
                · checked <?= e($report['checked_at']) ?>
            </p>
        </div>
        <div style="display:flex;gap:0.5rem;flex-wrap:wrap;">
            <a href="/admin/health" class="btn btn-primary">🔄 Re-check</a>
            <a href="/admin/health.json" target="_blank" rel="noopener" class="btn btn-secondary">⬇ health.json</a>
            <a href="/admin/tenants" class="btn btn-secondary">← Platform Admin</a>
        </div>
    </div>

    <!-- Summary chips -->
    <div style="display:flex;gap:0.6rem;flex-wrap:wrap;margin-bottom:1.25rem;">
        <span class="badge <?= $allOk ? 'badge-success' : 'badge-error' ?>" style="font-size:0.85rem;padding:0.45rem 0.9rem;">
            <?= $allOk ? 'ALL SYSTEMS HEALTHY' : 'DEGRADED — see failures below' ?>
        </span>
        <span class="badge badge-info" style="font-size:0.85rem;padding:0.45rem 0.9rem;">
            Endpoints: <?= count($probes) > 0 ? $probeOk . '/' . count($probes) . ' OK' : 'probes skipped' ?>
        </span>
        <span class="badge badge-success" style="font-size:0.85rem;padding:0.45rem 0.9rem;"><?= $sysCounts['ok'] ?> checks OK</span>
        <span class="badge badge-warning" style="font-size:0.85rem;padding:0.45rem 0.9rem;"><?= $sysCounts['warn'] ?> warnings</span>
        <span class="badge badge-error" style="font-size:0.85rem;padding:0.45rem 0.9rem;"><?= $sysCounts['fail'] ?> failures</span>
    </div>

    <!-- Endpoint / API probes -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:0.75rem;">
            <h3 style="font-size:1.1rem;font-weight:700;">🔌 Endpoint / API probes</h3>
            <span style="font-size:0.78rem;color:#64748b;">HTTP requests made server-side, no cookies, redirects not followed · ≤4s each</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Method</th>
                        <th>Endpoint</th>
                        <th>Purpose</th>
                        <th>Expected</th>
                        <th>Got</th>
                        <th>Latency</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($probes)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:1.5rem;color:#94a3b8;">Probes skipped.</td></tr>
                    <?php else: foreach ($probes as $p): ?>
                        <tr>
                            <td><span class="badge badge-info" style="font-family:monospace;font-size:0.72rem;"><?= e($p['method']) ?></span></td>
                            <td style="font-family:monospace;font-size:0.8rem;font-weight:600;"><?= e($p['path']) ?></td>
                            <td style="font-size:0.78rem;color:#64748b;"><?= e($p['label']) ?></td>
                            <td style="font-family:monospace;font-size:0.78rem;"><?= e($p['expected']) ?></td>
                            <td style="font-family:monospace;font-weight:700;color:<?= $p['status'] > 0 ? '#0f172a' : '#ef4444' ?>;">
                                <?= $p['status'] > 0 ? (int)$p['status'] : 'ERR' ?>
                            </td>
                            <td style="font-size:0.78rem;color:#64748b;"><?= (int)$p['ms'] ?> ms</td>
                            <td>
                                <span class="badge <?= $p['ok'] ? 'badge-success' : 'badge-error' ?>"><?= $p['ok'] ? 'OK' : 'FAIL' ?></span>
                                <?php if (($p['note'] ?? '') !== ''): ?>
                                    <div style="font-size:0.7rem;color:#64748b;max-width:320px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= e($p['note']) ?>"><?= e($p['note']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- System checks -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1rem;margin:1.25rem 0;">
        <?php foreach ($system as $group => $rows): ?>
            <div class="card" style="margin-bottom:0;">
                <h3 style="font-size:0.95rem;font-weight:700;margin-bottom:0.6rem;"><?= e($group) ?></h3>
                <?php foreach ($rows as $check): ?>
                    <div style="display:flex;justify-content:space-between;gap:0.75rem;align-items:baseline;padding:0.32rem 0;border-bottom:1px solid #f1f5f9;font-size:0.8rem;">
                        <span style="color:#475569;flex:0 0 auto;"><?= e($check['label']) ?></span>
                        <span style="text-align:right;min-width:0;">
                            <span style="font-family:monospace;color:#0f172a;max-width:220px;display:inline-block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;vertical-align:bottom;" title="<?= e($check['value']) ?>"><?= e($check['value']) ?></span>
                            <span class="badge <?= $badgeFor($check['status']) ?>" style="margin-left:0.4rem;"><?= strtoupper(e($check['status'])) ?></span>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Audit trail -->
    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-bottom:0.75rem;">
            <h3 style="font-size:1.1rem;font-weight:700;">🧾 Audit Trail</h3>
            <span style="font-size:0.78rem;color:#64748b;">Every tenant + platform rows · newest first</span>
        </div>

        <?php if (($auditError ?? '') !== ''): ?>
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:0.7rem 0.9rem;margin-bottom:0.75rem;font-size:0.82rem;color:#b91c1c;">
                <strong>Audit queries failed:</strong> <span style="font-family:monospace;"><?= e($auditError) ?></span>
            </div>
        <?php endif; ?>

        <form method="GET" action="/admin/health" style="display:flex;gap:0.5rem;flex-wrap:wrap;align-items:flex-end;margin-bottom:0.75rem;">
            <div style="flex:1 1 220px;">
                <label class="form-label" style="font-size:0.75rem;">Search</label>
                <input type="search" name="q" value="<?= e($q) ?>" class="form-input" placeholder="Action, entity, details, user…">
            </div>
            <div style="flex:0 1 230px;">
                <label class="form-label" style="font-size:0.75rem;">Action</label>
                <select name="action" class="form-input">
                    <option value="">All actions</option>
                    <?php foreach ($actionOptions as $ao): ?>
                        <option value="<?= e($ao['action']) ?>" <?= $actionFilter === $ao['action'] ? 'selected' : '' ?>>
                            <?= e($ao['action']) ?> (<?= (int)$ao['total'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:0 1 210px;">
                <label class="form-label" style="font-size:0.75rem;">Tenant</label>
                <select name="tenant" class="form-input">
                    <option value="">All tenants</option>
                    <option value="0" <?= $tenantFilter === '0' ? 'selected' : '' ?>>Platform only (no tenant)</option>
                    <?php foreach ($tenantOptions as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $tenantFilter === (string)$t['id'] ? 'selected' : '' ?>><?= e($t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="flex:0 1 150px;">
                <label class="form-label" style="font-size:0.75rem;">From</label>
                <input type="date" name="from" value="<?= e($from) ?>" class="form-input">
            </div>
            <div style="flex:0 1 150px;">
                <label class="form-label" style="font-size:0.75rem;">To</label>
                <input type="date" name="to" value="<?= e($to) ?>" class="form-input">
            </div>
            <button type="submit" class="btn btn-primary" style="font-size:0.8rem;padding:0.45rem 0.9rem;">Filter</button>
            <a href="/admin/health" class="btn btn-secondary" style="font-size:0.8rem;padding:0.45rem 0.9rem;">Reset</a>
        </form>

        <div style="font-size:0.8rem;color:#64748b;margin-bottom:0.6rem;">
            <?= number_format($total) ?> entr<?= $total === 1 ? 'y' : 'ies' ?>
            <?php if ($total > 0): ?>
                · showing <?= number_format(($page - 1) * $perPage + 1) ?>–<?= number_format(min($page * $perPage, $total)) ?>
            <?php endif; ?>
        </div>

        <div class="table-responsive" style="max-height:640px;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>When</th>
                        <th>Action</th>
                        <th>Tenant</th>
                        <th>User</th>
                        <th>Entity</th>
                        <th>Details</th>
                        <th>IP</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:2rem;color:#94a3b8;">No audit entries match these filters.</td></tr>
                    <?php else: foreach ($entries as $row): ?>
                        <tr>
                            <td style="font-size:0.74rem;color:#64748b;white-space:nowrap;"><?= e(date('d-M-Y H:i:s', strtotime((string)$row['created_at']))) ?></td>
                            <td><span class="badge badge-info" style="font-family:monospace;font-size:0.7rem;"><?= e($row['action']) ?></span></td>
                            <td style="font-size:0.8rem;">
                                <?php if ($row['tenant_id'] === null): ?>
                                    <span style="color:#94a3b8;">— platform —</span>
                                <?php else: ?>
                                    <?= e($row['tenant_name'] ?? ('tenant #' . $row['tenant_id'])) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-size:0.8rem;font-weight:600;"><?= e($row['user_name'] ?? '—') ?></div>
                                <?php if (!empty($row['user_email'])): ?>
                                    <div style="font-size:0.7rem;color:#94a3b8;"><?= e($row['user_email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:0.76rem;font-family:monospace;">
                                <?= e(($row['entity_type'] ?? '') !== '' && $row['entity_type'] !== null ? $row['entity_type'] : '—') ?><?= $row['entity_id'] !== null ? ' #' . (int)$row['entity_id'] : '' ?>
                            </td>
                            <td>
                                <?php $details = trim((string)($row['details'] ?? '')); ?>
                                <?php if ($details === '' || $details === 'null' || $details === '{}' || $details === '[]'): ?>
                                    <span style="color:#cbd5e1;">—</span>
                                <?php else: ?>
                                    <span title="<?= e($details) ?>" style="display:block;max-width:340px;font-family:monospace;font-size:0.7rem;color:#475569;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($details) ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="font-family:monospace;font-size:0.72rem;color:#64748b;white-space:nowrap;"><?= e($row['ip_address'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:0.7rem 0;border-top:1px solid #e2e8f0;font-size:0.85rem;">
                <span style="color:#64748b;">Page <?= $page ?> of <?= $totalPages ?></span>
                <span style="display:flex;gap:0.5rem;">
                    <?php if ($page > 1): ?>
                        <a href="<?= e($pageUrl($page - 1)) ?>" class="btn btn-secondary" style="font-size:0.78rem;padding:0.3rem 0.7rem;">← Prev</a>
                    <?php endif; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="<?= e($pageUrl($page + 1)) ?>" class="btn btn-secondary" style="font-size:0.78rem;padding:0.3rem 0.7rem;">Next →</a>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php $templateList = $templates ?? []; ?>
<div class="container">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
        <div>
            <h1 style="font-size:1.5rem;font-weight:800;color:#0f172a;">Bill &amp; Receipt Templates</h1>
            <p style="font-size:0.85rem;color:#64748b;">Visual designer for thermal and normal bills/receipts</p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a href="/app/settings" class="btn btn-secondary">Back to Settings</a>
            <a href="/app/templates/editor" class="btn btn-primary">+ New Template</a>
        </div>
    </div>
    <div class="card">
        <table class="data-table">
            <thead>
                <tr><th>Name</th><th>Type</th><th>Default</th><th>Updated</th><th>Actions</th></tr>
            </thead>
            <tbody>
            <?php if (empty($templateList)): ?>
                <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:2rem;">No templates yet. Create one.</td></tr>
            <?php else: foreach ($templateList as $tpl): ?>
                <tr>
                    <td><?= e($tpl['name'] ?? '') ?></td>
                    <td><span class="badge badge-info"><?= e($tpl['type'] ?? '') ?></span></td>
                    <td><?= !empty($tpl['is_default']) ? '<span class="badge badge-success">Yes</span>' : '-' ?></td>
                    <td><?= e($tpl['updated_at'] ?? $tpl['created_at'] ?? '') ?></td>
                    <td><a class="btn btn-secondary" style="padding:0.3rem 0.6rem;font-size:0.75rem;" href="/app/templates/editor?id=<?= (int)($tpl['id'] ?? 0) ?>">Edit in Designer</a></td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>

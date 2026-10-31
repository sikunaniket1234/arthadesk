<?php
$tpl = $template ?? [];
$templateId = (int)($tpl['id'] ?? 0);
$defaultHtml = '<div class="receipt"><h2>{{STORE_NAME}}</h2><div>Invoice: {{INVOICE_NO}}</div><div>Date: {{DATE}}</div><hr/>{{ITEMS}}<hr/><div>Total: {{TOTAL}}</div></div>';
$defaultCss = '.receipt{font-family:monospace;font-size:12px;width:280px;}.receipt h2{font-size:14px;text-align:center;}';
?>
<div class="container" style="max-width:1100px;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.2rem;">
        <div>
            <h1 style="font-size:1.4rem;font-weight:800;color:#0f172a;">Bill Template Designer</h1>
            <p style="font-size:0.85rem;color:#64748b;">Visual editor for Thermal / Normal bills</p>
        </div>
        <div style="display:flex;gap:0.5rem;">
            <a href="/app/templates" class="btn btn-secondary">Back</a>
        </div>
    </div>
    <form method="POST" action="/app/templates/save">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $templateId ?>">
        <div class="card" style="padding:1.2rem;">
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.8rem;">
                <div class="form-group">
                    <label class="form-label">Template Name</label>
                    <input class="form-input" name="name" value="<?= e($tpl['name'] ?? 'Default Template') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select class="form-input" name="type">
                        <?php foreach (['thermal' => 'Thermal (58/80mm)', 'normal' => 'Normal (A4/Letter)', 'a4' => 'A4'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= ($tpl['type'] ?? 'thermal') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:center;gap:0.5rem;margin-top:1.6rem;">
                    <input type="checkbox" name="is_default" value="1" <?= !empty($tpl['is_default']) ? 'checked' : '' ?>>
                    <label>Set as default</label>
                </div>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-top:1rem;">
            <div class="card" style="padding:1rem;">
                <div style="display:flex;justify-content:space-between;margin-bottom:0.6rem;">
                    <strong>HTML Template</strong>
                    <small style="color:#64748b;">Placeholders: {{STORE_NAME}}, {{INVOICE_NO}}, {{DATE}}, {{ITEMS}}, {{TOTAL}}</small>
                </div>
                <textarea name="html_template" style="width:100%;min-height:420px;font-family:monospace;font-size:0.85rem;padding:0.8rem;border:1px solid #e2e8f0;border-radius:8px;"><?= e($tpl['html_template'] ?? $defaultHtml) ?></textarea>
            </div>
            <div class="card" style="padding:1rem;">
                <div style="display:flex;justify-content:space-between;margin-bottom:0.6rem;">
                    <strong>Custom CSS</strong>
                    <small style="color:#64748b;">Styles for preview/print</small>
                </div>
                <textarea name="css" style="width:100%;min-height:420px;font-family:monospace;font-size:0.85rem;padding:0.8rem;border:1px solid #e2e8f0;border-radius:8px;"><?= e($tpl['css'] ?? $defaultCss) ?></textarea>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;">
            <button class="btn btn-primary">Save Template</button>
        </div>
    </form>
</div>

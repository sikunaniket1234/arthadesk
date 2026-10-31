<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;

class TemplateController {
    public function index(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $templates = Database::fetchAll(
            "SELECT * FROM bill_templates WHERE tenant_id = :tid ORDER BY type ASC, id ASC",
            ['tid' => $tenantId]
        );

        View::render('templates/index', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'templates' => $templates,
        ]);
    }

    public function editor(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $id = (int)$request->get('id', 0);
        $template = null;

        if ($id > 0) {
            $template = Database::fetchOne(
                "SELECT * FROM bill_templates WHERE id = :id AND tenant_id = :tid",
                ['id' => $id, 'tid' => $tenantId]
            );
        }

        View::render('templates/editor', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'template' => $template,
        ]);
    }

    public function save(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $id = (int)$request->input('id', 0);
        $name = trim((string)$request->input('name', 'Default Template'));
        $type = (string)$request->input('type', 'thermal');
        $css = (string)$request->input('css', '');
        $html = (string)$request->input('html_template', '');
        $isDefault = $request->input('is_default') ? 1 : 0;

        if ($name === '') {
            $name = 'Default Template';
        }
        if (!in_array($type, ['thermal', 'normal', 'a4'], true)) {
            $type = 'thermal';
        }

        if (strlen($html) > 500000 || strlen($css) > 200000) {
            flash('error', 'Template is too large.');
            Response::redirect('/app/templates');
            return;
        }

        if ($id > 0) {
            $existing = Database::fetchOne(
                "SELECT id FROM bill_templates WHERE id = :id AND tenant_id = :tid",
                ['id' => $id, 'tid' => $tenantId]
            );
            if (!$existing) {
                flash('error', 'Template not found.');
                Response::redirect('/app/templates');
                return;
            }

            Database::query(
                "UPDATE bill_templates SET name = :name, type = :type, css = :css, html_template = :html, is_default = :def, updated_at = NOW()
                 WHERE id = :id AND tenant_id = :tid",
                ['name' => $name, 'type' => $type, 'css' => $css, 'html' => $html, 'def' => $isDefault, 'id' => $id, 'tid' => $tenantId]
            );
            AuditService::log('template.update', 'bill_template', $id, ['name' => $name, 'type' => $type], $tenantId);
            flash('success', 'Template updated.');
        } else {
            Database::connect()->prepare(
                "INSERT INTO bill_templates (tenant_id, name, type, css, html_template, is_default, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, NOW())"
            )->execute([$tenantId, $name, $type, $css, $html, $isDefault]);
            $id = (int)Database::lastInsertId();
            AuditService::log('template.create', 'bill_template', $id, ['name' => $name, 'type' => $type], $tenantId);
            flash('success', 'Template created.');
        }

        if ($isDefault) {
            Database::query(
                "UPDATE bill_templates SET is_default = 0 WHERE tenant_id = :tid AND type = :type AND id <> :id",
                ['tid' => $tenantId, 'type' => $type, 'id' => $id]
            );
        }

        Response::redirect('/app/settings?tab=templates');
    }
}

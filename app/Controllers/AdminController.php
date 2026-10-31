<?php
namespace App\Controllers;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use App\Services\TenantResolver;

class AdminController {
    public function index(Request $request): void {
        $tenants = Database::fetchAll("
            SELECT t.*, tp.registration_mode, tp.gstin,
                   (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) as user_count,
                   (SELECT COUNT(*) FROM invoices i WHERE i.tenant_id = t.id) as invoice_count,
                   (SELECT COALESCE(SUM(total_amount), 0) FROM invoices i WHERE i.tenant_id = t.id AND i.payment_status <> 'cancelled') as total_sales
            FROM tenants t
            LEFT JOIN tenant_tax_profiles tp ON tp.tenant_id = t.id
            ORDER BY t.id ASC
        ");

        View::render('admin/index', [
            'tenants' => $tenants,
            'baseDomain' => TenantResolver::baseDomain(),
            'noLayout' => false,
        ]);
    }

    public function createTenant(Request $request): void {
        $name = trim((string)$request->input('name', ''));
        $owner = trim((string)$request->input('owner_name', ''));
        $phone = trim((string)$request->input('phone', ''));
        $email = strtolower(trim((string)$request->input('email', '')));
        $ownerEmail = strtolower(trim((string)$request->input('owner_email', '')));
        $ownerPassword = (string)$request->input('owner_password', '');
        $mode = (string)$request->input('registration_mode', 'regular');
        $gstin = strtoupper(trim((string)$request->input('gstin', ''))) ?: null;
        $stateCode = trim((string)$request->input('state_code', '21'));

        if ($name === '') {
            flash('error', 'Business name is required.');
            Response::redirect('/admin/tenants');
            return;
        }

        if (!in_array($mode, ['regular', 'composition', 'unregistered'], true)) {
            $mode = 'unregistered';
        }

        if ($gstin !== null && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
            flash('error', 'Invalid GSTIN format.');
            Response::redirect('/admin/tenants');
            return;
        }

        if ($ownerEmail !== '' && !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Owner email is invalid.');
            Response::redirect('/admin/tenants');
            return;
        }

        if ($ownerEmail !== '' && strlen($ownerPassword) < 8) {
            flash('error', 'Owner password must be at least 8 characters.');
            Response::redirect('/admin/tenants');
            return;
        }

        $slug = $this->normalizeSlug((string)$request->input('slug', ''));
        if ($slug === '') {
            $slug = $this->slugify($name);
        }

        $reserved = (array)Config::get('tenancy.reserved_slugs', []);
        $min = (int)Config::get('tenancy.min_slug_length', 3);
        $max = (int)Config::get('tenancy.max_slug_length', 50);

        if (strlen($slug) < $min || strlen($slug) > $max || in_array($slug, $reserved, true)) {
            flash('error', 'Store address is invalid or reserved. Use 3-50 letters, numbers, or hyphens.');
            Response::redirect('/admin/tenants');
            return;
        }

        $existing = Database::fetchOne("SELECT id FROM tenants WHERE slug = :slug LIMIT 1", ['slug' => $slug]);
        if ($existing) {
            flash('error', 'That store address is already in use. Choose another slug.');
            Response::redirect('/admin/tenants');
            return;
        }

        try {
            $tenantId = Database::transaction(function (\PDO $pdo) use ($name, $slug, $owner, $phone, $email, $mode, $gstin, $stateCode, $ownerEmail, $ownerPassword) {
                $stmt = $pdo->prepare("
                    INSERT INTO tenants (name, slug, owner_name, phone, email, status, currency, default_locale)
                    VALUES (?, ?, ?, ?, ?, 'active', 'INR', 'en')
                ");
                $stmt->execute([$name, $slug, $owner ?: null, $phone ?: null, $email ?: null]);
                $tenantId = (int)$pdo->lastInsertId();

                $taxStmt = $pdo->prepare("
                    INSERT INTO tenant_tax_profiles (tenant_id, registration_mode, gstin, legal_business_name, trade_name, registered_state_code)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $taxStmt->execute([$tenantId, $mode, $gstin, $name, $name, $stateCode]);

                $accounts = [
                    ['1010', 'Cash in Hand', 'asset'],
                    ['1020', 'Bank / UPI Clearing', 'asset'],
                    ['1030', 'Card Clearing', 'asset'],
                    ['1040', 'Accounts Receivable (Khata)', 'asset'],
                    ['1050', 'Inventory Asset', 'asset'],
                    ['2010', 'Output CGST Payable', 'liability'],
                    ['2020', 'Output SGST Payable', 'liability'],
                    ['2030', 'Output IGST Payable', 'liability'],
                    ['2050', 'Accounts Payable (Suppliers)', 'liability'],
                    ['3010', 'Owner Equity', 'equity'],
                    ['4010', 'Sales Revenue', 'revenue'],
                    ['4020', 'Sales Returns & Allowances', 'revenue'],
                    ['5010', 'Cost of Goods Sold (COGS)', 'expense'],
                    ['5020', 'Store Rent Expense', 'expense'],
                    ['5030', 'Electricity & Utilities', 'expense'],
                    ['5040', 'Tea & Refreshment / General Expense', 'expense'],
                ];
                $accStmt = $pdo->prepare("INSERT INTO accounts (tenant_id, code, name, type) VALUES (?, ?, ?, ?)");
                foreach ($accounts as $account) {
                    $accStmt->execute([$tenantId, $account[0], $account[1], $account[2]]);
                }

                if ($ownerEmail !== '') {
                    $userStmt = $pdo->prepare("
                        INSERT INTO users (tenant_id, name, email, password_hash, role, is_active)
                        VALUES (?, ?, ?, ?, 'tenant_admin', 1)
                    ");
                    $userStmt->execute([
                        $tenantId,
                        $owner ?: $name,
                        $ownerEmail,
                        password_hash($ownerPassword, PASSWORD_BCRYPT),
                    ]);
                }

                return $tenantId;
            });
        } catch (\Throwable $e) {
            Logger::error('tenant_create_failed', ['error' => $e->getMessage(), 'slug' => $slug], 'admin');
            flash('error', 'Unable to create the business tenant. Please check the details and try again.');
            Response::redirect('/admin/tenants');
            return;
        }

        AuditService::log('tenant.create', 'tenant', $tenantId, [
            'name' => $name,
            'slug' => $slug,
            'registration_mode' => $mode,
            'owner_email' => $ownerEmail ?: null,
        ], $tenantId);

        $url = TenantResolver::isLocalHost()
            ? '/app/pos'
            : 'https://' . $slug . '.' . TenantResolver::baseDomain();
        flash('success', "New business '{$name}' created. Store address: {$url}");
        Response::redirect('/admin/tenants');
    }

    public function toggleStatus(Request $request): void {
        $tenantId = (int)$request->input('tenant_id');
        $tenant = Database::fetchOne("SELECT * FROM tenants WHERE id = :id LIMIT 1", ['id' => $tenantId]);

        if (!$tenant) {
            flash('error', 'Tenant not found.');
            Response::redirect('/admin/tenants');
            return;
        }

        $newStatus = ($tenant['status'] === 'active') ? 'suspended' : 'active';
        Database::query("UPDATE tenants SET status = :status WHERE id = :id", [
            'status' => $newStatus,
            'id' => $tenantId,
        ]);

        AuditService::log('tenant.status', 'tenant', $tenantId, [
            'name' => $tenant['name'],
            'from' => $tenant['status'],
            'to' => $newStatus,
        ], $tenantId);

        flash('success', "Tenant '{$tenant['name']}' status changed to " . strtoupper($newStatus));
        Response::redirect('/admin/tenants');
    }

    protected function slugify(string $name): string {
        $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        $slug = substr($slug, 0, 40);
        if ($slug === '') {
            $slug = 'store';
        }

        $candidate = $slug;
        $suffix = 1;
        while (Database::fetchOne("SELECT id FROM tenants WHERE slug = :slug LIMIT 1", ['slug' => $candidate])) {
            $candidate = $slug . '-' . $suffix;
            $suffix++;
            if ($suffix > 999) {
                $candidate = $slug . '-' . bin2hex(random_bytes(3));
                break;
            }
        }

        return $candidate;
    }

    protected function normalizeSlug(string $slug): string {
        $slug = strtolower(trim($slug));
        $slug = (string)preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = trim((string)preg_replace('/-+/', '-', $slug), '-');
        return $slug;
    }
}

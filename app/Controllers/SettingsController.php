<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Core\Session;
use App\Services\AuditService;

class SettingsController {
    public function index(Request $request): void {
        $tenant    = $request->tenant();
        $taxProfile = $request->taxProfile();
        $tenantId  = $tenant['id'];

        $teamUsers = Database::fetchAll(
            "SELECT id, name, email, role, is_active FROM users WHERE tenant_id = :tid ORDER BY name ASC",
            ['tid' => $tenantId]
        );

        $taxCategories = Database::fetchAll(
            "SELECT * FROM tax_categories WHERE tenant_id IS NULL OR tenant_id = :tid ORDER BY rate_percent ASC",
            ['tid' => $tenantId]
        );

        View::render('settings/index', [
            'tenant'        => $tenant,
            'taxProfile'    => $taxProfile,
            'teamUsers'     => $teamUsers,
            'taxCategories' => $taxCategories,
        ]);
    }

    public function update(Request $request): void {
        $tenant    = $request->tenant();
        $tenantId  = $tenant['id'];

        $name                = trim($request->input('name', ''));
        $ownerName           = trim($request->input('owner_name', ''));
        $phone               = trim($request->input('phone', ''));
        $email               = trim($request->input('email', ''));
        $address             = trim($request->input('address', ''));
        $defaultLocale       = $request->input('default_locale', 'en');
        $allowNegativeStock  = $request->input('allow_negative_stock') ? 1 : 0;
        $invoicePrefix       = strtoupper(trim((string)$request->input('invoice_prefix', 'INV'))) ?: 'INV';
        $invoicePrefix       = preg_replace('/[^A-Z0-9-]/', '', $invoicePrefix) ?: 'INV';
        $invoicePrefix       = substr($invoicePrefix, 0, 10);
        $defaultPaymentMode  = (string)$request->input('default_payment_mode', 'cash');
        if (!in_array($defaultPaymentMode, ['cash', 'upi', 'card'], true)) {
            $defaultPaymentMode = 'cash';
        }

        // GST fields
        $registrationMode    = $request->input('registration_mode', 'regular');
        $gstin               = trim($request->input('gstin', '')) ?: null;
        $legalName           = trim($request->input('legal_business_name', '')) ?: $name;
        $tradeName           = trim($request->input('trade_name', '')) ?: null;
        $stateCode           = $request->input('registered_state_code', '21');
        $effectiveFrom       = $request->input('effective_from') ?: null;

        if (empty($name)) {
            flash('error', 'Business Name is required.');
            Response::redirect('/app/settings');
            return;
        }

        // Validate GSTIN if provided for registered modes
        if ($registrationMode !== 'unregistered' && !empty($gstin)) {
            if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', strtoupper($gstin))) {
                flash('error', 'Invalid GSTIN format. Expected: 22AAAAA0000A1Z5');
                Response::redirect('/app/settings#gst');
                return;
            }
            $gstin = strtoupper($gstin);
        }

        Database::transaction(function(\PDO $pdo) use (
            $tenantId, $name, $ownerName, $phone, $email, $address,
            $defaultLocale, $allowNegativeStock, $invoicePrefix, $defaultPaymentMode,
            $registrationMode, $gstin, $legalName, $tradeName, $stateCode, $effectiveFrom
        ) {
            // Update tenant
            $pdo->prepare("
                UPDATE tenants
                SET name = ?, owner_name = ?, phone = ?, email = ?, address = ?,
                    default_locale = ?, allow_negative_stock = ?, invoice_prefix = ?,
                    default_payment_mode = ?, updated_at = NOW()
                WHERE id = ?
            ")->execute([
                $name, $ownerName, $phone, $email, $address, $defaultLocale,
                $allowNegativeStock, $invoicePrefix, $defaultPaymentMode, $tenantId,
            ]);

            // Upsert tax profile
            $existing = $pdo->prepare("SELECT id FROM tenant_tax_profiles WHERE tenant_id = ? ORDER BY id DESC LIMIT 1");
            $existing->execute([$tenantId]);
            $taxId = $existing->fetchColumn();

            if ($taxId) {
                $pdo->prepare("
                    UPDATE tenant_tax_profiles
                    SET registration_mode = ?, gstin = ?, legal_business_name = ?,
                        trade_name = ?, registered_state_code = ?, effective_from = ?
                    WHERE id = ?
                ")->execute([$registrationMode, $gstin, $legalName, $tradeName, $stateCode, $effectiveFrom, $taxId]);
            } else {
                $pdo->prepare("
                    INSERT INTO tenant_tax_profiles
                        (tenant_id, registration_mode, gstin, legal_business_name, trade_name, registered_state_code, effective_from)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ")->execute([$tenantId, $registrationMode, $gstin, $legalName, $tradeName, $stateCode, $effectiveFrom]);
            }
        });

        // Refresh session
        $freshTenant = Database::fetchOne("SELECT * FROM tenants WHERE id = :id", ['id' => $tenantId]);
        $freshTax    = Database::fetchOne("SELECT * FROM tenant_tax_profiles WHERE tenant_id = :id ORDER BY id DESC LIMIT 1", ['id' => $tenantId]);
        Session::set('tenant', $freshTenant);
        Session::set('tax_profile', $freshTax);

        AuditService::log('settings.updated', 'tenant', (int)$tenantId, [
            'name' => $name,
            'invoice_prefix' => $invoicePrefix,
            'default_payment_mode' => $defaultPaymentMode,
            'registration_mode' => $registrationMode,
        ], (int)$tenantId);

        flash('success', 'Business settings & GST configuration saved successfully.');
        Response::redirect('/app/settings');
    }

    /**
     * Add a new team member to this tenant.
     */
    public function addUser(Request $request): void {
        $tenant   = $request->tenant();
        $tenantId = $tenant['id'];

        $name     = trim($request->input('user_name', ''));
        $email    = trim($request->input('user_email', ''));
        $password = $request->input('user_password', '');
        $role     = $request->input('user_role', 'cashier');

        if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
            flash('error', 'Name, valid email, and password (min 8 chars) are required.');
            Response::redirect('/app/settings');
            return;
        }

        $validRoles = ['cashier', 'inventory_manager', 'accountant', 'tenant_admin'];
        if (!in_array($role, $validRoles)) {
            $role = 'cashier';
        }

        // Check duplicate
        $exists = Database::fetchOne(
            "SELECT id FROM users WHERE tenant_id = :tid AND email = :email",
            ['tid' => $tenantId, 'email' => $email]
        );
        if ($exists) {
            flash('error', "A user with email '{$email}' already exists in this store.");
            Response::redirect('/app/settings');
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        Database::connect()->prepare(
            "INSERT INTO users (tenant_id, name, email, password_hash, role, is_active, created_at)
             VALUES (?, ?, ?, ?, ?, 1, NOW())"
        )->execute([$tenantId, $name, $email, $hash, $role]);
        $newUserId = (int)Database::connect()->lastInsertId();

        AuditService::log('user.created', 'user', $newUserId, [
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ], (int)$tenantId);

        flash('success', "User '{$name}' created successfully with role: " . str_replace('_', ' ', $role) . '.');
        Response::redirect('/app/settings');
    }
}

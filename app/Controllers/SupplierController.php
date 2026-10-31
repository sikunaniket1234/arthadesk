<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;

class SupplierController {
    public function index(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = $tenant['id'];

        $suppliers = Database::fetchAll("
            SELECT s.*,
                   (SELECT COUNT(*) FROM products p WHERE p.supplier_id = s.id) as product_count,
                   (SELECT COALESCE(SUM(total_amount - paid_amount), 0) FROM purchase_bills pb WHERE pb.supplier_id = s.id AND pb.status != 'cancelled') as payable_balance
            FROM suppliers s
            WHERE s.tenant_id = :tid
            ORDER BY s.name ASC
        ", ['tid' => $tenantId]);

        View::render('suppliers/index', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'suppliers' => $suppliers,
        ]);
    }

    public function store(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $user = $request->user();

        $data = $this->validated($request);
        if (is_string($data)) {
            flash('error', $data);
            Response::redirect('/app/suppliers');
            return;
        }

        $stmt = Database::connect()->prepare("
            INSERT INTO suppliers (tenant_id, name, phone, email, gstin, state_code, address, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $tenantId, $data['name'], $data['phone'], $data['email'], $data['gstin'],
            $data['state_code'], $data['address'],
        ]);
        $supplierId = (int)Database::connect()->lastInsertId();

        AuditService::log('supplier.created', 'supplier', $supplierId, [
            'name' => $data['name'],
            'gstin' => $data['gstin'],
        ], $tenantId, (int)($user['id'] ?? 0));

        flash('success', "Dealer / Supplier '{$data['name']}' created successfully.");
        Response::redirect('/app/suppliers');
    }

    public function update(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $supplierId = (int)$request->param('id');
        $user = $request->user();

        $existing = Database::fetchOne("SELECT * FROM suppliers WHERE id = :id AND tenant_id = :tid", [
            'id' => $supplierId,
            'tid' => $tenantId,
        ]);
        if (!$existing) {
            flash('error', 'Supplier not found.');
            Response::redirect('/app/suppliers');
            return;
        }

        $data = $this->validated($request);
        if (is_string($data)) {
            flash('error', $data);
            Response::redirect('/app/suppliers');
            return;
        }

        Database::query("
            UPDATE suppliers
            SET name = ?, phone = ?, email = ?, gstin = ?, state_code = ?, address = ?
            WHERE id = ? AND tenant_id = ?
        ", [
            $data['name'], $data['phone'], $data['email'], $data['gstin'],
            $data['state_code'], $data['address'], $supplierId, $tenantId,
        ]);

        AuditService::log('supplier.updated', 'supplier', $supplierId, [
            'old_name' => $existing['name'],
            'name' => $data['name'],
        ], $tenantId, (int)($user['id'] ?? 0));

        flash('success', "Dealer / Supplier '{$data['name']}' updated.");
        Response::redirect('/app/suppliers');
    }

    protected function validated(Request $request) {
        $name = trim((string)$request->input('name', ''));
        $phone = trim((string)$request->input('phone', '')) ?: null;
        $email = trim((string)$request->input('email', '')) ?: null;
        $gstin = trim((string)$request->input('gstin', '')) ?: null;
        $stateCode = (string)$request->input('state_code', '21');
        $address = trim((string)$request->input('address', '')) ?: null;

        if ($name === '' || mb_strlen($name) > 100) {
            return 'Supplier / Dealer name is required (max 100 characters).';
        }
        if ($phone !== null && $phone !== '') {
            // Only 10 numeric digits are accepted; tolerate spaces/dashes on entry.
            $phone = (string)preg_replace('/[\s\-().]/', '', $phone);
            if (!preg_match('/^[0-9]{10}$/', $phone)) {
                return 'Phone must be exactly 10 digits.';
            }
        } else {
            $phone = null;
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return 'Invalid email address.';
        }
        if (!preg_match('/^[0-9]{2}$/', $stateCode)) {
            $stateCode = '21';
        }

        if (!empty($gstin)) {
            $gstinUpper = strtoupper($gstin);
            if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstinUpper)) {
                return 'Invalid GSTIN format. Expected format like 21AAAAA0000A1Z5.';
            }
            $gstin = $gstinUpper;
        } else {
            $gstin = null;
        }

        return [
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'gstin' => $gstin,
            'state_code' => $stateCode,
            'address' => $address ? substr($address, 0, 500) : null,
        ];
    }
}

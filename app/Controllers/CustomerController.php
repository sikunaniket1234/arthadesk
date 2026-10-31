<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;

class CustomerController {
    public function index(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = $tenant['id'];

        $customers = Database::fetchAll("
            SELECT c.*,
                   (SELECT COUNT(*) FROM invoices i WHERE i.customer_id = c.id AND i.payment_status != 'cancelled') as invoice_count,
                   (SELECT COALESCE(SUM(total_amount), 0) FROM invoices i WHERE i.customer_id = c.id AND i.payment_status != 'cancelled') as total_purchased
            FROM customers c
            WHERE c.tenant_id = :tid
            ORDER BY c.name ASC
        ", ['tid' => $tenantId]);

        View::render('customers/index', [
            'tenant' => $tenant,
            'taxProfile' => $request->taxProfile(),
            'customers' => $customers,
        ]);
    }

    public function store(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $user = $request->user();

        $data = $this->validated($request);
        if (is_string($data)) {
            flash('error', $data);
            Response::redirect('/app/customers');
            return;
        }

        $stmt = Database::connect()->prepare("
            INSERT INTO customers (tenant_id, name, phone, email, gstin, state_code, address, credit_limit, balance, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.00, NOW())
        ");
        $stmt->execute([
            $tenantId, $data['name'], $data['phone'], $data['email'], $data['gstin'],
            $data['state_code'], $data['address'], $data['credit_limit'],
        ]);
        $customerId = (int)Database::connect()->lastInsertId();

        AuditService::log('customer.created', 'customer', $customerId, [
            'name' => $data['name'],
            'phone' => $data['phone'],
            'credit_limit' => $data['credit_limit'],
        ], $tenantId, (int)($user['id'] ?? 0));

        flash('success', "Customer '{$data['name']}' created successfully.");
        Response::redirect('/app/customers');
    }

    public function update(Request $request): void {
        $tenant = $request->tenant();
        $tenantId = (int)$tenant['id'];
        $customerId = (int)$request->param('id');
        $user = $request->user();

        $existing = Database::fetchOne("SELECT * FROM customers WHERE id = :id AND tenant_id = :tid", [
            'id' => $customerId,
            'tid' => $tenantId,
        ]);
        if (!$existing) {
            flash('error', 'Customer not found.');
            Response::redirect('/app/customers');
            return;
        }

        $data = $this->validated($request);
        if (is_string($data)) {
            flash('error', $data);
            Response::redirect('/app/customers');
            return;
        }

        Database::query("
            UPDATE customers
            SET name = ?, phone = ?, email = ?, gstin = ?, state_code = ?, address = ?, credit_limit = ?
            WHERE id = ? AND tenant_id = ?
        ", [
            $data['name'], $data['phone'], $data['email'], $data['gstin'],
            $data['state_code'], $data['address'], $data['credit_limit'], $customerId, $tenantId,
        ]);

        AuditService::log('customer.updated', 'customer', $customerId, [
            'old_name' => $existing['name'],
            'name' => $data['name'],
            'credit_limit' => $data['credit_limit'],
        ], $tenantId, (int)($user['id'] ?? 0));

        flash('success', "Customer '{$data['name']}' updated.");
        Response::redirect('/app/customers');
    }

    protected function validated(Request $request) {
        $name = trim((string)$request->input('name', ''));
        $phone = trim((string)$request->input('phone', '')) ?: null;
        $email = trim((string)$request->input('email', '')) ?: null;
        $gstin = trim((string)$request->input('gstin', '')) ?: null;
        $stateCode = (string)$request->input('state_code', '21');
        $address = trim((string)$request->input('address', '')) ?: null;
        $creditLimit = max(0, round((float)$request->input('credit_limit', 0), 2));

        if ($name === '' || mb_strlen($name) > 100) {
            return 'Customer name is required (max 100 characters).';
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
            'credit_limit' => $creditLimit,
        ];
    }
}

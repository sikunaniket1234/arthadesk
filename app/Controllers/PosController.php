<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Core\Logger;
use App\Services\AuditService;
use App\Services\IdempotencyService;
use App\Services\SalesTransactionService;
use App\Services\TaxEngine;
use Exception;

class PosController {
    public function index(Request $request): void {
        $tenant = $request->tenant();
        $taxProfile = $request->taxProfile();
        $tenantId = $tenant['id'];

        $products = Database::fetchAll("
            SELECT p.*, tc.category_code as tax_classification, tc.rate_percent as tax_rate_percent, u.name_en as unit_name, u.name_or as unit_name_or
            FROM products p
            LEFT JOIN tax_categories tc ON p.tax_category_id = tc.id
            LEFT JOIN units_of_measure u ON p.unit_id = u.id
            WHERE p.tenant_id = :tid AND p.is_active = 1
            ORDER BY p.name_en ASC
        ", ['tid' => $tenantId]);

        $categories = Database::fetchAll("
            SELECT DISTINCT category FROM products WHERE tenant_id = :tid AND is_active = 1 AND category IS NOT NULL
        ", ['tid' => $tenantId]);

        $customers = Database::fetchAll("
            SELECT id, name, phone, gstin, state_code, credit_limit, balance
            FROM customers WHERE tenant_id = :tid ORDER BY name ASC
        ", ['tid' => $tenantId]);

        $allTenants = [];
        if (($request->user()['role'] ?? '') === 'super_admin') {
            $allTenants = Database::fetchAll("SELECT id, name, slug, status FROM tenants ORDER BY id ASC");
        }

        View::render('pos/index', [
            'tenant' => $tenant,
            'taxProfile' => $taxProfile,
            'products' => $products,
            'categories' => array_column($categories, 'category'),
            'customers' => $customers,
            'allTenants' => $allTenants,
            'defaultPaymentMode' => $tenant['default_payment_mode'] ?? 'cash',
        ]);
    }

    public function calculate(Request $request): void {
        $taxProfile = $request->taxProfile() ?: [];
        $items = $request->input('items', []);
        $placeOfSupply = $request->input('place_of_supply', $taxProfile['registered_state_code'] ?? '21');

        if (!is_array($items) || empty($items)) {
            Response::json(['success' => false, 'error' => 'Cart is empty.'], 422);
            return;
        }

        $calc = TaxEngine::calculate($taxProfile, $items, (string)$placeOfSupply);
        Response::json($calc);
    }

    public function checkout(Request $request): void {
        $tenant = $request->tenant();
        $taxProfile = $request->taxProfile();
        $user = $request->user();

        $cartData = [
            'items' => $request->input('items', []),
            'customer_id' => $request->input('customer_id') ?: null,
            'walkin_name' => $request->input('walkin_name'),
            'walkin_phone' => $request->input('walkin_phone'),
            'payment_method' => $request->input('payment_method', $tenant['default_payment_mode'] ?? 'cash'),
            'payments' => $request->input('payments'),
            'place_of_supply' => $request->input('place_of_supply', $taxProfile['registered_state_code'] ?? '21'),
            'notes' => $request->input('notes'),
        ];

        $idemKey = trim((string)$request->header('Idempotency-Key', ''));
        $requestHash = hash('sha256', json_encode([
            'tenant' => (int)$tenant['id'],
            'user' => (int)$user['id'],
            'cart' => $cartData,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        if ($idemKey !== '') {
            $idem = IdempotencyService::begin((int)$tenant['id'], (int)$user['id'], $idemKey, $requestHash);
            if ($idem['state'] === 'completed') {
                $cached = $idem['response'] ?? [];
                $cached['replayed'] = true;
                Response::json($cached);
                return;
            }
            if ($idem['state'] === 'processing') {
                Response::json(['success' => false, 'error' => 'This checkout is already being processed. Please wait.'], 409);
                return;
            }
            if ($idem['state'] === 'conflict') {
                Response::json(['success' => false, 'error' => 'Idempotency key was reused with a different cart.'], 422);
                return;
            }
            if ($idem['state'] === 'invalid') {
                Response::json(['success' => false, 'error' => 'Invalid Idempotency-Key header.'], 422);
                return;
            }
        }

        try {
            $result = SalesTransactionService::createSale($tenant, $taxProfile, $cartData, (int)$user['id']);
            if ($idemKey !== '') {
                IdempotencyService::complete((int)$tenant['id'], $idemKey, $result);
            }

            AuditService::log('sale.created', 'invoice', (int)$result['invoice_id'], [
                'invoice_number' => $result['invoice_number'],
                'total_amount' => $result['total_amount'],
                'payment_status' => $result['payment_status'],
                'credit_amount' => $result['credit_amount'],
                'idempotency_key' => $idemKey !== '' ? substr($idemKey, 0, 12) . '…' : null,
            ], (int)$tenant['id'], (int)$user['id']);

            Response::json($result);
        } catch (\PDOException $e) {
            if ($idemKey !== '') {
                IdempotencyService::fail((int)$tenant['id'], $idemKey, 'Database error');
            }
            Logger::error('checkout_db_failed', [
                'tenant_id' => (int)$tenant['id'],
                'user_id' => (int)$user['id'],
                'error' => $e->getMessage(),
            ], 'api');
            Response::json(['success' => false, 'error' => 'Could not complete the sale. Please try again.'], 500);
        } catch (Exception $e) {
            if ($idemKey !== '') {
                IdempotencyService::fail((int)$tenant['id'], $idemKey, $e->getMessage());
            }
            Response::json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            if ($idemKey !== '') {
                IdempotencyService::fail((int)$tenant['id'], $idemKey, 'Internal error');
            }
            Logger::error('checkout_failed', [
                'tenant_id' => (int)$tenant['id'],
                'user_id' => (int)$user['id'],
                'error' => $e->getMessage(),
            ], 'api');
            Response::json(['success' => false, 'error' => 'Checkout failed. Please try again.'], 500);
        }
    }

    public function quickCustomer(Request $request): void {
        $tenant = $request->tenant();
        $user = $request->user();
        $name = trim((string)$request->input('name', ''));
        $phone = trim((string)$request->input('phone', '')) ?: null;
        $email = trim((string)$request->input('email', '')) ?: null;
        $gstin = trim((string)$request->input('gstin', '')) ?: null;
        $stateCode = (string)$request->input('state_code', '21');
        $address = trim((string)$request->input('address', '')) ?: null;
        $creditLimit = max(0, (float)$request->input('credit_limit', 0));

        if ($name === '' || mb_strlen($name) > 100) {
            Response::json(['success' => false, 'error' => 'Customer name is required (max 100 characters).'], 422);
            return;
        }
        if ($phone !== null && !preg_match('/^[0-9+\-\s]{6,20}$/', $phone)) {
            Response::json(['success' => false, 'error' => 'Invalid phone number.'], 422);
            return;
        }
        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['success' => false, 'error' => 'Invalid email address.'], 422);
            return;
        }
        if (!preg_match('/^[0-9]{2}$/', $stateCode)) {
            $stateCode = '21';
        }

        if (!empty($gstin)) {
            $gstinUpper = strtoupper($gstin);
            if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstinUpper)) {
                Response::json(['success' => false, 'error' => 'Invalid GSTIN format.'], 422);
                return;
            }
            $gstin = $gstinUpper;
        }

        try {
            $stmt = Database::connect()->prepare("
                INSERT INTO customers (tenant_id, name, phone, email, gstin, state_code, address, credit_limit, balance, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0.00, NOW())
            ");
            $stmt->execute([$tenant['id'], $name, $phone, $email, $gstin, $stateCode, $address, $creditLimit]);
            $customerId = (int)Database::connect()->lastInsertId();

            AuditService::log('customer.created', 'customer', $customerId, [
                'name' => $name,
                'phone' => $phone,
                'source' => 'pos_quick_add',
            ], (int)$tenant['id'], (int)$user['id']);

            Response::json([
                'success' => true,
                'customer' => [
                    'id' => $customerId,
                    'name' => $name,
                    'phone' => $phone,
                    'gstin' => $gstin,
                    'state_code' => $stateCode,
                    'credit_limit' => $creditLimit,
                    'balance' => 0,
                ],
            ]);
        } catch (\Throwable $e) {
            Logger::error('quick_customer_failed', [
                'tenant_id' => (int)$tenant['id'],
                'error' => $e->getMessage(),
            ], 'api');
            Response::json(['success' => false, 'error' => 'Could not create customer right now.'], 500);
        }
    }
}

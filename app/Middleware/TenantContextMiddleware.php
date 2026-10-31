<?php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;
use App\Services\TenantResolver;

class TenantContextMiddleware {
    public function handle(Request $request) {
        $user = $request->user() ?? Session::get('user');
        if (empty($user)) {
            Response::error('Authentication required.', 401);
            return false;
        }

        $role = $user['role'] ?? '';
        $resolved = $request->resolvedTenant() ?? TenantResolver::current();
        $tenantId = null;

        if (!empty($resolved['id'])) {
            if ($role !== 'super_admin' && (int)($user['tenant_id'] ?? 0) !== (int)$resolved['id']) {
                Response::error('You do not have access to this store.', 403);
                return false;
            }
            $tenantId = (int)$resolved['id'];
            Session::set('current_tenant_id', $tenantId);
        } elseif ($role === 'super_admin') {
            $tenantId = Session::get('current_tenant_id');
            if (empty($tenantId)) {
                Response::error('Select a business tenant from Platform Admin before using business screens.', 403);
                return false;
            }
        } else {
            $tenantId = $user['tenant_id'] ?? null;
        }

        if (empty($tenantId)) {
            Response::error('No business tenant is associated with this account.', 403);
            return false;
        }

        $tenant = Database::fetchOne("SELECT * FROM tenants WHERE id = :id LIMIT 1", ['id' => (int)$tenantId]);
        if (!$tenant) {
            Session::remove('current_tenant_id');
            Response::error('Business tenant not found.', 404);
            return false;
        }

        if ($role !== 'super_admin' && (int)$tenant['id'] !== (int)($user['tenant_id'] ?? 0)) {
            Response::error('Tenant isolation violation blocked.', 403);
            return false;
        }

        $taxProfile = Database::fetchOne(
            "SELECT * FROM tenant_tax_profiles WHERE tenant_id = :id ORDER BY id DESC LIMIT 1",
            ['id' => (int)$tenantId]
        );

        $request->setTenant($tenant);
        $request->setTaxProfile($taxProfile);
        Session::set('tenant', $tenant);
        Session::set('tax_profile', $taxProfile);
        Session::set('current_tenant_id', (int)$tenantId);

        return null;
    }
}

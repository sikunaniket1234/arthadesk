<?php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\TenantResolver;

class TenantResolverMiddleware {
    public function handle(Request $request) {
        $tenant = TenantResolver::resolve();
        $request->setResolvedTenant($tenant);

        if ($tenant === null && TenantResolver::isUnknownHost() && TenantResolver::enforceHosts()) {
            Response::error('This store address was not found.', 404);
            return false;
        }

        return null;
    }
}

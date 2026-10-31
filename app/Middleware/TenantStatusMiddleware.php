<?php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Database;

class TenantStatusMiddleware {
    public function handle(Request $request) {
        $tenant = $request->tenant();
        if (!$tenant) {
            return null;
        }

        // Live check against database
        $freshTenant = Database::fetchOne("SELECT status, name FROM tenants WHERE id = :id", ['id' => $tenant['id']]);
        if ($freshTenant && $freshTenant['status'] === 'suspended') {
            if ($request->isAjax()) {
                Response::json([
                    'error' => 'Account Suspended',
                    'message' => "The tenant account '{$freshTenant['name']}' is suspended. Business operations are currently disabled."
                ], 403);
            }

            Response::error(
                "Access Denied: The business account '{$freshTenant['name']}' is currently suspended. Please contact the platform administrator to reactivate your subscription.",
                403
            );
            return false;
        }

        return null;
    }
}

<?php
namespace App\Middleware;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\TenantResolver;

class SuperAdminMiddleware {
    public function handle(Request $request) {
        $user = Session::get('user');

        if (empty($user) || ($user['role'] ?? '') !== 'super_admin') {
            Logger::warning('admin_access_denied', [
                'user_id' => $user['id'] ?? null,
                'uri' => $request->uri(),
            ], 'security');

            if ($request->isAjax()) {
                Response::json(['error' => 'Forbidden'], 403);
                return false;
            }

            Response::error('You do not have permission to access platform administration.', 403);
            return false;
        }

        $enforce = (bool)Config::get('tenancy.enforce_hosts', true);
        if ($enforce && Config::get('app.env', 'development') === 'production' && !TenantResolver::isAdminHost()) {
            Logger::warning('admin_wrong_host', [
                'host' => TenantResolver::host(),
                'user_id' => $user['id'] ?? null,
            ], 'security');

            Response::error('Platform administration is available only on the admin host.', 403);
            return false;
        }

        return null;
    }
}

<?php
namespace App\Middleware;

use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\Authorization;

class AuthorizationMiddleware {
    public function handle(Request $request) {
        $user = Session::get('user');
        $role = $user['role'] ?? null;

        if (!Authorization::can($role, $request->uri())) {
            Logger::warning('route_access_denied', [
                'user_id' => $user['id'] ?? null,
                'role' => $role,
                'uri' => $request->uri(),
            ], 'security');

            if ($request->isAjax()) {
                Response::json(['error' => 'You do not have permission to perform this action.'], 403);
                return false;
            }

            Response::error('You do not have permission to access this page.', 403);
            return false;
        }

        return null;
    }
}

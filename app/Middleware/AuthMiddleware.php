<?php
namespace App\Middleware;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class AuthMiddleware {
    public function handle(Request $request) {
        $sessionUser = Session::get('user');
        if (empty($sessionUser['id'])) {
            $this->deny($request);
            return false;
        }

        try {
            $user = Database::fetchOne(
                "SELECT id, tenant_id, name, email, role, is_active FROM users WHERE id = :id LIMIT 1",
                ['id' => (int)$sessionUser['id']]
            );
        } catch (\Throwable $e) {
            Logger::error('auth_refresh_failed', ['error' => $e->getMessage()], 'security');
            Response::error('Authentication temporarily unavailable.', 503);
            return false;
        }

        if (!$user || empty($user['is_active'])) {
            Logger::warning('auth_session_revoked', ['user_id' => $sessionUser['id'] ?? null], 'security');
            Session::destroy();
            $this->deny($request);
            return false;
        }

        $user['id'] = (int)$user['id'];
        $user['tenant_id'] = $user['tenant_id'] !== null ? (int)$user['tenant_id'] : null;
        Session::set('user', $user);

        if (!Session::get('api_token')) {
            Session::set('api_token', bin2hex(random_bytes(24)));
        }

        $request->setUser($user);

        return null;
    }

    protected function deny(Request $request): void {
        if ($request->isAjax()) {
            Response::json(['error' => 'Unauthenticated'], 401);
            return;
        }
        Response::redirect('/auth/login');
    }
}

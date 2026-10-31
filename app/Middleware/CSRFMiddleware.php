<?php
namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

class CSRFMiddleware {
    public function handle(Request $request) {
        if (in_array($request->method(), ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            $token = $request->input('_csrf_token') ?? $request->header('X-CSRF-TOKEN');

            if (!Session::validateCsrf(is_string($token) ? $token : null)) {
                if ($request->isAjax() || $request->isJson()) {
                    Response::json(['error' => 'Invalid or expired CSRF token. Please refresh the page.'], 419);
                    return false;
                }
                Response::error('Page expired or invalid CSRF submission. Please go back and try again.', 419);
                return false;
            }
        }
        return null;
    }
}

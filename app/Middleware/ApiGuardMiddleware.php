<?php
namespace App\Middleware;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\RateLimiter;
use App\Services\TenantResolver;

class ApiGuardMiddleware {
    public function handle(Request $request) {
        $user = $request->user() ?? Session::get('user');
        if (empty($user['id'])) {
            Response::json(['success' => false, 'error' => 'Unauthenticated'], 401);
            return false;
        }

        $contentType = strtolower((string)$request->header('Content-Type', ''));
        if (!str_contains($contentType, 'application/json')) {
            Response::json(['success' => false, 'error' => 'Content-Type must be application/json'], 415);
            return false;
        }

        if (!$this->originAllowed($request)) {
            Logger::warning('api_origin_rejected', [
                'origin' => $request->header('Origin', ''),
                'referer' => $request->header('Referer', ''),
                'user_id' => $user['id'],
                'uri' => $request->uri(),
            ], 'security');
            Response::json(['success' => false, 'error' => 'Request origin is not allowed'], 403);
            return false;
        }

        if ((bool)Config::get('security.api.require_token', true)) {
            $expected = (string)Session::get('api_token', '');
            $provided = (string)$request->header('X-API-Token', '');
            if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
                Logger::warning('api_token_rejected', [
                    'user_id' => $user['id'],
                    'uri' => $request->uri(),
                ], 'security');
                Response::json(['success' => false, 'error' => 'Invalid API session token. Please sign in again.'], 403);
                return false;
            }
        }

        $isCheckout = str_contains($request->uri(), '/checkout');
        $limit = $isCheckout
            ? max(1, (int)Config::get('security.api.checkout_rate_limit', 30))
            : max(1, (int)Config::get('security.api.rate_limit', 120));
        $window = max(1, (int)Config::get('security.api.rate_window', 60));

        $rate = RateLimiter::hit('api:' . (int)$user['id'] . ':' . $request->uri(), $limit, $window);
        if (!$rate['allowed']) {
            if (!headers_sent()) {
                header('Retry-After: ' . (int)$rate['retry_after']);
            }
            Response::json([
                'success' => false,
                'error' => 'Too many requests. Please slow down and try again.',
            ], 429);
            return false;
        }

        return null;
    }

    protected function originAllowed(Request $request): bool {
        $origin = (string)$request->header('Origin', '');
        $referer = (string)$request->header('Referer', '');

        if ($origin === '' && $referer === '') {
            return true;
        }

        $allowedHosts = array_filter(array_unique([
            TenantResolver::host(),
            TenantResolver::baseDomain(),
            'www.' . TenantResolver::baseDomain(),
            TenantResolver::adminHost(),
            'localhost',
            '127.0.0.1',
        ]));

        $candidates = [];
        if ($origin !== '') {
            $candidates[] = parse_url($origin, PHP_URL_HOST);
        }
        if ($referer !== '') {
            $candidates[] = parse_url($referer, PHP_URL_HOST);
        }

        foreach ($candidates as $host) {
            if (!is_string($host) || $host === '') {
                continue;
            }
            $host = strtolower($host);
            if (in_array($host, $allowedHosts, true)) {
                return true;
            }
            if (str_ends_with($host, '.' . TenantResolver::baseDomain())) {
                return true;
            }
        }

        return false;
    }
}

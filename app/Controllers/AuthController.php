<?php
namespace App\Controllers;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Database;
use App\Core\View;
use App\Localization\Translator;
use App\Services\AuditService;
use App\Services\CaptchaService;
use App\Services\LoginThrottle;
use App\Services\TenantResolver;

class AuthController {
    public function showLogin(Request $request): void {
        if (Session::get('user')) {
            $this->redirectAfterLogin(Session::get('user'));
        }

        $tenant = TenantResolver::current();
        CaptchaService::prepare();

        View::render('auth/login', [
            'tenant' => $tenant,
            'tenantUnavailable' => $tenant && ($tenant['status'] ?? '') !== 'active',
            'captchaProvider' => CaptchaService::provider(),
            'captchaQuestion' => CaptchaService::provider() === 'math' ? CaptchaService::question() : '',
            'captchaSiteKey' => CaptchaService::siteKey(),
            'captchaEnabled' => CaptchaService::enabled(),
            'formTs' => time(),
            'noLayout' => true,
        ]);
    }

    public function login(Request $request): void {
        $email = strtolower(trim((string)$request->input('email', '')));
        $password = (string)$request->input('password', '');
        $ip = AuditService::ip();
        $tenant = TenantResolver::current();

        if ($email === '' || $password === '') {
            $this->failLogin($request, 'Email and password are required.', $email, $ip, 'missing_credentials');
            return;
        }

        if (!CaptchaService::verify($request)) {
            $this->failLogin($request, 'Captcha verification failed. Please try again.', $email, $ip, 'captcha_failed');
            return;
        }

        if (LoginThrottle::tooMany($email, $ip)) {
            $this->failLogin($request, 'Too many failed attempts. Please wait a few minutes and try again.', $email, $ip, 'throttled', false);
            return;
        }

        if ($tenant && ($tenant['status'] ?? '') !== 'active') {
            $this->failLogin($request, 'This store is currently unavailable. Please contact the platform administrator.', $email, $ip, 'tenant_unavailable', false, (int)$tenant['id']);
            return;
        }

        try {
            if ($tenant) {
                $user = Database::fetchOne("
                    SELECT u.*, t.name as tenant_name, t.slug as tenant_slug, t.status as tenant_status
                    FROM users u
                    LEFT JOIN tenants t ON u.tenant_id = t.id
                    WHERE u.email = :email AND u.tenant_id = :tid
                    LIMIT 1
                ", ['email' => $email, 'tid' => (int)$tenant['id']]);
            } else {
                $user = Database::fetchOne("
                    SELECT u.*, t.name as tenant_name, t.slug as tenant_slug, t.status as tenant_status
                    FROM users u
                    LEFT JOIN tenants t ON u.tenant_id = t.id
                    WHERE u.email = :email
                    ORDER BY (u.role = 'super_admin') DESC, u.id ASC
                    LIMIT 1
                ", ['email' => $email]);
            }
        } catch (\Throwable $e) {
            \App\Core\Logger::error('login_query_failed', ['error' => $e->getMessage()], 'security');
            $this->failLogin($request, 'Sign in is temporarily unavailable. Please try again.', $email, $ip, 'database_error', false);
            return;
        }

        if (!$user || !password_verify($password, (string)$user['password_hash'])) {
            $this->failLogin($request, 'Invalid email or password.', $email, $ip, 'invalid_credentials');
            return;
        }

        if (empty($user['is_active'])) {
            $this->failLogin($request, 'This user account is deactivated.', $email, $ip, 'inactive_user');
            return;
        }

        if (!empty($user['tenant_id']) && ($user['tenant_status'] ?? '') !== 'active') {
            $this->failLogin($request, 'This store is currently unavailable. Please contact the platform administrator.', $email, $ip, 'tenant_unavailable', false, (int)$user['tenant_id']);
            return;
        }

        $enforceHosts = TenantResolver::enforceHosts();
        $isProduction = Config::get('app.env', 'development') === 'production';
        if (!$tenant && $enforceHosts && $isProduction && ($user['role'] ?? '') !== 'super_admin') {
            LoginThrottle::record($email, $ip, false, 'wrong_host', (int)($user['tenant_id'] ?? 0));
            flash('error', 'Please sign in from your store address.');
            Response::redirect('/auth/login');
            return;
        }

        $this->setUserSession($user, $tenant);
        LoginThrottle::record($email, $ip, true, 'success', (int)($user['tenant_id'] ?? 0));
        AuditService::log('auth.login', 'user', (int)$user['id'], [
            'email' => $email,
            'role' => $user['role'],
            'host' => TenantResolver::host(),
        ], $user['tenant_id'] ? (int)$user['tenant_id'] : null, (int)$user['id']);

        flash('success', "Welcome back, {$user['name']}!");
        $this->redirectAfterLogin($user);
    }

    protected function failLogin(Request $request, string $message, string $email, string $ip, string $reason, bool $throttle = true, ?int $tenantId = null): void {
        if ($throttle) {
            LoginThrottle::record($email, $ip, false, $reason, $tenantId);
        }
        AuditService::log('auth.login_failed', 'user', null, [
            'email' => $email,
            'reason' => $reason,
            'host' => TenantResolver::host(),
        ], $tenantId);
        CaptchaService::reset();
        CaptchaService::prepare();
        flash('error', $message);
        Response::redirect('/auth/login');
    }

    protected function setUserSession(array $user, ?array $resolvedTenant = null): void {
        Session::regenerate();
        Session::set('user', [
            'id' => (int)$user['id'],
            'tenant_id' => $user['tenant_id'] ? (int)$user['tenant_id'] : null,
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
        ]);
        Session::set('api_token', bin2hex(random_bytes(24)));

        $tenantId = $user['tenant_id'] ? (int)$user['tenant_id'] : null;
        if (!$tenantId && $resolvedTenant) {
            $tenantId = (int)$resolvedTenant['id'];
        }

        if ($tenantId) {
            Session::set('current_tenant_id', $tenantId);
            $tenant = Database::fetchOne("SELECT * FROM tenants WHERE id = :id LIMIT 1", ['id' => $tenantId]);
            if ($tenant) {
                Session::set('tenant', $tenant);
                Session::set('tax_profile', Database::fetchOne(
                    "SELECT * FROM tenant_tax_profiles WHERE tenant_id = :id ORDER BY id DESC LIMIT 1",
                    ['id' => $tenantId]
                ));
                if (!empty($tenant['default_locale'])) {
                    Translator::setLocale((string)$tenant['default_locale']);
                }
            }
        }
    }

    protected function redirectAfterLogin(array $user): void {
        if (($user['role'] ?? '') === 'super_admin') {
            if (TenantResolver::current()) {
                Response::redirect('/app/pos');
            }
            Response::redirect('/admin/tenants');
        }
        Response::redirect('/app/pos');
    }

    public function switchTenant(Request $request): void {
        $user = Session::get('user');
        if (($user['role'] ?? '') !== 'super_admin') {
            Response::error('Only the platform administrator can switch tenants.', 403);
            return;
        }

        $tenantId = (int)$request->input('tenant_id');
        $tenant = Database::fetchOne("SELECT * FROM tenants WHERE id = :id LIMIT 1", ['id' => $tenantId]);

        if (!$tenant) {
            flash('error', 'Business tenant not found.');
            Response::redirect($this->safeReferer($request, '/admin/tenants'));
            return;
        }

        if (($tenant['status'] ?? '') !== 'active') {
            flash('error', 'This tenant is suspended. Reactivate it before opening its POS.');
            Response::redirect($this->safeReferer($request, '/admin/tenants'));
            return;
        }

        Session::set('current_tenant_id', (int)$tenant['id']);
        Session::set('tenant', $tenant);

        $taxProfile = Database::fetchOne(
            "SELECT * FROM tenant_tax_profiles WHERE tenant_id = :id ORDER BY id DESC LIMIT 1",
            ['id' => (int)$tenant['id']]
        );
        Session::set('tax_profile', $taxProfile);

        if (!empty($tenant['default_locale'])) {
            Translator::setLocale((string)$tenant['default_locale']);
        }

        AuditService::log('tenant.switch', 'tenant', (int)$tenant['id'], [
            'name' => $tenant['name'],
            'slug' => $tenant['slug'],
        ], (int)$tenant['id']);

        flash('success', "Switched business to: {$tenant['name']}");
        Response::redirect($this->safeReferer($request, '/app/pos'));
    }

    public function switchLocale(Request $request): void {
        $locale = (string)$request->input('locale', 'en');
        $supported = (array)Config::get('app.supported_locales', ['en', 'or']);
        if (in_array($locale, $supported, true)) {
            Translator::setLocale($locale);
        }
        Response::redirect($this->safeReferer($request, '/app/pos'));
    }

    public function logout(Request $request): void {
        $user = Session::get('user');
        if ($user) {
            AuditService::log('auth.logout', 'user', (int)$user['id'], [], $user['tenant_id'] ?? null, (int)$user['id']);
        }
        Session::destroy();
        Response::redirect('/auth/login');
    }

    protected function safeReferer(Request $request, string $fallback): string {
        $referer = (string)$request->header('Referer', '');
        if ($referer === '') {
            return $fallback;
        }
        $path = parse_url($referer, PHP_URL_PATH);
        if (!is_string($path) || !str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $fallback;
        }
        return $path;
    }
}

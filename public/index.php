<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/autoload.php';

$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (file_exists($vendorAutoload)) {
    require_once $vendorAutoload;
}

use App\Core\Config;
use App\Core\Logger;
use App\Core\Response;
use App\Core\Session;
use App\Core\Router;
use App\Core\Request;
use App\Core\MigrationRunner;
use App\Middleware\ApiGuardMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\AuthorizationMiddleware;
use App\Middleware\CSRFMiddleware;
use App\Middleware\SuperAdminMiddleware;
use App\Middleware\TenantContextMiddleware;
use App\Middleware\TenantResolverMiddleware;
use App\Middleware\TenantStatusMiddleware;

Config::load();
Logger::init();

$isProduction = Config::get('app.env', 'development') === 'production';
ini_set('display_errors', $isProduction ? '0' : '1');
ini_set('log_errors', '1');
error_reporting(E_ALL);
header_remove('X-Powered-By');

function arthaBootFailure(string $reason): void {
    Logger::critical('boot_guard_failed', ['reason' => $reason], 'security');
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Service unavailable</title></head><body>';
    echo '<h1>Service temporarily unavailable</h1><p>The application is not configured for production yet.</p>';
    echo '<p>Reference: ' . htmlspecialchars(Logger::requestId(), ENT_QUOTES, 'UTF-8') . '</p></body></html>';
    exit;
}

if ($isProduction) {
    $appKey = (string)Config::get('app.key', '');
    $appUrl = (string)Config::get('app.url', '');
    $dbPass = (string)Config::get('database.connections.mysql.password', '');

    if (Config::get('app.debug', false)) {
        arthaBootFailure('APP_DEBUG must be false in production');
    }
    if (strlen($appKey) < 32 || in_array($appKey, ['change-me', 'secret', 'base64:change-me'], true)) {
        arthaBootFailure('APP_KEY is missing or too short');
    }
    if (!str_starts_with($appUrl, 'https://')) {
        arthaBootFailure('APP_URL must use https in production');
    }
    if ($dbPass === '' || in_array($dbPass, ['arthapass', 'secure_password', 'password', 'root'], true)) {
        arthaBootFailure('Database password is a known default');
    }
    if (Config::get('app.env_switch_enabled', false) && strlen((string)Config::get('app.env_switch_key', '')) < 32) {
        arthaBootFailure('ENV_SWITCH_KEY must be at least 32 characters');
    }
    if (Config::get('captcha.enabled', true)) {
        $provider = (string)Config::get('captcha.provider', 'math');
        $allowMath = (bool)Config::get('captcha.allow_math_in_production', false);
        if ($provider === 'math' && !$allowMath) {
            arthaBootFailure('CAPTCHA_PROVIDER must be turnstile in production');
        }
        if ($provider === 'turnstile') {
            if ((string)Config::get('captcha.turnstile_site_key', '') === '' || (string)Config::get('captcha.turnstile_secret', '') === '') {
                arthaBootFailure('Turnstile keys are missing');
            }
        }
    }
}

set_exception_handler(function (\Throwable $e): void {
    Logger::critical('unhandled_exception', [
        'exception' => get_class($e),
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);

    if (!headers_sent()) {
        http_response_code(500);
    }

    $accept = (string)($_SERVER['HTTP_ACCEPT'] ?? '');
    $requestedWith = (string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    $contentType = (string)($_SERVER['CONTENT_TYPE'] ?? '');
    $wantsJson = str_contains($accept, 'application/json')
        || str_contains($contentType, 'application/json')
        || strtolower($requestedWith) === 'xmlhttprequest';

    if ($wantsJson) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'An unexpected error occurred. Please try again.',
            'request_id' => Logger::requestId(),
        ]);
        return;
    }

    header('Content-Type: text/html; charset=utf-8');
    $debug = (bool)Config::get('app.debug', false);
    echo '<!doctype html><html><head><meta charset="utf-8"><title>Something went wrong</title></head><body style="font-family:sans-serif;max-width:720px;margin:4rem auto;padding:0 1rem;">';
    echo '<h1>Something went wrong</h1>';
    echo '<p>Please try again. If the problem continues, contact support with the reference below.</p>';
    echo '<p><strong>Reference:</strong> ' . htmlspecialchars(Logger::requestId(), ENT_QUOTES, 'UTF-8') . '</p>';
    if ($debug) {
        echo '<pre style="white-space:pre-wrap;background:#f8fafc;padding:1rem;border-radius:8px;">';
        echo htmlspecialchars(get_class($e) . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine(), ENT_QUOTES, 'UTF-8');
        echo '</pre>';
    }
    echo '</body></html>';
});

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }

    Logger::warning('php_error', [
        'severity' => $severity,
        'message' => $message,
        'file' => $file,
        'line' => $line,
    ]);

    return false;
});

register_shutdown_function(function (): void {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        Logger::critical('php_fatal', [
            'message' => $error['message'],
            'file' => $error['file'],
            'line' => $error['line'],
        ]);
    }
});

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
    header('X-Request-ID: ' . Logger::requestId());

    // Public marketing pages are indexable; app, auth, admin and health
    // endpoints stay out of search results. Prefix-based so it holds no
    // matter how the request URI is normalised by the web server.
    $requestPath = (string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if ($requestPath === '' || $requestPath[0] !== '/') {
        $requestPath = '/' . ltrim($requestPath, '/');
    }
    $noIndexPrefixes = ['/app', '/admin', '/auth', '/healthz'];
    $noIndex = false;
    foreach ($noIndexPrefixes as $prefix) {
        if ($requestPath === $prefix || str_starts_with($requestPath, $prefix . '/')) {
            $noIndex = true;
            break;
        }
    }
    header($noIndex
        ? 'X-Robots-Tag: noindex, nofollow'
        : 'X-Robots-Tag: index, follow, max-image-preview:large');

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    if ($https && Config::get('security.headers.hsts', true)) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    if (Config::get('security.headers.csp', true)) {
        header("Content-Security-Policy: default-src 'self'; base-uri 'self'; object-src 'none'; frame-ancestors 'self'; form-action 'self'; img-src 'self' data:; script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com data:; connect-src 'self' https://challenges.cloudflare.com; frame-src https://challenges.cloudflare.com;");
    }
}

Session::start();

if (Config::get('app.auto_migrate', false)) {
    try {
        MigrationRunner::run((bool)Config::get('app.seed_demo', false));
    } catch (\Throwable $e) {
        Logger::critical('auto_migration_failed', ['error' => $e->getMessage()]);
        if (!Config::get('app.debug', false)) {
            arthaBootFailure('Database schema is not ready');
        }
    }
}

$router = new Router();
$request = new Request();

$apiPrefix = trim((string)Config::get('app.api_prefix', ''), '/');
$apiPath = static function (string $path) use ($apiPrefix): string {
    return '/' . ($apiPrefix !== '' ? $apiPrefix . '/' : '') . ltrim($path, '/');
};

// Public marketing surface. On a shop subdomain the root keeps its original
// behaviour (login / POS); on the apex domain it serves the landing page.
$router->get('/', 'LandingController@index', [TenantResolverMiddleware::class]);
$router->get('/robots.txt', 'LandingController@robots');
$router->get('/sitemap.xml', 'LandingController@sitemap');
$router->post('/leads', 'LandingController@submit', [CSRFMiddleware::class]);

$router->get('/healthz', function (Request $req) {
    $db = 'ok';
    try {
        \App\Core\Database::connect()->query('SELECT 1');
    } catch (\Throwable $e) {
        $db = 'down';
    }
    Response::json([
        'status' => $db === 'ok' ? 'ok' : 'degraded',
        'env' => Config::get('app.env'),
        'db' => $db,
        'request_id' => Logger::requestId(),
        'time' => gmdate('c'),
    ], $db === 'ok' ? 200 : 503);
});

$router->group(['middleware' => [TenantResolverMiddleware::class]], function (Router $r) {
    $r->get('/auth/login', 'AuthController@showLogin');
    $r->post('/auth/login', 'AuthController@login');
});

$router->get('/auth/logout', 'AuthController@logout');
$router->get('/auth/locale', 'AuthController@switchLocale');

$router->post('/auth/switch-tenant', 'AuthController@switchTenant', [
    AuthMiddleware::class,
    SuperAdminMiddleware::class,
    CSRFMiddleware::class,
]);

$router->group([
    'prefix' => '/admin',
    'middleware' => [AuthMiddleware::class, SuperAdminMiddleware::class, CSRFMiddleware::class],
], function (Router $r) {
    $r->get('/tenants', 'AdminController@index');
    $r->post('/tenants', 'AdminController@createTenant');
    $r->post('/tenants/status', 'AdminController@toggleStatus');

    $r->get('/leads', 'AdminController@leads');
    $r->post('/leads/status', 'AdminController@updateLeadStatus');

    $r->get('/health', 'AdminController@health');
    $r->get('/health.json', 'AdminController@healthJson');
});

$router->group([
    'prefix' => '/app',
    'middleware' => [
        TenantResolverMiddleware::class,
        AuthMiddleware::class,
        TenantContextMiddleware::class,
        TenantStatusMiddleware::class,
        AuthorizationMiddleware::class,
        CSRFMiddleware::class,
    ],
], function (Router $r) use ($apiPath) {
    $r->get('/pos', 'PosController@index');
    $r->post($apiPath('/pos/calculate'), 'PosController@calculate', [ApiGuardMiddleware::class]);
    $r->post($apiPath('/pos/checkout'), 'PosController@checkout', [ApiGuardMiddleware::class]);
    $r->post($apiPath('/pos/quick-customer'), 'PosController@quickCustomer', [ApiGuardMiddleware::class]);

    $r->get('/invoices', 'InvoiceController@index');
    $r->get('/invoices/export', 'InvoiceController@export');
    $r->get('/invoices/{id}', 'InvoiceController@show');
    $r->get('/invoices/{id}/thermal', 'InvoiceController@thermal');
    $r->get('/invoices/{id}/pdf', 'InvoiceController@pdf');
    $r->post('/invoices/{id}/void', 'InvoiceController@void');

    $r->get('/returns', 'ReturnController@index');
    $r->get('/returns/create', 'ReturnController@create');
    $r->post('/returns', 'ReturnController@store');
    $r->get('/returns/exchange', 'ReturnController@exchange');
    $r->post('/returns/exchange', 'ReturnController@storeExchange');

    $r->get('/products', 'ProductController@index');
    $r->post('/products', 'ProductController@store');
    $r->post('/products/bulk', 'ProductController@bulk');
    $r->get('/products/export', 'ProductController@export');
    $r->get('/products/import', 'ProductController@importForm');
    $r->post('/products/import', 'ProductController@importStore');
    $r->get('/products/{id}', 'ProductController@show');
    $r->post('/products/{id}/update', 'ProductController@update');
    $r->post('/products/{id}/quick-edit', 'ProductController@quickEdit');
    $r->post('/products/{id}/toggle', 'ProductController@toggle');

    $r->get('/customers', 'CustomerController@index');
    $r->post('/customers', 'CustomerController@store');
    $r->post('/customers/{id}/update', 'CustomerController@update');

    $r->get('/suppliers', 'SupplierController@index');
    $r->post('/suppliers', 'SupplierController@store');
    $r->post('/suppliers/{id}/update', 'SupplierController@update');

    $r->get('/inventory', 'InventoryController@index');
    $r->get('/inventory/export', 'InventoryController@export');
    $r->post('/inventory/adjust', 'InventoryController@adjust');

    $r->get('/purchases', 'PurchaseController@index');
    $r->get('/purchases/create', 'PurchaseController@create');
    $r->post('/purchases', 'PurchaseController@store');
    $r->post('/purchases/{id}/pay', 'PurchaseController@pay');

    $r->get('/accounting', 'AccountingController@index');
    $r->post('/accounting/expenses', 'AccountingController@addExpense');

    $r->get('/reports', 'ReportsController@index');
    $r->get('/reports/export', 'ReportsController@export');

    $r->get('/settings', 'SettingsController@index');
    $r->post('/settings', 'SettingsController@update');
    $r->post('/settings/users', 'SettingsController@addUser');

    $r->get('/templates', 'TemplateController@index');
    $r->get('/templates/editor', 'TemplateController@editor');
    $r->post('/templates/save', 'TemplateController@save');

    $r->get('/profile', 'ProfileController@index');
    $r->post('/profile', 'ProfileController@update');
});

$router->dispatch($request);

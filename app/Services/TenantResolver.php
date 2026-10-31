<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Session;

class TenantResolver {
    protected static bool $resolved = false;
    protected static ?array $tenant = null;
    protected static bool $platformHost = false;
    protected static bool $adminHost = false;
    protected static bool $unknownHost = false;

    public static function host(): string {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        $host = strtolower(trim($host));
        if (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }
        return rtrim($host, '.');
    }

    public static function baseDomain(): string {
        return strtolower(trim((string)Config::get('tenancy.base_domain', 'arthadesk.in')));
    }

    public static function adminHost(): string {
        return strtolower(trim((string)Config::get('tenancy.admin_host', 'app.arthadesk.in')));
    }

    public static function isLocalHost(?string $host = null): bool {
        $host = $host ?? self::host();
        return $host === ''
            || $host === 'localhost'
            || $host === '127.0.0.1'
            || $host === '::1'
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.test')
            || filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    public static function isAdminHost(?string $host = null): bool {
        $host = $host ?? self::host();
        if (self::isLocalHost($host)) {
            return true;
        }
        return $host === self::adminHost();
    }

    public static function isPlatformHost(?string $host = null): bool {
        $host = $host ?? self::host();
        if (self::isLocalHost($host)) {
            return true;
        }
        $base = self::baseDomain();
        return $host === $base || $host === 'www.' . $base || $host === self::adminHost();
    }

    public static function resolve(): ?array {
        if (self::$resolved) {
            return self::$tenant;
        }

        self::$resolved = true;
        $host = self::host();

        self::$adminHost = self::isAdminHost($host);

        if (self::isPlatformHost($host)) {
            self::$platformHost = true;
            Session::remove('resolved_tenant');
            return null;
        }

        $base = self::baseDomain();
        $slug = null;
        if ($base !== '' && str_ends_with($host, '.' . $base)) {
            $slug = substr($host, 0, -strlen('.' . $base));
        }

        if ($slug === null || $slug === '' || str_contains($slug, '.')) {
            self::$unknownHost = true;
            Session::remove('resolved_tenant');
            Logger::warning('tenant_host_unresolved', ['host' => $host], 'tenancy');
            return null;
        }

        $reserved = (array)Config::get('tenancy.reserved_slugs', []);
        if (in_array(strtolower($slug), $reserved, true)) {
            self::$unknownHost = true;
            Session::remove('resolved_tenant');
            return null;
        }

        try {
            $tenant = Database::fetchOne(
                "SELECT * FROM tenants WHERE slug = :slug LIMIT 1",
                ['slug' => strtolower($slug)]
            );
        } catch (\Throwable $e) {
            Logger::error('tenant_resolve_db_failed', ['host' => $host, 'error' => $e->getMessage()], 'tenancy');
            return null;
        }

        if (!$tenant) {
            self::$unknownHost = true;
            Session::remove('resolved_tenant');
            Logger::warning('tenant_not_found', ['host' => $host, 'slug' => $slug], 'tenancy');
            return null;
        }

        self::$tenant = $tenant;
        Session::set('resolved_tenant', $tenant);
        return $tenant;
    }

    public static function current(): ?array {
        $tenant = Session::get('resolved_tenant');
        return is_array($tenant) ? $tenant : null;
    }

    public static function isAdminRequestHost(): bool {
        return self::$adminHost || self::isAdminHost();
    }

    public static function isPlatformRequestHost(): bool {
        if (!self::$resolved) {
            self::resolve();
        }
        return self::$platformHost;
    }

    public static function isUnknownHost(): bool {
        if (!self::$resolved) {
            self::resolve();
        }
        return self::$unknownHost;
    }

    public static function enforceHosts(): bool {
        return (bool)Config::get('tenancy.enforce_hosts', true);
    }

    public static function tenantUrl(array $tenant): string {
        $slug = (string)($tenant['slug'] ?? '');
        $base = self::baseDomain();
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        if (self::isLocalHost()) {
            return '/app/pos';
        }
        return $scheme . '://' . $slug . '.' . $base;
    }

    public static function adminUrl(): string {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        if (self::isLocalHost()) {
            return '/admin/tenants';
        }
        return $scheme . '://' . self::adminHost() . '/admin/tenants';
    }

    public static function clear(): void {
        self::$resolved = false;
        self::$tenant = null;
        self::$platformHost = false;
        self::$adminHost = false;
        self::$unknownHost = false;
        Session::remove('resolved_tenant');
    }
}

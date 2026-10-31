<?php
namespace App\Services;

class Authorization {
    protected static array $rules = [
        'super_admin' => ['*'],
        'tenant_admin' => ['*'],
        'cashier' => [
            '/app/pos',
            '/app/invoices',
            '/app/returns',
            '/app/customers',
            '/app/profile',
        ],
        'inventory_manager' => [
            '/app/pos',
            '/app/invoices',
            '/app/returns',
            '/app/products',
            '/app/customers',
            '/app/suppliers',
            '/app/inventory',
            '/app/purchases',
            '/app/profile',
        ],
        'accountant' => [
            '/app/invoices',
            '/app/returns',
            '/app/accounting',
            '/app/reports',
            '/app/customers',
            '/app/suppliers',
            '/app/purchases',
            '/app/profile',
        ],
    ];

    public static function can(?string $role, string $uri): bool {
        if (!$role) {
            return false;
        }

        $allowed = self::$rules[$role] ?? [];
        if (in_array('*', $allowed, true)) {
            return true;
        }

        $path = '/' . trim(parse_url($uri, PHP_URL_PATH) ?: $uri, '/');
        foreach ($allowed as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }

    public static function canAny(?string $role, array $uris): bool {
        foreach ($uris as $uri) {
            if (self::can($role, $uri)) {
                return true;
            }
        }
        return false;
    }

    public static function rules(): array {
        return self::$rules;
    }
}

<?php
return [
    'name' => env('APP_NAME', 'Artha-Desk'),
    'env' => env('APP_ENV', 'development'),
    'debug' => env('APP_DEBUG', true),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'key' => env('APP_KEY', ''),
    'env_switch_key' => env('ENV_SWITCH_KEY', ''),
    'env_switch_enabled' => env('ENV_SWITCH_ENABLED', false),
    'locale' => env('DEFAULT_LOCALE', 'en'),
    'supported_locales' => explode(',', env('SUPPORTED_LOCALES', 'en,or')),
    'auto_migrate' => env('AUTO_MIGRATE', false),
    'seed_demo' => env('SEED_DEMO', false),
    'api_prefix' => trim((string)env('API_ROUTE_PREFIX', ''), '/'),
    'log_level' => env('LOG_LEVEL', 'info'),
    'log_retention_days' => (int)env('LOG_RETENTION_DAYS', 90),
    'session_lifetime' => (int)env('SESSION_LIFETIME', 7200),
];

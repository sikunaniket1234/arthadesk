<?php
return [
    'login_throttle' => [
        'enabled' => env('LOGIN_THROTTLE_ENABLED', true),
        'max_attempts' => (int)env('LOGIN_MAX_ATTEMPTS', 5),
        'window_minutes' => (int)env('LOGIN_WINDOW_MINUTES', 15),
        'ip_max_attempts' => (int)env('LOGIN_IP_MAX_ATTEMPTS', 20),
    ],
    'headers' => [
        'csp' => env('CSP_ENABLED', true),
        'hsts' => env('HSTS_ENABLED', true),
    ],
    'api' => [
        'require_origin' => env('API_REQUIRE_ORIGIN', true),
        'require_token' => env('API_REQUIRE_TOKEN', true),
        'rate_limit' => (int)env('API_RATE_LIMIT', 120),
        'rate_window' => (int)env('API_RATE_WINDOW', 60),
        'checkout_rate_limit' => (int)env('API_CHECKOUT_RATE_LIMIT', 30),
    ],
];

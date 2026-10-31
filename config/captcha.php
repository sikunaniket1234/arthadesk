<?php
return [
    'enabled' => env('CAPTCHA_ENABLED', true),
    'provider' => env('CAPTCHA_PROVIDER', 'math'),
    'turnstile_site_key' => env('TURNSTILE_SITE_KEY', ''),
    'turnstile_secret' => env('TURNSTILE_SECRET', ''),
    'turnstile_hostnames' => array_values(array_filter(array_map('trim', explode(',', (string)env('TURNSTILE_HOSTNAMES', ''))))),
    'math_ttl_seconds' => (int)env('CAPTCHA_MATH_TTL', 600),
    'min_form_seconds' => (int)env('CAPTCHA_MIN_FORM_SECONDS', 2),
    'allow_math_in_production' => env('CAPTCHA_ALLOW_MATH_IN_PRODUCTION', false),
];

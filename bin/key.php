<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

$appKey = 'base64:' . base64_encode(random_bytes(32));
$switchKey = bin2hex(random_bytes(32));

echo "APP_KEY={$appKey}\n";
echo "ENV_SWITCH_KEY={$switchKey}\n";
echo "\nStore ENV_SWITCH_KEY in a password manager. Do not commit these values.\n";

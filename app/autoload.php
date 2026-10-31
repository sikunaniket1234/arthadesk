<?php
// Custom PSR-4 Fallback Autoloader for Artha-Desk
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = dirname(__DIR__) . '/app/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require_once $file;
    }
});

// Load helper functions
$helperFile = dirname(__DIR__) . '/app/Support/helpers.php';
if (file_exists($helperFile)) {
    require_once $helperFile;
}

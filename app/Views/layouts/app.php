<?php
$user = auth_user();
$tenant = current_tenant();
$taxProfile = \App\Core\Session::get('tax_profile');
$flashes = get_flashes();
$regMode = $taxProfile['registration_mode'] ?? ($tenant ? 'regular' : '');
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale()) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e(__('app_name')) ?> — <?= e(__('tagline')) ?></title>
    <!-- Modern Fonts: Inter & Noto Sans Oriya for verified Odia font rendering -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Oriya:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="api-token" content="<?= e(api_token()) ?>">
</head>
<body>
    <header class="topbar">
        <div class="brand-section">
            <a href="/app/pos" class="brand-logo">
                <span>⚡ <?= e(__('app_name')) ?></span>
                <span class="brand-badge"><?= current_locale() === 'or' ? 'ଓଡ଼ିଶା' : 'v1.0' ?></span>
            </a>

            <?php if ($user && $tenant): ?>
                <div class="store-pill">
                    <strong><?= e($tenant['name']) ?></strong>
                    <?php if ($regMode === 'regular'): ?>
                        <span class="mode-tag mode-regular">GST Regular</span>
                    <?php elseif ($regMode === 'composition'): ?>
                        <span class="mode-tag mode-composition">GST Composition</span>
                    <?php else: ?>
                        <span class="mode-tag mode-unregistered">Unregistered</span>
                    <?php endif; ?>
                </div>
            <?php elseif ($user && ($user['role'] ?? '') === 'super_admin'): ?>
                <div class="store-pill">
                    <strong>Platform Mode</strong>
                    <span class="mode-tag" style="background:#334155;">Super Admin</span>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($user): ?>
            <nav class="nav-links">
                <?php if (($user['role'] ?? '') === 'super_admin'): ?>
                    <a href="/admin/tenants" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/admin') ? 'active' : '' ?>">Platform Admin</a>
                    <a href="/app/pos" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/pos') ? 'active' : '' ?>">POS Billing (Selected Store)</a>
                <?php else: ?>
                    <?php if (can_access('/app/pos')): ?>
                        <a href="/app/pos" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/pos') ? 'active' : '' ?>">POS</a>
                    <?php endif; ?>
                    <?php if (can_access('/app/invoices')): ?>
                        <a href="/app/invoices" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/invoices') ? 'active' : '' ?>">Invoices</a>
                    <?php endif; ?>
                    <?php if (can_access('/app/returns')): ?>
                        <a href="/app/returns" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/returns') ? 'active' : '' ?>">Returns</a>
                    <?php endif; ?>
                    <?php if (can_access('/app/products')): ?>
                        <a href="/app/products" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/products') ? 'active' : '' ?>">Products</a>
                    <?php endif; ?>
                    <?php if (can_access('/app/customers')): ?>
                        <a href="/app/customers" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/customers') ? 'active' : '' ?>">Customers</a>
                    <?php endif; ?>
                    <?php if (can_access('/app/suppliers')): ?>
                        <a href="/app/suppliers" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/suppliers') ? 'active' : '' ?>">Suppliers</a>
                    <?php endif; ?>
                    <?php if (can_access('/app/inventory')): ?>
                        <a href="/app/inventory" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/inventory') ? 'active' : '' ?>">Inventory</a>
                    <?php endif; ?>
                    <?php if (can_access('/app/purchases')): ?>
                        <a href="/app/purchases" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/purchases') ? 'active' : '' ?>">Purchases</a>
                    <?php endif; ?>
                    <?php if (can_access('/app/accounting')): ?>
                        <a href="/app/accounting" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/accounting') ? 'active' : '' ?>">Accounting</a>
                    <?php endif; ?>
                    <?php if (can_access('/app/reports')): ?>
                        <a href="/app/reports" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/reports') ? 'active' : '' ?>">Reports</a>
                    <?php endif; ?>
                    <?php if (can_access('/app/settings')): ?>
                        <a href="/app/settings" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/settings') ? 'active' : '' ?>">Settings</a>
                    <?php endif; ?>
                <?php endif; ?>
            </nav>

            <div class="top-actions">
                <!-- Language Switcher -->
                <a href="/auth/locale?locale=<?= current_locale() === 'or' ? 'en' : 'or' ?>" class="btn-lang" title="Toggle Language">
                    🌐 <?= current_locale() === 'or' ? 'English' : 'ଓଡ଼ିଆ' ?>
                </a>

                <!-- User Profile / Logout -->
                <a href="/app/profile" class="nav-link <?= str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/app/profile') ? 'active' : '' ?>" style="font-size:0.8rem;" title="My Profile">👤 <?= e($user['name']) ?></a>
                <a href="/auth/logout" class="nav-link" style="color:#ef4444;" title="Sign out">⏻</a>
            </div>
        <?php endif; ?>
    </header>

    <?php if (!empty($flashes)): ?>
        <div class="flash-container">
            <?php foreach ($flashes as $type => $messages): ?>
                <?php foreach ($messages as $msg): ?>
                    <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>">
                        <span><?= e($msg) ?></span>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <main>
        <?= $content ?>
    </main>
</body>
</html>

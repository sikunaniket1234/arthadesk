<?php
$tenant = $tenant ?? null;
$tenantUnavailable = $tenantUnavailable ?? false;
$captchaEnabled = $captchaEnabled ?? true;
$captchaProvider = $captchaProvider ?? 'math';
$captchaQuestion = $captchaQuestion ?? '';
$captchaSiteKey = $captchaSiteKey ?? '';
$formTs = $formTs ?? time();
$flashes = get_flashes();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Artha-Desk (ଅର୍ଥ-Desk)</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Noto+Sans+Oriya:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/style.css">
    <?php if ($captchaEnabled && $captchaProvider === 'turnstile'): ?>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <?php endif; ?>
    <style>
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .login-wrapper {
            width: 100%;
            max-width: 900px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            overflow: hidden;
        }
        @media (max-width: 768px) {
            .login-wrapper { grid-template-columns: 1fr; }
            .login-brand-side { display: none; }
        }
        .login-brand-side {
            background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%);
            color: #fff;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .login-form-side { padding: 3rem; background: #ffffff; }
        .form-group { margin-bottom: 1.1rem; }
        .form-label {
            display: block;
            font-size: 0.825rem;
            font-weight: 600;
            margin-bottom: 0.35rem;
            color: #475569;
        }
        .form-input {
            width: 100%;
            padding: 0.7rem 0.9rem;
            border: 1.5px solid #cbd5e1;
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            box-sizing: border-box;
        }
        .form-input:focus { border-color: #2563eb; }
        .store-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.35rem 0.7rem;
            border-radius: 999px;
            background: #eff6ff;
            color: #1d4ed8;
            font-size: 0.75rem;
            font-weight: 700;
            margin-bottom: 1rem;
        }
        .captcha-box {
            padding: 0.8rem;
            border: 1.5px dashed #cbd5e1;
            border-radius: 8px;
            background: #f8fafc;
            margin-bottom: 1.1rem;
        }
        .hp-field {
            position: absolute;
            left: -9999px;
            top: -9999px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-brand-side">
            <div>
                <h1 style="font-size: 2rem; font-weight: 800; letter-spacing: -0.5px; margin-bottom: 0.5rem;">
                    ⚡ Artha-Desk
                </h1>
                <h2 style="font-size: 1.25rem; font-family: 'Noto Sans Oriya', sans-serif; opacity: 0.95; font-weight: 600; margin-bottom: 1.5rem;">
                    ଅର୍ଥ-ଡେସ୍କ ଖୁଚୁରା ବିଲିଂ &amp; ଖାତା
                </h2>
                <p style="font-size: 0.9rem; line-height: 1.6; opacity: 0.85;">
                    The touch-friendly, bilingual billing and inventory SaaS for retail shops.
                </p>
                <div style="margin-top: 2rem; display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.85rem; opacity: 0.9;">
                    <div>✓ Odia &amp; English Dual-Language POS</div>
                    <div>✓ First-Class Indian GST (Regular, Composition, Unregistered)</div>
                    <div>✓ Concurrency-Safe Sequential Invoicing</div>
                    <div>✓ Balanced Double-Entry Financial Daybook</div>
                </div>
            </div>
            <div style="font-size: 0.75rem; opacity: 0.7;">
                Artha-Desk &copy; <?= date('Y') ?>
            </div>
        </div>

        <div class="login-form-side">
            <?php if ($tenant): ?>
                <div class="store-badge">🏪 <?= e($tenant['name']) ?></div>
            <?php endif; ?>

            <h2 style="font-size: 1.35rem; font-weight: 700; margin-bottom: 0.35rem; color: #0f172a;">
                <?= $tenant ? 'Sign in to your store' : 'Sign in' ?>
            </h2>
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 1.25rem;">
                <?= $tenant ? 'Use your store account credentials.' : 'Use your platform administrator credentials.' ?>
            </p>

            <?php foreach ($flashes as $type => $messages): ?>
                <?php foreach ($messages as $message): ?>
                    <div class="alert alert-<?= $type === 'error' ? 'error' : 'success' ?>" style="margin-bottom: 1rem;">
                        <?= e($message) ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <?php if ($tenantUnavailable): ?>
                <div class="alert alert-error" style="margin-bottom: 1rem;">
                    This store is currently unavailable. Please contact the platform administrator.
                </div>
            <?php else: ?>
                <form action="/auth/login" method="POST" autocomplete="on">
                    <?= csrf_field() ?>
                    <input type="hidden" name="form_ts" value="<?= (int)$formTs ?>">
                    <div class="hp-field" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" value="" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-input" placeholder="you@example.com" required autocomplete="username">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-input" required autocomplete="current-password">
                    </div>

                    <?php if ($captchaEnabled): ?>
                        <?php if ($captchaProvider === 'turnstile' && $captchaSiteKey !== ''): ?>
                            <div class="captcha-box">
                                <div class="cf-turnstile" data-sitekey="<?= e($captchaSiteKey) ?>" data-theme="light"></div>
                            </div>
                        <?php else: ?>
                            <div class="captcha-box">
                                <label class="form-label" for="captcha_answer">Human check: <?= e($captchaQuestion) ?></label>
                                <input type="text" id="captcha_answer" name="captcha_answer" class="form-input" inputmode="numeric" pattern="[0-9]*" placeholder="Type the answer" required autocomplete="off">
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; padding: 0.75rem;">
                        Sign In
                    </button>
                </form>
            <?php endif; ?>

            <p style="font-size: 0.72rem; color: #94a3b8; margin-top: 1.25rem; line-height: 1.5;">
                Protected by rate limiting<?= $captchaEnabled ? ' and CAPTCHA' : '' ?>. Never share your password.
            </p>
        </div>
    </div>
</body>
</html>

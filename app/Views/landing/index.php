<?php
/** @var array $seo */
/** @var array $contact */
/** @var string $base_domain */
/** @var string $admin_login_url */
/** @var array $flashes */
/** @var int $current_year */

$seo = $seo ?? [];
$contact = $contact ?? ['phone' => '', 'whatsapp' => ''];
$baseDomain = $base_domain ?? 'arthadesk.in';
$adminLogin = $admin_login_url ?? '/auth/login';
$flashes = $flashes ?? [];
$year = $current_year ?? (int)date('Y');

$phoneDigits = preg_replace('/[^0-9]/', '', (string)($contact['whatsapp'] ?: $contact['phone']));
$whatsappUrl = $phoneDigits !== '' ? 'https://wa.me/' . $phoneDigits : '';
$phoneHref = $contact['phone'] !== '' ? preg_replace('/[^0-9+]/', '', (string)$contact['phone']) : '';

$favicon = 'data:image/svg+xml,'
    . rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64"><rect width="64" height="64" rx="14" fill="#4f46e5"/><text x="32" y="43" font-family="Arial,Helvetica,sans-serif" font-size="34" font-weight="700" fill="#fff" text-anchor="middle">A</text></svg>');

$faq = [
    [
        'q' => 'Is Artha-Desk ready for GST billing?',
        'a' => 'Yes. Choose regular, composition or unregistered. Intra-state sales get CGST and SGST, inter-state sales get IGST decided automatically from the place of supply, GSTINs are format-checked, and the Reports screen gives you a tax summary broken down by GST rate for filing.',
    ],
    [
        'q' => 'Do I need a special printer?',
        'a' => 'For counter receipts, any standard 58 mm or 80 mm thermal receipt printer works. You can also print A4 invoices or download a PDF from the invoice screen for customers who need a formal bill.',
    ],
    [
        'q' => 'Can I bill customers in Odia?',
        'a' => 'Yes. The whole interface is bilingual — English and Odia — and every product can carry both an English and an Odia name, so your counter, your receipts and your reports all read the way your shop talks.',
    ],
    [
        'q' => 'Is my shop data safe and private?',
        'a' => 'Every shop is isolated from every other shop by store address, passwords are hashed, sensitive actions are written to an audit trail, and nightly database backups are taken. Voided bills keep their original record while stock and accounts are corrected.',
    ],
    [
        'q' => 'How do my staff log in?',
        'a' => 'You give each person an account on your own store address with the right role — cashier, inventory manager or accountant — so a cashier can bill without being able to touch your books or settings.',
    ],
    [
        'q' => 'What does it cost?',
        'a' => 'We onboard shops personally so we can size the right plan. Send your details through the form and we will share current pricing along with a live demo of your own store.',
    ],
];

$jsonLd = [
    [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => 'Artha-Desk',
        'url' => $seo['url'] ?? '/',
        'description' => 'GST billing, POS, inventory and accounting software for Indian retail shops.',
        'areaServed' => ['@type' => 'Country', 'name' => 'India'],
        'knowsLanguage' => ['en', 'or'],
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => 'Artha-Desk',
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'browserRequires' => 'Any modern browser',
        'description' => $seo['description'] ?? '',
        'url' => $seo['url'] ?? '/',
        'inLanguage' => ['en', 'or'],
        'featureList' => 'GST invoicing, point of sale, khata, inventory, purchases, accounting, reports',
    ],
    [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(static fn(array $item): array => [
            '@type' => 'Question',
            'name' => $item['q'],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item['a']],
        ], $faq),
    ],
];
$jsonLdString = json_encode($jsonLd, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
$jsonLdString = str_replace('</', '<\/', (string)$jsonLdString);

$iconCheck = '<svg class="check" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4l3.8 3.8 6.8-6.8a1 1 0 0 1 1.4 0z" clip-rule="evenodd"/></svg>';
$iconArrow = '<svg width="16" height="16" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="M10.3 4.3a1 1 0 0 1 1.4 0l6 6a1 1 0 0 1 0 1.4l-6 6a1 1 0 1 1-1.4-1.4L14.6 11H3a1 1 0 1 1 0-2h11.6L10.3 5.7a1 1 0 0 1 0-1.4z"/></svg>';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= e($seo['title'] ?? 'Artha-Desk — GST Billing & POS Software for Indian Shops') ?></title>
    <meta name="description" content="<?= e($seo['description'] ?? '') ?>">
    <meta name="robots" content="index, follow, max-image-preview:large">
    <link rel="canonical" href="<?= e($seo['canonical'] ?? '/') ?>">
    <meta name="theme-color" content="#4f46e5">
    <meta name="color-scheme" content="light">
    <meta name="keywords" content="GST billing software, POS for shops, retail billing Odisha, khata app, thermal billing software, Odia billing software, inventory software India">
    <link rel="icon" href="<?= $favicon ?>">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($seo['site_name'] ?? 'Artha-Desk') ?>">
    <meta property="og:title" content="<?= e($seo['title'] ?? '') ?>">
    <meta property="og:description" content="<?= e($seo['description'] ?? '') ?>">
    <meta property="og:url" content="<?= e($seo['url'] ?? '/') ?>">
    <meta property="og:locale" content="en_IN">
    <meta property="og:locale:alternate" content="or_IN">

    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= e($seo['title'] ?? '') ?>">
    <meta name="twitter:description" content="<?= e($seo['description'] ?? '') ?>">
    <meta name="twitter:url" content="<?= e($seo['url'] ?? '/') ?>">

    <script type="application/ld+json"><?= $jsonLdString ?></script>

    <link rel="stylesheet" href="/assets/css/landing.css">
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>

<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="/" aria-label="Artha-Desk home">
            <span class="brand-mark" aria-hidden="true">A</span>
            <span>Artha-Desk</span>
        </a>

        <nav class="nav" aria-label="Main navigation">
            <a href="#features">Features</a>
            <a href="#how">How it works</a>
            <a href="#faq">FAQ</a>
            <a href="#contact">Contact</a>
        </nav>

        <div class="header-actions">
            <button type="button" class="btn btn-ghost" id="loginOpen">Sign in</button>
            <a class="btn btn-primary" href="#contact">Get started</a>
        </div>
    </div>
</header>

<main id="main">
    <?php if (!empty($flashes)): ?>
        <?php foreach ($flashes as $type => $messages): ?>
            <?php foreach ((array)$messages as $message): ?>
                <div class="toast toast-<?= e($type === 'success' ? 'success' : 'error') ?>" role="status">
                    <?= e($message) ?>
                </div>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Hero -->
    <section class="hero">
        <div class="container hero-grid">
            <div>
                <span class="eyebrow">🇮🇳 Built for Indian retail · English &amp; <span lang="or">ଓଡ଼ିଆ</span></span>
                <h1>Billing, GST and khata for your shop — <span class="grad">in English &amp; Odia</span></h1>
                <p class="hero-sub">
                    Artha-Desk is a complete point of sale for Indian retailers: bill in seconds, accept
                    cash, UPI, card or credit, print a GST thermal receipt, and let stock, receivables and
                    your books update themselves.
                </p>

                <div class="hero-cta">
                    <a class="btn btn-primary btn-lg" href="#contact">Start your shop <?= $iconArrow ?></a>
                    <a class="btn btn-ghost btn-lg" href="#features">See what it does</a>
                </div>

                <ul class="hero-points">
                    <li><?= $iconCheck ?> GST ready — CGST/SGST/IGST</li>
                    <li><?= $iconCheck ?> 58/80 mm thermal print</li>
                    <li><?= $iconCheck ?> Khata with credit limits</li>
                    <li><?= $iconCheck ?> No install — runs in a browser</li>
                </ul>
            </div>

            <div class="mock" aria-hidden="true">
                <div class="mock-bar">
                    <span class="dot r"></span><span class="dot y"></span><span class="dot g"></span>
                    <span class="mock-title">Bill preview</span>
                </div>
                <div class="mock-body">
                    <div class="mock-lines">
                        <div class="mock-line">
                            <span>Tata Salt 1kg <span class="qty">2 × ₹30.00</span></span><span>₹60.00</span>
                        </div>
                        <div class="mock-line">
                            <span>Fortune Oil 1L <span class="qty">1 × ₹142.00</span></span><span>₹142.00</span>
                        </div>
                        <div class="mock-line">
                            <span>Parle-G 800g <span class="qty">3 × ₹55.00</span></span><span>₹165.00</span>
                        </div>
                        <div class="mock-line">
                            <span><span lang="or">ସାବନ</span> <span class="qty">5 × ₹18.00</span></span><span>₹90.00</span>
                        </div>
                    </div>
                    <div class="mock-total">
                        <div class="row"><span>Taxable</span><span>₹412.88</span></div>
                        <div class="row"><span>CGST @9%</span><span>₹37.16</span></div>
                        <div class="row"><span>SGST @9%</span><span>₹37.16</span></div>
                        <div class="grand"><span>Total</span><span>₹487.20</span></div>
                        <span class="pay-chip">UPI + Cash · split payment</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features -->
    <section class="section section-alt" id="features">
        <div class="container">
            <div class="section-head">
                <span class="kicker">Everything the counter needs</span>
                <h2>One shop, one system — from the till to your books</h2>
                <p>Stop juggling a bill book, a stock register and a separate accounts file. Artha-Desk keeps all of it in step automatically.</p>
            </div>

            <div class="feature-grid">
                <article class="feature">
                    <div class="feature-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M7 8h10M7 12h6M7 16h4"/></svg>
                    </div>
                    <h3>Lightning POS billing</h3>
                    <p>Tap or scan to add items, see the tax update live, and close the sale. Built for a busy counter on a phone or a desktop.</p>
                </article>

                <article class="feature">
                    <div class="feature-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/></svg>
                    </div>
                    <h3>GST invoices that pass muster</h3>
                    <p>CGST + SGST in your state, IGST across states, correct invoice numbering and GSTIN validation — decided automatically.</p>
                </article>

                <article class="feature">
                    <div class="feature-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="8" r="4"/><path d="M4 21c0-4 3.6-6 8-6s8 2 8 6"/></svg>
                    </div>
                    <h3>Khata that protects you</h3>
                    <p>Customer credit with limits, running balances and instant reminders of who owes what — the system blocks a sale that goes over limit.</p>
                </article>

                <article class="feature">
                    <div class="feature-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20M6 15h4"/></svg>
                    </div>
                    <h3>Split payments, sorted</h3>
                    <p>Take part cash and part UPI, or card plus credit, on a single bill. Every tender is recorded and reconciles exactly to the total.</p>
                </article>

                <article class="feature">
                    <div class="feature-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 7l9-4 9 4-9 4-9-4z"/><path d="M3 12l9 4 9-4M3 17l9 4 9-4"/></svg>
                    </div>
                    <h3>Stock that watches itself</h3>
                    <p>Every sale, return and purchase writes a movement. Low-stock alerts tell you what to reorder before the shelf runs dry.</p>
                </article>

                <article class="feature">
                    <div class="feature-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 19V5M4 19h16"/><path d="M8 15l4-6 3 4 4-7"/></svg>
                    </div>
                    <h3>Reports &amp; GST summary</h3>
                    <p>Sales, profit, stock, expenses and a tax summary by GST rate — filter by date and export straight to CSV for your CA.</p>
                </article>

                <article class="feature">
                    <div class="feature-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M9 3h6l1 4H8z"/><rect x="4" y="7" width="16" height="14" rx="2"/><path d="M9 13h6M9 17h4"/></svg>
                    </div>
                    <h3>Purchases &amp; dealer dues</h3>
                    <p>Record a dealer bill, stock arrives and cost prices update on their own. Track partial payments and what you still owe.</p>
                </article>

                <article class="feature">
                    <div class="feature-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/></svg>
                    </div>
                    <h3>Books that balance</h3>
                    <p>Every sale, return, purchase and expense posts a proper double-entry journal. Daybook, cashbook and trial balance come ready.</p>
                </article>

                <article class="feature">
                    <div class="feature-icon" aria-hidden="true">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M3 5h18v12H7l-4 4z"/><path d="M8 9h8M8 13h5"/></svg>
                    </div>
                    <h3>Returns &amp; exchanges</h3>
                    <p>Credit notes and exchanges against an old bill, with the stock and the ledger both put right in the same step.</p>
                </article>
            </div>
        </div>
    </section>

    <!-- How it works -->
    <section class="section" id="how">
        <div class="container">
            <div class="section-head">
                <span class="kicker">Getting started</span>
                <h2>From your first message to your first bill — in three steps</h2>
                <p>We set up the store, you approve the GST details, and your counter is billing the same week.</p>
            </div>

            <div class="steps">
                <div class="step">
                    <h3>Tell us about your shop</h3>
                    <p>Share your shop name, city and how you bill today. That is all we need to start — no forms in triplicate.</p>
                </div>
                <div class="step">
                    <h3>We set up your store</h3>
                    <p>You get your own store address, GST profile, invoice numbering, accounts and your team's logins, configured for your trade.</p>
                </div>
                <div class="step">
                    <h3>Start billing</h3>
                    <p>We load your products, connect your printer and walk your staff through a live sale. Most shops are independent within a day.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Showcase -->
    <section class="section section-alt">
        <div class="container split">
            <div>
                <span class="kicker">Accounting on autopilot</span>
                <h2>Your books keep themselves while you serve customers</h2>
                <p>No separate tally work at night. The moment a bill is printed, the ledger, stock and tax position are already updated.</p>
                <ul>
                    <li><?= $iconCheck ?> Balanced double-entry journals for every transaction</li>
                    <li><?= $iconCheck ?> Trial balance, daybook and cashbook on demand</li>
                    <li><?= $iconCheck ?> Expense vouchers mapped to the right head</li>
                    <li><?= $iconCheck ?> Returns and voids reverse cleanly — history is never rewritten</li>
                    <li><?= $iconCheck ?> Full audit trail: who did what, when, and from where</li>
                </ul>
            </div>

            <div class="mock" aria-hidden="true">
                <div class="mock-bar">
                    <span class="dot r"></span><span class="dot y"></span><span class="dot g"></span>
                    <span class="mock-title">Day summary</span>
                </div>
                <div class="mock-lines" style="border-right:0;">
                    <div class="mock-line"><span>Invoices today</span><span>64</span></div>
                    <div class="mock-line"><span>Sales value</span><span>₹38,420.00</span></div>
                    <div class="mock-line"><span>CGST collected</span><span>₹1,712.40</span></div>
                    <div class="mock-line"><span>SGST collected</span><span>₹1,712.40</span></div>
                    <div class="mock-line"><span>Cash in hand</span><span>₹12,980.00</span></div>
                    <div class="mock-line"><span>Khata received</span><span>₹4,650.00</span></div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ -->
    <section class="section" id="faq">
        <div class="container">
            <div class="section-head">
                <span class="kicker">Questions shopkeepers ask</span>
                <h2>Straight answers before you decide</h2>
            </div>

            <div class="faq">
                <?php foreach ($faq as $index => $item): ?>
                    <details<?= $index === 0 ? ' open' : '' ?>>
                        <summary><?= e($item['q']) ?></summary>
                        <p><?= e($item['a']) ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Contact -->
    <section class="section section-alt" id="contact">
        <div class="container contact-grid">
            <div class="contact-aside">
                <span class="badge">Talk to us — we onboard you personally</span>
                <h2>Ready to run your shop on Artha-Desk?</h2>
                <p>
                    Send your details and we will get back with current pricing, a live demo and a plan to
                    move your products across — usually within one working day.
                </p>

                <?php if ($whatsappUrl !== '' || $phoneHref !== ''): ?>
                    <ul class="contact-methods">
                        <?php if ($whatsappUrl !== ''): ?>
                            <li>
                                <span class="ic" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5.1-1.3A10 10 0 1 0 12 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .2-3.4-.7-2.9-1.2-4.7-4.1-4.9-4.3-.1-.2-1.1-1.5-1.1-2.8s.7-2 .9-2.2c.2-.3.5-.3.6-.3h.5c.2 0 .4 0 .6.5s.8 1.9.8 2c.1.1.1.3 0 .5-.1.2-.2.3-.3.5l-.4.4c-.1.1-.3.3-.1.6.2.3.7 1.2 1.6 2 1.1.9 2 1.2 2.3 1.4.3.1.4.1.6-.1l.9-1c.2-.2.4-.2.6-.1l1.9.9c.2.1.4.2.5.3 0 .1 0 .6-.2 1.2z"/></svg>
                                </span>
                                <a href="<?= e($whatsappUrl) ?>" rel="noopener">WhatsApp us</a>
                            </li>
                        <?php endif; ?>
                        <?php if ($phoneHref !== ''): ?>
                            <li>
                                <span class="ic" aria-hidden="true">
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M6.6 10.8a15.5 15.5 0 0 0 6.6 6.6l2.2-2.2c.3-.3.7-.4 1-.2 1.2.4 2.4.6 3.6.6.6 0 1 .4 1 1V20c0 .6-.4 1-1 1A17 17 0 0 1 3 4c0-.6.4-1 1-1h3.5c.6 0 1 .4 1 1 0 1.3.2 2.5.6 3.6.1.4 0 .8-.2 1l-2.3 2.2z"/></svg>
                                </span>
                                <a href="tel:<?= e($phoneHref) ?>"><?= e($contact['phone']) ?></a>
                            </li>
                        <?php endif; ?>
                    </ul>
                <?php endif; ?>
            </div>

            <div class="contact-card">
                <form action="/leads" method="post">
                    <?= csrf_field() ?>
                    <div class="hp-field" aria-hidden="true">
                        <label for="website">Website</label>
                        <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="name">Your name <span class="req">*</span></label>
                            <input type="text" id="name" name="name" required maxlength="100"
                                   placeholder="e.g. Bikram Sahoo" autocomplete="name">
                        </div>
                        <div class="field">
                            <label for="shop_name">Shop name</label>
                            <input type="text" id="shop_name" name="shop_name" maxlength="150"
                                   placeholder="e.g. Maa Tarini General Store" autocomplete="organization">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="field">
                            <label for="phone">Phone / WhatsApp <span class="req">*</span></label>
                            <input type="tel" id="phone" name="phone" maxlength="20"
                                   placeholder="98765 43210" autocomplete="tel" inputmode="tel">
                        </div>
                        <div class="field">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" maxlength="150"
                                   placeholder="you@example.com" autocomplete="email">
                        </div>
                    </div>

                    <div class="field">
                        <label for="city">City / Town</label>
                        <input type="text" id="city" name="city" maxlength="100" placeholder="e.g. Cuttack">
                    </div>

                    <div class="field">
                        <label for="message">Tell us about your shop</label>
                        <textarea id="message" name="message" maxlength="2000"
                                  placeholder="What do you sell, how many products, and how do you bill today?"></textarea>
                    </div>

                    <button type="submit" class="btn btn-accent btn-lg btn-block">Send enquiry <?= $iconArrow ?></button>

                    <p class="form-note">
                        Share a phone number or an email — either is enough for us to reach you.
                        We use your details only to reply about Artha-Desk.
                    </p>
                </form>
            </div>
        </div>
    </section>

    <!-- CTA band -->
    <section class="section">
        <div class="container">
            <div class="cta-band">
                <h2>Give your counter a system that works as hard as you do</h2>
                <p>Bilingual billing, GST, khata and books — in one place, on any device you already own.</p>
                <a class="btn btn-ghost btn-lg" href="#contact">Get started today <?= $iconArrow ?></a>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a class="brand" href="/">
                    <span class="brand-mark" aria-hidden="true">A</span>
                    <span>Artha-Desk</span>
                </a>
                <p>GST billing, point of sale, khata, inventory and accounting for Indian retail shops — in English and Odia.</p>
            </div>

            <div class="footer-col">
                <h4>Product</h4>
                <ul>
                    <li><a href="#features">Features</a></li>
                    <li><a href="#how">How it works</a></li>
                    <li><a href="#faq">FAQ</a></li>
                    <li><a href="#contact">Contact</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Sign in</h4>
                <ul>
                    <li><a href="#" id="footerLoginLink">Your shop address</a></li>
                    <li><a href="<?= e($adminLogin) ?>">Platform admin</a></li>
                    <li><a href="#contact">New shop onboarding</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <span>&copy; <?= $year ?> Artha-Desk. All rights reserved.</span>
            <span>Your shop lives at <strong>yourshop.<?= e($baseDomain) ?></strong></span>
        </div>
    </div>
</footer>

<!-- Store-address sign-in dialog -->
<div class="modal" id="loginModal" role="dialog" aria-modal="true" aria-labelledby="loginTitle">
    <div class="modal-card">
        <button type="button" class="modal-close" id="loginClose" aria-label="Close">&times;</button>
        <h3 id="loginTitle">Sign in to your shop</h3>
        <p>Each shop has its own address. Enter your store name to continue.</p>

        <form id="loginForm" method="get" action="/auth/login">
            <div class="field">
                <label for="shopSlug">Store address</label>
                <input type="text" id="shopSlug" name="slug" placeholder="yourshop"
                       autocomplete="off" autocapitalize="off" spellcheck="false">
                <div class="hint" id="shopUrlHint"><?= e($baseDomain) ?></div>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Continue</button>
        </form>

        <p class="form-note" style="margin-top:16px;">
            Platform administrator? <a href="<?= e($adminLogin) ?>">Sign in to the admin console</a>.
        </p>
    </div>
</div>

<script>
(function () {
    var base = <?= json_encode((string)$baseDomain, JSON_UNESCAPED_SLASHES) ?>;
    var adminLogin = <?= json_encode((string)$adminLogin, JSON_UNESCAPED_SLASHES) ?>;
    var modal = document.getElementById('loginModal');
    var slugInput = document.getElementById('shopSlug');
    var hint = document.getElementById('shopUrlHint');

    function openModal(e) {
        if (e) e.preventDefault();
        modal.classList.add('open');
        slugInput.focus();
    }
    function closeModal() { modal.classList.remove('open'); }

    document.getElementById('loginOpen').addEventListener('click', openModal);
    var footerLink = document.getElementById('footerLoginLink');
    if (footerLink) footerLink.addEventListener('click', openModal);
    document.getElementById('loginClose').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeModal(); });

    function hostHasDot() { return base.indexOf('.') !== -1; }

    slugInput.addEventListener('input', function () {
        var slug = this.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, '-');
        if (slug === '' || !hostHasDot()) {
            hint.textContent = base;
        } else {
            hint.textContent = slug + '.' + base;
        }
    });

    document.getElementById('loginForm').addEventListener('submit', function (e) {
        e.preventDefault();
        var slug = slugInput.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, '-');
        if (slug === '') { slugInput.focus(); return; }
        var target = hostHasDot() ? ('https://' + slug + '.' + base + '/auth/login') : '/auth/login';
        window.location.href = target;
    });

    // Keep in-page anchors from leaving a stale hash behind the modal.
    document.querySelectorAll('a[href^="#"]').forEach(function (a) {
        a.addEventListener('click', function () { closeModal(); });
    });
})();
</script>
</body>
</html>

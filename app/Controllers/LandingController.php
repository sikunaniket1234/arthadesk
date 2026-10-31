<?php
namespace App\Controllers;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Services\AuditService;
use App\Services\Mailer;
use App\Services\RateLimiter;
use App\Services\TenantResolver;

class LandingController {
    public function index(Request $request): void {
        // Resolved tenant = we are on a shop subdomain. Keep the original behaviour
        // there (login / POS) and reserve the marketing page for the apex domain.
        if ($request->tenant() !== null) {
            Response::redirect(auth_user() ? '/app/pos' : '/auth/login');
            return;
        }

        $appUrl = rtrim((string)Config::get('app.url', ''), '/');
        $baseDomain = TenantResolver::baseDomain();

        $seo = [
            'title' => 'Artha-Desk — GST Billing & POS Software for Indian Shops | English & Odia',
            'description' => 'Free-to-try GST billing, POS, khata, inventory and accounting software built for Indian retail shops. Bilingual English & Odia, thermal printing, UPI/cash/card split payments. Onboard your shop in minutes.',
            'canonical' => $appUrl . '/',
            'url' => $appUrl . '/',
            'site_name' => 'Artha-Desk',
            'locale' => 'en_IN',
            'base_domain' => $baseDomain,
        ];

        $isAdminLocal = TenantResolver::isLocalHost();

        View::render('landing/index', [
            'noLayout' => true,
            'seo' => $seo,
            'contact' => [
                'phone' => (string)Config::get('contact.phone', ''),
                'whatsapp' => (string)Config::get('contact.whatsapp', ''),
            ],
            'base_domain' => $baseDomain,
            'admin_login_url' => $isAdminLocal
                ? '/auth/login'
                : 'https://' . TenantResolver::adminHost() . '/auth/login',
            'flashes' => get_flashes(),
            'current_year' => (int)date('Y'),
        ]);
    }

    public function submit(Request $request): void {
        $name = trim((string)$request->input('name', ''));
        $shopName = trim((string)$request->input('shop_name', ''));
        $phone = trim((string)$request->input('phone', ''));
        $email = trim((string)$request->input('email', ''));
        $city = trim((string)$request->input('city', ''));
        $message = trim((string)$request->input('message', ''));
        $website = trim((string)$request->input('website', '')); // honeypot

        // Bots fill the hidden field: pretend success, store and send nothing.
        if ($website !== '') {
            Logger::info('lead_honeypot_triggered', ['ip_hash' => substr(hash('sha256', $this->clientIp()), 0, 12)], 'leads');
            flash('success', 'Thanks! We have received your message.');
            Response::redirect('/#contact');
            return;
        }

        if ($name === '' || mb_strlen($name) > 100) {
            flash('error', 'Please enter your name.');
            Response::redirect('/#contact');
            return;
        }

        if ($email === '' && $phone === '') {
            flash('error', 'Please share a phone number or an email address so we can reach you.');
            Response::redirect('/#contact');
            return;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'That email address does not look valid.');
            Response::redirect('/#contact');
            return;
        }

        if ($phone !== '' && !preg_match('/^[0-9+\-\s()]{6,20}$/', $phone)) {
            flash('error', 'That phone number does not look valid.');
            Response::redirect('/#contact');
            return;
        }

        if (mb_strlen($message) > 2000) {
            $message = mb_substr($message, 0, 2000);
        }

        $ip = $this->clientIp();
        $limit = max(1, (int)Config::get('contact.rate_limit', 5));
        $window = max(60, (int)Config::get('contact.rate_window', 3600));
        $gate = RateLimiter::hit('lead:' . $ip, $limit, $window);

        if (!$gate['allowed']) {
            Logger::warning('lead_rate_limited', ['ip' => $ip, 'retry_after' => $gate['retry_after']], 'leads');
            flash('error', 'You have sent several messages in a short time. Please try again in a few minutes.');
            Response::redirect('/#contact');
            return;
        }

        try {
            $stmt = Database::connect()->prepare("
                INSERT INTO leads (name, shop_name, phone, email, city, message, source, status, ip_address, user_agent, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'landing', 'new', ?, ?, NOW())
            ");
            $stmt->execute([
                mb_substr($name, 0, 100),
                $shopName !== '' ? mb_substr($shopName, 0, 150) : null,
                $phone !== '' ? $phone : null,
                $email !== '' ? $email : null,
                $city !== '' ? mb_substr($city, 0, 100) : null,
                $message !== '' ? $message : null,
                $ip,
                mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);
            $leadId = (int)Database::connect()->lastInsertId();
        } catch (\PDOException $e) {
            Logger::error('lead_insert_failed', ['error' => $e->getMessage()], 'leads');
            flash('error', 'We could not save your message right now. Please try again shortly.');
            Response::redirect('/#contact');
            return;
        }

        AuditService::log('lead.captured', 'lead', $leadId, [
            'name' => $name,
            'shop_name' => $shopName,
            'city' => $city,
            'has_email' => $email !== '',
            'has_phone' => $phone !== '',
        ]);

        // Best-effort notification. A mail failure never invalidates the stored lead.
        $to = (string)Config::get('contact.email', '');
        if ($to !== '') {
            Mailer::send(
                $to,
                'New Artha-Desk enquiry' . ($shopName !== '' ? ' — ' . $shopName : ''),
                implode("\n", [
                    'New enquiry from the Artha-Desk landing page.',
                    '',
                    'Name      : ' . $name,
                    'Shop      : ' . ($shopName ?: '-'),
                    'Phone     : ' . ($phone ?: '-'),
                    'Email     : ' . ($email ?: '-'),
                    'City      : ' . ($city ?: '-'),
                    'Message   : ' . ($message ?: '-'),
                    '',
                    'Lead #' . $leadId,
                ])
            );
        }

        flash('success', 'Thank you! We have received your enquiry and will get back to you shortly.');
        Response::redirect('/#contact');
    }

    public function robots(Request $request): void {
        $appUrl = rtrim((string)Config::get('app.url', ''), '/');

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        echo "User-agent: *\n";
        echo "Allow: /\n";
        echo "Disallow: /app/\n";
        echo "Disallow: /admin/\n";
        echo "Disallow: /auth/\n";
        echo "Disallow: /healthz\n";
        echo "\nSitemap: {$appUrl}/sitemap.xml\n";
    }

    public function sitemap(Request $request): void {
        $appUrl = rtrim((string)Config::get('app.url', ''), '/');

        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        $entries = [
            ['loc' => $appUrl . '/', 'priority' => '1.0', 'changefreq' => 'weekly'],
        ];

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($entries as $entry) {
            echo "  <url>\n";
            echo '    <loc>' . e($entry['loc']) . "</loc>\n";
            echo '    <lastmod>' . date('Y-m-d') . "</lastmod>\n";
            echo '    <changefreq>' . $entry['changefreq'] . "</changefreq>\n";
            echo '    <priority>' . $entry['priority'] . "</priority>\n";
            echo "  </url>\n";
        }
        echo "</urlset>\n";
    }

    protected function clientIp(): string {
        $remote = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        $isPublic = $remote !== '' && filter_var(
            $remote,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        );

        // Only trust forwarded headers when we are actually behind a proxy.
        if (!$isPublic) {
            $candidates = array_merge(
                explode(',', (string)($_SERVER['HTTP_CF_CONNECTING_IP'] ?? '')),
                explode(',', (string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? ''))
            );
            foreach ($candidates as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $candidate;
                }
            }
        }

        return substr($remote !== '' ? $remote : '0.0.0.0', 0, 45);
    }
}

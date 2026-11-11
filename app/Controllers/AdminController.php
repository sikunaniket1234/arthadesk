<?php
namespace App\Controllers;

use App\Core\Config;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Database;
use App\Core\View;
use App\Services\AuditService;
use App\Services\TenantResolver;

class AdminController {
    public function index(Request $request): void {
        $tenants = Database::fetchAll("
            SELECT t.*, tp.registration_mode, tp.gstin,
                   (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id) as user_count,
                   (SELECT COUNT(*) FROM invoices i WHERE i.tenant_id = t.id) as invoice_count,
                   (SELECT COALESCE(SUM(total_amount), 0) FROM invoices i WHERE i.tenant_id = t.id AND i.payment_status <> 'cancelled') as total_sales
            FROM tenants t
            LEFT JOIN tenant_tax_profiles tp ON tp.tenant_id = t.id
            ORDER BY t.id ASC
        ");

        View::render('admin/index', [
            'tenants' => $tenants,
            'baseDomain' => TenantResolver::baseDomain(),
            'noLayout' => false,
        ]);
    }

    public function createTenant(Request $request): void {
        $name = trim((string)$request->input('name', ''));
        $owner = trim((string)$request->input('owner_name', ''));
        $phone = trim((string)$request->input('phone', ''));
        $email = strtolower(trim((string)$request->input('email', '')));
        $ownerEmail = strtolower(trim((string)$request->input('owner_email', '')));
        $ownerPassword = (string)$request->input('owner_password', '');
        $mode = (string)$request->input('registration_mode', 'regular');
        $gstin = strtoupper(trim((string)$request->input('gstin', ''))) ?: null;
        $stateCode = trim((string)$request->input('state_code', '21'));

        if ($name === '') {
            flash('error', 'Business name is required.');
            Response::redirect('/admin/tenants');
            return;
        }

        if (!in_array($mode, ['regular', 'composition', 'unregistered'], true)) {
            $mode = 'unregistered';
        }

        if ($gstin !== null && !preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
            flash('error', 'Invalid GSTIN format.');
            Response::redirect('/admin/tenants');
            return;
        }

        if ($ownerEmail !== '' && !filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Owner email is invalid.');
            Response::redirect('/admin/tenants');
            return;
        }

        if ($ownerEmail !== '' && strlen($ownerPassword) < 8) {
            flash('error', 'Owner password must be at least 8 characters.');
            Response::redirect('/admin/tenants');
            return;
        }

        $slug = $this->normalizeSlug((string)$request->input('slug', ''));
        if ($slug === '') {
            $slug = $this->slugify($name);
        }

        $reserved = (array)Config::get('tenancy.reserved_slugs', []);
        $min = (int)Config::get('tenancy.min_slug_length', 3);
        $max = (int)Config::get('tenancy.max_slug_length', 50);

        if (strlen($slug) < $min || strlen($slug) > $max || in_array($slug, $reserved, true)) {
            flash('error', 'Store address is invalid or reserved. Use 3-50 letters, numbers, or hyphens.');
            Response::redirect('/admin/tenants');
            return;
        }

        $existing = Database::fetchOne("SELECT id FROM tenants WHERE slug = :slug LIMIT 1", ['slug' => $slug]);
        if ($existing) {
            flash('error', 'That store address is already in use. Choose another slug.');
            Response::redirect('/admin/tenants');
            return;
        }

        try {
            $tenantId = Database::transaction(function (\PDO $pdo) use ($name, $slug, $owner, $phone, $email, $mode, $gstin, $stateCode, $ownerEmail, $ownerPassword) {
                $stmt = $pdo->prepare("
                    INSERT INTO tenants (name, slug, owner_name, phone, email, status, currency, default_locale)
                    VALUES (?, ?, ?, ?, ?, 'active', 'INR', 'en')
                ");
                $stmt->execute([$name, $slug, $owner ?: null, $phone ?: null, $email ?: null]);
                $tenantId = (int)$pdo->lastInsertId();

                $taxStmt = $pdo->prepare("
                    INSERT INTO tenant_tax_profiles (tenant_id, registration_mode, gstin, legal_business_name, trade_name, registered_state_code)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $taxStmt->execute([$tenantId, $mode, $gstin, $name, $name, $stateCode]);

                $accounts = [
                    ['1010', 'Cash in Hand', 'asset'],
                    ['1020', 'Bank / UPI Clearing', 'asset'],
                    ['1030', 'Card Clearing', 'asset'],
                    ['1040', 'Accounts Receivable (Khata)', 'asset'],
                    ['1050', 'Inventory Asset', 'asset'],
                    ['2010', 'Output CGST Payable', 'liability'],
                    ['2020', 'Output SGST Payable', 'liability'],
                    ['2030', 'Output IGST Payable', 'liability'],
                    ['2050', 'Accounts Payable (Suppliers)', 'liability'],
                    ['3010', 'Owner Equity', 'equity'],
                    ['4010', 'Sales Revenue', 'revenue'],
                    ['4020', 'Sales Returns & Allowances', 'revenue'],
                    ['5010', 'Cost of Goods Sold (COGS)', 'expense'],
                    ['5020', 'Store Rent Expense', 'expense'],
                    ['5030', 'Electricity & Utilities', 'expense'],
                    ['5040', 'Tea & Refreshment / General Expense', 'expense'],
                ];
                $accStmt = $pdo->prepare("INSERT INTO accounts (tenant_id, code, name, type) VALUES (?, ?, ?, ?)");
                foreach ($accounts as $account) {
                    $accStmt->execute([$tenantId, $account[0], $account[1], $account[2]]);
                }

                if ($ownerEmail !== '') {
                    $userStmt = $pdo->prepare("
                        INSERT INTO users (tenant_id, name, email, password_hash, role, is_active)
                        VALUES (?, ?, ?, ?, 'tenant_admin', 1)
                    ");
                    $userStmt->execute([
                        $tenantId,
                        $owner ?: $name,
                        $ownerEmail,
                        password_hash($ownerPassword, PASSWORD_BCRYPT),
                    ]);
                }

                return $tenantId;
            });
        } catch (\Throwable $e) {
            Logger::error('tenant_create_failed', ['error' => $e->getMessage(), 'slug' => $slug], 'admin');
            flash('error', 'Unable to create the business tenant. Please check the details and try again.');
            Response::redirect('/admin/tenants');
            return;
        }

        AuditService::log('tenant.create', 'tenant', $tenantId, [
            'name' => $name,
            'slug' => $slug,
            'registration_mode' => $mode,
            'owner_email' => $ownerEmail ?: null,
        ], $tenantId);

        $url = TenantResolver::isLocalHost()
            ? '/app/pos'
            : 'https://' . $slug . '.' . TenantResolver::baseDomain();
        flash('success', "New business '{$name}' created. Store address: {$url}");
        Response::redirect('/admin/tenants');
    }

    public function toggleStatus(Request $request): void {
        $tenantId = (int)$request->input('tenant_id');
        $tenant = Database::fetchOne("SELECT * FROM tenants WHERE id = :id LIMIT 1", ['id' => $tenantId]);

        if (!$tenant) {
            flash('error', 'Tenant not found.');
            Response::redirect('/admin/tenants');
            return;
        }

        $newStatus = ($tenant['status'] === 'active') ? 'suspended' : 'active';
        Database::query("UPDATE tenants SET status = :status WHERE id = :id", [
            'status' => $newStatus,
            'id' => $tenantId,
        ]);

        AuditService::log('tenant.status', 'tenant', $tenantId, [
            'name' => $tenant['name'],
            'from' => $tenant['status'],
            'to' => $newStatus,
        ], $tenantId);

        flash('success', "Tenant '{$tenant['name']}' status changed to " . strtoupper($newStatus));
        Response::redirect('/admin/tenants');
    }

    public function leads(Request $request): void {
        $status = (string)$request->query('status', 'new');
        $allowed = ['new', 'contacted', 'onboarded', 'closed', 'all'];
        if (!in_array($status, $allowed, true)) {
            $status = 'new';
        }

        $where = '';
        $params = [];
        if ($status !== 'all') {
            $where = ' WHERE status = :status';
            $params['status'] = $status;
        }

        $leads = Database::fetchAll(
            "SELECT * FROM leads{$where} ORDER BY created_at DESC LIMIT 500",
            $params
        );

        $counts = ['new' => 0, 'contacted' => 0, 'onboarded' => 0, 'closed' => 0];
        foreach (Database::fetchAll("SELECT status, COUNT(*) AS total FROM leads GROUP BY status") as $row) {
            $counts[$row['status']] = (int)$row['total'];
        }

        View::render('admin/leads', [
            'leads' => $leads,
            'counts' => $counts,
            'status' => $status,
            'baseDomain' => TenantResolver::baseDomain(),
        ]);
    }

    public function updateLeadStatus(Request $request): void {
        $leadId = (int)$request->input('lead_id');
        $status = (string)$request->input('status', '');
        $back = '/admin/leads';

        if (!in_array($status, ['new', 'contacted', 'onboarded', 'closed'], true)) {
            flash('error', 'That lead status is not valid.');
            Response::redirect($back);
            return;
        }

        $lead = Database::fetchOne("SELECT id, name, status FROM leads WHERE id = :id", ['id' => $leadId]);
        if (!$lead) {
            flash('error', 'Lead not found.');
            Response::redirect($back);
            return;
        }

        Database::query("UPDATE leads SET status = :status WHERE id = :id", [
            'status' => $status,
            'id' => $leadId,
        ]);

        AuditService::log('lead.status_changed', 'lead', $leadId, [
            'name' => $lead['name'],
            'from' => $lead['status'],
            'to' => $status,
        ]);

        flash('success', "Lead status updated to " . strtoupper($status) . '.');
        Response::redirect($back . '?status=' . rawurlencode($status));
    }

    protected function slugify(string $name): string {
        $slug = strtolower(trim((string)preg_replace('/[^A-Za-z0-9]+/', '-', $name), '-'));
        $slug = substr($slug, 0, 40);
        if ($slug === '') {
            $slug = 'store';
        }

        $candidate = $slug;
        $suffix = 1;
        while (Database::fetchOne("SELECT id FROM tenants WHERE slug = :slug LIMIT 1", ['slug' => $candidate])) {
            $candidate = $slug . '-' . $suffix;
            $suffix++;
            if ($suffix > 999) {
                $candidate = $slug . '-' . bin2hex(random_bytes(3));
                break;
            }
        }

        return $candidate;
    }

    protected function normalizeSlug(string $slug): string {
        $slug = strtolower(trim($slug));
        $slug = (string)preg_replace('/[^a-z0-9-]+/', '-', $slug);
        $slug = trim((string)preg_replace('/-+/', '-', $slug), '-');
        return $slug;
    }

    /**
     * GET /admin/health — super-admin ops page: endpoint/API health + full audit trail.
     * Guarded by SuperAdminMiddleware (admin host only in production).
     */
    public function health(Request $request): void {
        $report = $this->healthReport(true);

        // --- Audit trail filters (super admin sees every tenant + platform rows) ---
        $q = trim((string)$request->query('q', ''));
        $actionFilter = trim((string)$request->query('action', ''));
        $tenantFilter = trim((string)$request->query('tenant', ''));
        $from = (string)$request->query('from', '');
        $to = (string)$request->query('to', '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
            $from = '';
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
            $to = '';
        }
        $page = max(1, (int)$request->query('page', 1));
        $perPage = 50;

        $where = [];
        $params = [];
        if ($q !== '') {
            $where[] = '(a.action LIKE ? OR a.entity_type LIKE ? OR a.details LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if ($actionFilter !== '') {
            $where[] = 'a.action = ?';
            $params[] = $actionFilter;
        }
        if ($tenantFilter === '0') {
            $where[] = 'a.tenant_id IS NULL';
        } elseif (ctype_digit($tenantFilter)) {
            $where[] = 'a.tenant_id = ?';
            $params[] = (int)$tenantFilter;
        }
        if ($from !== '') {
            $where[] = 'a.created_at >= ?';
            $params[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $where[] = 'a.created_at <= ?';
            $params[] = $to . ' 23:59:59';
        }
        $whereSql = $where !== [] ? ' WHERE ' . implode(' AND ', $where) : '';

        // Keep the page alive even if the DB is down — the health section reports it.
        $entries = [];
        $total = 0;
        $actionOptions = [];
        $tenantOptions = [];
        $auditError = '';
        try {
            $countRow = Database::fetchOne(
                "SELECT COUNT(*) AS c FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id{$whereSql}",
                $params
            );
            $total = (int)($countRow['c'] ?? 0);
            $totalPages = max(1, (int)ceil($total / $perPage));
            if ($page > $totalPages) {
                $page = $totalPages;
            }
            $offset = ($page - 1) * $perPage;

            $entries = Database::fetchAll(
                "SELECT a.*, u.name AS user_name, u.email AS user_email, t.name AS tenant_name
                 FROM audit_logs a
                 LEFT JOIN users u ON u.id = a.user_id
                 LEFT JOIN tenants t ON t.id = a.tenant_id{$whereSql}
                 ORDER BY a.created_at DESC, a.id DESC
                 LIMIT {$perPage} OFFSET {$offset}",
                $params
            );

            $actionOptions = Database::fetchAll(
                'SELECT action, COUNT(*) AS total FROM audit_logs GROUP BY action ORDER BY action ASC'
            );
            $tenantOptions = Database::fetchAll('SELECT id, name FROM tenants ORDER BY name ASC');
        } catch (\Throwable $e) {
            $auditError = $e->getMessage();
            $totalPages = 1;
            $page = 1;
        }

        View::render('admin/health', [
            'report' => $report,
            'entries' => $entries,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
            'perPage' => $perPage,
            'q' => $q,
            'actionFilter' => $actionFilter,
            'tenantFilter' => $tenantFilter,
            'from' => $from,
            'to' => $to,
            'actionOptions' => $actionOptions,
            'tenantOptions' => $tenantOptions,
            'auditError' => $auditError,
        ]);
    }

    /**
     * GET /admin/health.json — raw health report for direct checks (curl/monitoring).
     * 200 = healthy, 503 = degraded. ?probes=0 skips HTTP endpoint probes.
     */
    public function healthJson(Request $request): void {
        $withProbes = (string)$request->query('probes', '1') !== '0';
        $report = $this->healthReport($withProbes);

        $summary = ['system_ok' => 0, 'system_warn' => 0, 'system_fail' => 0, 'probes_ok' => 0, 'probes_total' => count($report['probes'])];
        foreach ($report['system'] as $rows) {
            foreach ($rows as $check) {
                if ($check['status'] === 'ok') {
                    $summary['system_ok']++;
                } elseif ($check['status'] === 'warn') {
                    $summary['system_warn']++;
                } elseif ($check['status'] === 'fail') {
                    $summary['system_fail']++;
                }
            }
        }
        foreach ($report['probes'] as $p) {
            if ($p['ok']) {
                $summary['probes_ok']++;
            }
        }

        Response::json([
            'status' => $report['ok'] ? 'ok' : 'degraded',
            'host' => (string)($_SERVER['HTTP_HOST'] ?? ''),
            'checked_at' => gmdate('c'),
            'summary' => $summary,
            'probes' => $report['probes'],
            'system' => $report['system'],
        ], $report['ok'] ? 200 : 503);
    }

    /** Runs all system checks + endpoint probes; returns ['system'=>groups,'probes'=>[],'ok'=>bool,'checked_at'=>str]. */
    protected function healthReport(bool $withProbes): array {
        $system = [];
        $add = function (string $group, string $label, string $value, string $status) use (&$system): void {
            $system[$group][] = ['label' => $label, 'value' => $value, 'status' => $status];
        };

        // ---- Runtime ----
        $env = (string)Config::get('app.env', 'unknown');
        $debug = (bool)Config::get('app.debug', false);
        $appUrl = (string)Config::get('app.url', '');
        $add('Runtime', 'Environment', $env, 'info');
        $add('Runtime', 'PHP', PHP_VERSION . ' · ' . PHP_SAPI, version_compare(PHP_VERSION, '8.1.0', '>=') ? 'ok' : 'fail');
        $add('Runtime', 'Debug mode', $debug ? 'enabled' : 'disabled', ($debug && $env === 'production') ? 'fail' : ($debug ? 'warn' : 'ok'));
        $add('Runtime', 'APP_URL', $appUrl, str_starts_with($appUrl, 'https://') ? 'ok' : 'warn');
        $add('Runtime', 'Timezone', date_default_timezone_get(), 'info');
        $add('Runtime', 'Memory limit', (string)ini_get('memory_limit'), 'info');

        // ---- Database ----
        try {
            $pdo = Database::connect();
            $add('Database', 'Connection', 'ok', 'ok');
            $add('Database', 'Server version', (string)$pdo->query('SELECT VERSION()')->fetchColumn(), 'info');
            $tables = (int)$pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()')->fetchColumn();
            $add('Database', 'Tables', (string)$tables, $tables >= 28 ? 'ok' : 'warn');
            $migrations = $pdo->query('SELECT migration FROM migrations ORDER BY id DESC')->fetchAll(\PDO::FETCH_COLUMN);
            $add('Database', 'Migrations', count($migrations) . ' applied' . ($migrations !== [] ? ' · ' . $migrations[0] : ''), $migrations !== [] ? 'ok' : 'warn');
            foreach (['tenants', 'users', 'products', 'invoices', 'audit_logs'] as $table) {
                $count = (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
                $add('Database', ucfirst(str_replace('_', ' ', $table)), number_format($count) . ' rows', 'info');
            }
            $todayAudit = (int)$pdo->query('SELECT COUNT(*) FROM audit_logs WHERE created_at >= CURDATE()')->fetchColumn();
            $add('Database', 'Audit entries today', number_format($todayAudit), 'info');
        } catch (\Throwable $e) {
            $add('Database', 'Connection', $e->getMessage(), 'fail');
        }

        // ---- Storage ----
        $root = dirname(__DIR__, 2);
        $storageDirs = [
            'storage/logs' => 'Logs',
            'storage/cache' => 'Cache',
            'storage/uploads' => 'Uploads',
            'storage/backups' => 'Backups',
            'storage/receipts' => 'Receipts',
        ];
        foreach ($storageDirs as $dir => $label) {
            $path = $root . '/' . $dir;
            $exists = is_dir($path);
            $writable = $exists && is_writable($path);
            $add('Storage', $label, $exists ? ($writable ? 'writable' : 'NOT writable') : 'missing', $writable ? 'ok' : 'fail');
        }

        // ---- Disk & cron ----
        $diskFree = @disk_free_space($root);
        if ($diskFree !== false) {
            $gb = round($diskFree / 1073741824, 2);
            $add('Disk & cron', 'Disk free', $gb . ' GB', $gb < 0.5 ? 'fail' : ($gb < 2 ? 'warn' : 'ok'));
        } else {
            $add('Disk & cron', 'Disk free', 'unavailable', 'warn');
        }

        $backups = glob($root . '/storage/backups/db-*.sql') ?: [];
        usort($backups, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
        if ($backups !== []) {
            $newest = $backups[0];
            $ageHours = (time() - (int)filemtime($newest)) / 3600;
            $ageText = $ageHours < 1 ? round($ageHours * 60) . 'm ago' : round($ageHours, 1) . 'h ago';
            $add('Disk & cron', 'Latest DB backup', basename($newest) . ' · ' . $ageText, $ageHours <= 48 ? 'ok' : ($ageHours <= 96 ? 'warn' : 'fail'));
        } else {
            $add('Disk & cron', 'Latest DB backup', 'none found', 'warn');
        }

        $appLog = $root . '/storage/logs/app-' . date('Y-m-d') . '.log';
        $add('Disk & cron', "Today's app log", file_exists($appLog) ? number_format((int)filesize($appLog)) . ' bytes' : 'not created yet', file_exists($appLog) ? 'ok' : 'warn');

        $cronLog = $root . '/storage/logs/cron-backup.log';
        if (file_exists($cronLog)) {
            $cronAge = (time() - (int)filemtime($cronLog)) / 3600;
            $add('Disk & cron', 'Backup cron', 'last ran ' . ($cronAge < 1 ? round($cronAge * 60) . 'm ago' : round($cronAge, 1) . 'h ago'), $cronAge <= 48 ? 'ok' : 'warn');
        } else {
            $add('Disk & cron', 'Backup cron', 'no cron-backup.log yet', 'warn');
        }

        // ---- Security ----
        $autoMigrate = (bool)Config::get('app.auto_migrate', false);
        $add('Security', 'AUTO_MIGRATE', $autoMigrate ? 'enabled' : 'disabled', ($autoMigrate && $env === 'production') ? 'warn' : 'ok');
        $csp = (bool)Config::get('security.headers.csp', true);
        $add('Security', 'CSP header', $csp ? 'on' : 'off', $csp ? 'ok' : 'warn');
        $add('Security', 'Mail driver', (string)Config::get('contact.mail_driver', 'log'), 'info');
        $envPath = $root . '/.env';
        if (file_exists($envPath)) {
            $mode = fileperms($envPath) & 0777;
            $add('Security', '.env permissions', sprintf('%04o', $mode), $mode === 0600 ? 'ok' : 'warn');
        }

        // ---- Endpoint / API probes (never sends cookies; cannot recurse) ----
        $probes = [];
        if ($withProbes) {
            $targets = [
                ['GET', '/healthz', [200], 'Liveness JSON API'],
                ['GET', '/robots.txt', [200], 'Static robots route'],
                ['GET', '/', [200, 301, 302], 'Root / landing'],
                ['GET', '/auth/login', [200, 302], 'Login page'],
                ['GET', '/app/pos', [301, 302], 'App auth guard (unauth → redirect)'],
                ['GET', '/app/products', [301, 302], 'App auth guard (unauth → redirect)'],
                ['GET', '/admin/health', [301, 302], 'Admin guard (super_admin only)'],
                ['POST', '/app/pos/calculate', [401, 419], 'POS calculate API — expects 401'],
                ['POST', '/app/pos/checkout', [401, 419], 'POS checkout API — expects 401'],
            ];
            foreach ($targets as [$method, $path, $accept, $label]) {
                $probes[] = $this->probe($method, $path, $accept, $label);
            }
        }

        $ok = true;
        foreach ($system as $rows) {
            foreach ($rows as $check) {
                if ($check['status'] === 'fail') {
                    $ok = false;
                }
            }
        }
        foreach ($probes as $p) {
            if (!$p['ok']) {
                $ok = false;
            }
        }

        return [
            'system' => $system,
            'probes' => $probes,
            'ok' => $ok,
            'checked_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Single HTTP probe against this host. Never sends cookies and never follows
     * redirects, so probing /admin/* cannot recurse into this page.
     */
    protected function probe(string $method, string $path, array $accept, string $label): array {
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
        $scheme = $https ? 'https' : 'http';
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            $host = (string)parse_url((string)Config::get('app.url', ''), PHP_URL_HOST);
        }
        $url = $scheme . '://' . $host . $path;

        $status = 0;
        $body = '';
        $error = '';
        $t0 = microtime(true);

        if (function_exists('curl_init')) {
            $headers = [
                'Connection: close',
                'Accept: ' . ($method === 'POST' ? 'application/json' : '*/*'),
                'User-Agent: ArthaDesk-OpsHealth/1.0',
            ];
            if ($method === 'POST') {
                $headers[] = 'X-Requested-With: XMLHttpRequest';
                $headers[] = 'Content-Type: application/json';
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_CUSTOMREQUEST => $method,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 4,
                CURLOPT_HTTPHEADER => $headers,
            ]);
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POSTFIELDS, '{}');
            }
            $resp = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($resp === false) {
                $error = curl_error($ch);
            } else {
                $body = (string)$resp;
            }
            curl_close($ch);
        } elseif (filter_var((string)ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $http = [
                'method' => $method,
                'timeout' => 4,
                'ignore_errors' => true,
                'follow_location' => 0,
                'header' => "Connection: close\r\n"
                    . 'Accept: ' . ($method === 'POST' ? 'application/json' : '*/*') . "\r\n"
                    . ($method === 'POST' ? "Content-Type: application/json\r\nX-Requested-With: XMLHttpRequest\r\n" : '')
                    . "User-Agent: ArthaDesk-OpsHealth/1.0\r\n",
            ];
            if ($method === 'POST') {
                $http['content'] = '{}';
            }
            $ctx = stream_context_create(['http' => $http]);
            $resp = @file_get_contents($url, false, $ctx);
            if ($resp === false) {
                $error = 'HTTP request failed';
            } else {
                $body = (string)$resp;
            }
            foreach (($http_response_header ?? []) as $headerLine) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $headerLine, $m)) {
                    $status = (int)$m[1];
                }
            }
        } else {
            $error = 'No HTTP client available (curl/allow_url_fopen)';
        }

        $ms = (int)round((microtime(true) - $t0) * 1000);
        $ok = $error === '' && $status > 0 && in_array($status, $accept, true);

        $note = $error;
        if ($path === '/healthz' && $body !== '') {
            $json = json_decode($body, true);
            if (is_array($json)) {
                $note = 'status=' . ($json['status'] ?? '?') . ' · db=' . ($json['db'] ?? '?') . ' · env=' . ($json['env'] ?? '?');
                if (($json['status'] ?? '') !== 'ok') {
                    $ok = false;
                }
            }
        }

        return [
            'method' => $method,
            'path' => $path,
            'label' => $label,
            'expected' => implode(' / ', $accept),
            'status' => $status,
            'ms' => $ms,
            'ok' => $ok,
            'note' => $note,
        ];
    }
}

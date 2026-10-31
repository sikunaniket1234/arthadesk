<?php
namespace App\Core;

class Request {
    protected array $get;
    protected array $post;
    protected array $server;
    protected ?array $json = null;
    protected array $params = [];
    protected ?array $tenant = null;
    protected ?array $resolvedTenant = null;
    protected ?array $taxProfile = null;
    protected ?array $user = null;

    public function __construct() {
        $this->get = $_GET;
        $this->post = $_POST;
        $this->server = $_SERVER;

        $input = file_get_contents('php://input');
        if (!empty($input)) {
            // Strip UTF-8 BOM if present
            if (str_starts_with($input, "\xEF\xBB\xBF")) {
                $input = substr($input, 3);
            }
            $input = trim($input);
            $contentType = $this->header('Content-Type', '');
            if (str_contains($contentType, 'application/json') || str_starts_with($input, '{') || str_starts_with($input, '[')) {
                $this->json = json_decode($input, true) ?? [];
            }
        }
    }

    public function method(): string {
        return strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');
    }

    public function uri(): string {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        return '/' . trim($path, '/');
    }

    public function get(string $key, $default = null) {
        return $this->get[$key] ?? $default;
    }

    /** Alias for get() — reads query string parameters. */
    public function query(string $key, $default = null) {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, $default = null) {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, $default = null) {
        if ($this->json !== null && array_key_exists($key, $this->json)) {
            return $this->json[$key];
        }
        return $this->post[$key] ?? $this->get[$key] ?? $default;
    }

    public function all(): array {
        if ($this->json !== null) {
            return array_merge($this->get, $this->json);
        }
        return array_merge($this->get, $this->post);
    }

    public function header(string $key, $default = null) {
        $keyUpper = strtoupper(str_replace('-', '_', $key));
        if (isset($this->server['HTTP_' . $keyUpper])) {
            return $this->server['HTTP_' . $keyUpper];
        }
        if (isset($this->server[$keyUpper])) {
            return $this->server[$keyUpper];
        }
        return $this->server[$key] ?? $default;
    }

    public function isAjax(): bool {
        return strtolower($this->header('X-Requested-With', '')) === 'xmlhttprequest' ||
               str_contains($this->header('Accept', ''), 'application/json');
    }

    public function setParams(array $params): void {
        $this->params = $params;
    }

    public function param(string $key, $default = null) {
        return $this->params[$key] ?? $default;
    }

    public function setTenant(?array $tenant): void {
        $this->tenant = $tenant;
    }

    public function tenant(): ?array {
        return $this->tenant;
    }

    public function setResolvedTenant(?array $tenant): void {
        $this->resolvedTenant = $tenant;
    }

    public function resolvedTenant(): ?array {
        return $this->resolvedTenant;
    }

    public function isJson(): bool {
        return str_contains($this->header('Content-Type', ''), 'application/json');
    }

    public function setTaxProfile(?array $taxProfile): void {
        $this->taxProfile = $taxProfile;
    }

    public function taxProfile(): ?array {
        return $this->taxProfile;
    }

    public function setUser(?array $user): void {
        $this->user = $user;
    }

    public function user(): ?array {
        return $this->user;
    }
}

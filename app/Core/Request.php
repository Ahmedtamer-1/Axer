<?php

namespace Axer\Core;

class Request
{
    protected array $server;
    protected array $get;
    protected array $post;
    protected array $files;
    protected array $cookies;
    protected ?string $content;

    /** Decoded JSON body, computed once. */
    protected ?array $jsonCache = null;
    protected bool $jsonDecoded = false;

    protected ?string $uriCache = null;

    /** Principal attached by AuthMiddleware after a token check. */
    protected ?array $auth = null;

    public function __construct(
        array $server = [],
        array $get = [],
        array $post = [],
        array $files = [],
        array $cookies = [],
        ?string $content = null
    ) {
        $this->server = $server;
        $this->get = $get;
        $this->post = $post;
        $this->files = $files;
        $this->cookies = $cookies;

        // Only read the raw body when there could be one. Reading
        // php://input on every GET was pure overhead.
        if ($content !== null) {
            $this->content = $content;
        } elseif (in_array($server['REQUEST_METHOD'] ?? 'GET', ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            $this->content = file_get_contents('php://input') ?: null;
        } else {
            $this->content = null;
        }
    }

    public static function capture(): self
    {
        return new self($_SERVER, $_GET, $_POST, $_FILES, $_COOKIE);
    }

    public function method(): string
    {
        $method = strtoupper($this->server['REQUEST_METHOD'] ?? 'GET');

        if ($method === 'POST') {
            $override = $this->post['_method'] ?? $this->server['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;

            if ($override && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
                return strtoupper($override);
            }
        }

        return $method;
    }

    /**
     * Path portion of the request, normalised.
     *
     * The old version returned REQUEST_URI verbatim, so "/products/%D8%A3"
     * never matched a route and "//shop" or "/shop/." slipped through as
     * distinct URIs.
     */
    public function uri(): string
    {
        if ($this->uriCache !== null) {
            return $this->uriCache;
        }

        $uri = $this->server['REQUEST_URI'] ?? '/';

        if (($pos = strpos($uri, '?')) !== false) {
            $uri = substr($uri, 0, $pos);
        }

        $uri = rawurldecode($uri);

        // Collapse duplicate slashes and strip a trailing one (except root).
        $uri = preg_replace('#/+#', '/', $uri) ?? '/';

        if (strlen($uri) > 1) {
            $uri = rtrim($uri, '/');
        }

        return $this->uriCache = ($uri === '' ? '/' : $uri);
    }

    public function get(string $key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    public function file(string $key): ?array
    {
        return $this->files[$key] ?? null;
    }

    public function cookie(string $key, $default = null)
    {
        return $this->cookies[$key] ?? $default;
    }

    /**
     * Look in the POST body, then the query string, then the JSON body.
     *
     * JSON was previously invisible to input(), so any API client sending
     * application/json got nothing but defaults.
     */
    public function input(string $key, $default = null)
    {
        if (array_key_exists($key, $this->post)) {
            return $this->post[$key];
        }

        if (array_key_exists($key, $this->get)) {
            return $this->get[$key];
        }

        $json = $this->jsonBody();

        if (array_key_exists($key, $json)) {
            return $json[$key];
        }

        return $default;
    }

    public function all(): array
    {
        return array_merge($this->jsonBody(), $this->get, $this->post);
    }

    public function has(string $key): bool
    {
        return $this->input($key, '__axer_missing__') !== '__axer_missing__';
    }

    /**
     * Trimmed string input, useful for form fields.
     */
    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    public function float(string $key, float $default = 0.0): float
    {
        $value = $this->input($key, $default);

        return is_numeric($value) ? (float) $value : $default;
    }

    public function boolean(string $key, bool $default = false): bool
    {
        $value = $this->input($key, $default);

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * The decoded JSON body. Decoded at most once per request — the old
     * version re-ran json_decode on every single json() call.
     */
    public function jsonBody(): array
    {
        if ($this->jsonDecoded) {
            return $this->jsonCache ?? [];
        }

        $this->jsonDecoded = true;
        $this->jsonCache = [];

        if ($this->content === null || $this->content === '') {
            return [];
        }

        $decoded = json_decode($this->content, true);

        if (is_array($decoded)) {
            $this->jsonCache = $decoded;
        }

        return $this->jsonCache;
    }

    public function json(?string $key = null, $default = null)
    {
        $data = $this->jsonBody();

        if ($key === null) {
            return $data;
        }

        return $data[$key] ?? $default;
    }

    public function content(): ?string
    {
        return $this->content;
    }

    /**
     * Read a request header.
     *
     * Content-Type and Content-Length are exposed by PHP without the HTTP_
     * prefix, so header('Content-Type') used to always return null.
     */
    public function header(string $key, $default = null)
    {
        $normalized = strtoupper(str_replace('-', '_', $key));

        if (in_array($normalized, ['CONTENT_TYPE', 'CONTENT_LENGTH', 'CONTENT_MD5'], true)) {
            return $this->server[$normalized] ?? $this->server['HTTP_' . $normalized] ?? $default;
        }

        return $this->server['HTTP_' . $normalized] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = (string) ($this->header('Authorization') ?? '');

        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers() ?: [];
            // Header names are case-insensitive; normalise before lookup.
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'Authorization') === 0) {
                    $header = (string) $value;
                    break;
                }
            }
        }

        // Some FastCGI setups only pass it through this rewritten variable.
        if ($header === '') {
            $header = (string) ($this->server['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        }

        if (stripos($header, 'Bearer ') === 0) {
            return trim(substr($header, 7)) ?: null;
        }

        return null;
    }

    public function isJson(): bool
    {
        return str_contains(strtolower((string) $this->header('Content-Type', '')), 'json');
    }

    /**
     * Should the client be answered with JSON rather than an HTML page?
     */
    public function expectsJson(): bool
    {
        if ($this->isJson() || $this->isAjax()) {
            return true;
        }

        $accept = strtolower((string) $this->header('Accept', ''));

        return str_contains($accept, 'application/json') && !str_contains($accept, 'text/html');
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With', '')) === 'xmlhttprequest';
    }

    public function isSecure(): bool
    {
        if (!empty($this->server['HTTPS']) && strtolower((string) $this->server['HTTPS']) !== 'off') {
            return true;
        }

        return ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }

    public function ip(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function userAgent(): string
    {
        return (string) ($this->server['HTTP_USER_AGENT'] ?? '');
    }

    /**
     * Attach the authenticated principal. Called by AuthMiddleware so that
     * controllers can tell who is making the request — previously the
     * middleware verified the token and then threw the identity away.
     */
    public function setAuth(array $auth): void
    {
        $this->auth = $auth;
    }

    public function auth(): ?array
    {
        return $this->auth;
    }

    public function userId(): ?int
    {
        $id = $this->auth['user_id'] ?? null;

        return $id === null ? null : (int) $id;
    }
}

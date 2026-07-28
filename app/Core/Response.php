<?php

namespace Axer\Core;

class Response
{
    protected string $content;
    protected int $statusCode;
    protected array $headers = [];
    protected bool $sent = false;

    public function __construct(string $content = '', int $statusCode = 200, array $headers = [])
    {
        $this->content = $content;
        $this->statusCode = $statusCode;
        $this->headers = $headers;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return null;
    }

    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setContent(string $content): self
    {
        $this->content = $content;
        return $this;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Mark the response as cacheable by browsers and CDNs.
     */
    public function cache(int $seconds, bool $public = true): self
    {
        $visibility = $public ? 'public' : 'private';
        $this->setHeader('Cache-Control', "{$visibility}, max-age={$seconds}");

        return $this;
    }

    public function noCache(): self
    {
        return $this->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function send(): void
    {
        if ($this->sent) {
            return;
        }

        $this->sent = true;

        if (!headers_sent()) {
            http_response_code($this->statusCode);

            // Default a content type so browsers do not have to sniff.
            if ($this->getHeader('Content-Type') === null && $this->content !== '') {
                $this->headers['Content-Type'] = 'text/html; charset=UTF-8';
            }

            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }

            // A conditional GET lets the browser skip the download entirely.
            if ($this->isCacheable()) {
                $etag = '"' . md5($this->content) . '"';
                header('ETag: ' . $etag);

                $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';

                if ($ifNoneMatch !== '' && trim($ifNoneMatch) === $etag) {
                    http_response_code(304);
                    return;
                }
            }
        }

        // HEAD must carry the headers but no body.
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
            return;
        }

        echo $this->content;
    }

    protected function isCacheable(): bool
    {
        if ($this->statusCode !== 200 || $this->content === '') {
            return false;
        }

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            return false;
        }

        $cacheControl = $this->getHeader('Cache-Control') ?? '';

        return !str_contains($cacheControl, 'no-store');
    }

    /**
     * JSON response.
     *
     * json_encode() returns false on failure (recursion, malformed UTF-8),
     * which used to be passed straight into a string parameter and blow up
     * with a TypeError. Failures now degrade to a 500 payload instead.
     */
    public static function json($data, int $statusCode = 200, array $headers = []): self
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

        try {
            $encoded = json_encode($data, $flags | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            Logger::error('Failed to encode JSON response: ' . $e->getMessage());
            $encoded = json_encode(['success' => false, 'message' => 'Internal serialization error.']);
            $statusCode = 500;
        }

        $headers['Content-Type'] = 'application/json; charset=utf-8';
        // Stop browsers from guessing a different type for API payloads.
        $headers['X-Content-Type-Options'] = 'nosniff';

        return new self((string) $encoded, $statusCode, $headers);
    }

    public static function redirect(string $url, int $statusCode = 302): self
    {
        // Refuse header injection via CR/LF in a redirect target.
        $url = str_replace(["\r", "\n", "\0"], '', $url);

        return new self('', $statusCode, ['Location' => $url]);
    }

    public static function notFound(string $message = 'The page you are looking for does not exist.'): self
    {
        return new self(
            '<!doctype html><meta charset="utf-8"><title>404 — Not found</title>'
            . '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:34rem;margin:20vh auto;padding:0 1.5rem;color:#0f172a}'
            . 'h1{font-size:1.5rem;margin:0 0 .5rem}p{color:#64748b}a{color:#6366f1}</style>'
            . '<h1>Page not found</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><a href="/">Return home</a></p>',
            404
        );
    }
}

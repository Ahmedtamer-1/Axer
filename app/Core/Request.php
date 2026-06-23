<?php

namespace Lume\Core;

class Request
{
    protected array $server;
    protected array $get;
    protected array $post;
    protected array $files;
    protected array $cookies;
    protected ?string $content;

    public function __construct(
        array $server = [],
        array $get = [],
        array $post = [],
        array $files = [],
        array $cookies = []
    ) {
        $this->server = $server;
        $this->get = $get;
        $this->post = $post;
        $this->files = $files;
        $this->cookies = $cookies;
        $this->content = file_get_contents('php://input') ?: null;
    }

    public static function capture(): self
    {
        return new self($_SERVER, $_GET, $_POST, $_FILES, $_COOKIE);
    }

    public function method(): string
    {
        $method = $this->server['REQUEST_METHOD'] ?? 'GET';
        
        if ($method === 'POST') {
            $override = $this->post['_method'] ?? $this->server['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? null;
            if ($override && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'])) {
                return strtoupper($override);
            }
        }
        
        return $method;
    }

    public function uri(): string
    {
        $uri = $this->server['REQUEST_URI'] ?? '/';
        $position = strpos($uri, '?');
        
        if ($position !== false) {
            $uri = substr($uri, 0, $position);
        }
        
        return $uri;
    }

    public function get(string $key, $default = null)
    {
        return $this->get[$key] ?? $default;
    }

    public function post(string $key, $default = null)
    {
        return $this->post[$key] ?? $default;
    }

    public function input(string $key, $default = null)
    {
        if (isset($this->post[$key])) {
            return $this->post[$key];
        }
        if (isset($this->get[$key])) {
            return $this->get[$key];
        }
        return $default;
    }

    public function all(): array
    {
        return array_merge($this->get, $this->post);
    }

    public function json(string $key = null, $default = null)
    {
        $data = json_decode($this->content, true) ?? [];
        if ($key === null) {
            return $data;
        }
        return $data[$key] ?? $default;
    }

    public function header(string $key, $default = null)
    {
        $key = 'HTTP_' . str_replace('-', '_', strtoupper($key));
        return $this->server[$key] ?? $default;
    }
    
    public function bearerToken(): ?string
    {
        $header = $this->header('Authorization');
        if (empty($header)) {
            if (function_exists('apache_request_headers')) {
                $headers = apache_request_headers();
                $header = $headers['Authorization'] ?? '';
            }
        }
        
        if (str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }
        
        return null;
    }
}

<?php

namespace Axer\Core;

class App
{
    protected static ?App $instance = null;
    protected Container $container;

    public function __construct()
    {
        $this->registerAutoloader();
        $this->registerErrorHandlers();

        self::$instance = $this;
        $this->container = new Container();

        $this->boot();
    }

    public static function getInstance(): App
    {
        return self::$instance;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    protected function registerAutoloader(): void
    {
        $baseDir = BASE_PATH . '/app/';

        spl_autoload_register(static function ($class) use ($baseDir) {
            $prefix = 'Axer\\';
            $len = strlen($prefix);

            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relative = substr($class, $len);

            // Reject traversal in a class name before it reaches the filesystem.
            if (str_contains($relative, '..')) {
                return;
            }

            $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

            if (is_file($file)) {
                require $file;
            }
        });
    }

    protected function registerErrorHandlers(): void
    {
        error_reporting(E_ALL);

        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return false;
            }

            // Deprecations and notices should be logged, not thrown. Promoting
            // every notice to an exception meant a single undefined array key
            // in a view took the whole page down with a 500.
            if ($severity & (E_DEPRECATED | E_USER_DEPRECATED | E_NOTICE | E_USER_NOTICE | E_WARNING | E_USER_WARNING)) {
                Logger::warning($message, ['file' => $file, 'line' => $line]);
                return true;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (\Throwable $e) {
            $this->handleException($e);
        });

        // Catch fatals that bypass the exception handler entirely.
        register_shutdown_function(function () {
            $error = error_get_last();

            if ($error && ($error['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
                Logger::error($error['message'], [
                    'file' => $error['file'],
                    'line' => $error['line'],
                ]);
            }
        });
    }

    protected function handleException(\Throwable $e): void
    {
        Logger::exception($e);

        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: text/html; charset=UTF-8');
        }

        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo '<h1>Application Error</h1>';
            echo '<p><strong>Message:</strong> ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</p>';
            echo '<p><strong>File:</strong> ' . htmlspecialchars($e->getFile(), ENT_QUOTES, 'UTF-8')
                . ' on line ' . $e->getLine() . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString(), ENT_QUOTES, 'UTF-8') . '</pre>';
            return;
        }

        // Never echo the exception message in production: it routinely
        // contains the database DSN, credentials and absolute paths.
        echo '<!doctype html><meta charset="utf-8"><title>500 — Something went wrong</title>';
        echo '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:34rem;margin:20vh auto;padding:0 1.5rem;color:#0f172a}'
            . 'h1{font-size:1.5rem;margin:0 0 .5rem}p{color:#64748b}a{color:#6366f1}</style>';
        echo '<h1>Something went wrong</h1>';
        echo '<p>We hit an unexpected error. The team has been notified — please try again in a moment.</p>';
        echo '<p><a href="/">Return home</a></p>';
    }

    protected function boot(): void
    {
        // Load configuration
        $this->container->singleton(Config::class, function () {
            return new Config(BASE_PATH . '/config/.env');
        });

        // Initialize Router
        $this->container->singleton(Router::class, function () {
            return new Router($this->container);
        });

        /** @var Config $config */
        $config = $this->container->get(Config::class);

        // Config::loadEnv() casts the literals true/false, but a value can
        // still arrive as the string "1"/"0" from the real environment.
        $debug = filter_var($config->get('APP_DEBUG', false), FILTER_VALIDATE_BOOL);
        define('APP_DEBUG', $debug);
        define('APP_ENV', (string) $config->get('APP_ENV', 'production'));

        ini_set('display_errors', $debug ? '1' : '0');

        if (!$debug && APP_ENV === 'production' && $config->get('APP_DEBUG') === true) {
            Logger::warning('APP_DEBUG is enabled in a production environment.');
        }

        $this->container->singleton(RateLimiter::class, function () {
            return new RateLimiter();
        });

        // Initialize Plugin System
        $this->container->singleton(\Axer\Services\PluginService::class, function () {
            return new \Axer\Services\PluginService();
        });

        // Boot plugins. A broken third-party plugin must not take the site
        // down, so failures are logged and the request continues.
        try {
            $this->container->get(\Axer\Services\PluginService::class)->init();
        } catch (\Throwable $e) {
            Logger::error('Plugin boot failed: ' . $e->getMessage());
        }
    }

    public function run(): void
    {
        Session::start();
        Csrf::token();

        /** @var Router $router */
        $router = $this->container->get(Router::class);

        // Load route definitions
        $this->loadRoutes($router);

        $request = Request::capture();

        // Security headers are emitted before dispatch so they still apply
        // if a controller echoes directly instead of returning a Response.
        $this->sendSecurityHeaders();

        if (($limited = $this->enforceRateLimit($request)) !== null) {
            $limited->send();
            return;
        }

        if (($rejected = $this->enforceCsrf($request)) !== null) {
            $rejected->send();
            return;
        }

        $response = $router->dispatch($request);
        $response->send();
    }

    protected function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        // X-XSS-Protection is deliberately omitted: the legacy auditor it
        // enabled is removed from modern browsers and introduced its own
        // vulnerabilities. CSP is the replacement.

        if (!empty($_SERVER['HTTPS']) || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }

        // Icons are self-hosted now, so no CDN host needs to be allowed.
        // 'unsafe-eval' is dropped — nothing in the app needs it. Inline
        // styles/scripts are still permitted because the themes rely on them.
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://connect.facebook.net https://analytics.tiktok.com https://www.googletagmanager.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "font-src 'self' https://fonts.gstatic.com data:",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https://connect.facebook.net https://analytics.tiktok.com",
            "frame-src 'self' https://accept.paymob.com https://www.facebook.com",
            "form-action 'self' https://accept.paymob.com",
            "base-uri 'self'",
            "frame-ancestors 'self'",
        ];

        header('Content-Security-Policy: ' . implode('; ', $csp));
    }

    /**
     * Global request ceiling. Per-route limits are applied separately by
     * RateLimitMiddleware; this is only a blunt backstop against floods.
     */
    protected function enforceRateLimit(Request $request): ?Response
    {
        // Never throttle the payment provider's callback.
        if (str_starts_with($request->uri(), '/checkout/callback')) {
            return null;
        }

        /** @var RateLimiter $limiter */
        $limiter = $this->container->get(RateLimiter::class);

        $key = 'global:' . RateLimiter::clientIp($this->trustedProxies());
        $limit = 300;
        $window = 60;

        if (!$limiter->tooManyAttempts($key, $limit, $window)) {
            return null;
        }

        $retryAfter = $limiter->availableIn($key, $window);

        Logger::warning('Rate limit exceeded', ['ip' => RateLimiter::clientIp(), 'uri' => $request->uri()]);

        if ($request->expectsJson()) {
            return Response::json([
                'success' => false,
                'message' => 'Too many requests. Please slow down.',
            ], 429)->setHeader('Retry-After', (string) $retryAfter);
        }

        return (new Response('429 Too Many Requests', 429))
            ->setHeader('Retry-After', (string) $retryAfter);
    }

    protected function enforceCsrf(Request $request): ?Response
    {
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return null;
        }

        $uri = $request->uri();

        // /api/* authenticates with bearer tokens, and the payment callback
        // is a server-to-server POST verified by HMAC signature instead.
        foreach (['/api', '/checkout/callback'] as $exempt) {
            if (str_starts_with($uri, $exempt)) {
                return null;
            }
        }

        if (Csrf::check(Csrf::fromRequest($request))) {
            return null;
        }

        Logger::warning('CSRF token mismatch', ['uri' => $uri]);

        if ($request->expectsJson()) {
            return Response::json([
                'success' => false,
                'message' => 'Your session expired. Please refresh the page and try again.',
            ], 419);
        }

        return new Response(
            '<!doctype html><meta charset="utf-8"><title>419 — Page expired</title>'
            . '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:34rem;margin:20vh auto;padding:0 1.5rem}</style>'
            . '<h1>Page expired</h1><p>Your session timed out for security. '
            . 'Please go back, refresh the page, and submit again.</p>',
            419
        );
    }

    protected function trustedProxies(): array
    {
        /** @var Config $config */
        $config = $this->container->get(Config::class);
        $raw = (string) $config->get('TRUSTED_PROXIES', '');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    protected function loadRoutes(Router $router): void
    {
        // Route files to load
        $routeFiles = [
            BASE_PATH . '/config/routes.php',
            BASE_PATH . '/config/admin_routes.php',
            BASE_PATH . '/config/api_routes.php',
        ];

        foreach ($routeFiles as $file) {
            if (is_file($file)) {
                require_once $file;
            }
        }
    }
}

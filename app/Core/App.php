<?php

namespace Lume\Core;

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
        spl_autoload_register(function ($class) {
            $prefix = 'Lume\\';
            $base_dir = BASE_PATH . '/app/';

            $len = strlen($prefix);
            if (strncmp($prefix, $class, $len) !== 0) {
                return;
            }

            $relative_class = substr($class, $len);
            $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

            if (file_exists($file)) {
                require $file;
            }
        });
    }

    protected function registerErrorHandlers(): void
    {
        error_reporting(E_ALL);
        
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) {
                return;
            }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        set_exception_handler(function (\Throwable $e) {
            $this->handleException($e);
        });
    }

    protected function handleException(\Throwable $e): void
    {
        // Simple exception handling, can be improved later
        http_response_code(500);
        
        if (defined('APP_DEBUG') && APP_DEBUG) {
            echo "<h1>Application Error</h1>";
            echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>";
            echo "<p><strong>File:</strong> " . $e->getFile() . " on line " . $e->getLine() . "</p>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        } else {
            echo "<h1>500 Internal Server Error</h1>";
            echo "<p>An unexpected error occurred. Please try again later.</p>";
        }
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
        
        // Define APP_DEBUG constant from config
        $config = $this->container->get(Config::class);
        define('APP_DEBUG', $config->get('APP_DEBUG', false));

        // Initialize Plugin System
        $this->container->singleton(\Lume\Services\PluginService::class, function () {
            return new \Lume\Services\PluginService();
        });
        
        // Boot plugins
        $this->container->get(\Lume\Services\PluginService::class)->init();
    }

    public function run(): void
    {
        // Simple CSRF Protection
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        /** @var Router $router */
        $router = $this->container->get(Router::class);
        
        // Load route definitions
        $this->loadRoutes($router);
        
        $request = Request::capture();

        // Rate Limiter
        if ($this->isRateLimited($request)) {
            http_response_code(429);
            echo "429 Too Many Requests";
            return;
        }

        // Validate CSRF for state-changing methods
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $token = $request->post('_csrf') ?? $request->header('X-CSRF-TOKEN') ?? $request->json('_csrf');
            // Skip CSRF for API or Webhooks
            if (!str_starts_with($request->uri(), '/api') && !str_starts_with($request->uri(), '/checkout/callback')) {
                if (!hash_equals($_SESSION['csrf_token'], $token ?? '')) {
                    http_response_code(419);
                    echo "419 Page Expired (CSRF Token Mismatch)";
                    return;
                }
            }
        }

        $response = $router->dispatch($request);
        
        // Apply global security headers
        header("X-Frame-Options: SAMEORIGIN");
        header("X-XSS-Protection: 1; mode=block");
        header("X-Content-Type-Options: nosniff");
        header("Content-Security-Policy: default-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.googleapis.com https://fonts.gstatic.com https://connect.facebook.net https://www.facebook.com https://analytics.tiktok.com; img-src 'self' data: https:;");

        $response->send();
    }

    protected function isRateLimited(Request $request): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "rate_limit:{$ip}";
        $limit = 100; // requests per minute
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 1, 'time' => time()];
            return false;
        }

        if (time() - $_SESSION[$key]['time'] > 60) {
            $_SESSION[$key] = ['count' => 1, 'time' => time()];
            return false;
        }

        $_SESSION[$key]['count']++;
        return $_SESSION[$key]['count'] > $limit;
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
            if (file_exists($file)) {
                require $file;
            }
        }
    }
}

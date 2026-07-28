<?php

namespace Axer\Core;

/**
 * Hardened session bootstrap.
 *
 * Sessions were started in a dozen places with PHP's defaults, which meant
 * the session cookie was readable from JavaScript, sent cross-site, and
 * never regenerated on login (session fixation).
 */
class Session
{
    protected static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Refuse session IDs the server never issued.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_name('axer_session');
        session_start();

        self::$started = true;
    }

    /**
     * Rotate the session ID while keeping the data. Call this immediately
     * after any privilege change (login, logout, role switch).
     */
    public static function regenerate(bool $deleteOld = true): void
    {
        self::start();

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOld);
        }
    }

    /**
     * Fully destroy the session, including the cookie. session_destroy()
     * alone leaves the cookie in the browser pointing at a dead session.
     */
    public static function destroy(): void
    {
        self::start();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }

        session_destroy();
        self::$started = false;
    }

    public static function get(string $key, $default = null)
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * One-shot message, read once then cleared. Used for the admin toasts.
     */
    public static function flash(string $key, $value = null)
    {
        self::start();

        if ($value === null) {
            $out = $_SESSION['_flash'][$key] ?? null;
            unset($_SESSION['_flash'][$key]);
            return $out;
        }

        $_SESSION['_flash'][$key] = $value;
        return null;
    }

    protected static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        // Behind a load balancer / CDN.
        if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
            return true;
        }

        return (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    }
}

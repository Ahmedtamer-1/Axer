<?php

namespace Axer\Core;

/**
 * Single source of truth for CSRF tokens.
 *
 * App::run(), CsrfMiddleware and ThemeService each used to grow their own
 * copy of this logic, and they disagreed about whether a missing token was
 * an error. They all call in here now.
 */
class Csrf
{
    public const SESSION_KEY = 'csrf_token';

    public static function token(): string
    {
        Session::start();

        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Issue a fresh token. Called after login/logout so a token captured
     * before a privilege change cannot be replayed after it.
     */
    public static function rotate(): string
    {
        Session::start();
        $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));

        return $_SESSION[self::SESSION_KEY];
    }

    /**
     * Constant-time comparison against the session token.
     *
     * hash_equals() raises a TypeError on null in PHP 8, so the candidate is
     * coerced to string before comparing.
     */
    public static function check(?string $candidate): bool
    {
        $expected = self::token();

        if (!is_string($candidate) || $candidate === '') {
            return false;
        }

        return hash_equals($expected, $candidate);
    }

    /**
     * Pull the token from wherever the client put it.
     */
    public static function fromRequest(Request $request): ?string
    {
        $token = $request->post('_csrf')
            ?? $request->header('X-CSRF-TOKEN')
            ?? $request->json('_csrf');

        return is_string($token) ? $token : null;
    }

    /**
     * Ready-made hidden input for forms.
     */
    public static function field(): string
    {
        return '<input type="hidden" name="_csrf" value="'
            . htmlspecialchars(self::token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

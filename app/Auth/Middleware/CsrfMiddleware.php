<?php

namespace Lume\Auth\Middleware;

use Lume\Core\Request;
use Lume\Core\Response;

class CsrfMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Generate token if not exists
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        // Verify on state-changing methods
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            // Check headers or post body
            $token = $request->post('_csrf') ?: $request->header('X-CSRF-TOKEN') ?: $request->json('_csrf');
            
            if (!$token || !hash_equals($_SESSION['csrf_token'], $token)) {
                return new Response('Invalid CSRF token', 403);
            }
        }

        return $next($request);
    }

    public static function getToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

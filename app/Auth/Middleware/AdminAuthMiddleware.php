<?php

namespace Axer\Auth\Middleware;

use Axer\Auth\Roles;
use Axer\Core\Middleware;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;

/**
 * Route-level guard for the /admin group.
 *
 * Previously the only gate was AdminController::checkAuth(), which every
 * controller method had to remember to call by hand (30 call sites). A new
 * method that forgot the call was silently public. This attaches to the
 * whole admin route group instead, so a forgotten call site is redundant
 * rather than a hole — checkAuth() stays in place as a cheap second check.
 *
 * Also enforces a minimum role tier, which nothing in the codebase did
 * before: `admin` and `superadmin` were previously interchangeable.
 */
class AdminAuthMiddleware implements Middleware
{
    public function __construct(private readonly string $minRole = 'admin')
    {
    }

    public function handle(Request $request, callable $next): Response
    {
        Session::start();

        if (!isset($_SESSION['admin_user'])) {
            $intended = $request->uri();

            if ($request->method() === 'GET' && $intended !== '/admin/login') {
                Session::put('intended_url', $intended);
            }

            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'message' => 'Your session has expired.'], 401);
            }

            return Response::redirect('/admin/login');
        }

        if (!Roles::atLeast($this->minRole)) {
            if ($request->expectsJson()) {
                return Response::json(['success' => false, 'message' => 'You do not have permission to do that.'], 403);
            }

            return new Response('403 Forbidden — you do not have permission to view this page.', 403);
        }

        return $next($request);
    }
}

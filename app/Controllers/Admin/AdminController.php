<?php

namespace Axer\Controllers\Admin;

use Axer\Auth\Roles;
use Axer\Core\Controller;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;

class AdminController extends Controller
{
    public function __construct()
    {
        Session::start();
    }

    /**
     * Redirect anonymous visitors to the login screen.
     *
     * This used to call header() + exit directly, which bypassed the
     * response pipeline entirely: no security headers, no output-buffer
     * cleanup, and untestable. It now throws a control-flow exception that
     * the base controller turns into a real redirect Response.
     *
     * The route group now also carries \Axer\Auth\Middleware\AdminAuthMiddleware,
     * which performs this same check before the controller is even
     * constructed. This call stays in place as a cheap second layer — if a
     * future route is ever registered without the middleware, this is
     * still here to catch it.
     */
    protected function checkAuth(Request $request, string $minRole = 'admin'): void
    {
        if (isset($_SESSION['admin_user']) && Roles::atLeast($minRole)) {
            return;
        }

        if (isset($_SESSION['admin_user'])) {
            // Logged in, but the wrong role — not a login problem.
            if ($request->expectsJson()) {
                throw new HttpResponseException(
                    Response::json(['success' => false, 'message' => 'You do not have permission to do that.'], 403)
                );
            }

            throw new HttpResponseException(new Response('403 Forbidden — you do not have permission to view this page.', 403));
        }

        // Send the visitor back where they were headed after logging in.
        $intended = $request->uri();

        if ($request->method() === 'GET' && $intended !== '/admin/login') {
            Session::put('intended_url', $intended);
        }

        if ($request->expectsJson()) {
            throw new HttpResponseException(
                Response::json(['success' => false, 'message' => 'Your session has expired.'], 401)
            );
        }

        throw new HttpResponseException(Response::redirect('/admin/login'));
    }

    /**
     * Render an admin view inside the shell layout.
     *
     * The view body and the layout are rendered in separate isolated
     * scopes. Previously both used extract($data) in the same scope as the
     * method's own locals ($view, $file, $content, $layoutFile), so a view
     * variable named "content" or "file" silently broke rendering — and
     * $data['view'] was injected on every call, shadowing any real "view"
     * key a page wanted to pass.
     */
    protected function renderAdmin(string $view, array $data = [], bool $useLayout = true): Response
    {
        $viewFile = $this->resolveView($view);

        if ($viewFile === null) {
            return new Response('Admin view not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8'), 404);
        }

        $body = $this->renderInIsolation($viewFile, $data);

        if (!$useLayout) {
            return new Response($body);
        }

        $layoutFile = BASE_PATH . '/admin/views/layouts/main.php';

        if (!is_file($layoutFile)) {
            return new Response($body);
        }

        return new Response($this->renderInIsolation($layoutFile, [
            'content' => $body,
            'title' => $data['title'] ?? null,
            'view' => $view,
        ]));
    }

    /**
     * Resolve a view path, refusing anything that could escape the views
     * directory.
     */
    protected function resolveView(string $view): ?string
    {
        if (str_contains($view, '..') || str_contains($view, "\0")) {
            return null;
        }

        $file = BASE_PATH . '/admin/views/' . $view . '.php';

        return is_file($file) ? $file : null;
    }

    /**
     * Execute a PHP view with $data in scope and nothing else the view
     * could accidentally collide with.
     */
    protected function renderInIsolation(string $__axer_file, array $__axer_data): string
    {
        $render = static function () use ($__axer_file, $__axer_data) {
            extract($__axer_data, EXTR_SKIP);

            ob_start();

            try {
                require $__axer_file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return ob_get_clean();
        };

        return $render();
    }
}

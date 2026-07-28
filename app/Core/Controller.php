<?php

namespace Axer\Core;

class Controller
{
    protected function json($data, int $status = 200, array $headers = []): Response
    {
        return Response::json($data, $status, $headers);
    }

    protected function redirect(string $url, int $status = 302): Response
    {
        return Response::redirect($url, $status);
    }

    /**
     * Render a plain PHP view.
     *
     * Concrete controllers (admin, storefront) override this with their
     * own layout-aware version, so this base implementation only exists as
     * a safety net for a controller that does not — it is written to the
     * same standard as the overrides rather than left as dead code:
     * isolated scope so a view variable cannot collide with $file or
     * $content, and a traversal guard on the view name.
     */
    protected function render(string $view, array $data = [], int $status = 200): Response
    {
        if (str_contains($view, '..') || str_contains($view, "\0")) {
            return new Response('Invalid view name.', 400);
        }

        $file = BASE_PATH . '/admin/views/' . $view . '.php';

        if (!is_file($file)) {
            return new Response('View not found: ' . htmlspecialchars($view, ENT_QUOTES, 'UTF-8'), 404);
        }

        $render = static function () use ($file, $data) {
            extract($data, EXTR_SKIP);

            ob_start();

            try {
                require $file;
            } catch (\Throwable $e) {
                ob_end_clean();
                throw $e;
            }

            return ob_get_clean();
        };

        return new Response($render(), $status);
    }
}

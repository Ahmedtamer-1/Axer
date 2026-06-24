<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Controller;
use Axer\Core\Response;
use Axer\Core\Request;

class AdminController extends Controller
{
    public function __construct()
    {
        // Simple session start for admin
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    protected function checkAuth(Request $request): void
    {
        // Placeholder check: Redirect to login if not authenticated as admin
        if (!isset($_SESSION['admin_user'])) {
            // For now, let's auto-login a dummy admin if in debug mode
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $_SESSION['admin_user'] = [
                    'id' => 1,
                    'email' => 'admin@axer.com',
                    'name' => 'Axer Administrator'
                ];
            } else {
                header('Location: /admin/login');
                exit;
            }
        }
    }

    protected function renderAdmin(string $view, array $data = [], bool $useLayout = true): Response
    {
        // Add layout wrapper
        $data['view'] = $view;
        
        extract($data);
        ob_start();
        $file = BASE_PATH . '/admin/views/' . $view . '.php';
        if (file_exists($file)) {
            require $file;
        } else {
            echo "Admin View not found: " . htmlspecialchars($view);
        }
        $content = ob_get_clean();

        if (!$useLayout) {
            return new Response($content);
        }

        // Render standard main layout
        ob_start();
        $layoutFile = BASE_PATH . '/admin/views/layouts/main.php';
        if (file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content; // fallback
        }
        $html = ob_get_clean();

        return new Response($html);
    }
}

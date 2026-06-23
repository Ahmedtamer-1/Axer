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
    
    protected function render(string $view, array $data = [], int $status = 200): Response
    {
        // For phase 1, a simple extract/include. 
        // In phase 3 this will be replaced/complemented by LumeScript.
        extract($data);
        ob_start();
        $file = BASE_PATH . '/admin/views/' . $view . '.php';
        if (file_exists($file)) {
            require $file;
        }
        $content = ob_get_clean();
        
        return new Response($content, $status);
    }
}

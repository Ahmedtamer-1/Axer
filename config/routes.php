<?php

use Axer\Core\Router;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Services\ThemeService;
use Axer\Services\PixelService;
use Axer\Template\Engine;

/** @var Router $router */

// Admin Redirect Route
$router->get('/admin', function (Request $request) {
    return new Response('', 302, ['Location' => '/admin/dashboard']);
});

// Helper function to render storefront pages dynamically
function renderStorefrontPage(Request $request, string $slug): Response {
    try {
        $page = QueryBuilder::table('pages')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();
            
        if (!$page) {
            return new Response('404 Page Not Found', 404);
        }
        
        $theme = ThemeService::getActiveTheme();
        $themeSlug = $theme ? $theme['slug'] : 'default';
        $themePath = BASE_PATH . '/content/themes/' . $themeSlug;
        
        $pixelService = new PixelService();
        $headScripts = $pixelService->getClientHeadScripts();
        $footerScripts = $pixelService->getClientFooterScripts();
        
        $content = '';
        $builderData = json_decode($page['builder_data'] ?? '[]', true);
        
        if (is_array($builderData) && !empty($builderData)) {
            foreach ($builderData as $block) {
                $type = $block['type'] ?? '';
                $settings = $block['settings'] ?? [];
                
                $sectionFile = $themePath . "/sections/{$type}.lume";
                if (file_exists($sectionFile)) {
                    $engine = new Engine([$themePath], BASE_PATH . '/storage/cache');
                    $engine->addGlobal('settings', $theme['settings'] ?? []);
                    $content .= $engine->render("sections/{$type}", $settings);
                } else {
                    // Fallback to basic HTML if block is standard html
                    if ($type === 'hero') {
                        $content .= "<div class='section hero-section' style='background-color: " . ($settings['bg_color'] ?? '#6366f1') . "; color: " . ($settings['text_color'] ?? '#ffffff') . ";'>";
                        $content .= "<h1>" . htmlspecialchars($settings['title'] ?? '') . "</h1>";
                        $content .= "<p>" . htmlspecialchars($settings['subtitle'] ?? '') . "</p>";
                        if (!empty($settings['button_text'])) {
                            $content .= "<a href='" . htmlspecialchars($settings['button_url'] ?? '#') . "' class='btn' style='background-color: " . ($settings['text_color'] ?? '#ffffff') . "; color: " . ($settings['bg_color'] ?? '#6366f1') . ";'>" . htmlspecialchars($settings['button_text']) . "</a>";
                        }
                        $content .= "</div>";
                    } elseif ($type === 'rich-text') {
                        $content .= "<div class='section rich-text-section' style='text-align: " . ($settings['align'] ?? 'center') . ";'>";
                        if (!empty($settings['title'])) {
                            $content .= "<h2 style='font-size: 2rem; margin-bottom: 1rem;'>" . htmlspecialchars($settings['title']) . "</h2>";
                        }
                        $content .= "<p style='font-size: 1.125rem; line-height: 1.7; color: #475569;'>" . nl2br(htmlspecialchars($settings['content'] ?? '')) . "</p>";
                        $content .= "</div>";
                    } else {
                        $content .= $page['content'] ?? '';
                    }
                }
            }
        } else {
            $content = $page['content'] ?? '';
        }
        
        $engine = new Engine([$themePath], BASE_PATH . '/storage/cache');
        $engine->addGlobal('page_title', $page['title'] . ' - ' . ($theme['settings']['store_name'] ?? 'Lume Store'));
        $engine->addGlobal('settings', $theme['settings'] ?? []);
        $engine->addGlobal('head_scripts', $headScripts);
        $engine->addGlobal('footer_scripts', $footerScripts);
        $engine->addGlobal('content', $content);
        
        $html = $engine->render('layouts/theme');
        return new Response($html);
        
    } catch (\Exception $e) {
        return new Response("Storefront Error: " . $e->getMessage(), 500);
    }
}

// Checkout & Payment Routes
$router->post('/checkout/process', [\Axer\Controllers\Storefront\CheckoutController::class, 'process']);
$router->post('/checkout/callback', [\Axer\Controllers\Storefront\CheckoutController::class, 'callback']);
$router->get('/checkout/callback', [\Axer\Controllers\Storefront\CheckoutController::class, 'callback']);

// Storefront Products Routes
$router->get('/products', [\Axer\Controllers\Storefront\ProductController::class, 'index']);
$router->get('/products/{slug}', [\Axer\Controllers\Storefront\ProductController::class, 'show']);

// Storefront Home Page Route
$router->get('/', function (Request $request) {
    return renderStorefrontPage($request, 'home');
});

// Storefront Custom Page Route Wildcard
$router->get('/{slug}', function (Request $request, string $slug) {
    return renderStorefrontPage($request, $slug);
});


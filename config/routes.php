<?php

use Axer\Core\Router;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Services\ThemeService;
use Axer\Services\PixelService;
use Axer\Controllers\Storefront\ProductController;
use Axer\Controllers\Storefront\CheckoutController;
use Axer\Controllers\Storefront\CartController;

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
                
                // Override legacy hero banner values if old text
                if ($type === 'hero' && isset($settings['title']) && str_contains($settings['title'], 'Lume')) {
                    $settings['title'] = str_replace('Lume', 'Axer', $settings['title']);
                }

                $sectionFile = $themePath . "/sections/{$type}.lume";
                if (file_exists($sectionFile)) {
                    $engine = new \Axer\Template\Engine([$themePath], BASE_PATH . '/storage/cache/templates');
                    $engine->addGlobal('settings', $theme['settings'] ?? []);
                    $content .= $engine->render("sections/{$type}", $settings);
                } else {
                    if ($type === 'hero') {
                        $content .= "<div class='hero-section' style='background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: #ffffff; text-align: center; padding: 5rem 2rem; border-radius: 1rem; margin-bottom: 3rem;'>";
                        $content .= "<h1 style='font-size: 3.5rem; font-weight: 800; margin-bottom: 1rem;'>" . htmlspecialchars($settings['title'] ?? 'Welcome to Axer Storefront') . "</h1>";
                        $content .= "<p style='font-size: 1.25rem; opacity: 0.9; max-width: 600px; margin: 0 auto 2rem;'>" . htmlspecialchars($settings['subtitle'] ?? 'Fully customizable headless e-commerce CMS') . "</p>";
                        $content .= "<a href='/shop' class='btn btn-primary' style='display: inline-block; padding: 0.875rem 2.25rem; background: #ffffff; color: #4f46e5; font-weight: 700; text-decoration: none; border-radius: 0.5rem;'>Shop Collection</a>";
                        $content .= "</div>";
                    } elseif ($type === 'rich-text') {
                        $content .= "<div class='section rich-text-section' style='text-align: " . ($settings['align'] ?? 'center') . "; padding: 3rem 1rem;'>";
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
        
        $html = ThemeService::render('layouts/theme', [
            'page_title' => $page['title'] . ' - ' . ($theme['settings']['store_name'] ?? 'Axer Store'),
            'content' => $content,
            'head_scripts' => $headScripts,
            'footer_scripts' => $footerScripts
        ]);

        return new Response($html);
        
    } catch (\Exception $e) {
        return new Response("Storefront Error: " . $e->getMessage(), 500);
    }
}

// Cart Routes
$router->get('/cart', [CartController::class, 'index']);
$router->post('/cart/add', [CartController::class, 'add']);
$router->post('/cart/update', [CartController::class, 'update']);
$router->post('/cart/remove', [CartController::class, 'remove']);

// Checkout & Payment Routes
$router->post('/checkout/process', [CheckoutController::class, 'process']);
$router->post('/checkout/callback', [CheckoutController::class, 'callback']);
$router->get('/checkout/callback', [CheckoutController::class, 'callback']);

// Storefront Products & Shop Routes
$router->get('/products', [ProductController::class, 'index']);
$router->get('/shop', [ProductController::class, 'index']);
$router->get('/products/{slug}', [ProductController::class, 'show']);

// Storefront Home Page Route
$router->get('/', function (Request $request) {
    return renderStorefrontPage($request, 'home');
});

// Storefront Custom Page Route Wildcard
$router->get('/{slug}', function (Request $request, string $slug) {
    return renderStorefrontPage($request, $slug);
});

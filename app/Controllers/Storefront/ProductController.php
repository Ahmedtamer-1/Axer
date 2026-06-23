<?php

namespace Lume\Controllers\Storefront;

use Lume\Core\Request;
use Lume\Core\Response;
use Lume\Database\QueryBuilder;
use Lume\Services\ThemeService;
use Lume\Template\Engine;
use Lume\Services\PixelService;

class ProductController
{
    protected function render(string $template, array $data = []): Response
    {
        $theme = ThemeService::getActiveTheme();
        $themeSlug = $theme ? $theme['slug'] : 'default';
        $themePath = BASE_PATH . '/content/themes/' . $themeSlug;
        
        $pixelService = new PixelService();
        $headScripts = $pixelService->getClientHeadScripts();
        $footerScripts = $pixelService->getClientFooterScripts();
        
        $engine = new Engine([$themePath], BASE_PATH . '/storage/cache');
        
        // Add global settings
        $engine->addGlobal('settings', $theme['settings'] ?? []);
        $engine->addGlobal('head_scripts', $headScripts);
        $engine->addGlobal('footer_scripts', $footerScripts);
        
        // Render inner content
        $content = $engine->render($template, $data);
        
        // Pass content to layout
        $engine->addGlobal('content', $content);
        $engine->addGlobal('page_title', $data['page_title'] ?? 'Products');
        
        return new Response($engine->render('layouts/theme'));
    }

    public function index(Request $request): Response
    {
        $products = QueryBuilder::table('products')
            ->where('status', 'active')
            ->orderBy('sort_order', 'ASC')
            ->get();
            
        // Format data for the simplistic Lume template engine
        foreach ($products as &$product) {
            $image = QueryBuilder::table('product_images')
                ->where('product_id', $product['id'])
                ->orderBy('sort_order', 'ASC')
                ->first();
            $product['image_url'] = $image ? $image['url'] : 'https://via.placeholder.com/400';
            $product['price'] = number_format((float)$product['price'], 2);
            $product['description'] = strip_tags(substr($product['description'] ?? '', 0, 60)) . '...';
        }

        return $this->render('products/index', [
            'page_title' => 'All Products',
            'products' => $products,
            'products_empty' => empty($products)
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $product = QueryBuilder::table('products')
            ->where('slug', $slug)
            ->where('status', 'active')
            ->first();

        if (!$product) {
            return new Response('Product Not Found', 404);
        }

        // Format product data
        $product['formatted_price'] = number_format((float)$product['price'], 2);
        $product['formatted_description'] = nl2br(htmlspecialchars($product['description'] ?? ''));

        // Fetch variants
        $variants = QueryBuilder::table('product_variants')
            ->where('product_id', $product['id'])
            ->orderBy('sort_order', 'ASC')
            ->get();
            
        // Format variants
        foreach ($variants as &$v) {
            $v['display_name'] = htmlspecialchars($v['color_name'] ?: $v['size']);
        }

        // Fetch images
        $images = QueryBuilder::table('product_images')
            ->where('product_id', $product['id'])
            ->orderBy('sort_order', 'ASC')
            ->get();

        return $this->render('products/show', [
            'page_title' => $product['name'],
            'product' => $product,
            'variants' => $variants,
            'has_variants' => count($variants) > 0,
            'images' => $images,
            'images_json' => json_encode($images)
        ]);
    }
}

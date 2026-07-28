<?php

namespace Axer\Controllers\Storefront;

use Axer\Core\Controller;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Services\ThemeService;

class ProductController extends Controller
{
    protected function render(string $view, array $data = [], int $status = 200): Response
    {
        $html = ThemeService::render($view, $data);
        return new Response($html, $status);
    }

    public function index(Request $request): Response
    {
        $products = QueryBuilder::table('products')
            ->where('status', 'active')
            ->orderBy('sort_order', 'ASC')
            ->get();
            
        foreach ($products as &$product) {
            $image = QueryBuilder::table('product_images')
                ->where('product_id', $product['id'])
                ->orderBy('sort_order', 'ASC')
                ->first();
            $product['image_url'] = $image ? $image['url'] : 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&auto=format&fit=crop&q=80';
            $product['price'] = number_format((float)$product['price'], 2);
            $product['description'] = strip_tags(substr($product['description'] ?? '', 0, 80)) . '...';
        }

        return $this->render('products/index', [
            'page_title' => 'Shop All Products',
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

        $product['formatted_price'] = number_format((float)$product['price'], 2);
        $product['formatted_description'] = nl2br(htmlspecialchars($product['description'] ?? ''));

        $variants = QueryBuilder::table('product_variants')
            ->where('product_id', $product['id'])
            ->orderBy('sort_order', 'ASC')
            ->get();
            
        $optionsSchema = json_decode($product['options_schema'] ?? '[]', true) ?: [];
        
        foreach ($variants as &$v) {
            $labels = array_filter([$v['option1_value'], $v['option2_value'], $v['option3_value']]);
            $v['display_name'] = htmlspecialchars(implode(' / ', $labels));
            if (empty($labels) && (!empty($v['color_name']) || !empty($v['size']))) {
                $v['display_name'] = htmlspecialchars($v['color_name'] ?: $v['size']);
            }
        }

        $images = QueryBuilder::table('product_images')
            ->where('product_id', $product['id'])
            ->orderBy('sort_order', 'ASC')
            ->get();

        return $this->render('products/show', [
            'page_title' => $product['name'],
            'product' => $product,
            'options' => $optionsSchema,
            'variants' => $variants,
            'variants_json' => json_encode($variants),
            'has_variants' => count($variants) > 0,
            'images' => $images,
            'images_json' => json_encode($images)
        ]);
    }
}

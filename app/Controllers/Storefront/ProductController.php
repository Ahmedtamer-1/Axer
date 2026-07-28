<?php

namespace Axer\Controllers\Storefront;

use Axer\Core\Controller;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Services\CartService;
use Axer\Services\ThemeService;
use Axer\Support\Str;

class ProductController extends Controller
{
    protected function render(string $view, array $data = [], int $status = 200): Response
    {
        return new Response(ThemeService::render($view, $data), $status);
    }

    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->get('page', 1));
        $perPage = 24;

        $query = QueryBuilder::table('products')
            ->where('status', 'active')
            ->orderBy('sort_order', 'ASC')
            ->orderBy('id', 'DESC');

        // Optional search box on the shop page.
        $search = $request->string('q');

        if ($search !== '') {
            $query->whereLike('name', $search);
        }

        $result = $query->paginate($perPage, $page);
        $products = $result['data'];

        // One query for every image instead of one query per product. A
        // 24-product grid previously issued 25 queries.
        $images = CartService::primaryImagesFor(array_column($products, 'id'));

        foreach ($products as $index => $product) {
            $id = (int) $product['id'];

            $products[$index]['image_url'] = $images[$id] ?? CartService::placeholderImage();
            $products[$index]['price'] = number_format((float) $product['price'], 2);
            $products[$index]['compare_price'] = $product['compare_price'] !== null
                ? number_format((float) $product['compare_price'], 2)
                : null;
            $products[$index]['on_sale'] = $product['compare_price'] !== null
                && (float) $product['compare_price'] > (float) $product['price'];
            // strip_tags before truncating, and truncate on a character
            // boundary: the old substr(…, 0, 80) cut Arabic mid-character
            // and could slice through a tag, leaving broken markup.
            $products[$index]['description'] = Str::excerpt($product['short_description'] ?: $product['description'], 90);
        }

        return $this->render('products/index', [
            'page_title' => $search !== '' ? 'Search: ' . $search : 'Shop All Products',
            'products' => $products,
            'products_empty' => $products === [],
            'search_term' => $search,
            'current_page' => $result['current_page'],
            'last_page' => $result['last_page'],
            'has_more' => $result['has_more'],
            'next_page' => $result['current_page'] + 1,
            'prev_page' => max(1, $result['current_page'] - 1),
            'total_products' => $result['total'],
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $product = QueryBuilder::table('products')
            ->where('slug', $slug)
            ->where('status', 'active')
            ->first();

        if ($product === null) {
            return Response::notFound('We could not find that product.');
        }

        $productId = (int) $product['id'];

        $product['formatted_price'] = number_format((float) $product['price'], 2);
        $product['formatted_compare_price'] = $product['compare_price'] !== null
            ? number_format((float) $product['compare_price'], 2)
            : null;
        $product['on_sale'] = $product['compare_price'] !== null
            && (float) $product['compare_price'] > (float) $product['price'];
        $product['in_stock'] = empty($product['track_stock']) || (int) $product['stock'] > 0;
        $product['formatted_description'] = nl2br(
            htmlspecialchars((string) ($product['description'] ?? ''), ENT_QUOTES, 'UTF-8')
        );

        $variants = QueryBuilder::table('product_variants')
            ->where('product_id', $productId)
            ->orderBy('sort_order', 'ASC')
            ->get();

        foreach ($variants as $index => $variant) {
            $labels = array_filter([
                $variant['option1_value'] ?? null,
                $variant['option2_value'] ?? null,
                $variant['option3_value'] ?? null,
            ], static fn($v) => $v !== null && $v !== '');

            $name = $labels !== []
                ? implode(' / ', $labels)
                : (string) (($variant['color_name'] ?? '') ?: ($variant['size'] ?? ''));

            // Store the raw label. Escaping here then feeding the same array
            // to json_encode double-encoded every option name in the
            // client-side variant picker.
            $variants[$index]['display_name'] = $name;
            $variants[$index]['available'] = (int) ($variant['stock'] ?? 0) > 0;
            $variants[$index]['formatted_price'] = $variant['price_override'] !== null
                ? number_format((float) $variant['price_override'], 2)
                : $product['formatted_price'];
        }

        $images = QueryBuilder::table('product_images')
            ->where('product_id', $productId)
            ->orderBy('is_primary', 'DESC')
            ->orderBy('sort_order', 'ASC')
            ->get();

        if ($images === []) {
            $images = [['url' => CartService::placeholderImage(), 'alt' => $product['name']]];
        }

        // Best-effort view counter; a failure here must not break the page.
        try {
            QueryBuilder::table('products')->where('id', $productId)->increment('views_count');
        } catch (\Throwable $e) {
            // non-critical
        }

        $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

        return $this->render('products/show', [
            'page_title' => $product['seo_title'] ?: $product['name'],
            'meta_description' => Str::excerpt($product['seo_description'] ?: $product['description'], 160),
            'product' => $product,
            'options' => json_decode((string) ($product['options_schema'] ?? '[]'), true) ?: [],
            'variants' => $variants,
            // JSON_HEX_* keeps the payload safe to embed inside a <script>.
            'variants_json' => json_encode($variants, $jsonFlags),
            'has_variants' => $variants !== [],
            'images' => $images,
            'images_json' => json_encode($images, $jsonFlags),
        ]);
    }
}

<?php

namespace Axer\Controllers\Storefront;

use Axer\Core\Controller;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Services\ThemeService;

class CartController extends Controller
{
    public function index(Request $request): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $cart = $_SESSION['cart'] ?? [];
        $items = [];
        $total = 0;

        foreach ($cart as $key => $item) {
            $product = QueryBuilder::table('products')->where('id', $item['product_id'])->first();
            if ($product) {
                $image = QueryBuilder::table('product_images')
                    ->where('product_id', $product['id'])
                    ->first();

                $variant = null;
                if (!empty($item['variant_id'])) {
                    $variant = QueryBuilder::table('product_variants')->where('id', $item['variant_id'])->first();
                }

                $price = $variant && $variant['price_override'] ? (float)$variant['price_override'] : (float)$product['price'];
                $itemTotal = $price * $item['quantity'];
                $total += $itemTotal;

                $items[] = [
                    'key' => $key,
                    'product_id' => $product['id'],
                    'name' => $product['name'],
                    'slug' => $product['slug'],
                    'variant_name' => $variant ? ($variant['display_name'] ?? $variant['color_name'] ?? $variant['size']) : null,
                    'price' => $price,
                    'quantity' => $item['quantity'],
                    'total' => $itemTotal,
                    'image_url' => $image ? $image['url'] : 'https://via.placeholder.com/150'
                ];
            }
        }

        $html = ThemeService::render('cart', [
            'page_title' => 'Shopping Cart',
            'cart_items' => $items,
            'cart_total' => number_format($total, 2),
            'raw_total' => $total,
            'is_empty' => empty($items)
        ]);

        return new Response($html);
    }

    public function add(Request $request): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $productId = (int)($request->post('product_id') ?? $request->json('product_id'));
        $variantId = $request->post('variant_id') ?? $request->json('variant_id');
        $quantity = max(1, (int)($request->post('quantity') ?? $request->json('quantity') ?? 1));

        if (!$productId) {
            return new Response('Invalid product', 400);
        }

        $key = $productId . '_' . ($variantId ?? 0);

        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        if (isset($_SESSION['cart'][$key])) {
            $_SESSION['cart'][$key]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$key] = [
                'product_id' => $productId,
                'variant_id' => $variantId,
                'quantity' => $quantity
            ];
        }

        if ($request->header('Accept') === 'application/json' || $request->json('product_id')) {
            $cartCount = array_sum(array_column($_SESSION['cart'], 'quantity'));
            return Response::json(['success' => true, 'cart_count' => $cartCount]);
        }

        return $this->redirect('/cart');
    }

    public function update(Request $request): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $key = $request->post('key');
        $quantity = (int)$request->post('quantity');

        if ($key && isset($_SESSION['cart'][$key])) {
            if ($quantity <= 0) {
                unset($_SESSION['cart'][$key]);
            } else {
                $_SESSION['cart'][$key]['quantity'] = $quantity;
            }
        }

        return $this->redirect('/cart');
    }

    public function remove(Request $request): Response
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $key = $request->post('key');
        if ($key && isset($_SESSION['cart'][$key])) {
            unset($_SESSION['cart'][$key]);
        }

        return $this->redirect('/cart');
    }
}

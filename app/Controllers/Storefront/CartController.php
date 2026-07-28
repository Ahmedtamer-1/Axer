<?php

namespace Axer\Controllers\Storefront;

use Axer\Core\Controller;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;
use Axer\Services\CartService;
use Axer\Services\ThemeService;

class CartController extends Controller
{
    public function index(Request $request): Response
    {
        Session::start();

        // CartService batches every lookup. This method previously issued
        // three queries per line item (product, image, variant), so a
        // ten-item cart cost thirty-one round trips.
        $cart = CartService::resolve();

        $subtotal = $cart['subtotal'];
        $shipping = $subtotal > 0 && $subtotal < 1000 ? 60.0 : 0.0;
        $total = $subtotal + $shipping;

        $html = ThemeService::renderPage('cart', [
            'page_title' => 'Shopping Cart',
            'cart_items' => $cart['items'],
            'cart_count' => $cart['count'],
            'cart_subtotal' => number_format($subtotal, 2),
            'cart_shipping' => $shipping > 0 ? number_format($shipping, 2) : 'Free',
            'cart_total' => number_format($total, 2),
            'raw_total' => $total,
            'free_shipping_remaining' => $subtotal > 0 && $subtotal < 1000
                ? number_format(1000 - $subtotal, 2)
                : null,
            'is_empty' => $cart['items'] === [],
        ]);

        // A cart is per-visitor and changes constantly — never let a proxy
        // or the browser serve a stale copy of somebody's basket.
        return (new Response($html))->noCache();
    }

    public function add(Request $request): Response
    {
        Session::start();

        $productId = (int) $request->input('product_id', 0);
        $variantId = $request->input('variant_id');
        $quantity = (int) $request->input('quantity', 1);

        if ($productId <= 0) {
            return $this->respond($request, false, 'Please choose a product first.', 400);
        }

        // Existence, ownership and stock are all validated in the service.
        // None of it was checked before: any integer could be added to the
        // cart, including ids of deleted or unpublished products.
        $result = CartService::add($productId, $variantId, $quantity);

        return $this->respond(
            $request,
            $result['ok'],
            $result['message'],
            $result['ok'] ? 200 : 422,
            $result['count']
        );
    }

    public function update(Request $request): Response
    {
        Session::start();

        $key = (string) $request->input('key', '');
        $quantity = (int) $request->input('quantity', 0);

        if ($key !== '') {
            CartService::updateQuantity($key, $quantity);
        }

        return $this->respond($request, true, 'Cart updated.', 200, CartService::count());
    }

    public function remove(Request $request): Response
    {
        Session::start();

        $key = (string) $request->input('key', '');

        if ($key !== '') {
            CartService::remove($key);
        }

        return $this->respond($request, true, 'Item removed.', 200, CartService::count());
    }

    /**
     * Answer JSON to background requests and redirect real form posts.
     *
     * The old detection was `$request->header('Accept') === 'application/json'
     * || $request->json('product_id')`, an exact string match that browsers
     * never send, so AJAX callers got a 302 to /cart instead of a payload.
     */
    protected function respond(
        Request $request,
        bool $ok,
        string $message,
        int $status = 200,
        ?int $count = null
    ): Response {
        $count ??= CartService::count();

        if ($request->expectsJson()) {
            return Response::json([
                'success' => $ok,
                'message' => $message,
                'cart_count' => $count,
            ], $status);
        }

        if (!$ok) {
            Session::flash('error', $message);
        }

        return $this->redirect('/cart');
    }
}

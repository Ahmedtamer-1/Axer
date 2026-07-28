<?php

namespace Axer\Controllers\Api;

use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class CartController extends ApiController
{
    private const MAX_QUANTITY = 99;

    public function index(Request $request): Response
    {
        $sessionId = (string) ($request->header('X-Session-Id') ?? '');

        if ($sessionId === '') {
            return $this->error('Session ID required', 400);
        }

        $items = QueryBuilder::table('cart_items')
            ->leftJoin('products', 'cart_items.product_id', '=', 'products.id')
            ->select('cart_items.*', 'products.name', 'products.price', 'products.image')
            ->where('cart_items.session_id', $sessionId)
            ->get();

        return $this->success(['items' => $items]);
    }

    public function add(Request $request): Response
    {
        $sessionId = (string) ($request->header('X-Session-Id') ?? '');
        $productId = (int) ($request->json('product_id') ?? 0);
        $quantity = (int) $request->json('quantity', 1);

        if ($sessionId === '' || $productId <= 0) {
            return $this->error('Session ID and product ID are required', 400);
        }

        $quantity = max(1, min(self::MAX_QUANTITY, $quantity));

        // Neither the product's existence nor its status was checked
        // before — any integer could be written into a cart, including
        // the id of a deleted or draft product.
        $product = QueryBuilder::table('products')
            ->where('id', $productId)
            ->where('status', 'active')
            ->first();

        if ($product === null) {
            return $this->error('That product is not available', 404);
        }

        try {
            QueryBuilder::transaction(function () use ($sessionId, $productId, $quantity) {
                $existing = QueryBuilder::table('cart_items')
                    ->where('session_id', $sessionId)
                    ->where('product_id', $productId)
                    ->first();

                if ($existing !== null) {
                    QueryBuilder::table('cart_items')
                        ->where('id', $existing['id'])
                        ->update(['quantity' => min(self::MAX_QUANTITY, $existing['quantity'] + $quantity)]);

                    return;
                }

                QueryBuilder::table('cart_items')->insert([
                    'session_id' => $sessionId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                ]);
            });
        } catch (\Throwable $e) {
            Logger::exception($e);

            return $this->error('Could not add this item to the cart', 500);
        }

        return $this->success(null, 'Item added to cart');
    }
}

<?php

namespace Axer\Controllers\Api;

use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Support\Str;

class OrderController extends ApiController
{
    public function create(Request $request): Response
    {
        // Same bug as profile(): this checked $_SESSION['user_id'], which
        // is never set by the token-based auth this endpoint sits behind
        // (AuthMiddleware attaches the user to the Request instead), so
        // every call failed with 401 regardless of a valid token.
        $userId = $request->userId();

        if ($userId === null) {
            return $this->error('Unauthorized', 401);
        }

        $items = $request->json('items', []);

        if (!is_array($items) || $items === []) {
            return $this->error('Order items cannot be empty', 400);
        }

        $address = $request->json('shipping_address');

        if (!is_array($address) || empty($address['address']) || empty($address['city'])) {
            return $this->error('A shipping address is required', 400);
        }

        // Prices and product names are read from the database, never from
        // the request — the previous version summed
        // $item['price'] * $item['quantity'] straight from client JSON,
        // so a buyer could name their own price for any order.
        $productIds = array_values(array_unique(array_filter(
            array_map(static fn($i) => (int) ($i['product_id'] ?? 0), $items)
        )));

        if ($productIds === []) {
            return $this->error('Order items cannot be empty', 400);
        }

        $products = QueryBuilder::table('products')
            ->whereIn('id', $productIds)
            ->where('status', 'active')
            ->keyBy('id');

        $lines = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $product = $products[$productId] ?? null;

            if ($product === null) {
                continue;
            }

            $quantity = max(1, min(99, (int) ($item['quantity'] ?? 1)));
            $price = (float) $product['price'];
            $lineTotal = round($price * $quantity, 2);
            $subtotal += $lineTotal;

            $lines[] = [
                'product_id' => $productId,
                'product_name' => $product['name'],
                'sku' => $product['sku'] ?? null,
                'price' => $price,
                'quantity' => $quantity,
                'total' => $lineTotal,
            ];
        }

        if ($lines === []) {
            return $this->error('None of the requested products are available', 422);
        }

        $user = QueryBuilder::table('users')->where('id', $userId)->first();
        $subtotal = round($subtotal, 2);

        try {
            $orderId = QueryBuilder::transaction(function () use ($lines, $subtotal, $address, $user, $userId) {
                // The previous insert used a `total_amount` column that
                // does not exist on this table and omitted `order_number`
                // and `shipping_address`, both NOT NULL with no default —
                // this call has never been able to succeed.
                $orderId = QueryBuilder::table('orders')->insertGetId([
                    'order_number' => Str::orderNumber(),
                    'user_id' => $userId,
                    'status' => 'pending',
                    'payment_status' => 'unpaid',
                    'payment_method' => 'cod',
                    'subtotal' => $subtotal,
                    'shipping_cost' => 0,
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total' => $subtotal,
                    'currency' => 'EGP',
                    'customer_email' => $user['email'] ?? '',
                    'customer_phone' => $address['phone'] ?? ($user['phone'] ?? ''),
                    'customer_name' => trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'Customer',
                    'shipping_address' => json_encode([
                        'address' => $address['address'],
                        'city' => $address['city'],
                        'country' => $address['country'] ?? 'EG',
                    ], JSON_UNESCAPED_UNICODE),
                ]);

                if ($orderId <= 0) {
                    throw new \RuntimeException('Could not create the order record.');
                }

                $rows = array_map(static fn(array $line) => [
                    'order_id' => $orderId,
                    'product_id' => $line['product_id'],
                    'product_name' => $line['product_name'],
                    'sku' => $line['sku'],
                    'price' => $line['price'],
                    'quantity' => $line['quantity'],
                    'total' => $line['total'],
                ], $lines);

                QueryBuilder::table('order_items')->insertMany($rows);

                return $orderId;
            });
        } catch (\Throwable $e) {
            Logger::exception($e);

            return $this->error('Failed to create order', 500);
        }

        return $this->success(['order_id' => $orderId, 'total' => $subtotal], 'Order created successfully', 201);
    }
}

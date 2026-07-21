<?php

namespace Axer\Controllers\Api;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;

class OrderController extends ApiController
{
    public function create(Request $request): Response
    {
        $userId = $_SESSION['user_id'] ?? null;
        if (!$userId) {
            return $this->error('Unauthorized', 401);
        }

        $items = $request->json('items', []);
        if (empty($items)) {
            return $this->error('Order items cannot be empty', 400);
        }

        try {
            $total = 0;
            foreach ($items as $item) {
                $total += ($item['price'] ?? 0) * ($item['quantity'] ?? 1);
            }

            $orderId = QueryBuilder::table('orders')->insert([
                'user_id' => $userId,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'total_amount' => $total,
                'created_at' => date('Y-m-d H:i:s')
            ]);

            return $this->success(['order_id' => $orderId], 'Order created successfully', 201);
        } catch (\Exception $e) {
            return $this->error('Failed to create order: ' . $e->getMessage(), 500);
        }
    }
}

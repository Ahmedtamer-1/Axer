<?php

namespace Lume\Controllers\Admin;

use Lume\Core\Request;
use Lume\Core\Response;
use Lume\Database\QueryBuilder;

class OrderController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->checkAuth($request);
        
        $orders = [];
        try {
            $orders = QueryBuilder::table('orders')->orderBy('id', 'desc')->get();
        } catch (\Exception $e) {
            // Table might not exist yet
        }

        return $this->renderAdmin('orders/index', [
            'title' => 'Orders',
            'orders' => $orders
        ]);
    }

    public function view(Request $request, string $id): Response
    {
        $this->checkAuth($request);
        
        $order = QueryBuilder::table('orders')->where('id', $id)->first();
        if (!$order) {
            return $this->redirect('/admin/orders');
        }

        // Fetch order items if table exists
        $items = [];
        try {
            $items = QueryBuilder::table('order_items')
                ->select('order_items.*, (SELECT url FROM product_images WHERE product_id = order_items.product_id AND is_primary = 1 LIMIT 1) as product_image')
                ->where('order_id', $id)
                ->get();
        } catch (\Exception $e) {}

        return $this->renderAdmin('orders/view', [
            'title' => 'Order Details #' . $order['order_number'],
            'order' => $order,
            'items' => $items
        ]);
    }

    public function updateStatus(Request $request, string $id): Response
    {
        $this->checkAuth($request);

        if ($request->method() === 'POST') {
            $status = $request->post('status');
            $paymentStatus = $request->post('payment_status');
            $adminNotes = $request->post('admin_notes');

            try {
                $updateData = [];
                if ($status) $updateData['status'] = $status;
                if ($paymentStatus) $updateData['payment_status'] = $paymentStatus;
                if ($adminNotes !== null) $updateData['admin_notes'] = $adminNotes;

                // Handle status specific timestamps
                if ($status === 'shipped') {
                    $updateData['shipped_at'] = date('Y-m-d H:i:s');
                } elseif ($status === 'delivered') {
                    $updateData['delivered_at'] = date('Y-m-d H:i:s');
                } elseif ($status === 'cancelled') {
                    $updateData['cancelled_at'] = date('Y-m-d H:i:s');
                }

                if ($paymentStatus === 'paid') {
                    $updateData['paid_at'] = date('Y-m-d H:i:s');
                }

                QueryBuilder::table('orders')->where('id', $id)->update($updateData);
            } catch (\Exception $e) {
                // Ignore or handle error
            }
        }

        return $this->redirect('/admin/orders/view/' . $id);
    }
}

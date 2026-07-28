<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;
use Axer\Database\QueryBuilder;

class OrderController extends AdminController
{
    /** Values the orders table's ENUM columns actually accept. */
    private const STATUSES = [
        'pending', 'confirmed', 'processing', 'shipped',
        'delivered', 'cancelled', 'refunded',
    ];

    private const PAYMENT_STATUSES = ['unpaid', 'pending', 'paid', 'failed', 'refunded'];

    public function index(Request $request): Response
    {
        $this->checkAuth($request);

        $page = max(1, (int) $request->get('page', 1));
        $status = (string) $request->get('status', '');
        $search = $request->string('q');

        $orders = [];
        $pagination = ['current_page' => 1, 'last_page' => 1, 'total' => 0, 'has_more' => false];

        try {
            $query = QueryBuilder::table('orders')->orderBy('id', 'desc');

            if (in_array($status, self::STATUSES, true)) {
                $query->where('status', $status);
            }

            if ($search !== '') {
                $query->whereGroup(static function (QueryBuilder $q) use ($search) {
                    $q->whereLike('order_number', $search)
                        ->orWhere('customer_email', 'LIKE', '%' . $search . '%');
                });
            }

            // The list used to fetch every order in the table with no
            // limit, which stops working as soon as a store has real
            // volume.
            $result = $query->paginate(30, $page);

            $orders = $result['data'];
            $pagination = $result;
        } catch (\Throwable $e) {
            Logger::warning('Could not load orders: ' . $e->getMessage());
        }

        return $this->renderAdmin('orders/index', [
            'title' => 'Orders',
            'orders' => $orders,
            'pagination' => $pagination,
            'filter_status' => $status,
            'search_term' => $search,
            'statuses' => self::STATUSES,
        ]);
    }

    public function view(Request $request, string $id): Response
    {
        $this->checkAuth($request);

        $order = QueryBuilder::table('orders')->where('id', (int) $id)->first();

        if ($order === null) {
            Session::flash('error', 'That order no longer exists.');

            return $this->redirect('/admin/orders');
        }

        $items = [];

        try {
            $items = QueryBuilder::table('order_items')->where('order_id', (int) $id)->get();

            // order_items already stores the image captured at purchase
            // time, so the correlated subquery this used to run per row was
            // both unnecessary and a snapshot of the *current* image rather
            // than the one the customer bought.
            foreach ($items as $index => $item) {
                $items[$index]['product_image'] = $item['image']
                    ?: \Axer\Services\CartService::placeholderImage();
            }
        } catch (\Throwable $e) {
            Logger::warning('Could not load order items: ' . $e->getMessage());
        }

        $shippingAddress = json_decode((string) ($order['shipping_address'] ?? ''), true);

        return $this->renderAdmin('orders/view', [
            'title' => 'Order ' . ($order['order_number'] ?? $id),
            'order' => $order,
            'items' => $items,
            'shipping_address' => is_array($shippingAddress) ? $shippingAddress : null,
            'statuses' => self::STATUSES,
            'payment_statuses' => self::PAYMENT_STATUSES,
        ]);
    }

    public function updateStatus(Request $request, string $id): Response
    {
        $this->checkAuth($request);

        if ($request->method() !== 'POST') {
            return $this->redirect('/admin/orders/view/' . $id);
        }

        $status = (string) $request->post('status', '');
        $paymentStatus = (string) $request->post('payment_status', '');
        $adminNotes = $request->post('admin_notes');

        $updateData = [];

        // Both columns are ENUMs. Writing an arbitrary POST value into them
        // threw a driver error under the strict SQL mode the connection now
        // sets, and silently stored an empty string before that.
        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $updateData['status'] = $status;

            $timestampColumn = match ($status) {
                'shipped' => 'shipped_at',
                'delivered' => 'delivered_at',
                'cancelled' => 'cancelled_at',
                default => null,
            };

            if ($timestampColumn !== null) {
                $updateData[$timestampColumn] = date('Y-m-d H:i:s');
            }
        }

        if ($paymentStatus !== '' && in_array($paymentStatus, self::PAYMENT_STATUSES, true)) {
            $updateData['payment_status'] = $paymentStatus;

            if ($paymentStatus === 'paid') {
                $updateData['paid_at'] = date('Y-m-d H:i:s');
            }
        }

        if ($adminNotes !== null) {
            $updateData['admin_notes'] = (string) $adminNotes;
        }

        if ($updateData === []) {
            Session::flash('error', 'Nothing to update.');

            return $this->redirect('/admin/orders/view/' . $id);
        }

        try {
            QueryBuilder::table('orders')->where('id', (int) $id)->update($updateData);
            Session::flash('success', 'Order updated.');
        } catch (\Throwable $e) {
            // This was swallowed entirely, so a failed save looked identical
            // to a successful one.
            Logger::exception($e);
            Session::flash('error', 'Could not update the order. Please try again.');
        }

        return $this->redirect('/admin/orders/view/' . $id);
    }
}

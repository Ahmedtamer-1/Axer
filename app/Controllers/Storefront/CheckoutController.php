<?php

namespace Axer\Controllers\Storefront;

use Axer\Core\Controller;
use Axer\Core\Event;
use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;
use Axer\Database\QueryBuilder;
use Axer\Services\CartService;
use Axer\Services\PaymobService;
use Axer\Support\Str;

class CheckoutController extends Controller
{
    protected ?PaymobService $gateway = null;

    /**
     * Credentials are resolved lazily.
     *
     * The constructor used to run a settings query unconditionally, so
     * merely routing to this controller cost a database round-trip even
     * for the GET callback page.
     */
    protected function gateway(): PaymobService
    {
        return $this->gateway ??= PaymobService::fromSettings();
    }

    public function process(Request $request): Response
    {
        if ($request->method() !== 'POST') {
            return new Response('Method Not Allowed', 405);
        }

        Session::start();

        // The amount to charge is computed here from the cart, never taken
        // from the request. It previously came from $request->post('amount'),
        // which let a shopper name their own price for any order.
        $cart = CartService::resolve();

        if ($cart['items'] === []) {
            return $this->redirect('/cart');
        }

        $customer = $this->collectCustomer($request);

        if ($customer['errors'] !== []) {
            Session::flash('error', implode(' ', $customer['errors']));
            return $this->redirect('/cart');
        }

        $subtotal = $cart['subtotal'];
        $shipping = $this->shippingCost($subtotal);
        $total = round($subtotal + $shipping, 2);
        $amountCents = (int) round($total * 100);

        if (!$this->gateway()->isConfigured()) {
            Logger::error('Checkout attempted while Paymob is not configured.');
            Session::flash('error', 'Online payment is not available right now. Please try again later.');

            return $this->redirect('/cart');
        }

        try {
            $orderId = $this->createPendingOrder($cart, $customer, $subtotal, $shipping, $total);
        } catch (\Throwable $e) {
            Logger::exception($e);
            Session::flash('error', 'We could not start your order. Please try again.');

            return $this->redirect('/cart');
        }

        try {
            $session = $this->gateway()->createPaymentSession($amountCents, [
                'first_name' => $customer['first_name'],
                'last_name' => $customer['last_name'],
                'email' => $customer['email'],
                'phone' => $customer['phone'],
                'street' => $customer['address'],
                'city' => $customer['city'],
                'state' => $customer['city'],
            ], $this->gatewayItems($cart['items']));
        } catch (\Throwable $e) {
            Logger::exception($e);

            QueryBuilder::table('orders')->where('id', $orderId)->update([
                'status' => 'cancelled',
                'payment_status' => 'failed',
                'admin_notes' => 'Gateway session could not be created.',
            ]);

            Session::flash('error', 'We could not reach the payment gateway. Please try again shortly.');

            return $this->redirect('/cart');
        }

        // Record the gateway reference so the callback can find this order.
        QueryBuilder::table('orders')->where('id', $orderId)->update([
            'payment_ref' => $session['gateway_order_id'],
            'payment_status' => 'pending',
        ]);

        return Response::redirect($session['iframe_url']);
    }

    /**
     * Persist the order and its line items before redirecting to the
     * gateway, so a completed payment always has something to attach to.
     *
     * The previous insert used columns that do not exist on this table
     * (`total_amount`, `transaction_id`) and omitted `order_number` and
     * `shipping_address`, both NOT NULL — so every checkout failed with a
     * SQL error before it ever reached Paymob.
     */
    protected function createPendingOrder(
        array $cart,
        array $customer,
        float $subtotal,
        float $shipping,
        float $total
    ): int {
        return QueryBuilder::transaction(function () use ($cart, $customer, $subtotal, $shipping, $total) {
            $orderId = QueryBuilder::table('orders')->insertGetId([
                'order_number' => Str::orderNumber(),
                'user_id' => $_SESSION['user']['id'] ?? null,
                'status' => 'pending',
                'payment_status' => 'unpaid',
                'payment_method' => 'paymob',
                'subtotal' => $subtotal,
                'shipping_cost' => $shipping,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total' => $total,
                'currency' => 'EGP',
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'],
                'customer_name' => trim($customer['first_name'] . ' ' . $customer['last_name']),
                'shipping_address' => json_encode([
                    'address' => $customer['address'],
                    'city' => $customer['city'],
                    'country' => 'EG',
                ], JSON_UNESCAPED_UNICODE),
                'customer_notes' => $customer['notes'],
            ]);

            if ($orderId <= 0) {
                throw new \RuntimeException('Could not create the order record.');
            }

            $rows = [];

            foreach ($cart['items'] as $item) {
                $rows[] = [
                    'order_id' => $orderId,
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'product_name' => $item['name'],
                    'variant_name' => $item['variant_name'],
                    'sku' => $item['sku'],
                    'price' => $item['price'],
                    'quantity' => $item['quantity'],
                    'total' => $item['total'],
                    'image' => $item['image_url'],
                ];
            }

            // One statement for the whole basket rather than one per line.
            QueryBuilder::table('order_items')->insertMany($rows);

            return $orderId;
        });
    }

    /**
     * Payment gateway callback.
     *
     * POST is the server-to-server notification; GET is where the shopper
     * lands in their browser afterwards. The two are handled separately —
     * the browser redirect must never be trusted to change order state.
     */
    public function callback(Request $request): Response
    {
        if ($request->method() === 'POST') {
            return $this->handleWebhook($request);
        }

        return $this->handleReturn($request);
    }

    protected function handleWebhook(Request $request): Response
    {
        $payload = $request->json();

        if (!is_array($payload) || $payload === []) {
            return Response::json(['success' => false, 'message' => 'Empty payload.'], 400);
        }

        // Paymob sends the signature as a query parameter on the callback URL.
        $hmac = $request->get('hmac') ?? $request->header('X-Paymob-Hmac');

        if (!$this->gateway()->hasHmacSecret()) {
            Logger::error('Rejected a payment callback: no HMAC secret is configured.');

            return Response::json(['success' => false, 'message' => 'Callback verification unavailable.'], 503);
        }

        // This check is the only authentication on this endpoint — CSRF is
        // deliberately skipped for it. Without the check, anybody could mark
        // any order paid by POSTing a JSON body.
        if (!$this->gateway()->verifyHmac($payload, is_string($hmac) ? $hmac : null)) {
            Logger::warning('Rejected a payment callback with an invalid HMAC', [
                'ip' => $request->ip(),
            ]);

            return Response::json(['success' => false, 'message' => 'Invalid signature.'], 403);
        }

        $object = $payload['obj'] ?? [];
        $gatewayOrderId = $object['order']['id'] ?? null;
        $success = ($object['success'] ?? false) === true;

        if (!$gatewayOrderId) {
            return Response::json(['success' => false, 'message' => 'Missing order reference.'], 400);
        }

        $order = QueryBuilder::table('orders')->where('payment_ref', (string) $gatewayOrderId)->first();

        if ($order === null) {
            Logger::warning('Payment callback for an unknown order', ['ref' => $gatewayOrderId]);

            return Response::json(['success' => false, 'message' => 'Unknown order.'], 404);
        }

        // Gateways retry; applying the transition twice would decrement
        // stock again.
        if ($order['payment_status'] === 'paid') {
            return Response::json(['success' => true, 'message' => 'Already processed.']);
        }

        // Confirm the gateway charged what we asked for.
        $paidCents = (int) ($object['amount_cents'] ?? 0);
        $expectedCents = (int) round(((float) $order['total']) * 100);

        if ($success && $paidCents !== $expectedCents) {
            Logger::error('Payment amount mismatch', [
                'order' => $order['order_number'],
                'expected_cents' => $expectedCents,
                'paid_cents' => $paidCents,
            ]);

            QueryBuilder::table('orders')->where('id', $order['id'])->update([
                'payment_status' => 'failed',
                'admin_notes' => 'Amount mismatch: gateway reported ' . $paidCents . ' cents.',
            ]);

            return Response::json(['success' => false, 'message' => 'Amount mismatch.'], 409);
        }

        if ($success) {
            $this->markPaid((int) $order['id'], (string) ($object['id'] ?? ''));
        } else {
            QueryBuilder::table('orders')->where('id', $order['id'])->update([
                'status' => 'cancelled',
                'payment_status' => 'failed',
            ]);
        }

        return Response::json(['success' => true]);
    }

    protected function markPaid(int $orderId, string $transactionId): void
    {
        QueryBuilder::transaction(function () use ($orderId, $transactionId) {
            QueryBuilder::table('orders')->where('id', $orderId)->update([
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'paid_at' => date('Y-m-d H:i:s'),
                'metadata' => json_encode(['gateway_transaction_id' => $transactionId]),
            ]);

            // Draw down stock now that the money has actually arrived. This
            // never happened before, so stock counts drifted from reality.
            $items = QueryBuilder::table('order_items')->where('order_id', $orderId)->get();

            foreach ($items as $item) {
                if (!empty($item['variant_id'])) {
                    QueryBuilder::table('product_variants')
                        ->where('id', $item['variant_id'])
                        ->increment('stock', -(int) $item['quantity']);
                }

                if (!empty($item['product_id'])) {
                    QueryBuilder::table('products')
                        ->where('id', $item['product_id'])
                        ->increment('stock', -(int) $item['quantity']);

                    QueryBuilder::table('products')
                        ->where('id', $item['product_id'])
                        ->increment('sales_count', (int) $item['quantity']);
                }
            }
        });

        $order = QueryBuilder::table('orders')->where('id', $orderId)->first();

        if ($order !== null) {
            Event::dispatch('order.paid', $order);
        }
    }

    /**
     * The page the shopper is returned to. Purely informational: the
     * authoritative state comes from the verified webhook above.
     */
    protected function handleReturn(Request $request): Response
    {
        Session::start();

        $success = $request->get('success') === 'true';
        $reference = (string) ($request->get('order') ?? '');

        $order = $reference !== ''
            ? QueryBuilder::table('orders')->where('payment_ref', $reference)->first()
            : null;

        if ($success) {
            // Only clear the basket once the payment actually succeeded;
            // the cart used to survive a completed order entirely.
            CartService::clear();
        }

        $orderNumber = $order['order_number'] ?? null;

        $body = $success
            ? '<h1>Thank you — your order is confirmed</h1>'
                . ($orderNumber
                    ? '<p>Your order reference is <strong>' . htmlspecialchars($orderNumber, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
                    : '<p>We have received your payment.</p>')
                . '<p>A confirmation email is on its way.</p>'
                . '<p><a class="btn" href="/shop">Continue shopping</a></p>'
            : '<h1>Payment was not completed</h1>'
                . '<p>Your card has not been charged. You can try again from your cart.</p>'
                . '<p><a class="btn" href="/cart">Return to cart</a></p>';

        $html = '<!doctype html><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . ($success ? 'Order confirmed' : 'Payment failed') . '</title>'
            . '<style>body{font:16px/1.6 system-ui,sans-serif;max-width:34rem;margin:15vh auto;padding:0 1.5rem;color:#0f172a}'
            . 'h1{font-size:1.6rem;margin:0 0 .75rem}p{color:#475569}'
            . '.btn{display:inline-block;margin-top:1rem;padding:.7rem 1.4rem;background:#6366f1;color:#fff;'
            . 'border-radius:.5rem;text-decoration:none;font-weight:600}</style>'
            . $body;

        return new Response($html, $success ? 200 : 402);
    }

    /**
     * Validate and normalise the customer details from the checkout form.
     * None of this was validated before — orders were written with
     * 'Guest' / 'guest@example.com' placeholders and a fixed phone number.
     */
    protected function collectCustomer(Request $request): array
    {
        $errors = [];

        $name = $request->string('name');
        $email = $request->string('email');
        $phone = $request->string('phone');
        $address = $request->string('address');
        $city = $request->string('city', 'Cairo');

        if ($name === '') {
            $errors[] = 'Please enter your name.';
        }

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if ($phone === '') {
            $errors[] = 'Please enter a contact phone number.';
        }

        if ($address === '') {
            $errors[] = 'Please enter a delivery address.';
        }

        $parts = preg_split('/\s+/u', $name, 2) ?: [''];

        return [
            'errors' => $errors,
            'first_name' => Str::limit($parts[0] ?? '', 100),
            'last_name' => Str::limit($parts[1] ?? '', 100),
            'email' => Str::limit($email, 255),
            'phone' => Str::limit($phone, 20),
            'address' => Str::limit($address, 500),
            'city' => Str::limit($city !== '' ? $city : 'Cairo', 100),
            'notes' => Str::limit($request->string('notes'), 1000),
        ];
    }

    /**
     * Flat-rate shipping with a free-delivery threshold. Kept simple and
     * server-side; the shipping_zones table can replace it later.
     */
    protected function shippingCost(float $subtotal): float
    {
        return $subtotal >= 1000.0 ? 0.0 : 60.0;
    }

    protected function gatewayItems(array $items): array
    {
        return array_map(static fn(array $item) => [
            'name' => Str::limit($item['name'], 50),
            'amount_cents' => (int) round($item['price'] * 100),
            'quantity' => $item['quantity'],
        ], $items);
    }
}

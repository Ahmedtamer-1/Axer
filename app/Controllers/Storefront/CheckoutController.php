<?php

namespace Axer\Controllers\Storefront;

use Axer\Core\Controller;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Core\Event;

class CheckoutController extends Controller
{
    protected string $apiKey;
    protected string $integrationId;
    protected string $iframeId;
    protected string $hmacSecret;

    public function __construct()
    {
        $settings = QueryBuilder::table('settings')->where('group', 'payments')->get();
        $config = [];
        foreach ($settings as $s) {
            $config[$s['key']] = $s['value'];
        }

        $this->apiKey = $config['paymob_api_key'] ?? '';
        $this->integrationId = $config['paymob_integration_id'] ?? '';
        $this->iframeId = $config['paymob_iframe_id'] ?? '';
        $this->hmacSecret = $config['paymob_hmac'] ?? '';
    }

    public function process(Request $request): Response
    {
        if ($request->method() !== 'POST') {
            return new Response('Method Not Allowed', 405);
        }

        $amount = $request->post('amount', 100);
        $amountCents = (int)($amount * 100);

        try {
            $authResponse = $this->sendPostRequest('https://accept.paymob.com/api/auth/tokens', [
                'api_key' => $this->apiKey
            ]);
            $token = $authResponse['token'] ?? null;
            if (!$token) throw new \Exception('Failed to authenticate with Paymob');

            $orderResponse = $this->sendPostRequest('https://accept.paymob.com/api/ecommerce/orders', [
                'auth_token' => $token,
                'delivery_needed' => 'false',
                'amount_cents' => $amountCents,
                'currency' => 'EGP',
                'items' => []
            ]);
            $orderId = $orderResponse['id'] ?? null;
            if (!$orderId) throw new \Exception('Failed to register order');

            $localOrderId = QueryBuilder::table('orders')->insert([
                'customer_name' => $request->post('name', 'Guest'),
                'customer_email' => $request->post('email', 'guest@example.com'),
                'total_amount' => $amount,
                'currency' => 'EGP',
                'status' => 'pending',
                'payment_method' => 'paymob',
                'transaction_id' => $orderId
            ]);

            $paymentKeyResponse = $this->sendPostRequest('https://accept.paymob.com/api/acceptance/payment_keys', [
                'auth_token' => $token,
                'amount_cents' => $amountCents,
                'expiration' => 3600,
                'order_id' => $orderId,
                'billing_data' => [
                    'apartment' => 'NA',
                    'email' => $request->post('email', 'guest@example.com'),
                    'floor' => 'NA',
                    'first_name' => $request->post('name', 'Guest'),
                    'street' => 'NA',
                    'building' => 'NA',
                    'phone_number' => '+201000000000',
                    'shipping_method' => 'NA',
                    'postal_code' => 'NA',
                    'city' => 'Cairo',
                    'country' => 'EG',
                    'last_name' => 'Customer',
                    'state' => 'Cairo'
                ],
                'currency' => 'EGP',
                'integration_id' => $this->integrationId
            ]);
            
            $paymentToken = $paymentKeyResponse['token'] ?? null;
            if (!$paymentToken) throw new \Exception('Failed to get payment token');

            $iframeUrl = "https://accept.paymob.com/api/acceptance/iframes/{$this->iframeId}?payment_token={$paymentToken}";
            
            return new Response('', 302, ['Location' => $iframeUrl]);
            
        } catch (\Exception $e) {
            return new Response('Payment Error: ' . $e->getMessage(), 500);
        }
    }

    public function callback(Request $request): Response
    {
        if ($request->method() === 'POST') {
            $data = $request->json();
            
            if ($data && isset($data['obj'])) {
                $obj = $data['obj'];
                $orderId = $obj['order']['id'] ?? null;
                $success = $obj['success'] ?? false;

                if ($orderId) {
                    $status = $success ? 'completed' : 'failed';
                    QueryBuilder::table('orders')->where('transaction_id', $orderId)->update([
                        'status' => $status
                    ]);

                    if ($success) {
                        $order = QueryBuilder::table('orders')->where('transaction_id', $orderId)->first();
                        if ($order) {
                            Event::dispatch('order.paid', $order);
                        }
                    }
                }
            }
            return new Response('OK', 200);
        }

        $success = $request->get('success') === 'true';
        if ($success) {
            return new Response('<h1>Payment Successful!</h1><p>Thank you for your order.</p>');
        } else {
            return new Response('<h1>Payment Failed</h1><p>Please try again.</p>');
        }
    }

    private function sendPostRequest(string $url, array $payload)
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }
}

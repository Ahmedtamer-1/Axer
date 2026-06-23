<?php

namespace Lume\Services;

use Lume\Core\Config;
use Lume\Core\App;
use Lume\Database\QueryBuilder;

class PaymobService
{
    protected string $secretKey;
    protected string $publicKey;
    protected string $hmacSecret;
    protected string $apiUrl = 'https://accept.paymob.com/v1/intention/';

    public function __construct()
    {
        $config = App::getInstance()->getContainer()->get(Config::class);
        $this->secretKey = $config->get('PAYMOB_SECRET_KEY', '');
        $this->publicKey = $config->get('PAYMOB_PUBLIC_KEY', '');
        $this->hmacSecret = $config->get('PAYMOB_HMAC', '');
    }

    /**
     * Create an Intention using Paymob v2 API.
     */
    public function createIntention(array $orderData): ?array
    {
        if (empty($this->secretKey)) {
            return null; // Gateway not configured
        }

        // Prepare the payload according to Paymob v2 specs
        $payload = [
            'amount' => $orderData['amount'] * 100, // Amount in cents
            'currency' => $orderData['currency'] ?? 'EGP',
            'payment_methods' => $orderData['payment_methods'] ?? ['card', 'wallet'],
            'items' => $orderData['items'] ?? [],
            'billing_data' => $orderData['billing_data'] ?? [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test@lume.com',
                'phone_number' => '+201000000000',
                'street' => 'NA',
                'building' => 'NA',
                'floor' => 'NA',
                'apartment' => 'NA',
                'city' => 'NA',
                'country' => 'EG',
            ],
            'customer' => $orderData['customer'] ?? null,
            'extras' => [
                'order_id' => $orderData['order_id']
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Token ' . $this->secretKey,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 201 || $httpCode === 200) {
            $data = json_decode($response, true);
            return [
                'client_secret' => $data['client_secret'] ?? null,
                'intention_id' => $data['id'] ?? null
            ];
        }

        return null;
    }

    /**
     * Verify Paymob Webhook HMAC signature.
     */
    public function verifyWebhook(array $payload, string $receivedHmac): bool
    {
        if (empty($this->hmacSecret)) {
            return false;
        }

        $obj = $payload['obj'] ?? [];
        
        // As per Paymob documentation, concatenate specific fields
        $concatenatedString = 
            ($obj['amount_cents'] ?? '') .
            ($obj['created_at'] ?? '') .
            ($obj['currency'] ?? '') .
            ($obj['error_occured'] === true ? 'true' : 'false') .
            ($obj['has_parent_transaction'] === true ? 'true' : 'false') .
            ($obj['id'] ?? '') .
            ($obj['integration_id'] ?? '') .
            ($obj['is_3d_secure'] === true ? 'true' : 'false') .
            ($obj['is_auth'] === true ? 'true' : 'false') .
            ($obj['is_capture'] === true ? 'true' : 'false') .
            ($obj['is_refunded'] === true ? 'true' : 'false') .
            ($obj['is_standalone_payment'] === true ? 'true' : 'false') .
            ($obj['is_voided'] === true ? 'true' : 'false') .
            ($obj['order']['id'] ?? '') .
            ($obj['owner'] ?? '') .
            ($obj['pending'] === true ? 'true' : 'false') .
            ($obj['source_data']['pan'] ?? '') .
            ($obj['source_data']['sub_type'] ?? '') .
            ($obj['source_data']['type'] ?? '') .
            ($obj['success'] === true ? 'true' : 'false');

        $calculatedHmac = hash_hmac('sha512', $concatenatedString, $this->hmacSecret);

        return hash_equals($calculatedHmac, $receivedHmac);
    }
}

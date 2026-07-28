<?php

namespace Axer\Services;

use Axer\Core\App;
use Axer\Core\Config;
use Axer\Core\Logger;
use Axer\Database\QueryBuilder;

/**
 * Paymob Accept client.
 *
 * Two things were wrong before:
 *
 *  - CheckoutController spoke to Paymob through its own private curl
 *    helper with no timeout, no transport-error check and no HTTP status
 *    check, so a slow gateway hung the request until PHP killed it.
 *  - This class already implemented verifyWebhook(), but nothing ever
 *    called it, and it read its credentials from .env while the checkout
 *    read the same credentials out of the `settings` table. Callbacks were
 *    therefore accepted unverified.
 */
class PaymobService
{
    protected const BASE = 'https://accept.paymob.com/api';

    /** Fields Paymob concatenates, in this exact order, to build the HMAC. */
    protected const HMAC_FIELDS = [
        'amount_cents', 'created_at', 'currency', 'error_occured',
        'has_parent_transaction', 'id', 'integration_id', 'is_3d_secure',
        'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment',
        'is_voided', 'order.id', 'owner', 'pending', 'source_data.pan',
        'source_data.sub_type', 'source_data.type', 'success',
    ];

    protected string $apiKey;
    protected string $integrationId;
    protected string $iframeId;
    protected string $hmacSecret;

    public function __construct(
        string $apiKey = '',
        string $integrationId = '',
        string $iframeId = '',
        string $hmacSecret = ''
    ) {
        $this->apiKey = $apiKey;
        $this->integrationId = $integrationId;
        $this->iframeId = $iframeId;
        $this->hmacSecret = $hmacSecret;
    }

    /**
     * Build from the admin Settings table, falling back to .env.
     *
     * This is the single place credentials are resolved, so the checkout
     * and the webhook handler can no longer disagree about where they live.
     */
    public static function fromSettings(): self
    {
        $settings = [];

        try {
            foreach (QueryBuilder::table('settings')->where('group', 'payments')->get() as $row) {
                $settings[$row['key']] = $row['value'];
            }
        } catch (\Throwable $e) {
            Logger::warning('Could not read payment settings: ' . $e->getMessage());
        }

        $config = null;

        try {
            $config = App::getInstance()->getContainer()->get(Config::class);
        } catch (\Throwable $e) {
            // CLI/installer context — settings alone will have to do.
        }

        $get = static function (string $settingKey, string $envKey) use ($settings, $config): string {
            $value = $settings[$settingKey] ?? null;

            if ($value === null || $value === '') {
                $value = $config?->get($envKey, '');
            }

            return (string) ($value ?? '');
        };

        return new self(
            $get('paymob_api_key', 'PAYMOB_API_KEY'),
            $get('paymob_integration_id', 'PAYMOB_INTEGRATION_ID'),
            $get('paymob_iframe_id', 'PAYMOB_IFRAME_ID'),
            $get('paymob_hmac', 'PAYMOB_HMAC')
        );
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->integrationId !== '' && $this->iframeId !== '';
    }

    public function hasHmacSecret(): bool
    {
        return $this->hmacSecret !== '';
    }

    /**
     * Run the three-step Accept handshake and return the hosted iframe URL.
     *
     * @param int   $amountCents computed server-side, never from the client
     * @param array $billing     first_name, last_name, email, phone, street, city, state
     */
    public function createPaymentSession(int $amountCents, array $billing, array $items = []): array
    {
        if ($amountCents < 100) {
            throw new \InvalidArgumentException('Order total is below the minimum chargeable amount.');
        }

        $auth = $this->post('/auth/tokens', ['api_key' => $this->apiKey]);
        $token = $auth['token'] ?? null;

        if (!$token) {
            throw new \RuntimeException('Could not authenticate with the payment gateway.');
        }

        $order = $this->post('/ecommerce/orders', [
            'auth_token' => $token,
            'delivery_needed' => 'false',
            'amount_cents' => $amountCents,
            'currency' => 'EGP',
            'items' => $items,
        ]);

        $gatewayOrderId = $order['id'] ?? null;

        if (!$gatewayOrderId) {
            throw new \RuntimeException('Could not register the order with the payment gateway.');
        }

        $paymentKey = $this->post('/acceptance/payment_keys', [
            'auth_token' => $token,
            'amount_cents' => $amountCents,
            'expiration' => 3600,
            'order_id' => $gatewayOrderId,
            'currency' => 'EGP',
            'integration_id' => $this->integrationId,
            'billing_data' => [
                'first_name' => $billing['first_name'] ?: 'Guest',
                'last_name' => $billing['last_name'] ?: 'Customer',
                'email' => $billing['email'] ?: 'guest@example.com',
                'phone_number' => $billing['phone'] ?: '+201000000000',
                'street' => $billing['street'] ?: 'NA',
                'city' => $billing['city'] ?: 'Cairo',
                'state' => $billing['state'] ?: 'Cairo',
                'country' => 'EG',
                'apartment' => 'NA',
                'floor' => 'NA',
                'building' => 'NA',
                'shipping_method' => 'NA',
                'postal_code' => 'NA',
            ],
        ]);

        $paymentToken = $paymentKey['token'] ?? null;

        if (!$paymentToken) {
            throw new \RuntimeException('Could not obtain a payment token.');
        }

        return [
            'gateway_order_id' => (string) $gatewayOrderId,
            'iframe_url' => self::BASE . "/acceptance/iframes/{$this->iframeId}?payment_token={$paymentToken}",
        ];
    }

    /**
     * Verify the HMAC on a callback payload.
     *
     * CSRF is (correctly) skipped for the callback route, so this signature
     * is the only thing that authenticates the caller. Without it, anyone
     * who could POST to /checkout/callback could mark any order as paid.
     */
    public function verifyHmac(array $payload, ?string $providedHmac): bool
    {
        if ($this->hmacSecret === '' || !is_string($providedHmac) || $providedHmac === '') {
            return false;
        }

        $object = $payload['obj'] ?? $payload;

        if (!is_array($object)) {
            return false;
        }

        $concatenated = '';

        foreach (self::HMAC_FIELDS as $field) {
            $concatenated .= $this->stringifyForHmac($this->dig($object, $field));
        }

        $expected = hash_hmac('sha512', $concatenated, $this->hmacSecret);

        return hash_equals($expected, strtolower(trim($providedHmac)));
    }

    /** Backwards-compatible alias for the previous method name. */
    public function verifyWebhook(array $payload, ?string $receivedHmac): bool
    {
        return $this->verifyHmac($payload, $receivedHmac);
    }

    /**
     * Read a possibly dotted path out of the payload without warning on a
     * missing key — the old implementation compared `$obj['error_occured']`
     * directly, which raised a warning whenever the field was absent.
     */
    protected function dig(array $data, string $path)
    {
        $value = $data;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Paymob derives the HMAC string from the JSON representation, so
     * booleans must render as "true"/"false", not PHP's "1"/"".
     */
    protected function stringifyForHmac($value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if ($value === null) {
            return '';
        }

        return (string) $value;
    }

    protected function post(string $path, array $payload): array
    {
        $ch = curl_init(self::BASE . $path);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            Logger::error('Paymob request failed', ['path' => $path, 'error' => $error]);
            throw new \RuntimeException('The payment gateway could not be reached.');
        }

        $decoded = json_decode((string) $response, true);

        if ($status < 200 || $status >= 300) {
            // Log the gateway's reason but never surface it: error bodies
            // echo the request back, including the API key.
            Logger::error('Paymob returned an error', [
                'path' => $path,
                'status' => $status,
                'body' => is_array($decoded) ? $decoded : substr((string) $response, 0, 500),
            ]);

            throw new \RuntimeException('The payment gateway rejected the request.');
        }

        return is_array($decoded) ? $decoded : [];
    }
}

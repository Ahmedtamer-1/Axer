<?php

namespace Axer\Controllers\Api;

use Axer\Core\Event;
use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Services\PaymobService;

class WebhookController extends ApiController
{
    /**
     * A second, entirely unauthenticated Paymob webhook.
     *
     * This dispatched order.paid straight from the request body with no
     * signature check whatsoever — worse than the bug in the storefront
     * checkout, which at least loaded (if never called) an HMAC secret.
     * Anyone who could reach this URL could fire a fake "payment
     * succeeded" event for any order id. It now goes through the same
     * verification as /checkout/callback.
     */
    public function paymob(Request $request): Response
    {
        $payload = $request->json();

        if ($payload === []) {
            return $this->error('Invalid request', 400);
        }

        $gateway = PaymobService::fromSettings();
        $hmac = $request->get('hmac') ?? $request->header('hmac') ?? $request->header('X-Paymob-Hmac');

        if (!$gateway->hasHmacSecret() || !$gateway->verifyHmac($payload, is_string($hmac) ? $hmac : null)) {
            Logger::warning('Rejected an unverified Paymob webhook call', ['ip' => $request->ip()]);

            return $this->error('Invalid signature', 403);
        }

        $object = $payload['obj'] ?? [];
        $paymobOrderId = $object['order']['id'] ?? null;
        $success = ($object['success'] ?? false) === true;

        if ($paymobOrderId === null) {
            return $this->success(null, 'Webhook processed');
        }

        $order = QueryBuilder::table('orders')->where('payment_ref', (string) $paymobOrderId)->first();

        // Only dispatch for an order this store actually knows about, and
        // only once — this endpoint and /checkout/callback both listen for
        // the same gateway, so a duplicate delivery must not fire the
        // event (and any downstream stock/email side effect) twice.
        if ($order !== null && $success && $order['payment_status'] !== 'paid') {
            Event::dispatch('order.paid', $order);
        }

        try {
            QueryBuilder::table('activity_log')->insert([
                'action' => 'paymob_webhook',
                'description' => 'Received transaction ' . ($success ? 'success' : 'failed'),
                'metadata' => json_encode([
                    'paymob_order_id' => $paymobOrderId,
                    'transaction_id' => $object['id'] ?? null,
                ]),
            ]);
        } catch (\Throwable $e) {
            Logger::warning('Could not write webhook activity log: ' . $e->getMessage());
        }

        return $this->success(null, 'Webhook processed');
    }
}

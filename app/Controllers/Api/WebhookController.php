<?php

namespace Axer\Controllers\Api;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Services\PaymobService;
use Axer\Database\QueryBuilder;

class WebhookController extends ApiController
{
    public function paymob(Request $request): Response
    {
        $payload = $request->json();
        $hmacHeader = $request->header('hmac');

        if (!$payload || !$hmacHeader) {
            return $this->error('Invalid request', 400);
        }

        $paymobService = new PaymobService();
        if (!$paymobService->verifyWebhook($payload, $hmacHeader)) {
            return $this->error('Invalid HMAC signature', 401);
        }

        // Webhook is authentic. Process transaction.
        $obj = $payload['obj'] ?? null;
        if ($obj && isset($obj['order']['id'])) {
            
            // In Paymob, the merchant_order_id can be passed in extras or order data
            // For this implementation, assume order ID is stored in integration or we map Paymob's order ID
            $paymobOrderId = $obj['order']['id'];
            $success = $obj['success'] ?? false;
            
            // This is a simplified webhook handler. We would look up the local order by Paymob order ID or intention ID
            // And update status.
            if ($success) {
                // Update order status to paid
                // Example logic:
                // QueryBuilder::table('orders')->where('payment_ref', $paymobOrderId)->update(['payment_status' => 'paid', 'paid_at' => date('Y-m-d H:i:s')]);
            } else {
                // Update order status to failed
            }
            
            // Log webhook event
            QueryBuilder::table('activity_log')->insert([
                'action' => 'paymob_webhook',
                'description' => 'Received transaction ' . ($success ? 'success' : 'failed'),
                'metadata' => json_encode(['paymob_order_id' => $paymobOrderId, 'transaction_id' => $obj['id']])
            ]);
        }

        return $this->success(null, 'Webhook processed');
    }
}

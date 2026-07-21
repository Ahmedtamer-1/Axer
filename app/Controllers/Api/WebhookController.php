<?php

namespace Axer\Controllers\Api;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Database\QueryBuilder;
use Axer\Core\Event;

class WebhookController extends ApiController
{
    public function paymob(Request $request): Response
    {
        $payload = $request->json();
        $hmacHeader = $request->header('hmac');

        if (!$payload) {
            return $this->error('Invalid request', 400);
        }

        $obj = $payload['obj'] ?? null;
        if ($obj && isset($obj['order']['id'])) {
            $paymobOrderId = $obj['order']['id'];
            $success = $obj['success'] ?? false;
            
            if ($success) {
                Event::dispatch('order.paid', $obj);
            }
            
            try {
                QueryBuilder::table('activity_log')->insert([
                    'action' => 'paymob_webhook',
                    'description' => 'Received transaction ' . ($success ? 'success' : 'failed'),
                    'metadata' => json_encode(['paymob_order_id' => $paymobOrderId, 'transaction_id' => $obj['id'] ?? null])
                ]);
            } catch (\Exception $e) {}
        }

        return $this->success(null, 'Webhook processed');
    }
}

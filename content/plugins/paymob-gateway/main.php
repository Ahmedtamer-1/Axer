<?php

namespace Axer\Plugins\PaymobGateway;

use Axer\Plugin\BasePlugin;
use Axer\Core\Event;

class Plugin extends BasePlugin
{
    public function register(): void
    {
        // Core dispatches 'order.paid' (CheckoutController, WebhookController)
        // — this used to listen for 'checkout.completed', an event nothing
        // ever fired, so the handler never ran.
        Event::listen('order.paid', [$this, 'handleCheckout']);
    }

    public function activate(): void {}
    public function deactivate(): void {}

    public function handleCheckout(array $orderData): void
    {
        // Paymob gateway intention trigger
    }

    public function getSettingsSchema(): array
    {
        return [
            ['name' => 'api_key', 'label' => 'Paymob API Key', 'type' => 'password'],
            ['name' => 'integration_id_card', 'label' => 'Card Integration ID', 'type' => 'text'],
            ['name' => 'integration_id_wallet', 'label' => 'Wallet Integration ID', 'type' => 'text'],
            ['name' => 'hmac_secret', 'label' => 'HMAC Secret', 'type' => 'password']
        ];
    }
}

<?php

namespace Axer\Plugins\GoogleOauth;

use Axer\Plugin\BasePlugin;

class Plugin extends BasePlugin
{
    public function register(): void {}
    public function activate(): void {}
    public function deactivate(): void {}

    public function getSettingsSchema(): array
    {
        return [
            ['name' => 'client_id', 'label' => 'Google Client ID', 'type' => 'text'],
            ['name' => 'client_secret', 'label' => 'Google Client Secret', 'type' => 'password'],
            ['name' => 'redirect_uri', 'label' => 'Redirect URI', 'type' => 'text']
        ];
    }
}

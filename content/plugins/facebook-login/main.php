<?php

namespace Axer\Plugins\FacebookLogin;

use Axer\Plugin\BasePlugin;

class Plugin extends BasePlugin
{
    public function register(): void {}
    public function activate(): void {}
    public function deactivate(): void {}

    public function getSettingsSchema(): array
    {
        return [
            ['name' => 'app_id', 'label' => 'Facebook App ID', 'type' => 'text'],
            ['name' => 'app_secret', 'label' => 'Facebook App Secret', 'type' => 'password']
        ];
    }
}

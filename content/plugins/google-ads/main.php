<?php

namespace Axer\Plugins\GoogleAds;

use Axer\Plugin\BasePlugin;
use Axer\Core\Event;

class Plugin extends BasePlugin
{
    public function register(): void
    {
        Event::listen('template.head_end', [$this, 'injectClientTag']);
    }

    public function activate(): void {}
    public function deactivate(): void {}

    public function injectClientTag(string $html): string
    {
        $gtmId = $this->settings['gtm_id'] ?? '';
        if (empty($gtmId)) return $html;

        $code = "<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','" . htmlspecialchars($gtmId) . "');</script>";

        return $html . "\n" . $code;
    }

    public function getSettingsSchema(): array
    {
        return [
            ['name' => 'conversion_id', 'label' => 'Google Ads Conversion ID', 'type' => 'text'],
            ['name' => 'gtm_id', 'label' => 'GTM Container ID', 'type' => 'text']
        ];
    }
}

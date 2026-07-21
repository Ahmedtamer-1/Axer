<?php

namespace Axer\Plugins\MetaPixel;

use Axer\Plugin\BasePlugin;
use Axer\Core\Event;

class Plugin extends BasePlugin
{
    public function register(): void
    {
        Event::listen('template.head_end', [$this, 'injectClientPixel']);
    }

    public function activate(): void {}
    public function deactivate(): void {}

    public function injectClientPixel(string $html): string
    {
        $pixelId = $this->settings['pixel_id'] ?? '';
        if (empty($pixelId)) return $html;

        $code = "<!-- Facebook Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '" . htmlspecialchars($pixelId) . "');
fbq('track', 'PageView');
</script>";

        return $html . "\n" . $code;
    }

    public function getSettingsSchema(): array
    {
        return [
            ['name' => 'pixel_id', 'label' => 'Facebook Pixel ID', 'type' => 'text'],
            ['name' => 'access_token', 'label' => 'Conversions API Access Token', 'type' => 'password']
        ];
    }
}

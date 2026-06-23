<?php

namespace Axer\Services;

use Axer\Core\Config;
use Axer\Core\App;

class PixelService
{
    protected string $fbPixelId;
    protected string $fbAccessToken;
    protected string $tiktokPixelId;
    protected string $tiktokAccessToken;
    protected string $clientIp;
    protected string $userAgent;

    public function __construct()
    {
        $config = App::getInstance()->getContainer()->get(Config::class);
        $this->fbPixelId = $config->get('FB_PIXEL_ID', '');
        $this->fbAccessToken = $config->get('FB_ACCESS_TOKEN', '');
        
        $this->tiktokPixelId = $config->get('TIKTOK_PIXEL_ID', '');
        $this->tiktokAccessToken = $config->get('TIKTOK_ACCESS_TOKEN', '');
        
        $this->clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Send event to Facebook Conversions API (Server-Side)
     */
    public function sendFacebookEvent(string $eventName, array $eventData, array $userData)
    {
        if (empty($this->fbPixelId) || empty($this->fbAccessToken)) {
            return false;
        }

        $url = "https://graph.facebook.com/v19.0/{$this->fbPixelId}/events?access_token={$this->fbAccessToken}";
        
        $payload = [
            'data' => [
                [
                    'event_name' => $eventName,
                    'event_time' => time(),
                    'action_source' => 'website',
                    'user_data' => array_merge([
                        'client_ip_address' => $this->clientIp,
                        'client_user_agent' => $this->userAgent,
                    ], $userData),
                    'custom_data' => $eventData
                ]
            ]
        ];

        return $this->sendPostRequest($url, $payload);
    }

    /**
     * Send event to TikTok Events API (Server-Side)
     */
    public function sendTikTokEvent(string $eventName, array $eventData, array $userData)
    {
        if (empty($this->tiktokPixelId) || empty($this->tiktokAccessToken)) {
            return false;
        }

        $url = "https://business-api.tiktok.com/open_api/v1.3/pixel/track/";
        
        $payload = [
            'pixel_code' => $this->tiktokPixelId,
            'event' => $eventName,
            'event_time' => time(),
            'context' => [
                'ad' => [
                    'callback' => ''
                ],
                'user' => [
                    'phone_number' => $userData['ph'] ?? '',
                    'email' => $userData['em'] ?? '',
                ],
                'page' => [
                    'url' => $_SERVER['HTTP_REFERER'] ?? ''
                ],
                'ip' => $this->clientIp,
                'user_agent' => $this->userAgent
            ],
            'properties' => $eventData
        ];

        $headers = [
            'Access-Token: ' . $this->tiktokAccessToken,
            'Content-Type: application/json'
        ];

        return $this->sendPostRequest($url, $payload, $headers);
    }

    /**
     * Track a Purchase event across all configured CAPIs
     */
    public function trackPurchase(array $order, array $customer)
    {
        $userData = [];
        if (!empty($customer['email'])) {
            $userData['em'] = hash('sha256', strtolower(trim($customer['email'])));
        }
        if (!empty($customer['phone'])) {
            $userData['ph'] = hash('sha256', trim($customer['phone']));
        }

        $eventData = [
            'currency' => $order['currency'] ?? 'EGP',
            'value' => $order['total_amount'] ?? 0,
            'content_ids' => array_column($order['items'] ?? [], 'product_id'),
            'content_type' => 'product',
        ];

        // Fire async or normally
        $this->sendFacebookEvent('Purchase', $eventData, $userData);
        $this->sendTikTokEvent('PlaceAnOrder', $eventData, $userData);
    }

    private function sendPostRequest(string $url, array $payload, array $headers = ['Content-Type: application/json'])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Short timeout so it doesn't block the request
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    public function getClientHeadScripts(): string
    {
        $scripts = '';
        if (!empty($this->fbPixelId)) {
            $scripts .= <<<HTML
<!-- Facebook Pixel Code -->
<script>
!function(f,b,e,v,n,t,s)
{if(f.fbq)return;n=f.fbq=function(){n.callMethod?
n.callMethod.apply(n,arguments):n.queue.push(arguments)};
if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
n.queue=[];t=b.createElement(e);t.async=!0;
t.src=v;s=b.getElementsByTagName(e)[0];
s.parentNode.insertBefore(t,s)}(window, document,'script',
'https://connect.facebook.net/en_US/fbevents.js');
fbq('init', '{$this->fbPixelId}');
fbq('track', 'PageView');
</script>
<noscript><img height="1" width="1" style="display:none"
src="https://www.facebook.com/tr?id={$this->fbPixelId}&ev=PageView&noscript=1"
/></noscript>
<!-- End Facebook Pixel Code -->

HTML;
        }

        if (!empty($this->tiktokPixelId)) {
            $scripts .= <<<HTML
<!-- TikTok Pixel Code -->
<script>
!function (w, d, t) {
  w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js";ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};var o=document.createElement("script");o.type="text/javascript",o.async=!0,o.src=i+"?sdkid="+e+"&lib="+t;var a=document.getElementsByTagName("script")[0];a.parentNode.insertBefore(o,a)};
  ttq.load('{$this->tiktokPixelId}');
  ttq.page();
}(window, document, 'ttq');
</script>
<!-- End TikTok Pixel Code -->

HTML;
        }

        return $scripts;
    }

    public function getClientFooterScripts(): string
    {
        return '';
    }
}

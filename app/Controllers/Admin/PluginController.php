<?php

namespace Lume\Controllers\Admin;

use Lume\Core\Request;
use Lume\Core\Response;

class PluginController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->checkAuth($request);
        
        $pluginsDir = BASE_PATH . '/content/plugins';
        $installedPlugins = [];
        
        if (is_dir($pluginsDir)) {
            $items = array_diff(scandir($pluginsDir), ['.', '..']);
            foreach ($items as $item) {
                if (is_dir($pluginsDir . '/' . $item) && file_exists($pluginsDir . '/' . $item . '/plugin.json')) {
                    $manifest = json_decode(file_get_contents($pluginsDir . '/' . $item . '/plugin.json'), true);
                    $installedPlugins[$item] = $manifest;
                }
            }
        }

        // Mock marketplace plugins for Phase 13
        $marketplacePlugins = [
            [
                'id' => 'lume-seo',
                'name' => 'Lume SEO Pro',
                'description' => 'Advanced SEO tools, sitemap generator, and meta tag manager for your storefront.',
                'price' => '$19.00',
                'developer' => 'Lume Team'
            ],
            [
                'id' => 'lume-abandoned-cart',
                'name' => 'Abandoned Cart Recovery',
                'description' => 'Automatically email customers who leave their checkout process incomplete.',
                'price' => '$29.00',
                'developer' => 'Lume Team'
            ],
            [
                'id' => 'lume-reviews',
                'name' => 'Product Reviews',
                'description' => 'Allow customers to leave reviews and ratings on your products.',
                'price' => 'Free',
                'developer' => 'Lume Team'
            ]
        ];

        return $this->renderAdmin('plugins/index', [
            'title' => 'Plugins & Marketplace',
            'installedPlugins' => $installedPlugins,
            'marketplacePlugins' => $marketplacePlugins
        ]);
    }
}

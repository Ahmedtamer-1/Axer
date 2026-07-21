<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Plugin\PluginManager;

class PluginController extends AdminController
{
    protected PluginManager $manager;

    public function __construct()
    {
        parent::__construct();
        $this->manager = new PluginManager();
    }

    public function index(Request $request): Response
    {
        $this->checkAuth($request);
        
        $installedPlugins = $this->manager->getInstalledPlugins();

        return $this->renderAdmin('plugins/index', [
            'title' => 'Installed Plugins',
            'installedPlugins' => $installedPlugins
        ]);
    }

    public function marketplace(Request $request): Response
    {
        $this->checkAuth($request);

        $catalogFile = BASE_PATH . '/content/plugin-catalog.json';
        $catalog = [];
        if (file_exists($catalogFile)) {
            $catalog = json_decode(file_get_contents($catalogFile), true) ?: [];
        }

        $installed = array_column($this->manager->getInstalledPlugins(), 'slug');

        foreach ($catalog as &$item) {
            $item['is_installed'] = in_array($item['slug'], $installed);
        }

        return $this->renderAdmin('plugins/marketplace', [
            'title' => 'Plugin Marketplace',
            'catalog' => $catalog
        ]);
    }

    public function activate(Request $request, string $slug): Response
    {
        $this->checkAuth($request);
        $this->manager->activate($slug);
        return $this->redirect('/admin/plugins');
    }

    public function deactivate(Request $request, string $slug): Response
    {
        $this->checkAuth($request);
        $this->manager->deactivate($slug);
        return $this->redirect('/admin/plugins');
    }

    public function settings(Request $request, string $slug): Response
    {
        $this->checkAuth($request);

        $plugins = $this->manager->getInstalledPlugins();
        $target = null;
        foreach ($plugins as $p) {
            if ($p['slug'] === $slug) {
                $target = $p;
                break;
            }
        }

        if (!$target) {
            return $this->redirect('/admin/plugins');
        }

        // Fetch settings schema from main plugin class
        $schema = [];
        $className = 'Axer\\Plugins\\' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug))) . '\\Plugin';
        if (class_exists($className)) {
            $instance = new $className($target['settings'] ?? []);
            $schema = $instance->getSettingsSchema();
        }

        if ($request->method() === 'POST') {
            $settings = $request->post('settings') ?? [];
            $this->manager->savePluginSettings($slug, $settings);
            return $this->redirect('/admin/plugins/settings/' . $slug);
        }

        return $this->renderAdmin('plugins/settings', [
            'title' => 'Plugin Settings — ' . $target['name'],
            'plugin' => $target,
            'schema' => $schema
        ]);
    }
}

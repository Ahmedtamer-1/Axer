<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;
use Axer\Plugin\PluginManager;
use Axer\Support\SettingsSchema;

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
        $this->checkAuth($request, 'superadmin');
        
        $installedPlugins = $this->manager->getInstalledPlugins();

        return $this->renderAdmin('plugins/index', [
            'title' => 'Installed Plugins',
            'installedPlugins' => $installedPlugins
        ]);
    }

    public function marketplace(Request $request): Response
    {
        $this->checkAuth($request, 'superadmin');

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
        $this->checkAuth($request, 'superadmin');
        $this->manager->activate($slug);
        return $this->redirect('/admin/plugins');
    }

    public function deactivate(Request $request, string $slug): Response
    {
        $this->checkAuth($request, 'superadmin');
        $this->manager->deactivate($slug);
        return $this->redirect('/admin/plugins');
    }

    public function settings(Request $request, string $slug): Response
    {
        $this->checkAuth($request, 'superadmin');

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

        // The manifest (plugin.json's settings_schema) is the source of
        // truth — it can be read without executing any plugin PHP, so an
        // inactive plugin's config form still renders. Only fall back to
        // the class's getSettingsSchema() for a plugin that declares its
        // schema in PHP instead, which requires loading it first: before
        // this, class_exists() only ever succeeded for a plugin that was
        // already active (PluginManager::init() only requires active
        // plugins' main.php), so every inactive plugin's settings page
        // rendered as "no custom settings" — the reported bug.
        $schema = $target['settings_schema'] ?? [];

        if ($schema === []) {
            $this->manager->loadPlugin($slug);
            $className = 'Axer\\Plugins\\' . str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $slug))) . '\\Plugin';

            if (class_exists($className)) {
                $instance = new $className($target['settings'] ?? []);
                $schema = $instance->getSettingsSchema();
            }
        }

        if ($request->method() === 'POST') {
            $posted = $request->post('settings') ?? [];
            $sanitized = SettingsSchema::sanitize($schema, is_array($posted) ? $posted : [], $target['settings'] ?? []);

            if ($sanitized['missingRequired'] !== []) {
                Session::flash('error', 'Please fill in: ' . implode(', ', $sanitized['missingRequired']));

                return $this->renderAdmin('plugins/settings', [
                    'title' => 'Plugin Settings — ' . $target['name'],
                    'plugin' => array_merge($target, ['settings' => $sanitized['values']]),
                    'schema' => $schema,
                ]);
            }

            $this->manager->savePluginSettings($slug, $sanitized['values']);
            Session::flash('success', 'Plugin settings saved.');

            return $this->redirect('/admin/plugins/settings/' . $slug);
        }

        return $this->renderAdmin('plugins/settings', [
            'title' => 'Plugin Settings — ' . $target['name'],
            'plugin' => $target,
            'schema' => $schema,
        ]);
    }
}

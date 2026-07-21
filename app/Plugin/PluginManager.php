<?php

namespace Axer\Plugin;

use Axer\Database\QueryBuilder;

class PluginManager
{
    protected static array $activePlugins = [];

    public function init(): void
    {
        $plugins = $this->getInstalledPlugins();
        foreach ($plugins as $plugin) {
            if ($plugin['is_active']) {
                $this->loadPlugin($plugin['slug']);
            }
        }
    }

    public function getInstalledPlugins(): array
    {
        $dir = BASE_PATH . '/content/plugins';
        if (!is_dir($dir)) {
            return [];
        }

        $plugins = [];
        $folders = scandir($dir);

        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..' || !is_dir($dir . '/' . $folder)) {
                continue;
            }

            $manifestPath = $dir . '/' . $folder . '/plugin.json';
            if (!file_exists($manifestPath)) {
                continue;
            }

            $json = json_decode(file_get_contents($manifestPath), true) ?: [];
            
            $dbRecord = null;
            try {
                $dbRecord = QueryBuilder::table('plugins')->where('slug', $folder)->first();
            } catch (\Exception $e) {}

            $plugins[] = [
                'slug' => $folder,
                'name' => $json['name'] ?? ucfirst($folder),
                'description' => $json['description'] ?? '',
                'version' => $json['version'] ?? '1.0.0',
                'author' => $json['author'] ?? 'Unknown',
                'is_active' => $dbRecord ? (bool)$dbRecord['is_active'] : false,
                'settings' => $dbRecord ? json_decode($dbRecord['settings'] ?? '[]', true) : []
            ];
        }

        return $plugins;
    }

    public function loadPlugin(string $slug): bool
    {
        $mainFile = BASE_PATH . '/content/plugins/' . $slug . '/main.php';
        if (!file_exists($mainFile)) {
            return false;
        }

        require_once $mainFile;
        
        $className = 'Axer\\Plugins\\' . $this->studly($slug) . '\\Plugin';
        if (class_exists($className)) {
            $settings = $this->getPluginSettings($slug);
            /** @var BasePlugin $plugin */
            $plugin = new $className($settings);
            $plugin->register();
            self::$activePlugins[$slug] = $plugin;
            return true;
        }

        return false;
    }

    public function activate(string $slug): bool
    {
        try {
            $existing = QueryBuilder::table('plugins')->where('slug', $slug)->first();
            if ($existing) {
                QueryBuilder::table('plugins')->where('slug', $slug)->update(['is_active' => 1]);
            } else {
                QueryBuilder::table('plugins')->insert([
                    'slug' => $slug,
                    'is_active' => 1,
                    'settings' => json_encode([])
                ]);
            }

            $this->loadPlugin($slug);
            if (isset(self::$activePlugins[$slug])) {
                self::$activePlugins[$slug]->activate();
            }

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function deactivate(string $slug): bool
    {
        try {
            if (isset(self::$activePlugins[$slug])) {
                self::$activePlugins[$slug]->deactivate();
            }

            QueryBuilder::table('plugins')->where('slug', $slug)->update(['is_active' => 0]);
            unset(self::$activePlugins[$slug]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getPluginSettings(string $slug): array
    {
        try {
            $record = QueryBuilder::table('plugins')->where('slug', $slug)->first();
            if ($record && !empty($record['settings'])) {
                return json_decode($record['settings'], true) ?: [];
            }
        } catch (\Exception $e) {}

        return [];
    }

    public function savePluginSettings(string $slug, array $settings): bool
    {
        try {
            QueryBuilder::table('plugins')->where('slug', $slug)->update([
                'settings' => json_encode($settings)
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }
}

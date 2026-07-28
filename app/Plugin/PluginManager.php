<?php

namespace Axer\Plugin;

use Axer\Core\Logger;
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

    /**
     * Plugins currently registered for this request. Was previously read
     * via ReflectionClass::getStaticPropertyValue() against the protected
     * $activePlugins property (PluginService::init()) — this is the public
     * accessor that made that reflection unnecessary.
     */
    public function getActivePlugins(): array
    {
        return self::$activePlugins;
    }

    public function getInstalledPlugins(): array
    {
        $dir = BASE_PATH . '/content/plugins';
        if (!is_dir($dir)) {
            return [];
        }

        // One query for every plugin's row, rather than one query per
        // folder inside the loop below.
        $rows = [];

        try {
            foreach (QueryBuilder::table('plugins')->get() as $row) {
                $rows[$row['slug']] = $row;
            }
        } catch (\Throwable $e) {
            // A missing `plugins` table or dead connection used to be
            // swallowed silently, which looked identical to "every plugin
            // is inactive" — impossible to tell apart without a log line.
            Logger::warning('Could not read installed plugins: ' . $e->getMessage());
        }

        $plugins = [];
        $folders = scandir($dir);

        foreach ($folders as $folder) {
            if ($folder === '.' || $folder === '..' || !is_dir($dir . '/' . $folder)) {
                continue;
            }

            if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $folder)) {
                continue;
            }

            $manifestPath = $dir . '/' . $folder . '/plugin.json';
            if (!file_exists($manifestPath)) {
                continue;
            }

            $json = json_decode(file_get_contents($manifestPath), true) ?: [];
            $dbRecord = $rows[$folder] ?? null;

            $plugins[] = [
                'slug' => $folder,
                'name' => $json['name'] ?? ucfirst($folder),
                'description' => $json['description'] ?? '',
                'version' => $json['version'] ?? '1.0.0',
                'author' => $json['author'] ?? 'Unknown',
                'is_active' => $dbRecord ? (bool) $dbRecord['is_active'] : false,
                'settings' => $dbRecord ? (json_decode($dbRecord['settings'] ?? '[]', true) ?: []) : [],
                // The manifest is the source of truth for the config-form
                // shape — a DB-stored copy is kept in sync on activate()
                // only so the theme customizer's pattern (schema travels
                // with the row) stays consistent; the manifest always wins
                // here since it doesn't require the plugin to be active.
                'settings_schema' => $json['settings_schema'] ?? [],
            ];
        }

        return $plugins;
    }

    public function loadPlugin(string $slug): bool
    {
        if (!$this->isValidSlug($slug)) {
            return false;
        }

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
        if (!$this->isValidSlug($slug)) {
            return false;
        }

        try {
            $pluginInfo = $this->scanPlugin($slug);
            $existing = QueryBuilder::table('plugins')->where('slug', $slug)->first();

            if ($existing) {
                QueryBuilder::table('plugins')->where('slug', $slug)->update([
                    'is_active' => 1,
                    // Refresh metadata from the manifest, the way
                    // ThemeService::activateTheme() does — otherwise a
                    // plugin upgraded on disk keeps a stale name/schema
                    // forever once its row exists.
                    'name' => $pluginInfo['name'] ?? $slug,
                    'description' => $pluginInfo['description'] ?? '',
                    'version' => $pluginInfo['version'] ?? '1.0.0',
                    'author' => $pluginInfo['author'] ?? 'Unknown',
                    'settings_schema' => json_encode($pluginInfo['settings_schema'] ?? [], JSON_UNESCAPED_UNICODE),
                ]);
            } else {
                QueryBuilder::table('plugins')->insert([
                    'slug' => $slug,
                    'name' => $pluginInfo['name'] ?? $slug,
                    'description' => $pluginInfo['description'] ?? '',
                    'version' => $pluginInfo['version'] ?? '1.0.0',
                    'author' => $pluginInfo['author'] ?? 'Unknown',
                    'is_active' => 1,
                    'settings' => json_encode([]),
                    'settings_schema' => json_encode($pluginInfo['settings_schema'] ?? [], JSON_UNESCAPED_UNICODE),
                ]);
            }

            $this->loadPlugin($slug);
            if (isset(self::$activePlugins[$slug])) {
                self::$activePlugins[$slug]->activate();
            }

            return true;
        } catch (\Throwable $e) {
            Logger::exception($e);

            return false;
        }
    }

    public function deactivate(string $slug): bool
    {
        if (!$this->isValidSlug($slug)) {
            return false;
        }

        try {
            if (isset(self::$activePlugins[$slug])) {
                self::$activePlugins[$slug]->deactivate();
            }

            QueryBuilder::table('plugins')->where('slug', $slug)->update(['is_active' => 0]);
            unset(self::$activePlugins[$slug]);
            return true;
        } catch (\Throwable $e) {
            Logger::exception($e);

            return false;
        }
    }

    public function getPluginSettings(string $slug): array
    {
        if (!$this->isValidSlug($slug)) {
            return [];
        }

        try {
            $record = QueryBuilder::table('plugins')->where('slug', $slug)->first();
            if ($record && !empty($record['settings'])) {
                return json_decode($record['settings'], true) ?: [];
            }
        } catch (\Throwable $e) {
            Logger::warning('Could not read settings for plugin ' . $slug . ': ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Insert-or-update a plugin's settings row.
     *
     * The old version was UPDATE-only: a plugin that had never been
     * activated had no row, so the UPDATE matched zero rows and this
     * still returned true — the settings form appeared to save
     * successfully while persisting nothing.
     */
    public function savePluginSettings(string $slug, array $settings): bool
    {
        if (!$this->isValidSlug($slug)) {
            return false;
        }

        try {
            $existing = QueryBuilder::table('plugins')->where('slug', $slug)->first();

            if ($existing) {
                QueryBuilder::table('plugins')->where('slug', $slug)->update([
                    'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                ]);

                return true;
            }

            $pluginInfo = $this->scanPlugin($slug);

            QueryBuilder::table('plugins')->insert([
                'slug' => $slug,
                'name' => $pluginInfo['name'] ?? $slug,
                'description' => $pluginInfo['description'] ?? '',
                'version' => $pluginInfo['version'] ?? '1.0.0',
                'author' => $pluginInfo['author'] ?? 'Unknown',
                'is_active' => 0,
                'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
                'settings_schema' => json_encode($pluginInfo['settings_schema'] ?? [], JSON_UNESCAPED_UNICODE),
            ]);

            return true;
        } catch (\Throwable $e) {
            Logger::exception($e);

            return false;
        }
    }

    protected function studly(string $value): string
    {
        return str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $value)));
    }

    public function scanPlugin(string $slug): array
    {
        if (!$this->isValidSlug($slug)) {
            return [];
        }

        $manifestPath = BASE_PATH . '/content/plugins/' . $slug . '/plugin.json';
        if (file_exists($manifestPath)) {
            return json_decode(file_get_contents($manifestPath), true) ?: [];
        }
        return [];
    }

    /**
     * Every method above builds a filesystem path (and loadPlugin() ends
     * in a require_once) from a slug that ultimately comes from a route
     * parameter — {slug} in /admin/plugins/settings/{slug} — with no
     * validation before this was added.
     */
    protected function isValidSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $slug) === 1;
    }
}

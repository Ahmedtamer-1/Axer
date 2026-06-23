<?php

namespace Axer\Services;

use Axer\Core\Event;

class PluginService
{
    protected array $plugins = [];

    /**
     * Initialize the plugin system and load active plugins
     */
    public function init(): void
    {
        $pluginsDir = BASE_PATH . '/content/plugins';
        if (!is_dir($pluginsDir)) {
            return;
        }

        // Ideally, we'd check the DB for active plugins. For now, load all valid ones.
        $items = scandir($pluginsDir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $pluginFile = $pluginsDir . '/' . $item . '/index.php';
            if (is_file($pluginFile)) {
                // Include the plugin so it can register its listeners and routes
                require_once $pluginFile;
                $this->plugins[] = $item;
            }
        }
        
        // Dispatch an event that plugins are loaded
        Event::dispatch('plugins.loaded', $this->plugins);
    }
}

<?php

namespace Axer\Services;

use Axer\Core\Event;

class PluginService
{
    protected array $plugins = [];

    public function init(): void
    {
        // Load active plugins via PluginManager
        $manager = new \Axer\Plugin\PluginManager();
        $manager->init();

        // This used to reach into PluginManager's protected static
        // $activePlugins via ReflectionClass::getStaticPropertyValue() —
        // a public accessor is simpler and doesn't depend on reflection
        // being able to bypass visibility.
        $activePlugins = array_keys($manager->getActivePlugins());

        // Dispatch an event that plugins are loaded
        Event::dispatch('plugins.loaded', $activePlugins);
    }
}

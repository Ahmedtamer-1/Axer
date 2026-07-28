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
        
        $activePlugins = array_keys(class_exists('\Axer\Plugin\PluginManager') ? (new \ReflectionClass('\Axer\Plugin\PluginManager'))->getStaticPropertyValue('activePlugins') : []);

        // Dispatch an event that plugins are loaded
        Event::dispatch('plugins.loaded', $activePlugins);
    }
}

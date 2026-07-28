<?php

namespace Axer\Plugin;

abstract class BasePlugin
{
    protected string $slug = '';
    protected array $settings = [];

    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
    }

    abstract public function register(): void;
    abstract public function activate(): void;
    abstract public function deactivate(): void;

    /**
     * Fallback for a plugin that declares its config form in PHP instead
     * of plugin.json's settings_schema (which PluginController prefers,
     * since it can be read without executing the plugin's code at all).
     */
    public function getSettingsSchema(): array
    {
        return [];
    }
}

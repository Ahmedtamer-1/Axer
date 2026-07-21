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

    public function getSettingsSchema(): array
    {
        return [];
    }

    public function getAdminRoutes(): array
    {
        return [];
    }

    public function getTemplateHooks(): array
    {
        return [];
    }

    public function getMigrations(): array
    {
        return [];
    }

    public function getCronJobs(): array
    {
        return [];
    }
}

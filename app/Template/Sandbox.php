<?php

namespace Axer\Template;

class Sandbox
{
    protected array $filters = [];
    protected array $tags = [];

    public function __construct()
    {
        $this->registerCoreFilters();
        $this->registerCoreTags();
    }

    public function registerFilter(string $name, callable $callback): void
    {
        $this->filters[$name] = $callback;
    }

    public function registerTag(string $name, callable $callback): void
    {
        $this->tags[$name] = $callback;
    }

    public function callFilter(string $name, ...$args)
    {
        if (isset($this->filters[$name])) {
            return call_user_func_array($this->filters[$name], $args);
        }
        throw new \Exception("Unknown filter: {$name}");
    }

    public function callTag(string $name, array $args = [])
    {
        if (isset($this->tags[$name])) {
            return call_user_func_array($this->tags[$name], $args);
        }
        throw new \Exception("Unknown tag: {$name}");
    }

    public function resolve($value, string $name)
    {
        // Add security: don't allow accessing certain globals or calling functions
        if (is_callable($value) && !is_string($value) && !is_array($value)) {
            return call_user_func($value);
        }
        return $value;
    }

    protected function registerCoreFilters(): void
    {
        Filters::register($this);
    }

    protected function registerCoreTags(): void
    {
        Tags::register($this);
    }
}

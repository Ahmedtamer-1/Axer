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

    public function hasFilter(string $name): bool
    {
        return isset($this->filters[$name]);
    }

    /**
     * An unknown filter should not take the page down. A theme author
     * typing `| titlecase` used to get a 500; now the value passes through
     * untouched and the mistake is logged.
     */
    public function callFilter(string $name, ...$args)
    {
        if (isset($this->filters[$name])) {
            return call_user_func_array($this->filters[$name], $args);
        }

        \Axer\Core\Logger::warning("Unknown template filter: {$name}");

        return $args[0] ?? '';
    }

    public function callTag(string $name, array $args = [])
    {
        if (isset($this->tags[$name])) {
            return call_user_func_array($this->tags[$name], $args);
        }

        \Axer\Core\Logger::warning("Unknown template tag: {$name}");

        return '';
    }

    /**
     * Read a property from an array or object without warning when it is
     * absent, and without ever reaching a method.
     */
    public function getProperty($subject, string $key)
    {
        if (is_array($subject)) {
            return $subject[$key] ?? null;
        }

        if ($subject instanceof \ArrayAccess) {
            return $subject->offsetExists($key) ? $subject[$key] : null;
        }

        if (is_object($subject)) {
            // Only public data, never callables — a template must not be
            // able to invoke arbitrary methods on a context object.
            $vars = get_object_vars($subject);

            return array_key_exists($key, $vars) && !is_callable($vars[$key]) ? $vars[$key] : null;
        }

        return null;
    }

    /**
     * Final value resolution. Closures supplied as context are invoked so
     * templates can use lazily computed values.
     */
    public function resolve($value)
    {
        if ($value instanceof \Closure) {
            return $value();
        }

        return $value;
    }

    /**
     * Guarantee something foreach-able.
     *
     * `{% for x in missing %}` compiled to `foreach (null as ...)`, a
     * fatal error in PHP 8. Any non-iterable now yields an empty list so
     * the loop simply renders nothing.
     */
    public function ensureIterable($value): iterable
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof \Traversable) {
            return $value;
        }

        return [];
    }

    /**
     * Stringify a value for output.
     *
     * `(string) $array` raises "Array to string conversion" and prints the
     * literal word "Array"; objects without __toString threw. Both now
     * degrade predictably.
     */
    public function toText($value): string
    {
        if ($value === null || is_bool($value)) {
            return $value ? '1' : '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        if ($value instanceof \Stringable) {
            return (string) $value;
        }

        if (is_array($value) || $value instanceof \JsonSerializable) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return '';
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

<?php

namespace Axer\Support;

/**
 * Dot-path helpers for nested settings arrays.
 *
 * Theme settings_schema field names are dot-paths ("colors.primary")
 * into a nested settings tree, while SettingsSchema::sanitize() works
 * against a flat dict keyed by the schema's field name. These convert
 * between the two shapes.
 */
class Arr
{
    public static function get(array $array, string $path, $default = null)
    {
        $segments = explode('.', $path);
        $current = $array;

        foreach ($segments as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    public static function set(array &$array, string $path, $value): void
    {
        $segments = explode('.', $path);
        $current = &$array;

        foreach ($segments as $index => $segment) {
            if ($index === count($segments) - 1) {
                $current[$segment] = $value;
                break;
            }

            if (!isset($current[$segment]) || !is_array($current[$segment])) {
                $current[$segment] = [];
            }

            $current = &$current[$segment];
        }
    }

    /**
     * A flat dict of every schema-declared dot-path pulled out of a nested
     * settings tree — the shape SettingsSchema::sanitize()'s $existing
     * parameter expects.
     */
    public static function flattenBySchema(array $nested, array $fields): array
    {
        $flat = [];

        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');

            if ($name === '') {
                continue;
            }

            $flat[$name] = self::get($nested, $name);
        }

        return $flat;
    }
}

<?php

namespace Axer\Support;

/**
 * Shared shape for a "settings form driven by a manifest" — used by both
 * the theme customizer (each theme's theme.json settings_schema) and
 * plugin configuration (each plugin's plugin.json settings_schema).
 *
 * A schema is a list of groups: [{name, settings: [{name, label, type,
 * ...}]}]. Some plugins still declare a flat, ungrouped list via
 * BasePlugin::getSettingsSchema() (the older PHP-based fallback) —
 * normalize() wraps that into a single unnamed group so callers only
 * ever deal with one shape.
 */
class SettingsSchema
{
    /**
     * @return array<int, array{name: string, settings: array}>
     */
    public static function normalizeGroups(array $schema): array
    {
        if ($schema === []) {
            return [];
        }

        $first = $schema[array_key_first($schema)];

        if (is_array($first) && array_key_exists('settings', $first)) {
            return $schema;
        }

        return [['name' => '', 'settings' => $schema]];
    }

    /**
     * @return array<int, array> Every field definition across all groups.
     */
    public static function fields(array $schema): array
    {
        $fields = [];

        foreach (self::normalizeGroups($schema) as $group) {
            foreach ($group['settings'] ?? [] as $field) {
                if (isset($field['name'])) {
                    $fields[] = $field;
                }
            }
        }

        return $fields;
    }

    /**
     * Validate and coerce posted values against a schema, dropping any key
     * the schema does not declare. A `password` field that was submitted
     * empty keeps its existing stored value instead of being wiped —
     * the form renders a masked placeholder rather than the real secret,
     * so an empty submission means "unchanged", not "clear this".
     *
     * @param array $posted  Raw $_POST['settings'] (or equivalent) values.
     * @param array $existing Previously stored values, for password fields.
     * @return array{values: array, missingRequired: string[]}
     */
    public static function sanitize(array $schema, array $posted, array $existing = []): array
    {
        $values = [];
        $missingRequired = [];

        foreach (self::fields($schema) as $field) {
            $name = (string) $field['name'];
            $type = $field['type'] ?? 'text';
            $raw = $posted[$name] ?? null;

            if ($type === 'password' && ($raw === null || $raw === '')) {
                if (array_key_exists($name, $existing)) {
                    $values[$name] = $existing[$name];
                }

                if (!empty($field['required']) && empty($existing[$name])) {
                    $missingRequired[] = (string) ($field['label'] ?? $name);
                }

                continue;
            }

            $value = self::coerce($type, $raw, $field);

            if (!empty($field['required']) && ($value === '' || $value === null)) {
                $missingRequired[] = (string) ($field['label'] ?? $name);
            }

            $values[$name] = $value;
        }

        return ['values' => $values, 'missingRequired' => $missingRequired];
    }

    protected static function coerce(string $type, $raw, array $field)
    {
        switch ($type) {
            case 'checkbox':
                return in_array($raw, ['1', 1, true, 'on', 'true'], true);

            case 'number':
                return $raw === null || $raw === '' ? null : (float) $raw;

            case 'select':
                $options = array_map('strval', $field['options'] ?? []);
                $value = (string) $raw;

                return in_array($value, $options, true) ? $value : ($options[0] ?? '');

            case 'color':
                $value = (string) $raw;

                return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : '';

            case 'url':
                $value = trim((string) $raw);

                return $value === '' || filter_var($value, FILTER_VALIDATE_URL) ? $value : '';

            case 'textarea':
            case 'text':
            case 'password':
            default:
                return trim((string) $raw);
        }
    }
}

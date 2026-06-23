<?php

namespace Lume\Services;

use Lume\Database\QueryBuilder;

class ThemeService
{
    public static function getActiveTheme(): ?array
    {
        try {
            $theme = QueryBuilder::table('themes')->where('is_active', 1)->first();
            if ($theme) {
                if (is_string($theme['settings'])) {
                    $theme['settings'] = json_decode($theme['settings'], true);
                }
                if (is_string($theme['settings_schema'])) {
                    $theme['settings_schema'] = json_decode($theme['settings_schema'], true);
                }
                return $theme;
            }
        } catch (\Exception $e) {
            // DB table might not be initialized
        }

        // Fallback or scan content/themes
        return self::scanTheme('default');
    }

    public static function scanTheme(string $slug): ?array
    {
        $path = BASE_PATH . '/content/themes/' . $slug . '/theme.json';
        if (!file_exists($path)) {
            return null;
        }

        $json = json_decode(file_get_contents($path), true);
        if (!$json) {
            return null;
        }

        return [
            'slug' => $slug,
            'name' => $json['name'] ?? ucfirst($slug),
            'description' => $json['description'] ?? '',
            'version' => $json['version'] ?? '1.0.0',
            'author' => $json['author'] ?? '',
            'author_url' => $json['author_url'] ?? '',
            'settings' => $json['settings'] ?? [],
            'settings_schema' => $json['settings_schema'] ?? []
        ];
    }

    public static function getAllThemes(): array
    {
        $themes = [];
        $dir = BASE_PATH . '/content/themes';
        
        if (!is_dir($dir)) {
            return [];
        }

        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || !is_dir($dir . '/' . $item)) {
                continue;
            }

            $theme = self::scanTheme($item);
            if ($theme) {
                // Check if active in database
                $dbTheme = null;
                try {
                    $dbTheme = QueryBuilder::table('themes')->where('slug', $item)->first();
                } catch (\Exception $e) {}
                
                $theme['is_active'] = $dbTheme ? (bool)$dbTheme['is_active'] : false;
                $themes[] = $theme;
            }
        }

        return $themes;
    }

    public static function activateTheme(string $slug): bool
    {
        try {
            $themeData = self::scanTheme($slug);
            if (!$themeData) {
                return false;
            }

            // Deactivate all
            QueryBuilder::table('themes')->update(['is_active' => 0]);

            // Check if exists
            $existing = QueryBuilder::table('themes')->where('slug', $slug)->first();
            if ($existing) {
                QueryBuilder::table('themes')->where('slug', $slug)->update(['is_active' => 1]);
            } else {
                QueryBuilder::table('themes')->insert([
                    'slug' => $slug,
                    'name' => $themeData['name'],
                    'description' => $themeData['description'],
                    'version' => $themeData['version'],
                    'author' => $themeData['author'],
                    'is_active' => 1,
                    'settings' => json_encode($themeData['settings']),
                    'settings_schema' => json_encode($themeData['settings_schema'])
                ]);
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public static function saveThemeSettings(string $slug, array $settings): bool
    {
        try {
            QueryBuilder::table('themes')->where('slug', $slug)->update([
                'settings' => json_encode($settings)
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

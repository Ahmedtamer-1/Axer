<?php

namespace Axer\Services;

use Axer\Core\Csrf;
use Axer\Core\Logger;
use Axer\Core\Session;
use Axer\Database\QueryBuilder;
use Axer\Template\Engine;
use Axer\Template\Filters;

class ThemeService
{
    /**
     * Per-request caches.
     *
     * getActiveTheme() ran a database query every time it was called, and
     * it is called by render(), getGlobalContext() and each section render
     * — so a single page issued the same query five or six times. The
     * Engine was also rebuilt (and its Sandbox with it) for every section.
     */
    protected static ?array $activeTheme = null;
    protected static bool $activeThemeLoaded = false;
    protected static array $engines = [];
    protected static ?array $storeSettings = null;

    public static function getActiveTheme(): ?array
    {
        if (self::$activeThemeLoaded) {
            return self::$activeTheme;
        }

        self::$activeThemeLoaded = true;

        try {
            $theme = QueryBuilder::table('themes')->where('is_active', 1)->first();

            if ($theme !== null) {
                foreach (['settings', 'settings_schema'] as $key) {
                    if (isset($theme[$key]) && is_string($theme[$key])) {
                        $theme[$key] = json_decode($theme[$key], true) ?? [];
                    }
                }

                return self::$activeTheme = $theme;
            }
        } catch (\Throwable $e) {
            // The themes table may not exist yet (pre-install).
            Logger::warning('Could not load the active theme: ' . $e->getMessage());
        }

        return self::$activeTheme = self::scanTheme('default');
    }

    public static function scanTheme(string $slug): ?array
    {
        // Theme slugs reach here from route parameters.
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $slug)) {
            return null;
        }

        $path = BASE_PATH . '/content/themes/' . $slug . '/theme.json';

        if (!is_file($path)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (!is_array($json)) {
            Logger::warning("Theme {$slug} has an invalid theme.json");
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
            'settings_schema' => $json['settings_schema'] ?? [],
        ];
    }

    /**
     * A shared Engine per theme, so the Sandbox and its filter table are
     * built once per request rather than once per section.
     */
    public static function engine(?string $slug = null): Engine
    {
        $slug ??= self::getActiveTheme()['slug'] ?? 'default';

        if (isset(self::$engines[$slug])) {
            return self::$engines[$slug];
        }

        $engine = new Engine(
            [BASE_PATH . '/content/themes/' . $slug],
            BASE_PATH . '/storage/cache/templates'
        );

        $engine->addGlobals(self::getGlobalContext());

        return self::$engines[$slug] = $engine;
    }

    public static function render(string $template, array $data = []): string
    {
        $theme = self::getActiveTheme();
        $engine = self::engine($theme['slug'] ?? 'default');

        return $engine->render($template, $data);
    }

    public static function getGlobalContext(?array $theme = null): array
    {
        $theme ??= self::getActiveTheme();
        $store = self::storeSettings();

        // Keep the money filter in step with the configured currency
        // instead of hardcoding EGP in the filter itself.
        Filters::$currency = $store['currency'];

        Session::start();

        return [
            'store' => $store,
            'theme' => $theme['settings'] ?? [],
            'cart_count' => CartService::count(),
            'current_user' => $_SESSION['user'] ?? $_SESSION['admin_user'] ?? null,
            'csrf_token' => Csrf::token(),
            'current_year' => date('Y'),
        ];
    }

    /**
     * Store identity, read from the settings table.
     *
     * These were hardcoded to "Axer Store" / "EGP", so the name a merchant
     * entered in Settings never appeared anywhere on the storefront.
     */
    public static function storeSettings(): array
    {
        if (self::$storeSettings !== null) {
            return self::$storeSettings;
        }

        $defaults = [
            'name' => 'Axer Store',
            'tagline' => '',
            'currency' => 'EGP',
            'currency_symbol' => 'EGP ',
            'email' => '',
            'phone' => '',
        ];

        try {
            $rows = QueryBuilder::table('settings')->where('group', 'general')->get();

            foreach ($rows as $row) {
                $key = str_replace('store_', '', (string) $row['key']);

                if (array_key_exists($key, $defaults) && $row['value'] !== null && $row['value'] !== '') {
                    $defaults[$key] = $row['value'];
                }
            }

            if ($defaults['currency_symbol'] === 'EGP ' && $defaults['currency'] !== 'EGP') {
                $defaults['currency_symbol'] = $defaults['currency'] . ' ';
            }
        } catch (\Throwable $e) {
            Logger::warning('Could not load store settings: ' . $e->getMessage());
        }

        return self::$storeSettings = $defaults;
    }

    public static function getAllThemes(): array
    {
        $dir = BASE_PATH . '/content/themes';

        if (!is_dir($dir)) {
            return [];
        }

        // One query for every theme's activation state, rather than one
        // query inside the directory loop.
        $active = [];

        try {
            foreach (QueryBuilder::table('themes')->get() as $row) {
                $active[$row['slug']] = (bool) $row['is_active'];
            }
        } catch (\Throwable $e) {
            Logger::warning('Could not read installed themes: ' . $e->getMessage());
        }

        $themes = [];

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..' || !is_dir($dir . '/' . $item)) {
                continue;
            }

            $theme = self::scanTheme($item);

            if ($theme !== null) {
                $theme['is_active'] = $active[$item] ?? false;
                $themes[] = $theme;
            }
        }

        return $themes;
    }

    public static function activateTheme(string $slug): bool
    {
        $themeData = self::scanTheme($slug);

        if ($themeData === null) {
            return false;
        }

        try {
            // Deactivating everything and activating one row must not be
            // interruptible, or the store ends up with no active theme.
            QueryBuilder::transaction(static function () use ($slug, $themeData) {
                QueryBuilder::table('themes')->where('is_active', 1)->update(['is_active' => 0]);

                $existing = QueryBuilder::table('themes')->where('slug', $slug)->first();

                if ($existing !== null) {
                    QueryBuilder::table('themes')->where('slug', $slug)->update(['is_active' => 1]);
                    return;
                }

                QueryBuilder::table('themes')->insert([
                    'slug' => $slug,
                    'name' => $themeData['name'],
                    'description' => $themeData['description'],
                    'version' => $themeData['version'],
                    'author' => $themeData['author'],
                    'is_active' => 1,
                    'settings' => json_encode($themeData['settings'], JSON_UNESCAPED_UNICODE),
                    'settings_schema' => json_encode($themeData['settings_schema'], JSON_UNESCAPED_UNICODE),
                ]);
            });

            self::flushCaches();

            return true;
        } catch (\Throwable $e) {
            Logger::exception($e);

            return false;
        }
    }

    public static function saveThemeSettings(string $slug, array $settings): bool
    {
        try {
            QueryBuilder::table('themes')->where('slug', $slug)->update([
                'settings' => json_encode($settings, JSON_UNESCAPED_UNICODE),
            ]);

            // Theme settings are compiled into the templates, so the cache
            // has to go with them — otherwise a saved colour change did not
            // appear until the template file itself was touched.
            self::flushCaches();

            return true;
        } catch (\Throwable $e) {
            Logger::exception($e);

            return false;
        }
    }

    /**
     * Drop compiled templates and the per-request memo.
     */
    public static function flushCaches(): void
    {
        self::$activeTheme = null;
        self::$activeThemeLoaded = false;
        self::$storeSettings = null;

        foreach (self::$engines as $engine) {
            $engine->clearCache();
        }

        self::$engines = [];
    }
}

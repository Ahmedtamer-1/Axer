<?php

namespace Axer\Controllers\Admin;

use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Session;
use Axer\Database\QueryBuilder;
use Axer\Services\ThemeService;

class ThemeController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->checkAuth($request, 'superadmin');

        $themes = ThemeService::getAllThemes();

        return $this->renderAdmin('themes/index', [
            'title' => 'Theme Settings',
            'themes' => $themes
        ]);
    }

    /**
     * A theme's preview image, keyed by its own slug rather than "whatever
     * theme is currently active" — the public /theme-assets/{path} route
     * (Filters::asset_url) always resolves against the active theme, which
     * doesn't work for a grid that shows every installed theme at once,
     * including inactive ones.
     */
    public function screenshot(Request $request, string $slug): Response
    {
        $this->checkAuth($request, 'superadmin');

        $theme = ThemeService::scanTheme($slug);

        if ($theme === null || empty($theme['screenshot'])) {
            return Response::notFound();
        }

        $themeRoot = realpath(BASE_PATH . '/content/themes/' . $slug);

        if ($themeRoot === false) {
            return Response::notFound();
        }

        $file = realpath($themeRoot . '/' . ltrim((string) $theme['screenshot'], '/'));

        $mimeTypes = [
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
        ];
        $extension = $file !== false ? strtolower(pathinfo($file, PATHINFO_EXTENSION)) : '';

        if (
            $file === false
            || !str_starts_with($file, $themeRoot . DIRECTORY_SEPARATOR)
            || !is_file($file)
            || !isset($mimeTypes[$extension])
        ) {
            return Response::notFound();
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return Response::notFound();
        }

        return (new Response($content))
            ->setHeader('Content-Type', $mimeTypes[$extension])
            ->cache(3600);
    }

    public function activate(Request $request, string $slug): Response
    {
        $this->checkAuth($request, 'superadmin');

        if (ThemeService::activateTheme($slug)) {
            Session::flash('success', 'Theme activated.');
        } else {
            Session::flash('error', 'Could not activate that theme.');
        }

        return $this->redirect('/admin/themes');
    }

    public function customizer(Request $request, string $slug): Response
    {
        $this->checkAuth($request, 'superadmin');
        
        $theme = ThemeService::scanTheme($slug);
        if (!$theme) {
            return $this->redirect('/admin/themes');
        }

        // Load this theme's own saved settings if it has ever been
        // activated before — not just when it's the currently active one,
        // otherwise customizing a theme you'd previously configured and
        // then switched away from silently reset the form to manifest
        // defaults.
        try {
            $row = QueryBuilder::table('themes')->where('slug', $slug)->first();
            if ($row && !empty($row['settings'])) {
                $theme['settings'] = json_decode($row['settings'], true) ?: $theme['settings'];
            }
        } catch (\Throwable $e) {
            // Fall back to manifest defaults.
        }

        if ($request->method() === 'POST') {
            $settings = $request->post('settings') ?? [];

            if (ThemeService::saveThemeSettings($slug, is_array($settings) ? $settings : [])) {
                Session::flash('success', 'Theme settings saved.');
            } else {
                Session::flash('error', 'Could not save theme settings.');
            }

            return $this->redirect('/admin/themes/customize/' . $slug);
        }

        return $this->renderAdmin('themes/customizer', [
            'title' => 'Customize - ' . htmlspecialchars($theme['name']),
            'theme' => $theme
        ]);
    }
}

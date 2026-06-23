<?php

namespace Lume\Controllers\Admin;

use Lume\Core\Request;
use Lume\Core\Response;
use Lume\Services\ThemeService;

class ThemeController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->checkAuth($request);
        
        $themes = ThemeService::getAllThemes();
        
        return $this->renderAdmin('themes/index', [
            'title' => 'Theme Settings',
            'themes' => $themes
        ]);
    }

    public function activate(Request $request, string $slug): Response
    {
        $this->checkAuth($request);
        
        ThemeService::activateTheme($slug);
        
        return $this->redirect('/admin/themes');
    }

    public function customizer(Request $request, string $slug): Response
    {
        $this->checkAuth($request);
        
        $theme = ThemeService::scanTheme($slug);
        if (!$theme) {
            return $this->redirect('/admin/themes');
        }

        // Get saved settings from database if exists
        $activeTheme = ThemeService::getActiveTheme();
        if ($activeTheme && $activeTheme['slug'] === $slug) {
            $theme['settings'] = $activeTheme['settings'];
        }

        if ($request->method() === 'POST') {
            $settings = $request->post('settings') ?? [];
            ThemeService::saveThemeSettings($slug, $settings);
            return $this->redirect('/admin/themes/customize/' . $slug);
        }

        return $this->renderAdmin('themes/customizer', [
            'title' => 'Customize - ' . htmlspecialchars($theme['name']),
            'theme' => $theme
        ]);
    }
}

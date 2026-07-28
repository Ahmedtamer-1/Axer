<?php

use Axer\Auth\Middleware\AdminAuthMiddleware;
use Axer\Core\Router;
use Axer\Controllers\Admin\DashboardController;
use Axer\Controllers\Admin\PageController;
use Axer\Controllers\Admin\ThemeController;

/** @var Router $router */

$router->group([
    'prefix' => '/admin'
], function (Router $router) {

    // Auth — must stay outside the guarded group below, or nobody could
    // ever reach the login screen to establish a session.
    $router->get('/login', [\Axer\Controllers\Admin\AuthController::class, 'showLogin']);
    $router->post('/login', [\Axer\Controllers\Admin\AuthController::class, 'login']);
    $router->get('/logout', [\Axer\Controllers\Admin\AuthController::class, 'logout']);

    /**
     * Everything else requires a logged-in admin. This used to be
     * enforced only by each controller method calling checkAuth() by
     * hand (30 call sites) — a forgotten call silently left a page
     * public. Attaching the guard to the whole group makes that the
     * default instead of opt-in.
     */
    $router->group(['middleware' => [new AdminAuthMiddleware('admin')]], function (Router $router) {

        // Dashboard
        $router->get('/dashboard', [DashboardController::class, 'index']);

        // Products CRUD & Spreadsheet API
        $router->get('/products', [\Axer\Controllers\Admin\ProductController::class, 'index']);
        $router->get('/products/create', [\Axer\Controllers\Admin\ProductController::class, 'create']);
        $router->post('/products/create', [\Axer\Controllers\Admin\ProductController::class, 'create']);
        $router->get('/products/edit/{id}', [\Axer\Controllers\Admin\ProductController::class, 'edit']);
        $router->post('/products/edit/{id}', [\Axer\Controllers\Admin\ProductController::class, 'edit']);
        $router->post('/products/delete/{id}', [\Axer\Controllers\Admin\ProductController::class, 'delete']);
        $router->post('/api/products/update/{id}', [\Axer\Controllers\Admin\ProductApiController::class, 'updateField']);
        $router->post('/api/products/quick-create', [\Axer\Controllers\Admin\ProductApiController::class, 'quickCreate']);
        $router->post('/api/products/bulk-delete', [\Axer\Controllers\Admin\ProductApiController::class, 'bulkDelete']);

        // Orders CRUD
        $router->get('/orders', [\Axer\Controllers\Admin\OrderController::class, 'index']);
        $router->get('/orders/view/{id}', [\Axer\Controllers\Admin\OrderController::class, 'view']);
        $router->post('/orders/update/{id}', [\Axer\Controllers\Admin\OrderController::class, 'updateStatus']);

        // Media
        $router->get('/media', [\Axer\Controllers\Admin\MediaController::class, 'index']);
        $router->post('/media/upload', [\Axer\Controllers\Admin\MediaController::class, 'upload']);
        $router->post('/media/delete', [\Axer\Controllers\Admin\MediaController::class, 'delete']);
        $router->get('/api/media', [\Axer\Controllers\Admin\MediaController::class, 'apiList']);

        // Pages CRUD
        $router->get('/pages', [PageController::class, 'index']);

        $router->get('/pages/create', [PageController::class, 'create']);
        $router->post('/pages/create', [PageController::class, 'create']);

        $router->get('/pages/edit/{id}', [PageController::class, 'edit']);
        $router->post('/pages/edit/{id}', [PageController::class, 'edit']);

        $router->delete('/pages/delete/{id}', [PageController::class, 'delete']);
        $router->post('/pages/delete/{id}', [PageController::class, 'delete']); // Form method override support

        // Visual Page Builder
        $router->get('/pages/builder/{id}', [PageController::class, 'builder']);
        $router->post('/pages/builder/save/{id}', [PageController::class, 'saveBuilder']);
        $router->post('/pages/builder/preview', [PageController::class, 'preview']);
    });

    /**
     * Store-critical configuration — credentials, payment/tracking
     * integrations, and the active theme — is restricted to superadmin.
     * Previously `admin` and `superadmin` were identical everywhere.
     */
    $router->group(['middleware' => [new AdminAuthMiddleware('superadmin')]], function (Router $router) {

        // Settings
        $router->get('/settings', [\Axer\Controllers\Admin\SettingsController::class, 'index']);
        $router->post('/settings', [\Axer\Controllers\Admin\SettingsController::class, 'index']);

        // Themes
        $router->get('/themes', [ThemeController::class, 'index']);
        $router->post('/themes/activate/{slug}', [ThemeController::class, 'activate']);
        $router->get('/themes/customize/{slug}', [ThemeController::class, 'customizer']);
        $router->post('/themes/customize/{slug}', [ThemeController::class, 'customizer']);

        // Plugins & Marketplace
        $router->get('/plugins', [\Axer\Controllers\Admin\PluginController::class, 'index']);
        $router->get('/plugins/marketplace', [\Axer\Controllers\Admin\PluginController::class, 'marketplace']);
        $router->post('/plugins/activate/{slug}', [\Axer\Controllers\Admin\PluginController::class, 'activate']);
        $router->post('/plugins/deactivate/{slug}', [\Axer\Controllers\Admin\PluginController::class, 'deactivate']);
        $router->get('/plugins/settings/{slug}', [\Axer\Controllers\Admin\PluginController::class, 'settings']);
        $router->post('/plugins/settings/{slug}', [\Axer\Controllers\Admin\PluginController::class, 'settings']);
    });
});

<?php

use Lume\Core\Router;
use Lume\Controllers\Admin\DashboardController;
use Lume\Controllers\Admin\PageController;
use Lume\Controllers\Admin\ThemeController;

/** @var Router $router */

$router->group([
    'prefix' => '/admin'
], function (Router $router) {
    
    // Auth
    $router->get('/login', [\Lume\Controllers\Admin\AuthController::class, 'login']);
    $router->post('/login', [\Lume\Controllers\Admin\AuthController::class, 'login']);
    $router->get('/logout', [\Lume\Controllers\Admin\AuthController::class, 'logout']);

    // Dashboard
    $router->get('/dashboard', [DashboardController::class, 'index']);

    // Products CRUD
    $router->get('/products', [\Lume\Controllers\Admin\ProductController::class, 'index']);
    $router->get('/products/create', [\Lume\Controllers\Admin\ProductController::class, 'create']);
    $router->post('/products/create', [\Lume\Controllers\Admin\ProductController::class, 'create']);
    $router->get('/products/edit/{id}', [\Lume\Controllers\Admin\ProductController::class, 'edit']);
    $router->post('/products/edit/{id}', [\Lume\Controllers\Admin\ProductController::class, 'edit']);
    $router->post('/products/delete/{id}', [\Lume\Controllers\Admin\ProductController::class, 'delete']);

    // Orders CRUD
    $router->get('/orders', [\Lume\Controllers\Admin\OrderController::class, 'index']);
    $router->get('/orders/view/{id}', [\Lume\Controllers\Admin\OrderController::class, 'view']);
    $router->post('/orders/update/{id}', [\Lume\Controllers\Admin\OrderController::class, 'updateStatus']);

    // Settings
    $router->get('/settings', [\Lume\Controllers\Admin\SettingsController::class, 'index']);
    $router->post('/settings', [\Lume\Controllers\Admin\SettingsController::class, 'index']);

    // Media
    $router->get('/media', [\Lume\Controllers\Admin\MediaController::class, 'index']);
    $router->post('/media/upload', [\Lume\Controllers\Admin\MediaController::class, 'upload']);
    $router->post('/media/delete', [\Lume\Controllers\Admin\MediaController::class, 'delete']);
    $router->get('/api/media', [\Lume\Controllers\Admin\MediaController::class, 'apiList']);

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

    // Themes
    $router->get('/themes', [ThemeController::class, 'index']);
    $router->post('/themes/activate/{slug}', [ThemeController::class, 'activate']);
    $router->get('/themes/customize/{slug}', [ThemeController::class, 'customizer']);
    $router->post('/themes/customize/{slug}', [ThemeController::class, 'customizer']);

    // Plugins & Marketplace
    $router->get('/plugins', [\Lume\Controllers\Admin\PluginController::class, 'index']);

});

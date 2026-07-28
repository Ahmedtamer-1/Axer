<?php

use Axer\Core\Router;
use Axer\Controllers\Api\AuthController;
use Axer\Controllers\Api\ProductController;
use Axer\Controllers\Api\CartController;
use Axer\Controllers\Api\OrderController;
use Axer\Controllers\Api\UserController;
use Axer\Controllers\Api\WebhookController;
use Axer\Auth\Middleware\CorsMiddleware;
use Axer\Auth\Middleware\AuthMiddleware;

/** @var Router $router */

$router->group([
    'prefix' => '/api/v1',
    'middleware' => [CorsMiddleware::class]
], function (Router $router) {

    // Public routes
    $router->get('/products', [ProductController::class, 'index']);
    $router->get('/products/{id}', [ProductController::class, 'show']);

    $router->get('/cart', [CartController::class, 'index']);
    $router->post('/cart/add', [CartController::class, 'add']);

    // Auth — implemented from the start (rate-limited, Argon2id, timing-safe)
    // but never routed anywhere, so there was no way to reach it.
    $router->post('/auth/login', [AuthController::class, 'login']);
    $router->post('/auth/register', [AuthController::class, 'register']);

    // Webhooks
    $router->post('/webhooks/paymob', [WebhookController::class, 'paymob']);
    
    // Protected routes
    $router->group([
        'middleware' => [AuthMiddleware::class]
    ], function (Router $router) {
        $router->post('/orders', [OrderController::class, 'create']);
        $router->get('/user/profile', [UserController::class, 'profile']);
    });
    
});

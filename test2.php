<?php
session_start();
define('BASE_PATH', __DIR__);
require 'app/Core/App.php';
$app = new \Lume\Core\App();
$request = new \Lume\Core\Request();
$router = new \Lume\Core\Router($app);
require 'config/routes.php';
$response = renderStorefrontPage($request, 'home');
echo $response->getContent();


<?php

declare(strict_types=1);
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Define root path
define('BASE_PATH', __DIR__);

// Load the application bootstrap
require_once BASE_PATH . '/app/Core/App.php';

use Axer\Core\App;

// Bootstrap the application
$app = new App();
$app->run();

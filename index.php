<?php

declare(strict_types=1);

// Define root path
define('BASE_PATH', __DIR__);

// Load the application bootstrap
require_once BASE_PATH . '/app/Core/App.php';

use Lume\Core\App;

// Bootstrap the application
$app = new App();
$app->run();

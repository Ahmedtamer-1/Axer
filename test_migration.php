<?php
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/app/Core/App.php';

try {
    $app = new \Lume\Core\App();
    $migration = new \Lume\Database\Migration();
    $migration->run(BASE_PATH . '/database/migrations');
} catch (\Exception $e) {
    echo "Fatal: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}

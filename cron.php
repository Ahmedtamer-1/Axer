<?php

// cron.php
// Setup via crontab: * * * * * php /path/to/cron.php

define('BASE_PATH', __DIR__);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script can only be run from the command line.');
}

require_once BASE_PATH . '/app/Core/App.php';

$app = new \Lume\Core\App();

// Dispatch cron event to any listening plugins or internal services
\Lume\Core\Event::dispatch('cron.run');

echo "[" . date('Y-m-d H:i:s') . "] Lume cron jobs executed successfully.\n";

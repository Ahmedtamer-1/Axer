<?php
require 'index.php'; // This should load the autoloader

try {
    $engine = new \Lume\Template\Engine(['content/themes/default'], 'storage/cache');
    echo $engine->render('layouts/theme');
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}

<?php
/**
 * Axer CMS - Database Updater
 * Drop this file in your root folder and visit /update_db.php in your browser
 * to apply any missing database updates/migrations.
 */

$basePath = __DIR__;
$envPath = $basePath . '/config/.env';

if (!file_exists($envPath)) {
    die("<h3>Error: Axer is not installed!</h3><p>Could not find config/.env. Please run install.php first.</p>");
}

// Define BASE_PATH if not defined
if (!defined('BASE_PATH')) {
    define('BASE_PATH', $basePath);
}

try {
    // Bootstrap the Axer App
    require_once BASE_PATH . '/app/Core/App.php';
    $app = new \Axer\Core\App();

    echo "<h2>Axer Database Updater</h2>";
    echo "<p>Checking for missing database updates...</p>";

    // Run migrations
    $migration = new \Axer\Database\Migration();
    
    ob_start();
    $migration->run(BASE_PATH . '/database/migrations');
    $output = ob_get_clean();

    echo "<pre style='background: #1e293b; color: #a7f3d0; padding: 15px; border-radius: 5px;'>" . htmlspecialchars($output) . "</pre>";

    echo "<p style='color: green; font-weight: bold;'>Update complete! Your database is now perfectly up to date.</p>";
    echo "<p>You should delete this file (<code>update_db.php</code>) for security reasons.</p>";

} catch (Exception $e) {
    echo "<h3>Error updating database:</h3>";
    echo "<pre style='color: red;'>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

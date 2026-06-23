<?php
session_start();
$basePath = __DIR__;
$envPath = $basePath . '/config/.env';

if (file_exists($envPath)) {
    die("Lume is already installed. Cannot clean database.");
}

if (!isset($_SESSION['db_host'])) {
    die("No database session found. Please start install.php first.");
}

try {
    $pdo = new PDO("mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4", $_SESSION['db_user'], $_SESSION['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Disable foreign key checks to drop tables easily
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

    // Fetch all tables
    $stmt = $pdo->query("SELECT concat('DROP TABLE IF EXISTS `', table_name, '`;')
                          FROM information_schema.tables
                          WHERE table_schema = '{$_SESSION['db_name']}'");
    $dropQueries = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($dropQueries as $query) {
        $pdo->exec($query);
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

    echo "<h1>Database Reset Successful</h1>";
    echo "<p>All tables have been dropped. You can now <a href='install.php?step=3'>resume the installation</a>.</p>";

} catch (PDOException $e) {
    echo "Error cleaning database: " . $e->getMessage();
}

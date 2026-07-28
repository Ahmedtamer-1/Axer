<?php

namespace Axer\Database;

use PDO;
use PDOException;
use Axer\Core\Config;
use Axer\Core\Logger;

class Connection
{
    protected static ?PDO $instance = null;

    public static function getInstance(Config $config): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host     = $config->get('DB_HOST', '127.0.0.1');
        $port     = $config->get('DB_PORT', '3306');
        $database = $config->get('DB_DATABASE', 'axer');
        $username = $config->get('DB_USERNAME', 'root');
        $password = $config->get('DB_PASSWORD', '');
        $charset  = $config->get('DB_CHARSET', 'utf8mb4');

        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Real prepared statements, not client-side interpolation.
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Reuse the statement cache across identical queries.
            PDO::ATTR_STRINGIFY_FETCHES  => false,
            PDO::MYSQL_ATTR_INIT_COMMAND =>
                "SET SESSION sql_mode='STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION', time_zone='+00:00'",
        ];

        try {
            self::$instance = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            // The original message contains the DSN (host, port, database
            // name) and sometimes the username. It was being concatenated
            // into an exception that the error handler rendered straight to
            // the browser. Log the detail, surface a generic message.
            Logger::error('Database connection failed', [
                'message' => $e->getMessage(),
                'host' => $host,
                'database' => $database,
            ]);

            throw new \RuntimeException('Database connection failed.', 0, $e);
        }

        return self::$instance;
    }

    /**
     * Return the active handle, building it from config only if one has
     * not already been supplied. This lets QueryBuilder stay usable
     * without a booted App (installer scripts, CLI tasks, tests).
     */
    public static function resolve(): PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $config = \Axer\Core\App::getInstance()->getContainer()->get(Config::class);

        return self::getInstance($config);
    }

    /**
     * Inject a handle. Used by the installer (which changes credentials
     * mid-process) and by the test suite (which runs against SQLite).
     */
    public static function setInstance(?PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /**
     * Drop the cached handle so the next call reconnects.
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}

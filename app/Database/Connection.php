<?php

namespace Lume\Database;

use PDO;
use PDOException;
use Lume\Core\Config;

class Connection
{
    protected static ?PDO $instance = null;

    public static function getInstance(Config $config): PDO
    {
        if (self::$instance === null) {
            $host = $config->get('DB_HOST', '127.0.0.1');
            $port = $config->get('DB_PORT', '3306');
            $database = $config->get('DB_DATABASE', 'lume');
            $username = $config->get('DB_USERNAME', 'root');
            $password = $config->get('DB_PASSWORD', '');
            $charset = $config->get('DB_CHARSET', 'utf8mb4');

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset={$charset}";

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                throw new \Exception("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }
}

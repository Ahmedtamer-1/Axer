<?php

namespace Axer\Database;

use Axer\Core\App;
use Axer\Core\Config;

class Migration
{
    protected \PDO $pdo;

    public function __construct()
    {
        $config = App::getInstance()->getContainer()->get(Config::class);
        $this->pdo = Connection::getInstance($config);
        $this->createMigrationsTable();
    }

    protected function createMigrationsTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `migrations` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `batch` INT UNSIGNED NOT NULL,
            `migrated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $this->pdo->exec($sql);
    }

    public function getRanMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM migrations ORDER BY batch ASC, migration ASC");
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getNextBatchNumber(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) FROM migrations");
        $batch = $stmt->fetchColumn();
        return $batch ? (int)$batch + 1 : 1;
    }

    public function run(string $path): void
    {
        $files = glob($path . '/*.php');
        if (!$files) {
            echo "No migrations found.\n";
            return;
        }
        
        sort($files);
        $ran = $this->getRanMigrations();
        $batch = $this->getNextBatchNumber();
        $migrated = false;

        foreach ($files as $file) {
            $name = basename($file, '.php');
            
            if (!in_array($name, $ran)) {
                require_once $file;
                
                // Class name must be derived from file name e.g. 001_create_users.php -> CreateUsers
                $className = $this->getClassName($name);
                
                if (class_exists($className)) {
                    $migration = new $className();
                    echo "Migrating: {$name}\n";
                    
                    try {
                        $migration->up($this->pdo);
                        
                        $stmt = $this->pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)");
                        $stmt->execute([$name, $batch]);
                        
                        echo "Migrated:  {$name}\n";
                        $migrated = true;
                    } catch (\Exception $e) {
                        throw new \Exception("Error migrating {$name}: " . $e->getMessage(), 0, $e);
                    }
                }
            }
        }
        
        if (!$migrated) {
            echo "Nothing to migrate.\n";
        }
    }

    protected function getClassName(string $name): string
    {
        // Strip out the numbers and underscores at the beginning
        $name = preg_replace('/^\d+_/', '', $name);
        return str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    }
}

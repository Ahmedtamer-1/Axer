<?php
class CreateSettings {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `group` VARCHAR(50) NOT NULL DEFAULT 'general',
            `key` VARCHAR(100) NOT NULL,
            `value` TEXT DEFAULT NULL,
            `type` VARCHAR(20) NOT NULL DEFAULT 'string',
            UNIQUE KEY unique_setting (`group`, `key`),
            INDEX idx_group (`group`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `settings`;");
    }
}

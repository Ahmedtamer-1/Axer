<?php
class CreateThemes {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `themes` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(100) NOT NULL UNIQUE,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `version` VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            `author` VARCHAR(255) DEFAULT NULL,
            `author_url` VARCHAR(500) DEFAULT NULL,
            `screenshot` VARCHAR(500) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 0,
            `settings` LONGTEXT DEFAULT NULL,
            `settings_schema` LONGTEXT DEFAULT NULL,
            `installed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `themes`;");
    }
}

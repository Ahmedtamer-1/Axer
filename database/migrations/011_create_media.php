<?php
class CreateMedia {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `media` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `filename` VARCHAR(255) NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `path` VARCHAR(500) NOT NULL,
            `mime_type` VARCHAR(100) NOT NULL,
            `size` INT UNSIGNED NOT NULL,
            `width` INT UNSIGNED DEFAULT NULL,
            `height` INT UNSIGNED DEFAULT NULL,
            `alt_text` VARCHAR(255) DEFAULT NULL,
            `folder` VARCHAR(100) DEFAULT 'general',
            `thumbnails` LONGTEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_folder (`folder`),
            INDEX idx_mime (`mime_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `media`;");
    }
}

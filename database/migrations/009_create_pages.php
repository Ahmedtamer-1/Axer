<?php
class CreatePages {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `pages` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL UNIQUE,
            `content` LONGTEXT DEFAULT NULL,
            `template` VARCHAR(100) DEFAULT 'page',
            `status` ENUM('published','draft') NOT NULL DEFAULT 'draft',
            `builder_data` LONGTEXT DEFAULT NULL,
            `seo_title` VARCHAR(255) DEFAULT NULL,
            `seo_description` TEXT DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `show_in_nav` TINYINT(1) NOT NULL DEFAULT 0,
            `metadata` LONGTEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (`slug`),
            INDEX idx_status (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `pages`;");
    }
}

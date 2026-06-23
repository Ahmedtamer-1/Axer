<?php

class CreateCategories
{
    public function up(\PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `parent_id` INT UNSIGNED DEFAULT NULL,
            `name` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL UNIQUE,
            `description` TEXT DEFAULT NULL,
            `image` VARCHAR(255) DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `seo_title` VARCHAR(255) DEFAULT NULL,
            `seo_description` TEXT DEFAULT NULL,
            `metadata` LONGTEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
            INDEX idx_slug (`slug`),
            INDEX idx_parent (`parent_id`),
            INDEX idx_active_sort (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `categories`;");
    }
}

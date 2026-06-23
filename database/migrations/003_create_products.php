<?php
class CreateProducts {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `products` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL UNIQUE,
            `description` TEXT DEFAULT NULL,
            `short_description` VARCHAR(500) DEFAULT NULL,
            `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `compare_price` DECIMAL(10,2) DEFAULT NULL,
            `cost_price` DECIMAL(10,2) DEFAULT NULL,
            `sku` VARCHAR(100) DEFAULT NULL,
            `barcode` VARCHAR(100) DEFAULT NULL,
            `stock` INT NOT NULL DEFAULT 0,
            `track_stock` TINYINT(1) NOT NULL DEFAULT 1,
            `weight` DECIMAL(8,2) DEFAULT NULL,
            `status` ENUM('active','draft','archived') NOT NULL DEFAULT 'draft',
            `featured` TINYINT(1) NOT NULL DEFAULT 0,
            `category_id` INT UNSIGNED DEFAULT NULL,
            `brand` VARCHAR(100) DEFAULT NULL,
            `tags` LONGTEXT DEFAULT NULL,
            `seo_title` VARCHAR(255) DEFAULT NULL,
            `seo_description` TEXT DEFAULT NULL,
            `metadata` LONGTEXT DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `sales_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
            INDEX idx_slug (`slug`),
            INDEX idx_status (`status`),
            INDEX idx_category (`category_id`),
            INDEX idx_featured (`featured`, `status`),
            INDEX idx_price (`price`),
            FULLTEXT idx_search (`name`, `description`, `short_description`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `products`;");
    }
}

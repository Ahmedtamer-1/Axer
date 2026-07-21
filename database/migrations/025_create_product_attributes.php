<?php

class CreateProductAttributes
{
    public function up(\PDO $pdo): void
    {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS `product_attributes` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `name` VARCHAR(100) NOT NULL,
                `slug` VARCHAR(100) NOT NULL UNIQUE,
                `type` ENUM('text','number','select','multi_select','boolean','date') NOT NULL DEFAULT 'text',
                `options` JSON DEFAULT NULL,
                `is_required` TINYINT(1) DEFAULT 0,
                `is_filterable` TINYINT(1) DEFAULT 0,
                `show_in_table` TINYINT(1) DEFAULT 1,
                `sort_order` INT DEFAULT 0,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

            CREATE TABLE IF NOT EXISTS `product_attribute_values` (
                `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `product_id` INT UNSIGNED NOT NULL,
                `attribute_id` INT UNSIGNED NOT NULL,
                `value` TEXT NOT NULL,
                FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
                FOREIGN KEY (`attribute_id`) REFERENCES `product_attributes`(`id`) ON DELETE CASCADE,
                UNIQUE KEY `uk_product_attr` (`product_id`, `attribute_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `product_attribute_values`");
        $pdo->exec("DROP TABLE IF EXISTS `product_attributes`");
    }
}

<?php
class CreateProductVariants {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `product_variants` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(255) DEFAULT NULL,
            `size` VARCHAR(50) DEFAULT NULL,
            `color_name` VARCHAR(50) DEFAULT NULL,
            `color_hex` VARCHAR(7) DEFAULT NULL,
            `sku` VARCHAR(100) DEFAULT NULL,
            `price_override` DECIMAL(10,2) DEFAULT NULL,
            `cost_price` DECIMAL(10,2) DEFAULT NULL,
            `stock` INT NOT NULL DEFAULT 0,
            `weight` DECIMAL(8,2) DEFAULT NULL,
            `image` VARCHAR(255) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT NOT NULL DEFAULT 0,
            `metadata` LONGTEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            UNIQUE KEY unique_variant (`product_id`, `size`, `color_name`),
            INDEX idx_product (`product_id`),
            INDEX idx_sku (`sku`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `product_variants`;");
    }
}

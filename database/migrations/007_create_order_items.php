<?php
class CreateOrderItems {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `order_items` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT UNSIGNED NOT NULL,
            `product_id` INT UNSIGNED DEFAULT NULL,
            `variant_id` INT UNSIGNED DEFAULT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `variant_name` VARCHAR(255) DEFAULT NULL,
            `sku` VARCHAR(100) DEFAULT NULL,
            `price` DECIMAL(10,2) NOT NULL,
            `cost_price` DECIMAL(10,2) DEFAULT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `total` DECIMAL(10,2) NOT NULL,
            `image` VARCHAR(500) DEFAULT NULL,
            `metadata` LONGTEXT DEFAULT NULL,
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
            INDEX idx_order (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `order_items`;");
    }
}

<?php
class CreateProductImages {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `product_images` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT UNSIGNED NOT NULL,
            `variant_id` INT UNSIGNED DEFAULT NULL,
            `url` VARCHAR(500) NOT NULL,
            `alt_text` VARCHAR(255) DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL,
            INDEX idx_product (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `product_images`;");
    }
}

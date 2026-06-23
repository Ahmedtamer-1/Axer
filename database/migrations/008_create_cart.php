<?php
class CreateCart {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `cart_items` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `session_id` VARCHAR(128) NOT NULL,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `product_id` INT UNSIGNED NOT NULL,
            `variant_id` INT UNSIGNED DEFAULT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `metadata` LONGTEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            INDEX idx_session (`session_id`),
            INDEX idx_user (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `cart_items`;");
    }
}

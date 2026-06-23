<?php
class CreateReviews {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `reviews` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `order_id` INT UNSIGNED DEFAULT NULL,
            `author_name` VARCHAR(200) NOT NULL,
            `rating` TINYINT UNSIGNED NOT NULL,
            `title` VARCHAR(255) DEFAULT NULL,
            `body` TEXT DEFAULT NULL,
            `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            INDEX idx_product (`product_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `reviews`;");
    }
}

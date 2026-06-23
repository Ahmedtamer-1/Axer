<?php
class CreateOrders {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `order_number` VARCHAR(20) NOT NULL UNIQUE,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `status` ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
            `payment_status` ENUM('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
            `payment_method` VARCHAR(50) DEFAULT NULL,
            `payment_ref` VARCHAR(255) DEFAULT NULL,
            `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `shipping_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `currency` VARCHAR(3) NOT NULL DEFAULT 'EGP',
            `coupon_code` VARCHAR(50) DEFAULT NULL,
            `customer_email` VARCHAR(255) NOT NULL,
            `customer_phone` VARCHAR(20) DEFAULT NULL,
            `customer_name` VARCHAR(200) NOT NULL,
            `shipping_address` LONGTEXT NOT NULL,
            `billing_address` LONGTEXT DEFAULT NULL,
            `tracking_number` VARCHAR(100) DEFAULT NULL,
            `tracking_url` VARCHAR(500) DEFAULT NULL,
            `shipping_carrier` VARCHAR(50) DEFAULT NULL,
            `customer_notes` TEXT DEFAULT NULL,
            `admin_notes` TEXT DEFAULT NULL,
            `pixel_sent` LONGTEXT DEFAULT NULL,
            `metadata` LONGTEXT DEFAULT NULL,
            `paid_at` TIMESTAMP NULL,
            `shipped_at` TIMESTAMP NULL,
            `delivered_at` TIMESTAMP NULL,
            `cancelled_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX idx_order_number (`order_number`),
            INDEX idx_user (`user_id`),
            INDEX idx_status (`status`),
            INDEX idx_payment_status (`payment_status`),
            INDEX idx_created (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `orders`;");
    }
}

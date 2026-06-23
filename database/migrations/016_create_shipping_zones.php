<?php
class CreateShippingZones {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `shipping_zones` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `regions` LONGTEXT NOT NULL,
            `type` ENUM('flat','weight','free','calculated') NOT NULL DEFAULT 'flat',
            `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `free_above` DECIMAL(10,2) DEFAULT NULL,
            `min_days` INT DEFAULT NULL,
            `max_days` INT DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `shipping_zones`;");
    }
}

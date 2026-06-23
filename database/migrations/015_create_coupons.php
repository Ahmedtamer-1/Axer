<?php
class CreateCoupons {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `coupons` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(50) NOT NULL UNIQUE,
            `type` ENUM('percentage','fixed','free_shipping') NOT NULL DEFAULT 'percentage',
            `value` DECIMAL(10,2) NOT NULL,
            `min_order` DECIMAL(10,2) DEFAULT NULL,
            `max_uses` INT UNSIGNED DEFAULT NULL,
            `uses_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `max_uses_per_user` INT UNSIGNED DEFAULT NULL,
            `applicable_to` ENUM('all','products','categories') NOT NULL DEFAULT 'all',
            `applicable_ids` LONGTEXT DEFAULT NULL,
            `starts_at` TIMESTAMP NULL,
            `expires_at` TIMESTAMP NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_code (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `coupons`;");
    }
}

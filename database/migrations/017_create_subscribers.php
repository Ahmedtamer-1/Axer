<?php
class CreateSubscribers {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `subscribers` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `name` VARCHAR(200) DEFAULT NULL,
            `status` ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
            `source` VARCHAR(50) DEFAULT 'website',
            `subscribed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `subscribers`;");
    }
}

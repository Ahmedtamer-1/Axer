<?php
class CreateActivityLog {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `activity_log` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `action` VARCHAR(100) NOT NULL,
            `entity_type` VARCHAR(50) DEFAULT NULL,
            `entity_id` INT UNSIGNED DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `metadata` LONGTEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX idx_user (`user_id`),
            INDEX idx_entity (`entity_type`, `entity_id`),
            INDEX idx_created (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `activity_log`;");
    }
}

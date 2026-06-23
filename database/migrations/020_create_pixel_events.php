<?php
class CreatePixelEvents {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `pixel_events` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `event_type` VARCHAR(50) NOT NULL,
            `platform` VARCHAR(20) NOT NULL,
            `event_data` LONGTEXT NOT NULL,
            `order_id` INT UNSIGNED DEFAULT NULL,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `session_id` VARCHAR(128) DEFAULT NULL,
            `sent_client` TINYINT(1) NOT NULL DEFAULT 0,
            `sent_server` TINYINT(1) NOT NULL DEFAULT 0,
            `response_code` INT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event (`event_type`, `platform`),
            INDEX idx_created (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `pixel_events`;");
    }
}

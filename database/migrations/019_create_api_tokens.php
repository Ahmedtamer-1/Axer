<?php
class CreateApiTokens {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `api_tokens` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `token_hash` VARCHAR(64) NOT NULL UNIQUE,
            `name` VARCHAR(100) NOT NULL DEFAULT 'default',
            `permissions` LONGTEXT DEFAULT NULL,
            `last_used_at` TIMESTAMP NULL,
            `expires_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX idx_token (`token_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `api_tokens`;");
    }
}

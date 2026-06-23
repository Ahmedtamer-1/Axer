<?php

class CreateUsers
{
    public function up(\PDO $pdo): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `password_hash` VARCHAR(255) NULL,
            `first_name` VARCHAR(100) NOT NULL DEFAULT '',
            `last_name` VARCHAR(100) NOT NULL DEFAULT '',
            `phone` VARCHAR(20) DEFAULT NULL,
            `role` ENUM('customer','admin','superadmin') NOT NULL DEFAULT 'customer',
            `avatar` VARCHAR(255) DEFAULT NULL,
            `google_id` VARCHAR(255) DEFAULT NULL UNIQUE,
            `email_verified` TINYINT(1) NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `last_login_at` TIMESTAMP NULL,
            `metadata` LONGTEXT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (`email`),
            INDEX idx_role (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        
        $pdo->exec($sql);
        
        $sql = "CREATE TABLE IF NOT EXISTS `user_addresses` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `label` VARCHAR(50) DEFAULT 'default',
            `first_name` VARCHAR(100) NOT NULL,
            `last_name` VARCHAR(100) NOT NULL,
            `address_line1` VARCHAR(255) NOT NULL,
            `address_line2` VARCHAR(255) DEFAULT NULL,
            `city` VARCHAR(100) NOT NULL,
            `state` VARCHAR(100) DEFAULT NULL,
            `postal_code` VARCHAR(20) DEFAULT NULL,
            `country` VARCHAR(2) NOT NULL DEFAULT 'EG',
            `phone` VARCHAR(20) DEFAULT NULL,
            `is_default` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $pdo->exec($sql);
        
        $sql = "CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL,
            `token_hash` VARCHAR(64) NOT NULL,
            `expires_at` TIMESTAMP NOT NULL,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (`email`),
            INDEX idx_token (`token_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        
        $pdo->exec($sql);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DROP TABLE IF EXISTS `password_resets`;");
        $pdo->exec("DROP TABLE IF EXISTS `user_addresses`;");
        $pdo->exec("DROP TABLE IF EXISTS `users`;");
    }
}

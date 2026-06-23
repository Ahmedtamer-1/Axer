<?php

return new class extends \Axer\Database\Migration {
    public function up(): void
    {
        $this->db->execute("
            CREATE TABLE IF NOT EXISTS `rate_limits` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `ip_address` VARCHAR(45) NOT NULL,
                `endpoint` VARCHAR(255) NOT NULL,
                `attempts` INT NOT NULL DEFAULT 1,
                `lockout_time` TIMESTAMP NULL DEFAULT NULL,
                `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `uk_ip_endpoint` (`ip_address`, `endpoint`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
    }

    public function down(): void
    {
        $this->db->execute("DROP TABLE IF EXISTS `rate_limits`");
    }
};

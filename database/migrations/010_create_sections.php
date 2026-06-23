<?php
class CreateSections {
    public function up(\PDO $pdo): void {
        $sql = "CREATE TABLE IF NOT EXISTS `sections` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `page_id` INT UNSIGNED DEFAULT NULL,
            `location` VARCHAR(50) DEFAULT 'body',
            `type` VARCHAR(50) NOT NULL,
            `settings` LONGTEXT NOT NULL,
            `content` LONGTEXT DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE,
            INDEX idx_page_location (`page_id`, `location`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        $pdo->exec($sql);
    }
    public function down(\PDO $pdo): void {
        $pdo->exec("DROP TABLE IF EXISTS `sections`;");
    }
}

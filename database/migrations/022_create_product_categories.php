<?php

return new class extends \Axer\Database\Migration {
    public function up(): void
    {
        $this->db->execute("
            CREATE TABLE IF NOT EXISTS `product_categories` (
                `product_id` BIGINT UNSIGNED NOT NULL,
                `category_id` BIGINT UNSIGNED NOT NULL,
                PRIMARY KEY (`product_id`, `category_id`),
                CONSTRAINT `fk_pc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
                CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        
        // Optional: migrate existing category_id from products table to the new junction table.
        // We'll leave category_id on products table as a 'primary category' if they want, 
        // but typically you drop it. Let's just create the junction table for now.
    }

    public function down(): void
    {
        $this->db->execute("DROP TABLE IF EXISTS `product_categories`");
    }
};

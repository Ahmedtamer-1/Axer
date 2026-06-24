-- Axer CMS Database Snapshot
-- NOTE: This file is a snapshot dump for convenience. The authoritative schema source is in database/migrations/
-- NOTE: JSON columns are downgraded to LONGTEXT for broader compatibility with shared hosting environments (like Hostinger MariaDB versions that don't fully support native JSON).

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
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
            `metadata` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_email (`email`),
            INDEX idx_role (`role`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_addresses` (
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
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `password_resets` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL,
            `token_hash` VARCHAR(64) NOT NULL,
            `expires_at` TIMESTAMP NOT NULL,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (`email`),
            INDEX idx_token (`token_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `categories` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `parent_id` INT UNSIGNED DEFAULT NULL,
            `name` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL UNIQUE,
            `description` TEXT DEFAULT NULL,
            `image` VARCHAR(255) DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `seo_title` VARCHAR(255) DEFAULT NULL,
            `seo_description` TEXT DEFAULT NULL,
            `metadata` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`parent_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
            INDEX idx_slug (`slug`),
            INDEX idx_parent (`parent_id`),
            INDEX idx_active_sort (`is_active`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `products` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL UNIQUE,
            `description` TEXT DEFAULT NULL,
            `short_description` VARCHAR(500) DEFAULT NULL,
            `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `compare_price` DECIMAL(10,2) DEFAULT NULL,
            `cost_price` DECIMAL(10,2) DEFAULT NULL,
            `sku` VARCHAR(100) DEFAULT NULL,
            `barcode` VARCHAR(100) DEFAULT NULL,
            `stock` INT NOT NULL DEFAULT 0,
            `track_stock` TINYINT(1) NOT NULL DEFAULT 1,
            `weight` DECIMAL(8,2) DEFAULT NULL,
            `status` ENUM('active','draft','archived') NOT NULL DEFAULT 'draft',
            `featured` TINYINT(1) NOT NULL DEFAULT 0,
            `category_id` INT UNSIGNED DEFAULT NULL,
            `brand` VARCHAR(100) DEFAULT NULL,
            `tags` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `options_schema` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `seo_title` VARCHAR(255) DEFAULT NULL,
            `seo_description` TEXT DEFAULT NULL,
            `metadata` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `views_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `sales_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `deleted_at` TIMESTAMP NULL DEFAULT NULL,
            FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
            INDEX idx_slug (`slug`),
            INDEX idx_status (`status`),
            INDEX idx_category (`category_id`),
            INDEX idx_featured (`featured`, `status`),
            INDEX idx_price (`price`),
            FULLTEXT idx_search (`name`, `description`, `short_description`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_categories` (
            `product_id` INT UNSIGNED NOT NULL,
            `category_id` INT UNSIGNED NOT NULL,
            PRIMARY KEY (`product_id`, `category_id`),
            CONSTRAINT `fk_pc_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_pc_category` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `product_variants` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(255) DEFAULT NULL,
            `option1_value` VARCHAR(255) DEFAULT NULL,
            `option2_value` VARCHAR(255) DEFAULT NULL,
            `option3_value` VARCHAR(255) DEFAULT NULL,
            `size` VARCHAR(50) DEFAULT NULL,
            `color_name` VARCHAR(50) DEFAULT NULL,
            `color_hex` VARCHAR(7) DEFAULT NULL,
            `sku` VARCHAR(100) DEFAULT NULL,
            `barcode` VARCHAR(100) DEFAULT NULL,
            `price_override` DECIMAL(10,2) DEFAULT NULL,
            `compare_price` DECIMAL(10,2) DEFAULT NULL,
            `cost_price` DECIMAL(10,2) DEFAULT NULL,
            `stock` INT NOT NULL DEFAULT 0,
            `weight` DECIMAL(8,2) DEFAULT NULL,
            `image` VARCHAR(255) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT NOT NULL DEFAULT 0,
            `metadata` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            UNIQUE KEY unique_variant (`product_id`, `size`, `color_name`),
            INDEX idx_product (`product_id`),
            INDEX idx_sku (`sku`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `product_images` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT UNSIGNED NOT NULL,
            `variant_id` INT UNSIGNED DEFAULT NULL,
            `url` VARCHAR(500) NOT NULL,
            `alt_text` VARCHAR(255) DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`variant_id`) REFERENCES `product_variants`(`id`) ON DELETE SET NULL,
            INDEX idx_product (`product_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `orders` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `order_number` VARCHAR(20) NOT NULL UNIQUE,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `status` ENUM('pending','confirmed','processing','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
            `payment_status` ENUM('unpaid','pending','paid','failed','refunded') NOT NULL DEFAULT 'unpaid',
            `payment_method` VARCHAR(50) DEFAULT NULL,
            `payment_ref` VARCHAR(255) DEFAULT NULL,
            `subtotal` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `shipping_cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `tax_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `total` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `currency` VARCHAR(3) NOT NULL DEFAULT 'EGP',
            `coupon_code` VARCHAR(50) DEFAULT NULL,
            `customer_email` VARCHAR(255) NOT NULL,
            `customer_phone` VARCHAR(20) DEFAULT NULL,
            `customer_name` VARCHAR(200) NOT NULL,
            `shipping_address` LONGTEXT /* (JSON fallback) */ NOT NULL,
            `billing_address` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `tracking_number` VARCHAR(100) DEFAULT NULL,
            `tracking_url` VARCHAR(500) DEFAULT NULL,
            `shipping_carrier` VARCHAR(50) DEFAULT NULL,
            `customer_notes` TEXT DEFAULT NULL,
            `admin_notes` TEXT DEFAULT NULL,
            `pixel_sent` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `metadata` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `paid_at` TIMESTAMP NULL,
            `shipped_at` TIMESTAMP NULL,
            `delivered_at` TIMESTAMP NULL,
            `cancelled_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX idx_order_number (`order_number`),
            INDEX idx_user (`user_id`),
            INDEX idx_status (`status`),
            INDEX idx_payment_status (`payment_status`),
            INDEX idx_created (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `order_items` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `order_id` INT UNSIGNED NOT NULL,
            `product_id` INT UNSIGNED DEFAULT NULL,
            `variant_id` INT UNSIGNED DEFAULT NULL,
            `product_name` VARCHAR(255) NOT NULL,
            `variant_name` VARCHAR(255) DEFAULT NULL,
            `sku` VARCHAR(100) DEFAULT NULL,
            `price` DECIMAL(10,2) NOT NULL,
            `cost_price` DECIMAL(10,2) DEFAULT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `total` DECIMAL(10,2) NOT NULL,
            `image` VARCHAR(500) DEFAULT NULL,
            `metadata` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE SET NULL,
            INDEX idx_order (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `cart_items` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `session_id` VARCHAR(128) NOT NULL,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `product_id` INT UNSIGNED NOT NULL,
            `variant_id` INT UNSIGNED DEFAULT NULL,
            `quantity` INT NOT NULL DEFAULT 1,
            `metadata` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            INDEX idx_session (`session_id`),
            INDEX idx_user (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pages` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `title` VARCHAR(255) NOT NULL,
            `slug` VARCHAR(255) NOT NULL UNIQUE,
            `content` LONGTEXT DEFAULT NULL,
            `template` VARCHAR(100) DEFAULT 'page',
            `status` ENUM('published','draft') NOT NULL DEFAULT 'draft',
            `builder_data` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `seo_title` VARCHAR(255) DEFAULT NULL,
            `seo_description` TEXT DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `show_in_nav` TINYINT(1) NOT NULL DEFAULT 0,
            `metadata` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_slug (`slug`),
            INDEX idx_status (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sections` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `page_id` INT UNSIGNED DEFAULT NULL,
            `location` VARCHAR(50) DEFAULT 'body',
            `type` VARCHAR(50) NOT NULL,
            `settings` LONGTEXT /* (JSON fallback) */ NOT NULL,
            `content` LONGTEXT DEFAULT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (`page_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE,
            INDEX idx_page_location (`page_id`, `location`, `sort_order`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `media` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `filename` VARCHAR(255) NOT NULL,
            `original_name` VARCHAR(255) NOT NULL,
            `path` VARCHAR(500) NOT NULL,
            `mime_type` VARCHAR(100) NOT NULL,
            `size` INT UNSIGNED NOT NULL,
            `width` INT UNSIGNED DEFAULT NULL,
            `height` INT UNSIGNED DEFAULT NULL,
            `alt_text` VARCHAR(255) DEFAULT NULL,
            `folder` VARCHAR(100) DEFAULT 'general',
            `thumbnails` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_folder (`folder`),
            INDEX idx_mime (`mime_type`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `settings` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `group` VARCHAR(50) NOT NULL DEFAULT 'general',
            `key` VARCHAR(100) NOT NULL,
            `value` TEXT DEFAULT NULL,
            `type` VARCHAR(20) NOT NULL DEFAULT 'string',
            UNIQUE KEY unique_setting (`group`, `key`),
            INDEX idx_group (`group`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `themes` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(100) NOT NULL UNIQUE,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `version` VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            `author` VARCHAR(255) DEFAULT NULL,
            `author_url` VARCHAR(500) DEFAULT NULL,
            `screenshot` VARCHAR(500) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 0,
            `settings` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `settings_schema` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `installed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `plugins` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `slug` VARCHAR(100) NOT NULL UNIQUE,
            `name` VARCHAR(255) NOT NULL,
            `description` TEXT DEFAULT NULL,
            `version` VARCHAR(20) NOT NULL DEFAULT '1.0.0',
            `author` VARCHAR(255) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 0,
            `settings` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `hooks` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `installed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `coupons` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `code` VARCHAR(50) NOT NULL UNIQUE,
            `type` ENUM('percentage','fixed','free_shipping') NOT NULL DEFAULT 'percentage',
            `value` DECIMAL(10,2) NOT NULL,
            `min_order` DECIMAL(10,2) DEFAULT NULL,
            `max_uses` INT UNSIGNED DEFAULT NULL,
            `uses_count` INT UNSIGNED NOT NULL DEFAULT 0,
            `max_uses_per_user` INT UNSIGNED DEFAULT NULL,
            `applicable_to` ENUM('all','products','categories') NOT NULL DEFAULT 'all',
            `applicable_ids` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `starts_at` TIMESTAMP NULL,
            `expires_at` TIMESTAMP NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_code (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `shipping_zones` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `name` VARCHAR(255) NOT NULL,
            `regions` LONGTEXT /* (JSON fallback) */ NOT NULL,
            `type` ENUM('flat','weight','free','calculated') NOT NULL DEFAULT 'flat',
            `cost` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            `free_above` DECIMAL(10,2) DEFAULT NULL,
            `min_days` INT DEFAULT NULL,
            `max_days` INT DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `sort_order` INT NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `subscribers` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `email` VARCHAR(255) NOT NULL UNIQUE,
            `name` VARCHAR(200) DEFAULT NULL,
            `status` ENUM('active','unsubscribed') NOT NULL DEFAULT 'active',
            `source` VARCHAR(50) DEFAULT 'website',
            `subscribed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_email (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `activity_log` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `action` VARCHAR(100) NOT NULL,
            `entity_type` VARCHAR(50) DEFAULT NULL,
            `entity_id` INT UNSIGNED DEFAULT NULL,
            `description` TEXT DEFAULT NULL,
            `ip_address` VARCHAR(45) DEFAULT NULL,
            `user_agent` VARCHAR(500) DEFAULT NULL,
            `metadata` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
            INDEX idx_user (`user_id`),
            INDEX idx_entity (`entity_type`, `entity_id`),
            INDEX idx_created (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `api_tokens` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `user_id` INT UNSIGNED NOT NULL,
            `token_hash` VARCHAR(64) NOT NULL UNIQUE,
            `name` VARCHAR(100) NOT NULL DEFAULT 'default',
            `permissions` LONGTEXT /* (JSON fallback) */ DEFAULT NULL,
            `last_used_at` TIMESTAMP NULL,
            `expires_at` TIMESTAMP NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
            INDEX idx_token (`token_hash`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `pixel_events` (
            `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `event_type` VARCHAR(50) NOT NULL,
            `platform` VARCHAR(20) NOT NULL,
            `event_data` LONGTEXT /* (JSON fallback) */ NOT NULL,
            `order_id` INT UNSIGNED DEFAULT NULL,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `session_id` VARCHAR(128) DEFAULT NULL,
            `sent_client` TINYINT(1) NOT NULL DEFAULT 0,
            `sent_server` TINYINT(1) NOT NULL DEFAULT 0,
            `response_code` INT DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_event (`event_type`, `platform`),
            INDEX idx_created (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `reviews` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `product_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED DEFAULT NULL,
            `order_id` INT UNSIGNED DEFAULT NULL,
            `author_name` VARCHAR(200) NOT NULL,
            `rating` TINYINT UNSIGNED NOT NULL,
            `title` VARCHAR(255) DEFAULT NULL,
            `body` TEXT DEFAULT NULL,
            `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
            INDEX idx_product (`product_id`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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

-- Default Admin User
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `role`) VALUES 
('admin@example.com', '$argon2id$v=19$m=65536,t=4,p=1$bnF0N0N3SS9rNlE5QnZ4Rg$MS03eBs/O6y9vcYvUaaExvH8FCoYZ7u9TGjP8HCHKwQ', 'Admin', 'admin');

-- Default Settings
INSERT IGNORE INTO `settings` (`group`, `key`, `value`, `type`) VALUES 
('general', 'store_name', 'My Axer Store', 'string'),
('general', 'store_email', 'admin@example.com', 'string'),
('general', 'currency', 'USD', 'string'),
('general', 'currency_symbol', '$', 'string'),
('general', 'font_family', 'Outfit', 'string'),
('general', 'primary_color', '#6366f1', 'string');

-- Default Home Page
INSERT INTO `pages` (`title`, `slug`, `content`, `template`, `status`, `builder_data`) VALUES 
('Home', 'home', '<h1>Welcome to Axer Store</h1>', 'page', 'published', '[{"id":"hero-1","type":"hero","settings":{"title":"Welcome to Axer Storefront","subtitle":"Fully customisable headless e-commerce CMS","button_text":"Shop Now","button_url":"/products","bg_color":"#6366f1","text_color":"#ffffff"}},{"id":"text-image-1","type":"text-image","settings":{"title":"Discover Our Products","content":"We offer the best quality products for your everyday needs. Browse our catalog and find amazing deals.","image_url":"https://via.placeholder.com/600x400","image_position":"right","bg_color":"#f8fafc","text_color":"#0f172a"}}]');

SET FOREIGN_KEY_CHECKS = 1;
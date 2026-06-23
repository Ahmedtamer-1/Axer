<?php

class MockPDO extends PDO {
    public string $sql = "";
    
    public function __construct() {}
    
    public function exec($statement): int|false {
        $this->sql .= $statement . "\n\n";
        return 0;
    }
}

$mock = new MockPDO();

$files = glob(__DIR__ . '/database/migrations/*.php');
sort($files);

foreach ($files as $file) {
    require_once $file;
    $name = basename($file, '.php');
    $name = preg_replace('/^\d+_/', '', $name);
    $className = str_replace(' ', '', ucwords(str_replace('_', ' ', $name)));
    
    if (class_exists($className)) {
        $migration = new $className();
        $migration->up($mock);
    }
}

// Add the default inserts from install.php
$mock->sql .= "
-- Default Admin User
INSERT INTO `users` (`email`, `password_hash`, `first_name`, `role`) VALUES 
('admin@example.com', '" . password_hash('password123', PASSWORD_ARGON2ID) . "', 'Admin', 'admin');

-- Default Settings
INSERT IGNORE INTO `settings` (`group`, `key`, `value`, `type`) VALUES 
('general', 'store_name', 'My Lume Store', 'string'),
('general', 'store_email', 'admin@example.com', 'string'),
('general', 'currency', 'USD', 'string'),
('general', 'currency_symbol', '$', 'string'),
('general', 'font_family', 'Outfit', 'string'),
('general', 'primary_color', '#6366f1', 'string');

-- Default Home Page
INSERT INTO `pages` (`title`, `slug`, `content`, `template`, `status`, `builder_data`) VALUES 
('Home', 'home', '<h1>Welcome to Lume Store</h1>', 'page', 'published', '[{\"id\":\"hero-1\",\"type\":\"hero\",\"settings\":{\"title\":\"Welcome to Lume Storefront\",\"subtitle\":\"Fully customisable headless e-commerce CMS\",\"button_text\":\"Shop Now\",\"button_url\":\"/products\",\"bg_color\":\"#6366f1\",\"text_color\":\"#ffffff\"}},{\"id\":\"text-image-1\",\"type\":\"text-image\",\"settings\":{\"title\":\"Discover Our Products\",\"content\":\"We offer the best quality products for your everyday needs. Browse our catalog and find amazing deals.\",\"image_url\":\"https://via.placeholder.com/600x400\",\"image_position\":\"right\",\"bg_color\":\"#f8fafc\",\"text_color\":\"#0f172a\"}}]');
";

file_put_contents(__DIR__ . '/database.sql', "SET FOREIGN_KEY_CHECKS = 0;\n\n" . $mock->sql . "\nSET FOREIGN_KEY_CHECKS = 1;");

echo "Generated database.sql";

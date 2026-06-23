<?php

// Axer CMS Installer Wizard
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$basePath = __DIR__;
$envPath = $basePath . '/config/.env';

if (file_exists($envPath)) {
    die("Axer is already installed. If you want to reinstall, please delete config/.env file first.");
}

$step = $_GET['step'] ?? 1;
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['_csrf'] ?? '')) {
        die("CSRF Token Mismatch");
    }
    
    if ($step == 2) {
        $dbHost = $_POST['db_host'] ?? '127.0.0.1';
        $dbName = $_POST['db_name'] ?? '';
        $dbUser = $_POST['db_user'] ?? '';
        $dbPass = $_POST['db_pass'] ?? '';

        // Test connection
        try {
            $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);

            // Save to session temporarily
            $_SESSION['db_host'] = $dbHost;
            $_SESSION['db_name'] = $dbName;
            $_SESSION['db_user'] = $dbUser;
            $_SESSION['db_pass'] = $dbPass;

            header("Location: install.php?step=3");
            exit;
        } catch (PDOException $e) {
            $error = "Database connection failed: " . $e->getMessage();
        }
    } elseif ($step == 3) {
        $adminName = $_POST['admin_name'] ?? '';
        $adminEmail = $_POST['admin_email'] ?? '';
        $adminPass = $_POST['admin_pass'] ?? '';

        if (empty($adminName) || empty($adminEmail) || empty($adminPass)) {
            $error = "All fields are required.";
        } else {
            try {
                // Generate env file FIRST
                $envContent = "APP_ENV=production\n";
                $envContent .= "APP_DEBUG=false\n";
                $envContent .= "DB_HOST={$_SESSION['db_host']}\n";
                $envContent .= "DB_DATABASE={$_SESSION['db_name']}\n";
                $envContent .= "DB_USERNAME={$_SESSION['db_user']}\n";
                $envContent .= "DB_PASSWORD={$_SESSION['db_pass']}\n";
                $envContent .= "APP_KEY=" . bin2hex(random_bytes(32)) . "\n";
                
                file_put_contents($envPath, $envContent);

                // Define BASE_PATH if not defined
                if (!defined('BASE_PATH')) {
                    define('BASE_PATH', $basePath);
                }

                // Bootstrap the Axer App to register autoloader and bootstrap Config
                require_once BASE_PATH . '/app/Core/App.php';
                $app = new \Axer\Core\App();

                // Run migrations
                $migration = new \Axer\Database\Migration();
                ob_start();
                $migration->run(BASE_PATH . '/database/migrations');
                ob_end_clean();

                // Connect to PDO to insert default data
                $pdo = new PDO("mysql:host={$_SESSION['db_host']};dbname={$_SESSION['db_name']};charset=utf8mb4", $_SESSION['db_user'], $_SESSION['db_pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                // Insert admin user
                $stmt = $pdo->prepare("INSERT INTO users (email, password_hash, first_name, role) VALUES (?, ?, ?, 'admin')");
                $stmt->execute([
                    $adminEmail,
                    password_hash($adminPass, PASSWORD_ARGON2ID),
                    $adminName
                ]);

                // Insert default settings
                $defaultSettings = [
                    ['group' => 'general', 'key' => 'store_name', 'value' => 'My Axer Store', 'type' => 'string'],
                    ['group' => 'general', 'key' => 'store_email', 'value' => $adminEmail, 'type' => 'string'],
                    ['group' => 'general', 'key' => 'currency', 'value' => 'USD', 'type' => 'string'],
                    ['group' => 'general', 'key' => 'currency_symbol', 'value' => '$', 'type' => 'string'],
                    ['group' => 'general', 'key' => 'font_family', 'value' => 'Outfit', 'type' => 'string'],
                    ['group' => 'general', 'key' => 'primary_color', 'value' => '#6366f1', 'type' => 'string'],
                ];
                $stmt = $pdo->prepare("INSERT IGNORE INTO settings (`group`, `key`, `value`, `type`) VALUES (?, ?, ?, ?)");
                foreach ($defaultSettings as $setting) {
                    $stmt->execute([$setting['group'], $setting['key'], $setting['value'], $setting['type']]);
                }

                // Activate default theme
                \Axer\Services\ThemeService::activateTheme('default');

                // Insert default 'Home' page
                $defaultBuilderData = json_encode([
                    [
                        'id' => 'hero-1',
                        'type' => 'hero',
                        'settings' => [
                            'title' => 'Welcome to Axer Storefront',
                            'subtitle' => 'Fully customisable headless e-commerce CMS',
                            'button_text' => 'Shop Now',
                            'button_url' => '/products',
                            'bg_color' => '#6366f1',
                            'text_color' => '#ffffff'
                        ]
                    ],
                    [
                        'id' => 'text-image-1',
                        'type' => 'text-image',
                        'settings' => [
                            'title' => 'Discover Our Products',
                            'content' => 'We offer the best quality products for your everyday needs. Browse our catalog and find amazing deals.',
                            'image_url' => 'https://via.placeholder.com/600x400',
                            'image_position' => 'right',
                            'bg_color' => '#f8fafc',
                            'text_color' => '#0f172a'
                        ]
                    ]
                ]);
                
                $stmt = $pdo->prepare("INSERT INTO pages (title, slug, content, template, status, builder_data) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    'Home',
                    'home',
                    '<h1>Welcome to Axer Store</h1><p>This is your brand new storefront built with Axer CMS.</p>',
                    'page',
                    'published',
                    $defaultBuilderData
                ]);

                // Clean session
                session_destroy();

                header("Location: install.php?step=4");
                exit;
            } catch (Exception $e) {
                // Clean up written env file on failure so installer can retry
                if (file_exists($envPath)) {
                    unlink($envPath);
                }
                $error = "Installation failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Axer Installer</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Outfit', sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .installer-card { background: #1e293b; padding: 2.5rem; border-radius: 0.5rem; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.5); width: 100%; max-width: 480px; }
        h1 { margin-top: 0; font-size: 1.75rem; color: #6366f1; text-align: center; }
        .steps { display: flex; justify-content: space-between; margin-bottom: 2rem; border-bottom: 1px solid #334155; padding-bottom: 1rem; }
        .step { color: #94a3b8; font-size: 0.875rem; font-weight: 500; }
        .step.active { color: #f8fafc; font-weight: 600; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; margin-bottom: 0.5rem; font-size: 0.875rem; font-weight: 500; }
        input[type="text"], input[type="password"], input[type="email"] { width: 100%; padding: 0.75rem; border-radius: 0.375rem; border: 1px solid #334155; background: #0f172a; color: white; box-sizing: border-box; }
        input:focus { outline: none; border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,0.2); }
        .btn { display: inline-block; width: 100%; padding: 0.875rem; background: #6366f1; color: white; border: none; border-radius: 0.375rem; font-weight: 600; cursor: pointer; text-align: center; text-decoration: none; box-sizing: border-box; }
        .btn:hover { background: #4f46e5; }
        .alert { padding: 1rem; border-radius: 0.375rem; margin-bottom: 1.5rem; font-size: 0.875rem; }
        .alert-error { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #fca5a5; }
        .alert-success { background: rgba(34, 197, 94, 0.1); border: 1px solid rgba(34, 197, 94, 0.2); color: #86efac; text-align: center; }
    </style>
</head>
<body>
    <div class="installer-card">
        <h1>Axer Installer</h1>
        
        <div class="steps">
            <div class="step <?= $step == 1 ? 'active' : '' ?>">1. Welcome</div>
            <div class="step <?= $step == 2 ? 'active' : '' ?>">2. Database</div>
            <div class="step <?= $step == 3 ? 'active' : '' ?>">3. Admin Setup</div>
            <div class="step <?= $step == 4 ? 'active' : '' ?>">4. Done</div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <p style="text-align: center; color: #94a3b8; margin-bottom: 2rem;">Welcome to the Axer installation wizard. This will help you set up your database and admin account.</p>
            <a href="install.php?step=2" class="btn">Start Installation</a>
        
        <?php elseif ($step == 2): ?>
            <form method="POST">
    <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <div class="form-group">
                    <label>Database Host</label>
                    <input type="text" name="db_host" value="127.0.0.1" required>
                </div>
                <div class="form-group">
                    <label>Database Name</label>
                    <input type="text" name="db_name" required>
                </div>
                <div class="form-group">
                    <label>Database User</label>
                    <input type="text" name="db_user" required>
                </div>
                <div class="form-group">
                    <label>Database Password</label>
                    <input type="password" name="db_pass">
                </div>
                <button type="submit" class="btn">Next Step</button>
            </form>
            
        <?php elseif ($step == 3): ?>
            <form method="POST">
    <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <div class="form-group">
                    <label>Admin Name</label>
                    <input type="text" name="admin_name" required>
                </div>
                <div class="form-group">
                    <label>Admin Email</label>
                    <input type="email" name="admin_email" required>
                </div>
                <div class="form-group">
                    <label>Admin Password</label>
                    <input type="password" name="admin_pass" required>
                </div>
                <button type="submit" class="btn">Install Axer</button>
            </form>
            
        <?php elseif ($step == 4): ?>
            <div class="alert alert-success">
                <strong>Installation Complete!</strong><br><br>
                Axer CMS has been successfully installed.
            </div>
            <p style="text-align: center; color: #94a3b8; margin-bottom: 2rem; font-size: 0.875rem;">
                For security reasons, please delete or rename <code>install.php</code> before going live.
            </p>
            <a href="/admin/dashboard" class="btn">Go to Admin Dashboard</a>
        <?php endif; ?>
    </div>
</body>
</html>

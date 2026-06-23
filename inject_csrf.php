<?php
$files = [
    'admin/views/orders/view.php',
    'admin/views/pages/create.php',
    'admin/views/pages/edit.php',
    'admin/views/pages/index.php',
    'admin/views/products/create.php',
    'admin/views/products/edit.php',
    'admin/views/products/index.php',
    'admin/views/settings/index.php',
    'admin/views/themes/customizer.php',
    'admin/views/themes/index.php',
    'app/Controllers/Admin/AuthController.php',
    'install.php'
];

foreach ($files as $f) {
    $path = __DIR__ . '/' . $f;
    if (file_exists($path)) {
        $content = file_get_contents($path);
        
        // Find forms that have method="POST"
        // And append <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ? >"> right after <form ...>
        $content = preg_replace(
            '/(<form[^>]*method=["\']POST["\'][^>]*>)/i', 
            "$1\n    <input type=\"hidden\" name=\"_csrf\" value=\"<?= \$_SESSION['csrf_token'] ?? '' ?>\">", 
            $content
        );
        
        file_put_contents($path, $content);
        echo "Injected CSRF into $f\n";
    }
}

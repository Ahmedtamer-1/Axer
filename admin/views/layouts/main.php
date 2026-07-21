<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($title) ? htmlspecialchars($title) . ' - Axer Admin' : 'Axer Admin' ?></title>
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Admin Stylesheet -->
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body>
    <?php
    $currentUri = $_SERVER['REQUEST_URI'] ?? '';
    function isPageActive($path, $currentUri) {
        return strpos($currentUri, $path) !== false ? 'class="active"' : '';
    }
    $adminName = $_SESSION['admin_user']['name'] ?? 'Admin';
    $adminRole = ucfirst($_SESSION['admin_user']['role'] ?? 'superadmin');
    $initial = strtoupper(substr($adminName, 0, 1));
    ?>
    <aside>
        <div class="sidebar-brand">
            <i data-lucide="sparkles"></i>
            <span>Axer CMS</span>
        </div>
        <ul class="sidebar-menu">
            <li <?= isPageActive('/admin/dashboard', $currentUri) ?>><a href="/admin/dashboard"><i data-lucide="layout-dashboard"></i> Dashboard</a></li>
            <li <?= isPageActive('/admin/products', $currentUri) ?>><a href="/admin/products"><i data-lucide="shopping-bag"></i> Products</a></li>
            <li <?= isPageActive('/admin/media', $currentUri) ?>><a href="/admin/media"><i data-lucide="image"></i> Media</a></li>
            <li <?= isPageActive('/admin/orders', $currentUri) ?>><a href="/admin/orders"><i data-lucide="receipt"></i> Orders</a></li>
            <li <?= isPageActive('/admin/pages', $currentUri) ?>><a href="/admin/pages"><i data-lucide="file-text"></i> Pages</a></li>
            <li <?= isPageActive('/admin/themes', $currentUri) ?>><a href="/admin/themes"><i data-lucide="palette"></i> Themes</a></li>
            <li <?= isPageActive('/admin/plugins', $currentUri) ?>><a href="/admin/plugins"><i data-lucide="plug"></i> Plugins</a></li>
            <li <?= isPageActive('/admin/settings', $currentUri) ?>><a href="/admin/settings"><i data-lucide="settings"></i> Settings</a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="admin-avatar"><?= $initial ?></div>
            <div class="admin-info" style="flex-grow: 1;">
                <span class="admin-name"><?= htmlspecialchars($adminName) ?></span>
                <span class="admin-role"><?= htmlspecialchars($adminRole) ?></span>
            </div>
            <a href="/admin/logout" title="Sign Out" style="color: var(--text-sidebar); text-decoration: none;"><i data-lucide="log-out"></i></a>
        </div>
    </aside>

    <main>
        <?= $content ?>
    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>

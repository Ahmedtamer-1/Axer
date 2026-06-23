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
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        :root {
            --sidebar-width: 260px;
            --bg-sidebar: #0f172a;
            --bg-sidebar-hover: #1e293b;
            --bg-sidebar-active: #334155;
            --text-sidebar: #94a3b8;
            --text-sidebar-active: #f8fafc;
            --bg-content: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --primary: #6366f1;
            --primary-hover: #4f46e5;
            --success: #10b981;
            --danger: #ef4444;
            --shadow-sm: 0 1px 2px 0 rgb(0 0 0 / 0.05);
            --shadow-md: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            --font-main: 'Outfit', sans-serif;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-content);
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styling */
        aside {
            width: var(--sidebar-width);
            background-color: var(--bg-sidebar);
            color: var(--text-sidebar);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            border-right: 1px solid rgba(255, 255, 255, 0.05);
        }

        .sidebar-brand {
            padding: 1.5rem;
            font-size: 1.5rem;
            font-weight: 700;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .sidebar-menu {
            list-style: none;
            padding: 1.5rem 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            flex-grow: 1;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: var(--text-sidebar);
            text-decoration: none;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .sidebar-menu a:hover {
            background-color: var(--bg-sidebar-hover);
            color: var(--text-sidebar-active);
        }

        .sidebar-menu li.active a {
            background-color: var(--bg-sidebar-active);
            color: var(--text-sidebar-active);
        }

        .sidebar-footer {
            padding: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .admin-info {
            display: flex;
            flex-direction: column;
        }

        .admin-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: #ffffff;
        }

        .admin-role {
            font-size: 0.75rem;
            color: var(--text-sidebar);
        }

        /* Main Content Styling */
        main {
            margin-left: var(--sidebar-width);
            flex-grow: 1;
            padding: 2.5rem;
            max-width: 1600px;
        }

        .header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .header-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-main);
        }

        .header-title p {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .header-actions {
            display: flex;
            gap: 0.75rem;
        }

        /* UI Components */
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 600;
            border-radius: 0.5rem;
            border: 1px solid transparent;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-primary {
            background-color: var(--primary);
            color: #ffffff;
        }

        .btn-primary:hover {
            background-color: var(--primary-hover);
        }

        .btn-secondary {
            background-color: #ffffff;
            border-color: var(--border-color);
            color: var(--text-main);
        }

        .btn-secondary:hover {
            background-color: var(--bg-content);
        }

        .card {
            background-color: var(--bg-card);
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            aside {
                display: none;
            }
            main {
                margin-left: 0;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php
    $currentUri = $_SERVER['REQUEST_URI'];
    function isPageActive($path, $currentUri) {
        return strpos($currentUri, $path) !== false ? 'class="active"' : '';
    }
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
            <li <?= isPageActive('/admin/settings', $currentUri) ?>><a href="/admin/settings"><i data-lucide="settings"></i> Settings</a></li>
        </ul>
        <div class="sidebar-footer">
            <div class="admin-avatar">A</div>
            <div class="admin-info">
                <span class="admin-name">Admin</span>
                <span class="admin-role">Superadmin</span>
            </div>
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

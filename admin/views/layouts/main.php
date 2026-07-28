<?php

use Axer\Auth\Roles;
use Axer\Core\Csrf;
use Axer\Support\Asset;

/**
 * Admin shell.
 *
 * @var string $content  rendered view body
 * @var string $title    page title
 * @var string $view     view path, used for nav highlighting
 */

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$navItems = [
    ['path' => '/admin/dashboard', 'icon' => 'layout-dashboard', 'label' => 'Dashboard'],
    ['path' => '/admin/products',  'icon' => 'shopping-bag',     'label' => 'Products'],
    ['path' => '/admin/media',     'icon' => 'image',            'label' => 'Media'],
    ['path' => '/admin/orders',    'icon' => 'receipt',          'label' => 'Orders'],
    ['path' => '/admin/pages',     'icon' => 'file-text',        'label' => 'Pages'],
    ['path' => '/admin/themes',    'icon' => 'palette',          'label' => 'Themes',   'role' => 'superadmin'],
    ['path' => '/admin/plugins',   'icon' => 'plug',             'label' => 'Plugins',  'role' => 'superadmin'],
    ['path' => '/admin/settings',  'icon' => 'settings',         'label' => 'Settings', 'role' => 'superadmin'],
];

// Settings/Themes/Plugins are gated to superadmin by AdminAuthMiddleware;
// the nav used to render all 8 items for any logged-in admin regardless
// of role, which just meant a plain admin got a 403 after clicking.
$navItems = array_values(array_filter(
    $navItems,
    static fn(array $item): bool => !isset($item['role']) || Roles::atLeast($item['role'])
));

/**
 * Match on the path segment, not a substring of the whole URI.
 *
 * The previous helper was a global function declared inside this template
 * (a redeclaration fatal if the layout ever rendered twice) and it used
 * strpos() against the raw REQUEST_URI, so "/admin/settings?tab=products"
 * lit up the Products tab as well.
 */
$isActive = static function (string $path) use ($currentPath): bool {
    return $currentPath === $path || str_starts_with($currentPath, $path . '/');
};

$adminUser = $_SESSION['admin_user'] ?? [];
$adminName = $adminUser['name'] ?? 'Admin';
$adminRole = ucfirst((string) ($adminUser['role'] ?? 'superadmin'));
$initial = mb_strtoupper(mb_substr($adminName, 0, 1, 'UTF-8'), 'UTF-8');

$flashSuccess = \Axer\Core\Session::flash('success');
$flashError = \Axer\Core\Session::flash('error');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <meta name="csrf-token" content="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
    <title><?= isset($title) ? htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') . ' — Axer Admin' : 'Axer Admin' ?></title>

    <?php
    // Apply the saved theme before first paint so the page never flashes
    // light before switching to dark.
    ?>
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('axer-theme');
                var dark = stored ? stored === 'dark'
                    : window.matchMedia('(prefers-color-scheme: dark)').matches;
                document.documentElement.dataset.theme = dark ? 'dark' : 'light';
            } catch (e) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= Asset::url('/admin/assets/css/admin.css') ?>">
</head>
<body>
    <a href="#main-content" class="skip-link">Skip to content</a>

    <button type="button" class="sidebar-toggle" id="sidebarToggle"
            aria-label="Open navigation" aria-expanded="false" aria-controls="sidebar">
        <i data-lucide="menu"></i>
    </button>

    <div class="sidebar-scrim" id="sidebarScrim" hidden></div>

    <aside id="sidebar">
        <div class="sidebar-brand">
            <i data-lucide="sparkles"></i>
            <span>Axer CMS</span>
        </div>

        <nav aria-label="Main">
            <ul class="sidebar-menu">
                <?php foreach ($navItems as $item): ?>
                    <?php $active = $isActive($item['path']); ?>
                    <li<?= $active ? ' class="active"' : '' ?>>
                        <a href="<?= htmlspecialchars($item['path'], ENT_QUOTES, 'UTF-8') ?>"
                           <?= $active ? 'aria-current="page"' : '' ?>>
                            <i data-lucide="<?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                            <span><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="sidebar-footer">
            <div class="admin-avatar" aria-hidden="true"><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></div>
            <div class="admin-info">
                <span class="admin-name"><?= htmlspecialchars($adminName, ENT_QUOTES, 'UTF-8') ?></span>
                <span class="admin-role"><?= htmlspecialchars($adminRole, ENT_QUOTES, 'UTF-8') ?></span>
            </div>
            <button type="button" class="icon-btn" id="themeToggle" title="Toggle dark mode" aria-label="Toggle dark mode">
                <i data-lucide="moon" class="theme-icon-dark"></i>
                <i data-lucide="sun" class="theme-icon-light"></i>
            </button>
            <a href="/admin/logout" class="icon-btn" title="Sign out" aria-label="Sign out">
                <i data-lucide="log-out"></i>
            </a>
        </div>
    </aside>

    <main id="main-content">
        <?= $content ?? '' ?>
    </main>

    <div class="toast-region" id="toastRegion" role="status" aria-live="polite"></div>

    <?php if ($flashSuccess || $flashError): ?>
    <script type="application/json" id="axer-flash">
        <?= json_encode(
            array_filter(['success' => $flashSuccess, 'error' => $flashError]),
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
        ) ?>
    </script>
    <?php endif; ?>

    <script src="<?= Asset::url('/admin/assets/js/icons.js') ?>" defer></script>
    <script src="<?= Asset::url('/admin/assets/js/admin.js') ?>" defer></script>
</body>
</html>

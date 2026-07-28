<?php

use Axer\Core\Logger;
use Axer\Core\Request;
use Axer\Core\Response;
use Axer\Core\Router;
use Axer\Database\QueryBuilder;
use Axer\Services\CartService;
use Axer\Services\ThemeService;
use Axer\Controllers\Storefront\AccountController;
use Axer\Controllers\Storefront\CartController;
use Axer\Controllers\Storefront\CheckoutController;
use Axer\Controllers\Storefront\NewsletterController;
use Axer\Controllers\Storefront\ProductController;
use Axer\Support\Str;

/** @var Router $router */

// Admin Redirect Route
$router->get('/admin', static function () {
    return Response::redirect('/admin/dashboard');
});

/**
 * Serve a theme's own static assets (CSS, JS, fonts, images).
 *
 * The `asset_url` template filter has always emitted `/theme-assets/<path>`
 * (app/Template/Filters.php), but nothing ever served that prefix — a
 * theme had no way to ship a real stylesheet and had to inline every rule
 * into its .lume files instead. Confined to the active theme's assets/
 * directory via realpath(), with an extension whitelist so this can't be
 * used to read arbitrary files.
 */
$router->get('/theme-assets/{path}', static function (Request $request, string $path) {
    $theme = ThemeService::getActiveTheme();
    $slug = $theme['slug'] ?? 'default';

    if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $slug)) {
        return Response::notFound();
    }

    $assetsRoot = realpath(BASE_PATH . '/content/themes/' . $slug . '/assets');

    if ($assetsRoot === false) {
        return Response::notFound();
    }

    $requested = realpath($assetsRoot . '/' . $path);

    if (
        $requested === false
        || !str_starts_with($requested, $assetsRoot . DIRECTORY_SEPARATOR)
        || !is_file($requested)
    ) {
        return Response::notFound();
    }

    $extension = strtolower(pathinfo($requested, PATHINFO_EXTENSION));

    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'svg' => 'image/svg+xml',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'webp' => 'image/webp',
        'woff2' => 'font/woff2',
    ];

    if (!isset($mimeTypes[$extension])) {
        return Response::notFound();
    }

    $content = file_get_contents($requested);

    if ($content === false) {
        return Response::notFound();
    }

    return (new Response($content))
        ->setHeader('Content-Type', $mimeTypes[$extension])
        ->cache(86400 * 7);
})->where('path', '[A-Za-z0-9_\-./]+');

/**
 * Render a storefront page from its builder blocks.
 *
 * Previously a global function declared in this route file — a
 * redeclaration fatal if the routes were ever loaded twice — which also
 * constructed a fresh Template\Engine (and therefore a fresh Sandbox and
 * filter table) inside the block loop, once per section on the page.
 */
if (!class_exists('AxerStorefrontPage', false)) {
    class AxerStorefrontPage
    {
        public static function render(Request $request, string $slug): Response
        {
            try {
                $isAdmin = isset($_SESSION['admin_user']);
                $isPreview = $isAdmin && $request->get('preview') === '1';

                $query = QueryBuilder::table('pages')->where('slug', $slug);

                if (!$isPreview) {
                    $query->where('status', 'published');
                }

                $page = $query->first();

                if ($page === null) {
                    return Response::notFound();
                }

                $theme = ThemeService::getActiveTheme();
                $themeSlug = $theme['slug'] ?? 'default';
                $themePath = BASE_PATH . '/content/themes/' . $themeSlug;

                // One engine for the whole page, not one per section.
                $engine = ThemeService::engine($themeSlug);

                $builderData = json_decode((string) ($page['builder_data'] ?? '[]'), true);

                $content = is_array($builderData) && $builderData !== []
                    ? self::renderBlocks($builderData, $engine, $themePath, $page)
                    : (string) ($page['content'] ?? '');

                if ($isPreview && $page['status'] !== 'published') {
                    $content = self::draftPreviewBanner() . $content;
                }

                $html = ThemeService::wrapContentInLayout($content, [
                    'page_title' => ($page['seo_title'] ?? '') ?: $page['title'],
                    'meta_description' => Str::excerpt(($page['seo_description'] ?? '') ?: ($page['content'] ?? ''), 160),
                ]);

                return new Response($html);
            } catch (\Throwable $e) {
                // The old handler echoed the exception message to the
                // visitor, leaking SQL and absolute file paths.
                Logger::exception($e);

                if (defined('APP_DEBUG') && APP_DEBUG) {
                    throw $e;
                }

                return new Response('Something went wrong loading this page.', 500);
            }
        }

        protected static function renderBlocks(
            array $blocks,
            \Axer\Template\Engine $engine,
            string $themePath,
            array $page
        ): string {
            // Products are fetched once for the whole page, with their
            // images batched — the old version ran an image query per
            // product, inside the block loop.
            $products = self::featuredProducts();
            $content = '';

            foreach ($blocks as $block) {
                $type = (string) ($block['type'] ?? '');
                $settings = is_array($block['settings'] ?? null) ? $block['settings'] : [];

                // Never let a builder-supplied type escape the theme dir.
                if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $type)) {
                    continue;
                }

                if (!is_file($themePath . "/sections/{$type}.lume")) {
                    $content .= self::fallbackBlock($type, $settings, $page);
                    continue;
                }

                $settings['featured_products'] = $products;
                $settings['products_empty'] = $products === [];

                try {
                    $content .= $engine->render("sections/{$type}", $settings);
                } catch (\Throwable $e) {
                    // One broken section should not take the whole page down.
                    Logger::error("Section {$type} failed to render: " . $e->getMessage());
                }
            }

            return $content;
        }

        protected static function featuredProducts(int $limit = 8): array
        {
            try {
                $products = QueryBuilder::table('products')
                    ->where('status', 'active')
                    ->orderBy('featured', 'DESC')
                    ->orderBy('sort_order', 'ASC')
                    ->limit($limit)
                    ->get();
            } catch (\Throwable $e) {
                Logger::warning('Could not load featured products: ' . $e->getMessage());

                return [];
            }

            $images = CartService::primaryImagesFor(array_column($products, 'id'));

            foreach ($products as $index => $product) {
                $id = (int) $product['id'];

                $products[$index]['image_url'] = $images[$id] ?? CartService::placeholderImage();
                $products[$index]['price'] = number_format((float) $product['price'], 2);
                $products[$index]['description'] = Str::excerpt(
                    ($product['short_description'] ?? '') ?: ($product['description'] ?? ''),
                    90
                );
            }

            return $products;
        }

        /**
         * Minimal markup for a block type the active theme has no section
         * template for.
         */
        protected static function fallbackBlock(string $type, array $settings, array $page): string
        {
            $escape = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

            if ($type === 'hero') {
                return '<div class="hero-section" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);'
                    . ' color: #fff; text-align: center; padding: 5rem 2rem; border-radius: 1rem; margin-bottom: 3rem;">'
                    . '<h1 style="font-size: clamp(2rem, 6vw, 3.5rem); font-weight: 800; margin-bottom: 1rem;">'
                    . $escape($settings['title'] ?? 'Welcome to Axer Storefront') . '</h1>'
                    . '<p style="font-size: 1.25rem; opacity: 0.9; max-width: 600px; margin: 0 auto 2rem;">'
                    . $escape($settings['subtitle'] ?? 'Fully customizable headless e-commerce CMS') . '</p>'
                    . '<a href="/shop" class="btn btn-primary" style="display: inline-block; padding: 0.875rem 2.25rem;'
                    . ' background: #fff; color: #4f46e5; font-weight: 700; text-decoration: none;'
                    . ' border-radius: 0.5rem;">Explore Shop Collection</a>'
                    . '</div>';
            }

            if ($type === 'rich-text') {
                $align = in_array($settings['align'] ?? 'center', ['left', 'center', 'right'], true)
                    ? $settings['align']
                    : 'center';

                $html = '<div class="section rich-text-section" style="text-align: ' . $align . '; padding: 3rem 1rem;">';

                if (!empty($settings['title'])) {
                    $html .= '<h2 style="font-size: 2rem; margin-bottom: 1rem;">' . $escape($settings['title']) . '</h2>';
                }

                return $html . '<p style="font-size: 1.125rem; line-height: 1.7; color: #475569;">'
                    . nl2br($escape($settings['content'] ?? '')) . '</p></div>';
            }

            if ($type === 'text-image') {
                $html = '<div class="section" style="padding: 3rem 1rem; background:' . $escape($settings['bg_color'] ?? '#f8fafc') . ';'
                    . ' color:' . $escape($settings['text_color'] ?? '#0f172a') . ';">'
                    . '<div style="max-width: 1000px; margin: 0 auto;">';

                if (!empty($settings['title'])) {
                    $html .= '<h2 style="font-size: 2rem; margin-bottom: 1rem;">' . $escape($settings['title']) . '</h2>';
                }

                $html .= '<p style="font-size: 1.1rem; line-height: 1.7;">' . nl2br($escape($settings['content'] ?? '')) . '</p>';

                if (!empty($settings['image_url'])) {
                    $html .= '<img src="' . $escape($settings['image_url']) . '" alt="" style="width: 100%; max-height: 420px; object-fit: cover; margin-top: 1.5rem; border-radius: 0.75rem;">';
                }

                return $html . '</div></div>';
            }

            if ($type === 'image-gallery') {
                $html = '<div class="section" style="padding: 3rem 1rem;">';

                if (!empty($settings['title'])) {
                    $html .= '<h2 style="text-align: center; font-size: 2rem; margin-bottom: 2rem;">' . $escape($settings['title']) . '</h2>';
                }

                $html .= '<div style="max-width: 1200px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">';

                foreach (['image_1', 'image_2', 'image_3', 'image_4'] as $key) {
                    if (!empty($settings[$key])) {
                        $html .= '<img src="' . $escape($settings[$key]) . '" alt="" style="width: 100%; aspect-ratio: 1/1; object-fit: cover; border-radius: 0.5rem;">';
                    }
                }

                return $html . '</div></div>';
            }

            if ($type === 'faq') {
                $html = '<div class="section" style="max-width: 720px; margin: 0 auto; padding: 3rem 1.5rem;">';

                if (!empty($settings['title'])) {
                    $html .= '<h2 style="text-align: center; font-size: 2rem; margin-bottom: 2rem;">' . $escape($settings['title']) . '</h2>';
                }

                foreach ([1, 2, 3] as $n) {
                    if (!empty($settings["q{$n}"])) {
                        $html .= '<div style="border-bottom: 1px solid #e2e8f0; padding: 1.25rem 0;">'
                            . '<h3 style="font-weight: 700; margin-bottom: 0.5rem;">' . $escape($settings["q{$n}"]) . '</h3>'
                            . '<p style="color: #64748b;">' . $escape($settings["a{$n}"] ?? '') . '</p></div>';
                    }
                }

                return $html . '</div>';
            }

            if ($type === 'testimonials') {
                $html = '<div class="section" style="padding: 3rem 1rem;">';

                if (!empty($settings['title'])) {
                    $html .= '<h2 style="text-align: center; font-size: 2rem; margin-bottom: 2rem;">' . $escape($settings['title']) . '</h2>';
                }

                $html .= '<div style="max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem;">';

                foreach ([1, 2, 3] as $n) {
                    if (!empty($settings["quote{$n}"])) {
                        $html .= '<div style="border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.5rem;">'
                            . '<p style="margin-bottom: 1rem;">"' . $escape($settings["quote{$n}"]) . '"</p>'
                            . '<div style="font-weight: 700;">' . $escape($settings["author{$n}"] ?? '') . '</div></div>';
                    }
                }

                return $html . '</div></div>';
            }

            if ($type === 'cta') {
                return '<div class="section" style="background: #0f172a; color: #fff; text-align: center; padding: 4rem 2rem; border-radius: 1rem; margin: 2rem 0;">'
                    . '<h2 style="color: #fff; font-size: 2rem; margin-bottom: 0.75rem;">' . $escape($settings['title'] ?? 'Ready to get started?') . '</h2>'
                    . '<p style="opacity: 0.85; margin-bottom: 2rem;">' . $escape($settings['subtitle'] ?? '') . '</p>'
                    . '<a href="' . $escape($settings['button_url'] ?? '/shop') . '" style="display: inline-block; padding: 0.875rem 2.25rem; background: #fff; color: #0f172a; font-weight: 700; text-decoration: none; border-radius: 0.5rem;">'
                    . $escape($settings['button_text'] ?? 'Shop Now') . '</a></div>';
            }

            if ($type === 'spacer') {
                $height = preg_match('/^[0-9.]+(px|rem|em|vh)$/', (string) ($settings['height'] ?? '')) ? $settings['height'] : '3rem';

                return '<div style="height: ' . $escape($height) . ';" aria-hidden="true"></div>';
            }

            // Unknown block: fall back to the page's own body content.
            return (string) ($page['content'] ?? '');
        }

        /**
         * Shown above a draft page's content when an admin loads it with
         * ?preview=1. Inline-styled so it renders regardless of theme.
         */
        protected static function draftPreviewBanner(): string
        {
            return '<div style="background:#f59e0b;color:#1e1500;text-align:center;'
                . 'padding:0.75rem 1rem;font-weight:700;font-size:0.9rem;">'
                . 'Draft preview — this page is not published yet.</div>';
        }
    }
}

// Cart Routes
$router->get('/cart', [CartController::class, 'index']);
$router->post('/cart/add', [CartController::class, 'add']);
$router->post('/cart/update', [CartController::class, 'update']);
$router->post('/cart/remove', [CartController::class, 'remove']);

// Checkout & Payment Routes
$router->post('/checkout/process', [CheckoutController::class, 'process']);
$router->post('/checkout/callback', [CheckoutController::class, 'callback']);
$router->get('/checkout/callback', [CheckoutController::class, 'callback']);

// Newsletter — the `subscribers` table existed with nothing routed to it.
$router->post('/newsletter/subscribe', [NewsletterController::class, 'subscribe']);

// Customer Accounts — registered ahead of the /{slug} wildcard below so
// it cannot be shadowed by a CMS page of the same slug.
$router->get('/account', [AccountController::class, 'dashboard']);
$router->get('/account/login', [AccountController::class, 'showLogin']);
$router->post('/account/login', [AccountController::class, 'login']);
$router->get('/account/register', [AccountController::class, 'showRegister']);
$router->post('/account/register', [AccountController::class, 'register']);
$router->get('/account/logout', [AccountController::class, 'logout']);

// Storefront Products & Shop Routes
$router->get('/products', [ProductController::class, 'index']);
$router->get('/shop', [ProductController::class, 'index']);
$router->get('/products/{slug}', [ProductController::class, 'show']);

// Storefront Home Page Route
$router->get('/', static function (Request $request) {
    return AxerStorefrontPage::render($request, 'home');
});

// Storefront Custom Page Route Wildcard — registered last so it cannot
// shadow a more specific route above. Constrained so that asset-looking
// requests (favicon.ico, robots.txt) do not trigger a page lookup.
$router->get('/{slug}', static function (Request $request, string $slug) {
    return AxerStorefrontPage::render($request, $slug);
})->where('slug', '[a-zA-Z0-9_\-]+');

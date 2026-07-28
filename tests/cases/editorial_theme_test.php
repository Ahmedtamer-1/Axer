<?php

/**
 * Every template the "editorial" theme ships has to satisfy the same
 * de-facto contract as "default" (see ThemeService/AxerStorefrontPage) —
 * a missing or broken template throws from Engine::resolveTemplate() the
 * first time a real request hits that route. Rendered here with
 * representative data for each template so a typo in a .lume file fails
 * the test suite instead of only surfacing as a 500 on the live site.
 */

use Axer\Template\Engine;

group('Editorial theme templates');

$manifest = json_decode(file_get_contents(BASE_PATH . '/content/themes/editorial/theme.json'), true);

test('theme.json is valid and declares a settings_schema', function () use ($manifest) {
    assertTrue($manifest !== null);
    assertTrue(!empty($manifest['settings_schema']));
});

$engine = new Engine(
    [BASE_PATH . '/content/themes/editorial'],
    sys_get_temp_dir() . '/axer-editorial-tpl-' . getmypid()
);

$engine->addGlobals([
    'store' => ['name' => 'Test Store', 'tagline' => 'tag', 'currency' => 'USD', 'currency_symbol' => '$'],
    'theme' => $manifest['settings'],
    'cart_count' => 2,
    'current_user' => null,
    'is_admin' => true,
    'nav_pages' => [['slug' => 'about', 'title' => 'About']],
    'csrf_token' => 'abc',
    'current_year' => 2026,
]);

$cases = [
    'layouts/theme' => ['content' => '<p>x</p>', 'head_scripts' => '', 'footer_scripts' => '', 'page_title' => 'Home', 'meta_description' => 'd', 'flash_success' => null, 'flash_error' => null],
    'sections/hero' => ['title' => 'T', 'subtitle' => 'S', 'button_text' => 'B', 'button_url' => '/x'],
    'sections/featured-products' => ['title' => 'Best', 'featured_products' => [['slug' => 'x', 'name' => 'X', 'description' => 'd', 'image_url' => 'i', 'id' => 1, 'price' => '10.00']], 'products_empty' => false],
    'sections/rich-text' => ['title' => 'T', 'content' => 'C', 'align' => 'center'],
    'sections/text-image' => ['title' => 'T', 'content' => 'C', 'image_url' => 'i', 'image_position' => 'left', 'bg_color' => '#fff', 'text_color' => '#000'],
    'sections/newsletter' => ['title' => 'T', 'subtitle' => 'S'],
    'products/index' => ['products' => [['slug' => 'a', 'name' => 'A', 'description' => 'd', 'image_url' => 'i', 'price' => '5.00']], 'products_empty' => false, 'search_term' => ''],
    'products/show' => ['product' => ['id' => 1, 'name' => 'P', 'formatted_price' => '10.00', 'formatted_description' => 'd', 'stock' => 5, 'price' => 10], 'options' => [], 'variants' => [], 'variants_json' => '[]', 'has_variants' => false, 'images' => [], 'images_json' => '[]'],
    'cart' => ['cart_items' => [], 'cart_count' => 0, 'cart_subtotal' => '0.00', 'cart_shipping' => 'Free', 'cart_total' => '0.00', 'is_empty' => true],
    'account/login' => ['error' => null, 'email' => ''],
    'account/register' => ['error' => null, 'email' => '', 'name' => ''],
    'account/index' => ['account' => ['id' => 1, 'name' => 'Jane', 'email' => 'jane@example.com']],
];

foreach ($cases as $template => $data) {
    test("renders {$template}", function () use ($engine, $template, $data) {
        $html = $engine->render($template, $data);

        assertTrue($html !== '', 'expected non-empty output');
    });
}

test('sections/featured-products renders its empty state', function () use ($engine) {
    $html = $engine->render('sections/featured-products', ['title' => 'Best', 'featured_products' => [], 'products_empty' => true]);

    assertContainsStr('No products yet', $html);
});

test('the layout only shows the admin link when is_admin is true', function () use ($engine) {
    $withAdmin = $engine->render('layouts/theme', [
        'content' => '', 'head_scripts' => '', 'footer_scripts' => '', 'page_title' => 'Home',
        'meta_description' => '', 'flash_success' => null, 'flash_error' => null,
    ]);

    assertContainsStr('/admin/dashboard', $withAdmin, 'is_admin=true (set on the shared engine) should render the admin link');
});

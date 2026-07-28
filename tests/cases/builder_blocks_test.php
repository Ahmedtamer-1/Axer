<?php

/**
 * Page builder blocks added alongside the original four (hero,
 * featured-products, rich-text, newsletter): text-image (existed on disk
 * in every theme but had no admin/assets/js/builder.js registry entry, so
 * it was unreachable from the "Add Section Block" library), plus five new
 * ones — image-gallery, faq, testimonials, cta, spacer.
 *
 * Covers two things: every new block renders in both themes, and
 * AxerStorefrontPage::fallbackBlock() — the safety net a theme falls back
 * to when it doesn't ship a given section template — produces the same
 * blocks without leaking unescaped settings into the page.
 */

use Axer\Core\Container;
use Axer\Core\Router;
use Axer\Template\Engine;

group('New page builder blocks');

$newBlockCases = [
    'text-image' => ['title' => 'T', 'content' => 'C', 'image_url' => 'i.jpg', 'image_position' => 'right', 'bg_color' => '#fff', 'text_color' => '#000'],
    'image-gallery' => ['title' => 'Gallery', 'image_1' => 'a.jpg', 'image_2' => 'b.jpg', 'image_3' => '', 'image_4' => ''],
    'faq' => ['title' => 'FAQ', 'q1' => 'Question?', 'a1' => 'Answer.', 'q2' => '', 'a2' => '', 'q3' => '', 'a3' => ''],
    'testimonials' => ['title' => 'Reviews', 'quote1' => 'Great!', 'author1' => 'Jane', 'quote2' => '', 'author2' => '', 'quote3' => '', 'author3' => ''],
    'cta' => ['title' => 'Buy now', 'subtitle' => 'sub', 'button_text' => 'Go', 'button_url' => '/shop'],
    'spacer' => ['height' => '4rem'],
];

foreach (['default', 'editorial'] as $theme) {
    $engine = new Engine([BASE_PATH . "/content/themes/{$theme}"], sys_get_temp_dir() . '/axer-builder-tpl-' . getmypid() . '-' . $theme);
    $engine->addGlobals([
        'store' => ['name' => 'S'], 'theme' => [], 'cart_count' => 0, 'current_user' => null,
        'is_admin' => false, 'nav_pages' => [], 'csrf_token' => 'x', 'current_year' => 2026,
    ]);

    foreach ($newBlockCases as $type => $settings) {
        test("{$theme} theme renders sections/{$type}", function () use ($engine, $type, $settings) {
            $html = $engine->render("sections/{$type}", $settings);

            assertTrue($html !== '', 'expected non-empty output');
        });
    }
}

group('fallbackBlock() — themes that omit a section template');

$router = new Router(new Container());
require BASE_PATH . '/config/routes.php';

$fallback = new ReflectionMethod('AxerStorefrontPage', 'fallbackBlock');
$fallback->setAccessible(true);

foreach ($newBlockCases as $type => $settings) {
    test("fallback renders {$type}", function () use ($fallback, $type, $settings) {
        $html = $fallback->invoke(null, $type, $settings, []);

        assertTrue($html !== '', 'expected non-empty output');
    });
}

test('fallback escapes settings instead of interpolating them raw', function () use ($fallback) {
    $html = $fallback->invoke(null, 'faq', ['q1' => '<script>alert(1)</script>', 'a1' => 'x'], []);

    assertNotContainsStr('<script>alert(1)</script>', $html);
});

test('an invalid spacer height falls back to a safe default instead of injecting CSS', function () use ($fallback) {
    $html = $fallback->invoke(null, 'spacer', ['height' => '</style><script>evil</script>'], []);

    assertNotContainsStr('<script>evil</script>', $html);
    assertContainsStr('3rem', $html);
});

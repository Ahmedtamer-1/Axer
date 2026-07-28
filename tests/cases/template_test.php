<?php

use Axer\Template\Engine;

group('Template engine');

$cacheDir = sys_get_temp_dir() . '/axer-tpl-' . getmypid();
@mkdir($cacheDir, 0777, true);

$engine = new Engine([], $cacheDir, true);

/** Compile and run a template source string directly. */
$render = static function (string $source, array $data = []) use ($engine, $cacheDir): string {
    $compiled = $engine->compileSource($source);
    $file = $cacheDir . '/case-' . md5($source . serialize($data)) . '.php';
    file_put_contents($file, $compiled);

    $reflection = new ReflectionMethod($engine, 'evaluate');
    $reflection->setAccessible(true);

    return $reflection->invoke($engine, $file, $data);
};

test('{% else %} renders only the branch that applies', function () use ($render) {
    $tpl = '{% if cart_count %}<span>{{ cart_count }}</span>{% else %}<span>0</span>{% endif %}';

    // The header template relies on this. Without else support, both arms
    // were emitted and the cart badge showed the count *and* a zero.
    assertSame('<span>3</span>', $render($tpl, ['cart_count' => 3]));
    assertSame('<span>0</span>', $render($tpl, ['cart_count' => 0]));
});

test('comparison operators are actually evaluated', function () use ($render) {
    // From sections/text-image.lume — this decides the flex direction.
    $tpl = "{% if image_position == 'left' %}row-reverse{% else %}row{% endif %}";

    assertSame('row-reverse', $render($tpl, ['image_position' => 'left']));
    assertSame('row', $render($tpl, ['image_position' => 'right']));

    // Previously the `== 'left'` was discarded, so any non-empty value
    // produced "row-reverserow".
    assertNotContainsStr('row-reverserow', $render($tpl, ['image_position' => 'right']));
});

test('!=, >, <, >= and <= all work', function () use ($render) {
    assertSame('yes', $render("{% if a != 'b' %}yes{% else %}no{% endif %}", ['a' => 'c']));
    assertSame('yes', $render('{% if n > 5 %}yes{% else %}no{% endif %}', ['n' => 10]));
    assertSame('no', $render('{% if n < 5 %}yes{% else %}no{% endif %}', ['n' => 10]));
    assertSame('yes', $render('{% if n >= 10 %}yes{% else %}no{% endif %}', ['n' => 10]));
    assertSame('yes', $render('{% if n <= 10 %}yes{% else %}no{% endif %}', ['n' => 10]));
});

test('and / or / not combine conditions', function () use ($render) {
    $tpl = '{% if a and b %}both{% else %}not both{% endif %}';
    assertSame('both', $render($tpl, ['a' => 1, 'b' => 1]));
    assertSame('not both', $render($tpl, ['a' => 1, 'b' => 0]));

    assertSame('either', $render('{% if a or b %}either{% else %}neither{% endif %}', ['a' => 0, 'b' => 1]));
    assertSame('negated', $render('{% if not a %}negated{% endif %}', ['a' => false]));
});

test('elseif picks the first matching branch', function () use ($render) {
    $tpl = "{% if s == 'a' %}A{% elseif s == 'b' %}B{% elseif s == 'c' %}C{% else %}other{% endif %}";

    assertSame('A', $render($tpl, ['s' => 'a']));
    assertSame('B', $render($tpl, ['s' => 'b']));
    assertSame('C', $render($tpl, ['s' => 'c']));
    assertSame('other', $render($tpl, ['s' => 'z']));
});

test('parenthesised filter arguments are applied', function () use ($render) {
    // Every theme writes it this way; only the `| filter: arg` form parsed,
    // so the default was silently dropped and the CSS variable came out
    // empty ("--primary: ;").
    assertSame('#6366f1', $render("{{ color | default('#6366f1') }}", []));
    assertSame('#ff0000', $render("{{ color | default('#6366f1') }}", ['color' => '#ff0000']));
});

test('the legacy colon filter syntax still works', function () use ($render) {
    assertSame('#6366f1', $render("{{ color | default: '#6366f1' }}", []));
});

test('filters chain left to right', function () use ($render) {
    assertSame('HELLO', $render('{{ name | trim | upper }}', ['name' => '  hello  ']));
});

test('output is escaped unless the raw filter is used', function () use ($render) {
    $payload = ['bio' => '<script>alert(1)</script>'];

    assertContainsStr('&lt;script&gt;', $render('{{ bio }}', $payload));
    assertContainsStr('<script>', $render('{{ bio | raw }}', $payload));
});

test('a for loop over a missing collection renders nothing instead of fataling', function () use ($render) {
    // foreach (null as ...) is a fatal in PHP 8; one absent context key
    // used to take the entire page down.
    assertSame('', $render('{% for p in products %}{{ p.name }}{% endfor %}', []));
    assertSame('', $render('{% for p in products %}{{ p.name }}{% endfor %}', ['products' => null]));
    assertSame('', $render('{% for p in products %}{{ p.name }}{% endfor %}', ['products' => 'not a list']));
});

test('for loops render each item', function () use ($render) {
    $out = $render('{% for p in products %}[{{ p.name }}]{% endfor %}', [
        'products' => [['name' => 'A'], ['name' => 'B']],
    ]);

    assertSame('[A][B]', $out);
});

test('for/else provides an empty state', function () use ($render) {
    $tpl = '{% for p in products %}[{{ p.name }}]{% else %}No products{% endfor %}';

    assertSame('[A]', $render($tpl, ['products' => [['name' => 'A']]]));
    assertSame('No products', $render($tpl, ['products' => []]));
    assertSame('No products', $render($tpl, []));
});

test('nested loops and conditionals compile correctly', function () use ($render) {
    $tpl = '{% for p in products %}{% if p.active %}[{{ p.name }}]{% endif %}{% endfor %}';

    $out = $render($tpl, ['products' => [
        ['name' => 'A', 'active' => true],
        ['name' => 'B', 'active' => false],
        ['name' => 'C', 'active' => true],
    ]]);

    assertSame('[A][C]', $out);
});

test('missing nested keys resolve to empty, not a warning', function () use ($render) {
    assertSame('', $render('{{ product.image.url }}', ['product' => []]));
    assertSame('', $render('{{ theme.colors.primary }}', []));
    assertSame('ok', $render('{{ theme.colors.primary }}', ['theme' => ['colors' => ['primary' => 'ok']]]));
});

test('an array reaching output does not print the word Array', function () use ($render) {
    assertNotContainsStr('Array', $render('{{ items }}', ['items' => ['a', 'b']]));
});

test('context data cannot clobber the renderer internals', function () use ($render) {
    // These names collide with the locals the old render() used.
    $out = $render('{{ template }}|{{ context }}|{{ compiledFile }}', [
        'template' => 'T',
        'context' => 'C',
        'compiledFile' => 'F',
    ]);

    assertSame('T|C|F', $out);
});

test('an unknown filter degrades instead of throwing', function () use ($render) {
    assertSame('hello', $render('{{ name | nosuchfilter }}', ['name' => 'hello']));
});

test('money filter formats to two decimals', function () use ($render) {
    assertSame('1,234.50 EGP', $render('{{ price | money }}', ['price' => 1234.5]));
});

test('default() treats 0 as a real value', function () use ($render) {
    assertSame('0', $render("{{ stock | default('n/a') }}", ['stock' => 0]));
    assertSame('n/a', $render("{{ stock | default('n/a') }}", ['stock' => null]));
});

test('truncate is multibyte safe', function () use ($render) {
    $arabic = 'قميص قطني أزرق جميل جدا للبيع الآن';
    $out = $render('{{ text | truncate(10) }}', ['text' => $arabic]);

    assertTrue(mb_check_encoding($out, 'UTF-8'), 'truncation must not split a character');
    assertTrue(mb_strlen($out, 'UTF-8') <= 12);
});

test('comments are stripped', function () use ($render) {
    assertSame('ab', $render('a{# this is a comment #}b'));
});

test('template names cannot traverse out of the theme directory', function () use ($engine) {
    assertThrows(
        static fn() => $engine->render('../../../config/.env'),
        'template resolution must reject ..'
    );
});

array_map('unlink', glob($cacheDir . '/*.php') ?: []);
@rmdir($cacheDir);

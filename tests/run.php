<?php

/**
 * Dependency-free test runner.
 *
 *   php tests/run.php
 *
 * Covers the pure logic that broke most often — SQL generation, template
 * compilation, slug/text helpers and request parsing. Anything requiring a
 * live MySQL connection is out of scope here.
 */

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require_once BASE_PATH . '/app/Core/App.php';

// App registers the autoloader in its constructor, but booting the whole
// application would demand a live database. Register the same rule here.
spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'Axer\\')) {
        return;
    }

    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, 5)) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

if (!defined('APP_DEBUG')) {
    define('APP_DEBUG', true);
}

$GLOBALS['axer_tests'] = ['passed' => 0, 'failed' => 0, 'failures' => []];

function test(string $name, callable $fn): void
{
    try {
        $fn();
        $GLOBALS['axer_tests']['passed']++;
        echo "  \033[32m✓\033[0m {$name}\n";
    } catch (\Throwable $e) {
        $GLOBALS['axer_tests']['failed']++;
        $GLOBALS['axer_tests']['failures'][] = $name . ' — ' . $e->getMessage();
        echo "  \033[31m✗\033[0m {$name}\n";
        echo "      \033[31m" . $e->getMessage() . "\033[0m\n";
    }
}

function group(string $name): void
{
    echo "\n\033[1m{$name}\033[0m\n";
}

function assertSame($expected, $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        throw new \Exception(
            ($message ? $message . ': ' : '')
            . 'expected ' . var_export($expected, true)
            . ', got ' . var_export($actual, true)
        );
    }
}

function assertTrue($value, string $message = 'expected true'): void
{
    if ($value !== true) {
        throw new \Exception($message . ', got ' . var_export($value, true));
    }
}

function assertFalse($value, string $message = 'expected false'): void
{
    if ($value !== false) {
        throw new \Exception($message . ', got ' . var_export($value, true));
    }
}

function assertContainsStr(string $needle, string $haystack, string $message = ''): void
{
    if (!str_contains($haystack, $needle)) {
        throw new \Exception(
            ($message ? $message . ': ' : '') . "expected to find '{$needle}' in '{$haystack}'"
        );
    }
}

function assertNotContainsStr(string $needle, string $haystack, string $message = ''): void
{
    if (str_contains($haystack, $needle)) {
        throw new \Exception(
            ($message ? $message . ': ' : '') . "did not expect '{$needle}' in '{$haystack}'"
        );
    }
}

function assertThrows(callable $fn, string $message = 'expected an exception'): void
{
    try {
        $fn();
    } catch (\Throwable $e) {
        return;
    }

    throw new \Exception($message);
}

foreach (glob(__DIR__ . '/cases/*.php') as $file) {
    require $file;
}

$results = $GLOBALS['axer_tests'];

echo "\n" . str_repeat('─', 52) . "\n";

if ($results['failed'] === 0) {
    echo "\033[32m{$results['passed']} passed\033[0m\n";
    exit(0);
}

echo "\033[32m{$results['passed']} passed\033[0m, \033[31m{$results['failed']} failed\033[0m\n\n";

foreach ($results['failures'] as $failure) {
    echo "  • {$failure}\n";
}

exit(1);

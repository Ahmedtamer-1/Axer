<?php

/**
 * Theme customizer settings, executed against an in-memory SQLite
 * database. Covers the customizer's defects: saving overwrote the whole
 * settings column with only the posted fields (destroying anything the
 * form didn't render, like layout.button_radius and the store block),
 * activate() never refreshed a theme's stored metadata after the first
 * time it was activated, and saving a theme that was never activated had
 * no row to UPDATE.
 */

use Axer\Database\Connection;
use Axer\Database\QueryBuilder;
use Axer\Services\ThemeService;

group('Theme customizer settings');

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec('CREATE TABLE themes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT UNIQUE,
    name TEXT,
    description TEXT,
    version TEXT,
    author TEXT,
    author_url TEXT,
    screenshot TEXT,
    is_active INTEGER DEFAULT 0,
    settings TEXT,
    settings_schema TEXT,
    installed_at TEXT
)');

$pdo->exec('CREATE TABLE settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    "group" TEXT,
    "key" TEXT,
    value TEXT,
    type TEXT
)');

$pdo->exec('CREATE TABLE pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT,
    status TEXT,
    show_in_nav INTEGER DEFAULT 0,
    sort_order INTEGER DEFAULT 0,
    title TEXT
)');

Connection::setInstance($pdo);

test('saveThemeSettings() persists even for a theme that was never activated', function () {
    assertSame(null, QueryBuilder::table('themes')->where('slug', 'default')->first());

    $ok = ThemeService::saveThemeSettings('default', ['colors.primary' => '#222222']);
    assertTrue($ok);

    $row = QueryBuilder::table('themes')->where('slug', 'default')->first();
    assertTrue($row !== null, 'a row should now exist even though the theme was never activated');
    assertSame(0, (int) $row['is_active']);

    $settings = json_decode($row['settings'], true);
    assertSame('#222222', $settings['colors']['primary']);
});

test('activating seeds the full settings tree on top of what was already saved', function () {
    $ok = ThemeService::activateTheme('default');
    assertTrue($ok);

    $row = QueryBuilder::table('themes')->where('slug', 'default')->first();
    assertSame(1, (int) $row['is_active']);

    $settings = json_decode($row['settings'], true);
    assertTrue(isset($settings['layout']['button_radius']), 'manifest defaults should be present');
});

test('saving only a subset of fields does not delete the rest of the settings tree', function () {
    ThemeService::flushCaches();

    // The customizer only posts colors.* and typography.* — layout.* and
    // store.* were never in the form, so the old whole-column overwrite
    // deleted them on the very first save.
    $ok = ThemeService::saveThemeSettings('default', [
        'colors.primary' => '#111827',
    ]);
    assertTrue($ok);

    $row = QueryBuilder::table('themes')->where('slug', 'default')->first();
    $settings = json_decode($row['settings'], true);

    assertSame('#111827', $settings['colors']['primary']);
    assertTrue(isset($settings['layout']['button_radius']), 'layout.button_radius must survive a save that never touched it');
    assertTrue(isset($settings['store']['name']), 'the store block must survive a save that never touched it');
});

test('an out-of-range select value is clamped to a declared option', function () {
    ThemeService::flushCaches();

    ThemeService::saveThemeSettings('default', [
        'layout.button_radius' => '<script>alert(1)</script>',
    ]);

    $row = QueryBuilder::table('themes')->where('slug', 'default')->first();
    $settings = json_decode($row['settings'], true);

    assertTrue(
        in_array($settings['layout']['button_radius'], ['0px', '0.25rem', '0.75rem', '999px'], true),
        'an invalid select value must not reach storage verbatim'
    );
});

<?php

/**
 * Plugin configuration, executed against an in-memory SQLite database.
 *
 * Covers the three stacked defects that made every plugin's settings page
 * render "This plugin does not require any custom settings configuration.":
 * class_exists() only succeeding for already-active plugins, no seed rows
 * on a fresh install, and savePluginSettings() being UPDATE-only.
 */

use Axer\Database\Connection;
use Axer\Database\QueryBuilder;
use Axer\Plugin\PluginManager;
use Axer\Support\SettingsSchema;

group('Plugin configuration');

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$pdo->exec('CREATE TABLE plugins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    slug TEXT UNIQUE,
    name TEXT,
    description TEXT,
    version TEXT,
    author TEXT,
    is_active INTEGER DEFAULT 0,
    settings TEXT,
    settings_schema TEXT,
    installed_at TEXT
)');

Connection::setInstance($pdo);

test('an inactive plugin still exposes its settings_schema from the manifest', function () {
    // This is the reported bug: PluginController::settings() used to build
    // the schema by class_exists()-checking a class that is only loaded
    // for active plugins. getInstalledPlugins() now reads settings_schema
    // straight from plugin.json, which needs no PHP to be executed.
    $manager = new PluginManager();
    $plugins = $manager->getInstalledPlugins();

    $googleAds = null;
    foreach ($plugins as $plugin) {
        if ($plugin['slug'] === 'google-ads') {
            $googleAds = $plugin;
        }
    }

    assertTrue($googleAds !== null, 'google-ads plugin should be discovered from content/plugins/');
    assertFalse($googleAds['is_active'], 'no plugins row exists yet, so it must read as inactive');
    assertTrue($googleAds['settings_schema'] !== [], 'schema must come from the manifest, not the PHP class');
});

test('savePluginSettings() persists even when the plugin was never activated', function () {
    // The old version was UPDATE-only: with no existing row it matched
    // zero rows and still returned true, silently discarding the save.
    $manager = new PluginManager();

    assertSame(null, QueryBuilder::table('plugins')->where('slug', 'meta-pixel')->first());

    $ok = $manager->savePluginSettings('meta-pixel', ['pixel_id' => '123456789']);
    assertTrue($ok);

    $row = QueryBuilder::table('plugins')->where('slug', 'meta-pixel')->first();
    assertTrue($row !== null, 'a row should now exist for a plugin that was never activated');

    $settings = json_decode($row['settings'], true);
    assertSame('123456789', $settings['pixel_id']);
});

test('activate() then savePluginSettings() updates the same row instead of inserting a duplicate', function () {
    $manager = new PluginManager();
    $manager->activate('tiktok-pixel');
    $manager->savePluginSettings('tiktok-pixel', ['pixel_id' => 'abc']);

    $rows = QueryBuilder::table('plugins')->where('slug', 'tiktok-pixel')->get();
    assertSame(1, count($rows));
    assertSame(1, (int) $rows[0]['is_active']);
});

test('a route-supplied slug cannot escape the plugins directory', function () {
    $manager = new PluginManager();

    // loadPlugin() ends in require_once — this must never reach the
    // filesystem with an unvalidated path.
    assertFalse($manager->loadPlugin('../../../etc/passwd'));
    assertFalse($manager->activate('../../../etc/passwd'));
    assertSame([], $manager->scanPlugin('../../../etc/passwd'));
});

group('SettingsSchema (shared by plugin config and theme customizer)');

test('unknown posted keys are dropped, not persisted', function () {
    $schema = [['name' => 'Tracking', 'settings' => [
        ['name' => 'conversion_id', 'label' => 'Conversion ID', 'type' => 'text'],
    ]]];

    $result = SettingsSchema::sanitize($schema, ['conversion_id' => 'abc', 'injected' => 'x'], []);

    assertSame(['conversion_id' => 'abc'], $result['values']);
});

test('a required field left empty is reported', function () {
    $schema = [['name' => 'g', 'settings' => [
        ['name' => 'gtm_id', 'label' => 'GTM ID', 'type' => 'text', 'required' => true],
    ]]];

    $result = SettingsSchema::sanitize($schema, [], []);

    assertSame(['GTM ID'], $result['missingRequired']);
});

test('a password field submitted empty keeps its stored value instead of being wiped', function () {
    $schema = [['name' => 'g', 'settings' => [
        ['name' => 'api_key', 'label' => 'API Key', 'type' => 'password'],
    ]]];

    $result = SettingsSchema::sanitize($schema, ['api_key' => ''], ['api_key' => 'existing-secret']);

    assertSame('existing-secret', $result['values']['api_key']);
});

test('a select value outside the declared options does not pass through', function () {
    $schema = [['name' => 'g', 'settings' => [
        ['name' => 'radius', 'label' => 'Radius', 'type' => 'select', 'options' => ['0px', '0.75rem']],
    ]]];

    $result = SettingsSchema::sanitize($schema, ['radius' => '<script>'], []);

    assertTrue(in_array($result['values']['radius'], ['0px', '0.75rem'], true));
});

test('a flat ungrouped schema (the BasePlugin PHP fallback) normalizes to one group', function () {
    $flat = [['name' => 'pixel_id', 'label' => 'Pixel', 'type' => 'text']];
    $groups = SettingsSchema::normalizeGroups($flat);

    assertSame(1, count($groups));
    assertSame('pixel_id', $groups[0]['settings'][0]['name']);
});

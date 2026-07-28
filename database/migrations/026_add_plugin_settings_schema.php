<?php

/**
 * The `themes` table already carried a `settings_schema` column so the
 * theme customizer could render its form straight from the manifest.
 * `plugins` had no equivalent, so a plugin's configuration form could
 * only be built by executing the plugin's PHP class — which only
 * happened for already-active plugins (see PluginManager::loadPlugin()).
 * This brings plugins to the same shape as themes.
 */
class AddPluginSettingsSchema
{
    public function up(\PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE `plugins` ADD COLUMN `settings_schema` LONGTEXT DEFAULT NULL AFTER `settings`");
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("ALTER TABLE `plugins` DROP COLUMN `settings_schema`");
    }
}

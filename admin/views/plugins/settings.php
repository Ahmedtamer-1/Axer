<?php

use Axer\Support\SettingsSchema;

$groups = SettingsSchema::normalizeGroups($schema);
$settings = $plugin['settings'] ?? [];
?>
<div class="header-bar">
    <div class="header-title">
        <h1>Plugin Settings: <?= htmlspecialchars($plugin['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p>Configure API credentials and settings for this extension.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/plugins" class="btn btn-secondary"><i data-lucide="arrow-left"></i> Back to Plugins</a>
    </div>
</div>

<div class="card" style="max-width: 650px;">
    <form method="POST" action="/admin/plugins/settings/<?= htmlspecialchars($plugin['slug'], ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Axer\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

        <?php if ($groups === []): ?>
            <p style="color: var(--text-muted);">This plugin does not require any custom settings configuration.</p>
        <?php else: ?>
            <?php foreach ($groups as $group): ?>
                <?php if (($group['name'] ?? '') !== ''): ?>
                    <h3 style="font-size: 0.95rem; font-weight: 700; margin: 1.75rem 0 1rem; color: var(--text-color);"><?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                <?php endif; ?>

                <?php foreach (($group['settings'] ?? []) as $field): ?>
                    <?php
                    $fieldNamePrefix = 'settings';
                    $value = $settings[$field['name']] ?? '';
                    require BASE_PATH . '/admin/views/partials/settings-field.php';
                    ?>
                <?php endforeach; ?>
            <?php endforeach; ?>

            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Settings</button>
            </div>
        <?php endif; ?>
    </form>
</div>

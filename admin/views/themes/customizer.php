<?php

use Axer\Support\Arr;
use Axer\Support\SettingsSchema;

$schema = $theme['settings_schema'] ?? [];
$groups = SettingsSchema::normalizeGroups($schema);
$settings = $theme['settings'] ?? [];
?>
<div class="header-bar">
    <div class="header-title">
        <h1>Customize Theme: <?= htmlspecialchars($theme['name'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p>Modify colors, typography, and storefront layout settings.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/themes" class="btn btn-secondary"><i data-lucide="arrow-left"></i> Back to Themes</a>
    </div>
</div>

<form method="POST" action="/admin/themes/customize/<?= htmlspecialchars($theme['slug'], ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Axer\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

    <?php if ($groups === []): ?>
        <div class="card">
            <p style="color: var(--text-muted);">This theme does not declare any customizable settings.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <?php foreach ($groups as $group): ?>
                <div class="card">
                    <?php if (($group['name'] ?? '') !== ''): ?>
                        <h3 style="margin-bottom: 1.25rem; font-size: 1.1rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem;">
                            <i data-lucide="sliders-horizontal"></i> <?= htmlspecialchars($group['name'], ENT_QUOTES, 'UTF-8') ?>
                        </h3>
                    <?php endif; ?>

                    <?php foreach (($group['settings'] ?? []) as $field): ?>
                        <?php
                        // Field names are dot-paths ("colors.primary") into
                        // the nested settings tree, so the posted key stays
                        // flat (settings[colors.primary]) — ThemeService
                        // expands it back into the tree on save.
                        $fieldNamePrefix = 'settings';
                        $value = Arr::get($settings, (string) $field['name'], '');
                        require BASE_PATH . '/admin/views/partials/settings-field.php';
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 1rem; text-align: right;">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;"><i data-lucide="save"></i> Save Theme Settings</button>
        </div>
    <?php endif; ?>
</form>

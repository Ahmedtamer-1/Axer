<div class="header-bar">
    <div class="header-title">
        <h1>Plugin Settings: <?= htmlspecialchars($plugin['name']) ?></h1>
        <p>Configure API credentials and settings for this extension.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/plugins" class="btn btn-secondary"><i data-lucide="arrow-left"></i> Back to Plugins</a>
    </div>
</div>

<div class="card" style="max-width: 650px;">
    <form method="POST" action="/admin/plugins/settings/<?= htmlspecialchars($plugin['slug']) ?>">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Axer\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
        
        <?php if (empty($schema)): ?>
            <p style="color: var(--text-muted);">This plugin does not require any custom settings configuration.</p>
        <?php else: ?>
            <?php foreach ($schema as $field): 
                $val = $plugin['settings'][$field['name']] ?? '';
            ?>
                <div class="form-group">
                    <label class="form-label"><?= htmlspecialchars($field['label']) ?></label>
                    <?php if (($field['type'] ?? 'text') === 'password'): ?>
                        <input type="password" class="form-control" name="settings[<?= htmlspecialchars($field['name']) ?>]" value="<?= htmlspecialchars($val) ?>" placeholder="••••••••">
                    <?php elseif (($field['type'] ?? 'text') === 'textarea'): ?>
                        <textarea class="form-control" rows="4" name="settings[<?= htmlspecialchars($field['name']) ?>]"><?= htmlspecialchars($val) ?></textarea>
                    <?php else: ?>
                        <input type="text" class="form-control" name="settings[<?= htmlspecialchars($field['name']) ?>]" value="<?= htmlspecialchars($val) ?>">
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <div style="margin-top: 1.5rem; text-align: right;">
                <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Settings</button>
            </div>
        <?php endif; ?>
    </form>
</div>

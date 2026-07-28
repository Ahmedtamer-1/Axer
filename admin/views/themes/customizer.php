<div class="header-bar">
    <div class="header-title">
        <h1>Customize Theme: <?= htmlspecialchars($theme['name']) ?></h1>
        <p>Modify colors, typography, and storefront layout settings.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/themes" class="btn btn-secondary"><i data-lucide="arrow-left"></i> Back to Themes</a>
    </div>
</div>

<form method="POST" action="/admin/themes/customize/<?= htmlspecialchars($theme['slug']) ?>">
    <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Axer\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <!-- Colors Card -->
        <div class="card">
            <h3 style="margin-bottom: 1.25rem; font-size: 1.1rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="palette"></i> Theme Colors
            </h3>
            
            <?php 
            $colors = $theme['settings']['colors'] ?? [
                'primary' => '#6366f1',
                'secondary' => '#1e293b',
                'background' => '#ffffff',
                'text' => '#1e293b'
            ];
            foreach ($colors as $key => $val): 
            ?>
                <div class="form-group">
                    <label class="form-label"><?= ucfirst($key) ?> Color</label>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="color" name="settings[colors][<?= htmlspecialchars($key) ?>]" value="<?= htmlspecialchars($val) ?>" style="width: 45px; height: 40px; border: 1px solid var(--border-color); border-radius: 0.375rem; cursor: pointer;">
                        <input type="text" class="form-control" value="<?= htmlspecialchars($val) ?>" readonly style="font-family: monospace;">
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Typography & Layout Card -->
        <div class="card">
            <h3 style="margin-bottom: 1.25rem; font-size: 1.1rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="type"></i> Typography & Layout
            </h3>

            <?php 
            $typo = $theme['settings']['typography'] ?? ['heading_font' => 'Outfit', 'body_font' => 'Outfit'];
            ?>
            <div class="form-group">
                <label class="form-label">Heading Font</label>
                <select name="settings[typography][heading_font]" class="form-control">
                    <option value="Outfit" <?= ($typo['heading_font'] ?? '') === 'Outfit' ? 'selected' : '' ?>>Outfit</option>
                    <option value="Inter" <?= ($typo['heading_font'] ?? '') === 'Inter' ? 'selected' : '' ?>>Inter</option>
                    <option value="Roboto" <?= ($typo['heading_font'] ?? '') === 'Roboto' ? 'selected' : '' ?>>Roboto</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Body Font</label>
                <select name="settings[typography][body_font]" class="form-control">
                    <option value="Outfit" <?= ($typo['body_font'] ?? '') === 'Outfit' ? 'selected' : '' ?>>Outfit</option>
                    <option value="Inter" <?= ($typo['body_font'] ?? '') === 'Inter' ? 'selected' : '' ?>>Inter</option>
                    <option value="Roboto" <?= ($typo['body_font'] ?? '') === 'Roboto' ? 'selected' : '' ?>>Roboto</option>
                </select>
            </div>

            <?php 
            $layout = $theme['settings']['layout'] ?? ['container_width' => '1200px'];
            ?>
            <div class="form-group">
                <label class="form-label">Container Width</label>
                <input type="text" class="form-control" name="settings[layout][container_width]" value="<?= htmlspecialchars($layout['container_width'] ?? '1200px') ?>">
            </div>
        </div>
    </div>

    <div style="margin-top: 1rem; text-align: right;">
        <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;"><i data-lucide="save"></i> Save Theme Settings</button>
    </div>
</form>

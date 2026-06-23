<div class="header-bar">
    <div class="header-title">
        <h1>Customize Theme - <?= htmlspecialchars($theme['name']) ?></h1>
        <p>Edit global styles, colors, and typography settings.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/themes" class="btn btn-secondary"><i data-lucide="arrow-left"></i> Back to Themes</a>
    </div>
</div>

<style>
    .customizer-layout {
        display: grid;
        grid-template-columns: 360px 1fr;
        gap: 1.5rem;
        align-items: start;
    }
    .customizer-sidebar {
        background-color: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
    }
    .form-section-title {
        font-size: 0.8125rem;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: var(--text-muted);
        margin: 1.5rem 0 1rem;
        border-bottom: 1px solid var(--border-color);
        padding-bottom: 0.5rem;
    }
    .form-section-title:first-of-type {
        margin-top: 0;
    }
    .form-group {
        margin-bottom: 1.25rem;
    }
    .form-group label {
        display: block;
        font-weight: 500;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    .form-control {
        width: 100%;
        padding: 0.625rem 0.875rem;
        border-radius: 0.375rem;
        border: 1px solid var(--border-color);
        font-family: var(--font-main);
        font-size: 0.875rem;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
    }
    .preview-pane {
        background-color: #ffffff;
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        height: 600px;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
    }
</style>

<div class="customizer-layout">
    <div class="customizer-sidebar">
        <form action="/admin/themes/customize/<?= $theme['slug'] ?>" method="POST">
            <?php foreach ($theme['settings_schema'] as $section): ?>
                <div class="form-section-title"><?= htmlspecialchars($section['name']) ?></div>
                <?php foreach ($section['settings'] as $setting): ?>
                    <?php 
                    $val = $theme['settings'][$setting['name']] ?? '';
                    ?>
                    <div class="form-group">
                        <label for="settings_<?= $setting['name'] ?>"><?= htmlspecialchars($setting['label']) ?></label>
                        <?php if ($setting['type'] === 'select'): ?>
                            <select id="settings_<?= $setting['name'] ?>" name="settings[<?= $setting['name'] ?>]" class="form-control">
                                <?php foreach ($setting['options'] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $opt == $val ? 'selected' : '' ?>><?= htmlspecialchars($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($setting['type'] === 'color'): ?>
                            <input type="color" id="settings_<?= $setting['name'] ?>" name="settings[<?= $setting['name'] ?>]" class="form-control" style="height: 40px; padding: 2px;" value="<?= htmlspecialchars($val) ?>">
                        <?php else: ?>
                            <input type="text" id="settings_<?= $setting['name'] ?>" name="settings[<?= $setting['name'] ?>]" class="form-control" value="<?= htmlspecialchars($val) ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            
            <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 1rem;"><i data-lucide="save"></i> Save Options</button>
        </form>
    </div>
    
    <div class="preview-pane">
        <!-- In a real environment, this can load a live preview of the frontpage -->
        <div style="text-align: center;">
            <i data-lucide="eye" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"></i>
            <p>Theme Settings Customizer Panel.</p>
            <p style="font-size:0.75rem; margin-top: 0.25rem;">Preview will reflect on your actual shopfront instantly upon saving.</p>
        </div>
    </div>
</div>

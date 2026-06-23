<div class="header-bar">
    <div class="header-title">
        <h1>Themes</h1>
        <p>Manage and customize templates for your shop storefront.</p>
    </div>
</div>

<style>
    .themes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 1.5rem;
    }
    .theme-card {
        background-color: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        overflow: hidden;
        box-shadow: var(--shadow-sm);
        display: flex;
        flex-direction: column;
        transition: all 0.2s ease;
    }
    .theme-card:hover {
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }
    .theme-screenshot {
        height: 200px;
        background-color: #e2e8f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: var(--text-muted);
        font-weight: 600;
        border-bottom: 1px solid var(--border-color);
    }
    .theme-body {
        padding: 1.5rem;
        flex-grow: 1;
        display: flex;
        flex-direction: column;
    }
    .theme-name {
        font-size: 1.125rem;
        font-weight: 600;
        margin-bottom: 0.5rem;
    }
    .theme-desc {
        font-size: 0.875rem;
        color: var(--text-muted);
        line-height: 1.5;
        margin-bottom: 1.25rem;
        flex-grow: 1;
    }
    .theme-meta {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-bottom: 1.25rem;
        display: flex;
        gap: 1rem;
    }
    .theme-actions {
        display: flex;
        gap: 0.75rem;
    }
</style>

<div class="themes-grid">
    <?php foreach ($themes as $theme): ?>
        <div class="theme-card">
            <div class="theme-screenshot">
                <i data-lucide="palette" style="width: 48px; height: 48px; opacity: 0.5;"></i>
            </div>
            <div class="theme-body">
                <div class="theme-name">
                    <?= htmlspecialchars($theme['name']) ?>
                    <?php if ($theme['is_active']): ?>
                        <span class="badge badge-published" style="font-size: 0.7rem; margin-left: 0.5rem;">Active</span>
                    <?php endif; ?>
                </div>
                <div class="theme-desc"><?= htmlspecialchars($theme['description']) ?></div>
                <div class="theme-meta">
                    <span>Version: <?= htmlspecialchars($theme['version']) ?></span>
                    <span>By: <?= htmlspecialchars($theme['author']) ?></span>
                </div>
                <div class="theme-actions">
                    <?php if ($theme['is_active']): ?>
                        <a href="/admin/themes/customize/<?= $theme['slug'] ?>" class="btn btn-primary" style="flex-grow:1;"><i data-lucide="settings"></i> Customize Theme</a>
                    <?php else: ?>
                        <form action="/admin/themes/activate/<?= $theme['slug'] ?>" method="POST" style="flex-grow:1; display:flex;">
                            <button type="submit" class="btn btn-secondary" style="flex-grow:1;"><i data-lucide="power"></i> Activate</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

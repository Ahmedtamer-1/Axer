<div class="header-bar">
    <div class="header-title">
        <h1>Plugins & Marketplace</h1>
        <p>Extend the functionality of your store with powerful plugins.</p>
    </div>
</div>

<style>
    .plugin-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }
    .plugin-card {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }
    .plugin-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 1rem;
    }
    .plugin-icon {
        width: 48px;
        height: 48px;
        background-color: rgba(99, 102, 241, 0.1);
        color: var(--primary);
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .plugin-info h3 {
        font-size: 1.125rem;
        color: var(--text-main);
        margin-bottom: 0.25rem;
    }
    .plugin-info p {
        font-size: 0.875rem;
        color: var(--text-muted);
        line-height: 1.5;
    }
    .plugin-footer {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid var(--border-color);
    }
    .plugin-price {
        font-weight: 600;
        color: var(--text-main);
    }
</style>

<h2 style="margin-bottom: 1.5rem; font-size: 1.25rem; color: var(--text-main);">Installed Plugins</h2>
<?php if (empty($installedPlugins)): ?>
    <div class="card" style="text-align: center; padding: 3rem;">
        <i data-lucide="package" style="width: 48px; height: 48px; color: var(--text-muted); margin-bottom: 1rem;"></i>
        <h3 style="color: var(--text-main); margin-bottom: 0.5rem;">No plugins installed</h3>
        <p style="color: var(--text-muted);">Explore the marketplace below to find plugins for your store.</p>
    </div>
<?php else: ?>
    <div class="plugin-grid" style="margin-bottom: 3rem;">
        <?php foreach ($installedPlugins as $id => $plugin): ?>
            <div class="card plugin-card">
                <div>
                    <div class="plugin-header">
                        <div class="plugin-icon"><i data-lucide="puzzle"></i></div>
                        <span class="badge" style="background-color: rgba(16, 185, 129, 0.1); color: var(--success); padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Active</span>
                    </div>
                    <div class="plugin-info">
                        <h3><?= htmlspecialchars($plugin['name'] ?? $id) ?></h3>
                        <p><?= htmlspecialchars($plugin['description'] ?? 'No description provided.') ?></p>
                    </div>
                </div>
                <div class="plugin-footer">
                    <span style="font-size: 0.875rem; color: var(--text-muted);">v<?= htmlspecialchars($plugin['version'] ?? '1.0.0') ?></span>
                    <button class="btn btn-secondary" style="padding: 0.5rem 1rem;">Settings</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<h2 style="margin: 3rem 0 1.5rem 0; font-size: 1.25rem; color: var(--text-main);">Axer Marketplace</h2>
<div class="plugin-grid">
    <?php foreach ($marketplacePlugins as $plugin): ?>
        <div class="card plugin-card">
            <div>
                <div class="plugin-header">
                    <div class="plugin-icon" style="background-color: #f8fafc; color: var(--text-muted); border: 1px solid var(--border-color);"><i data-lucide="download-cloud"></i></div>
                    <div class="plugin-price"><?= htmlspecialchars($plugin['price']) ?></div>
                </div>
                <div class="plugin-info">
                    <h3><?= htmlspecialchars($plugin['name']) ?></h3>
                    <p style="margin-bottom: 0.5rem; font-size: 0.75rem;">By <?= htmlspecialchars($plugin['developer']) ?></p>
                    <p><?= htmlspecialchars($plugin['description']) ?></p>
                </div>
            </div>
            <div class="plugin-footer">
                <button class="btn btn-primary" style="width: 100%; justify-content: center;">Install Plugin</button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

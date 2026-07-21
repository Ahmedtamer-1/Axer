<div class="header-bar">
    <div class="header-title">
        <h1>Plugin Marketplace</h1>
        <p>Explore and install integrations for payment gateways, ad pixels, and OAuth login providers.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/plugins" class="btn btn-secondary"><i data-lucide="arrow-left"></i> Installed Plugins</a>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
    <?php foreach ($catalog as $item): ?>
        <div class="card" style="display: flex; flex-direction: column; justify-content: space-between;">
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 1rem;">
                    <div style="width: 44px; height: 44px; border-radius: 0.5rem; background: rgba(99,102,241,0.1); color: var(--primary); display: flex; align-items: center; justify-content: center;">
                        <i data-lucide="<?= htmlspecialchars($item['icon'] ?? 'plug') ?>"></i>
                    </div>
                    <span class="badge badge-secondary" style="text-transform: uppercase; font-size: 0.7rem;"><?= htmlspecialchars($item['category'] ?? 'extension') ?></span>
                </div>
                <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.5rem;"><?= htmlspecialchars($item['name']) ?></h3>
                <p style="font-size: 0.875rem; color: var(--text-muted); line-height: 1.5; margin-bottom: 1.25rem;"><?= htmlspecialchars($item['description']) ?></p>
            </div>
            
            <div style="display: flex; align-items: center; justify-content: space-between; border-top: 1px solid var(--border-color); padding-top: 1rem; margin-top: 0.5rem;">
                <span style="font-size: 0.75rem; color: var(--text-muted);">By <?= htmlspecialchars($item['author']) ?></span>
                <?php if ($item['is_installed']): ?>
                    <button class="btn btn-secondary" disabled style="opacity: 0.7;"><i data-lucide="check"></i> Installed</button>
                <?php else: ?>
                    <form action="/admin/plugins/activate/<?= htmlspecialchars($item['slug']) ?>" method="POST">
                        <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="btn btn-primary"><i data-lucide="download"></i> Install & Enable</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="header-bar">
    <div class="header-title">
        <h1>Installed Plugins</h1>
        <p>Manage and configure store integrations and extensions.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/plugins/marketplace" class="btn btn-primary"><i data-lucide="shopping-bag"></i> Plugin Marketplace</a>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Plugin</th>
                    <th>Description</th>
                    <th>Version</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($installedPlugins)): ?>
                    <tr>
                        <td colspan="5" style="text-align: center; color: var(--text-muted); padding: 2rem;">No plugins installed yet. Visit the Marketplace to explore extensions.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($installedPlugins as $plugin): ?>
                        <tr>
                            <td style="font-weight: 600;">
                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                    <i data-lucide="plug" style="color: var(--primary);"></i>
                                    <?= htmlspecialchars($plugin['name']) ?>
                                </div>
                            </td>
                            <td style="color: var(--text-muted); max-width: 400px;"><?= htmlspecialchars($plugin['description']) ?></td>
                            <td><span class="badge badge-secondary">v<?= htmlspecialchars($plugin['version']) ?></span></td>
                            <td>
                                <?php if ($plugin['is_active']): ?>
                                    <span class="badge badge-success">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-danger">Disabled</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.5rem;">
                                    <a href="/admin/plugins/settings/<?= $plugin['slug'] ?>" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem;"><i data-lucide="settings"></i> Configure</a>
                                    
                                    <?php if ($plugin['is_active']): ?>
                                        <form action="/admin/plugins/deactivate/<?= $plugin['slug'] ?>" method="POST" style="display: inline;">
                                            <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                            <button type="submit" class="btn btn-secondary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem; color: var(--danger);"><i data-lucide="power"></i> Disable</button>
                                        </form>
                                    <?php else: ?>
                                        <form action="/admin/plugins/activate/<?= $plugin['slug'] ?>" method="POST" style="display: inline;">
                                            <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                            <button type="submit" class="btn btn-primary" style="padding: 0.4rem 0.75rem; font-size: 0.8rem;"><i data-lucide="zap"></i> Enable</button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

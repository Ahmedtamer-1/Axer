<?php
/** @var array $settings */
/** @var string|null $error */
/** @var string|null $success */
?>
<div class="header-bar">
    <div class="header-title">
        <h1>Store Settings</h1>
        <p>Configure core shop identity, currency, and store configuration.</p>
    </div>
</div>

<?php if ($success): ?>
    <div class="alert alert-success" style="padding: 1rem; border-radius: 0.5rem; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: #10b981; margin-bottom: 1.5rem;">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger" style="padding: 1rem; border-radius: 0.5rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; margin-bottom: 1.5rem;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 800px;">
    <form method="POST" action="/admin/settings" style="display: flex; flex-direction: column; gap: 1.5rem;">
        <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        
        <h3 style="font-size: 1.1rem; color: var(--primary); display: flex; align-items: center; gap: 0.5rem;">
            <i data-lucide="store"></i> Store Information
        </h3>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">Store Name</label>
                <input type="text" class="form-control" name="settings[general][store_name]" value="<?= htmlspecialchars($settings['general']['store_name'] ?? 'Axer Store') ?>" required>
            </div>
            
            <div class="form-group">
                <label class="form-label">Store Email</label>
                <input type="email" class="form-control" name="settings[general][store_email]" value="<?= htmlspecialchars($settings['general']['store_email'] ?? 'admin@axer.com') ?>" required>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group">
                <label class="form-label">Default Currency</label>
                <select name="settings[general][currency]" class="form-control">
                    <option value="EGP" <?= ($settings['general']['currency'] ?? 'EGP') === 'EGP' ? 'selected' : '' ?>>EGP (Egyptian Pound)</option>
                    <option value="USD" <?= ($settings['general']['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD (US Dollar)</option>
                    <option value="EUR" <?= ($settings['general']['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR (Euro)</option>
                    <option value="SAR" <?= ($settings['general']['currency'] ?? '') === 'SAR' ? 'selected' : '' ?>>SAR (Saudi Riyal)</option>
                    <option value="AED" <?= ($settings['general']['currency'] ?? '') === 'AED' ? 'selected' : '' ?>>AED (UAE Dirham)</option>
                </select>
            </div>
            
            <div class="form-group">
                <label class="form-label">Currency Symbol</label>
                <input type="text" class="form-control" name="settings[general][currency_symbol]" value="<?= htmlspecialchars($settings['general']['currency_symbol'] ?? 'EGP ') ?>" required>
            </div>
        </div>

        <div class="card" style="background: rgba(99,102,241,0.05); border: 1px solid rgba(99,102,241,0.15); margin-top: 1rem;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h4 style="font-size: 0.95rem; font-weight: 600; color: var(--primary); margin-bottom: 0.25rem;"><i data-lucide="plug" style="vertical-align: middle;"></i> Payment & Integration Extensions</h4>
                    <p style="font-size: 0.85rem; color: var(--text-muted); margin: 0;">Integrations (Paymob, Pixels, Social OAuth) are managed as plug-and-play extensions.</p>
                </div>
                <a href="/admin/plugins" class="btn btn-secondary" style="font-size: 0.8rem;"><i data-lucide="arrow-right"></i> Manage Plugins</a>
            </div>
        </div>

        <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                <i data-lucide="save"></i> Save Settings
            </button>
        </div>
    </form>
</div>

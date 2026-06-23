<?php
/** @var array $settings */
/** @var string|null $error */
/** @var string|null $success */
?>
<div class="header-bar">
    <div class="header-title">
        <h1>Settings</h1>
        <p>Configure your shop identity, payments, and tracking pixels.</p>
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

<style>
    .tabs {
        display: flex;
        gap: 1rem;
        border-bottom: 1px solid var(--border-color);
        margin-bottom: 1.5rem;
    }
    .tab {
        padding: 0.75rem 1rem;
        cursor: pointer;
        font-weight: 500;
        color: var(--text-muted);
        border-bottom: 2px solid transparent;
    }
    .tab.active {
        color: var(--primary);
        border-bottom-color: var(--primary);
    }
    .tab-content {
        display: none;
    }
    .tab-content.active {
        display: block;
    }
</style>

<div class="card" style="max-width: 800px;">
    <div class="tabs">
        <div class="tab active" onclick="switchTab('general')">General</div>
        <div class="tab" onclick="switchTab('payments')">Payments</div>
        <div class="tab" onclick="switchTab('pixels')">Tracking Pixels</div>
    </div>

    <form method="POST" action="/admin/settings" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        
        <!-- General Settings Tab -->
        <div id="general" class="tab-content active">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Store Name</label>
                    <input type="text" name="settings[general][store_name]" value="<?= htmlspecialchars($settings['general']['store_name'] ?? '') ?>" required style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
                </div>
                
                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Store Email</label>
                    <input type="email" name="settings[general][store_email]" value="<?= htmlspecialchars($settings['general']['store_email'] ?? '') ?>" required style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Currency</label>
                    <select name="settings[general][currency]" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem; background: white;">
                        <option value="USD" <?= ($settings['general']['currency'] ?? '') === 'USD' ? 'selected' : '' ?>>USD (US Dollar)</option>
                        <option value="EGP" <?= ($settings['general']['currency'] ?? '') === 'EGP' ? 'selected' : '' ?>>EGP (Egyptian Pound)</option>
                        <option value="EUR" <?= ($settings['general']['currency'] ?? '') === 'EUR' ? 'selected' : '' ?>>EUR (Euro)</option>
                        <option value="GBP" <?= ($settings['general']['currency'] ?? '') === 'GBP' ? 'selected' : '' ?>>GBP (British Pound)</option>
                    </select>
                </div>
                
                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Currency Symbol</label>
                    <input type="text" name="settings[general][currency_symbol]" value="<?= htmlspecialchars($settings['general']['currency_symbol'] ?? '') ?>" required style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Font Family</label>
                    <select name="settings[general][font_family]" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem; background: white;">
                        <option value="Outfit" <?= ($settings['general']['font_family'] ?? '') === 'Outfit' ? 'selected' : '' ?>>Outfit</option>
                        <option value="Inter" <?= ($settings['general']['font_family'] ?? '') === 'Inter' ? 'selected' : '' ?>>Inter</option>
                        <option value="Roboto" <?= ($settings['general']['font_family'] ?? '') === 'Roboto' ? 'selected' : '' ?>>Roboto</option>
                        <option value="Georgia" <?= ($settings['general']['font_family'] ?? '') === 'Georgia' ? 'selected' : '' ?>>Georgia</option>
                    </select>
                </div>
                
                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Primary Branding Color</label>
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <input type="color" name="settings[general][primary_color]" value="<?= htmlspecialchars($settings['general']['primary_color'] ?? '#6366f1') ?>" style="border: 1px solid var(--border-color); padding: 0; border-radius: 0.25rem; width: 45px; height: 40px; cursor: pointer;">
                        <input type="text" id="primary_color_text" value="<?= htmlspecialchars($settings['general']['primary_color'] ?? '#6366f1') ?>" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem; flex-grow: 1;" oninput="document.querySelector('input[type=color]').value = this.value">
                    </div>
                </div>
            </div>
        </div>

        <!-- Payments Tab -->
        <div id="payments" class="tab-content">
            <h3 style="margin-bottom: 1rem; color: var(--text-main);">Paymob Integration</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">Configure your Paymob credentials to accept online card payments.</p>
            
            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1.5rem;">
                <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">API Key</label>
                <input type="password" name="settings[payments][paymob_api_key]" value="<?= htmlspecialchars($settings['payments']['paymob_api_key'] ?? '') ?>" placeholder="Enter Paymob API Key" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Integration ID</label>
                    <input type="text" name="settings[payments][paymob_integration_id]" value="<?= htmlspecialchars($settings['payments']['paymob_integration_id'] ?? '') ?>" placeholder="e.g. 123456" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
                </div>
                
                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Iframe ID</label>
                    <input type="text" name="settings[payments][paymob_iframe_id]" value="<?= htmlspecialchars($settings['payments']['paymob_iframe_id'] ?? '') ?>" placeholder="e.g. 123456" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
                </div>
            </div>
            
            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1.5rem;">
                <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">HMAC Secret</label>
                <input type="password" name="settings[payments][paymob_hmac]" value="<?= htmlspecialchars($settings['payments']['paymob_hmac'] ?? '') ?>" placeholder="Enter HMAC secret for webhook verification" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
            </div>
        </div>

        <!-- Pixels Tab -->
        <div id="pixels" class="tab-content">
            <h3 style="margin-bottom: 1rem; color: var(--text-main);">Ad Pixels & Tracking</h3>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">Set up your tracking pixels for Facebook, TikTok, and Google.</p>
            
            <div style="margin-bottom: 2rem;">
                <h4 style="margin-bottom: 0.5rem;">Facebook / Meta</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">FB Pixel ID</label>
                        <input type="text" name="settings[pixels][fb_pixel_id]" value="<?= htmlspecialchars($settings['pixels']['fb_pixel_id'] ?? '') ?>" placeholder="Enter FB Pixel ID" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
                    </div>
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">CAPI Access Token</label>
                        <input type="password" name="settings[pixels][fb_access_token]" value="<?= htmlspecialchars($settings['pixels']['fb_access_token'] ?? '') ?>" placeholder="Optional Conversions API Token" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
                    </div>
                </div>
            </div>

            <div>
                <h4 style="margin-bottom: 0.5rem;">TikTok</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">TikTok Pixel ID</label>
                        <input type="text" name="settings[pixels][tiktok_pixel_id]" value="<?= htmlspecialchars($settings['pixels']['tiktok_pixel_id'] ?? '') ?>" placeholder="Enter TikTok Pixel ID" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
                    </div>
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Events API Access Token</label>
                        <input type="password" name="settings[pixels][tiktok_access_token]" value="<?= htmlspecialchars($settings['pixels']['tiktok_access_token'] ?? '') ?>" placeholder="Optional Events API Token" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                <i data-lucide="save"></i> Save Settings
            </button>
        </div>
    </form>
</div>

<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab').forEach(el => el.classList.remove('active'));
        
        document.getElementById(tabId).classList.add('active');
        event.target.classList.add('active');
    }

    document.querySelector('input[type=color]').addEventListener('input', function(e) {
        document.getElementById('primary_color_text').value = e.target.value;
    });
</script>

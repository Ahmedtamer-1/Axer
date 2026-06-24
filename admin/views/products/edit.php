<?php
/** @var array $product */
/** @var array|null $primaryImage */
/** @var array $images */
/** @var array $variants */
/** @var string|null $error */
?>
<div class="header-bar">
    <div class="header-title">
        <h1>Edit Product: <?= htmlspecialchars($product['name']) ?></h1>
        <p>Manage product details, variants, and media.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/products" class="btn btn-secondary">Back to Products</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" style="padding: 1rem; border-radius: 0.5rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; margin-bottom: 1.5rem;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; align-items: start;">
    
    <!-- Left Column: Details & Variants -->
    <div style="display: flex; flex-direction: column; gap: 2rem;">
        
        <!-- Basic Details Form -->
        <div class="card">
            <form id="product-form" method="POST" action="/admin/products/edit/<?= $product['id'] ?>" style="display: flex; flex-direction: column; gap: 1.5rem;">
                <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="update">
                
                <h3 style="margin-top: 0; margin-bottom: 0.5rem;">Basic Details</h3>
                
                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Product Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($product['name']) ?>" required style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; background: var(--bg-main); color: var(--text-main);">
                </div>

                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Slug (URL Path)</label>
                    <input type="text" name="slug" value="<?= htmlspecialchars($product['slug']) ?>" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; background: var(--bg-main); color: var(--text-main);">
                </div>

                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Description</label>
                    <textarea name="description" rows="4" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; resize: vertical; background: var(--bg-main); color: var(--text-main);"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1rem;">
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Price</label>
                        <input type="number" name="price" step="0.01" value="<?= htmlspecialchars($product['price']) ?>" required style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; background: var(--bg-main); color: var(--text-main);">
                    </div>
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Base Stock</label>
                        <input type="number" name="stock" value="<?= htmlspecialchars($product['stock']) ?>" required style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; background: var(--bg-main); color: var(--text-main);">
                    </div>
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Status</label>
                        <select name="status" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; background: var(--bg-main); color: var(--text-main);">
                            <option value="draft" <?= $product['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                            <option value="active" <?= $product['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="archived" <?= $product['status'] === 'archived' ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>
                </div>

                <div style="display: flex; justify-content: flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">Save Changes</button>
                </div>
            </form>
        </div>

        <!-- Variants Management -->
        <input type="hidden" name="variants_payload" id="variants_payload" value="" form="product-form">
        
        <!-- Layer 1: Options -->
        <div class="card">
            <h3 style="margin-top: 0; margin-bottom: 1rem;">Variants</h3>
            <div id="vm-options-card" style="border: 1px solid var(--border-color); border-radius: 0.5rem; overflow: hidden;">
                <!-- Filled by JS -->
            </div>
        </div>

        <!-- Layer 2: Variants Table -->
        <div class="card" id="vm-variants-card" style="display: none;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="margin: 0;">Variant combinations</h3>
                <span id="vm-variants-count" style="background: var(--bg-content); padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.875rem; font-weight: 500;"></span>
            </div>
            
            <div id="vm-bulk-toolbar" style="display: none; padding: 0.5rem; background: var(--bg-content); border: 1px solid var(--border-color); border-bottom: none; border-radius: 0.5rem 0.5rem 0 0; gap: 0.5rem;">
                <button type="button" class="btn btn-secondary" style="font-size: 0.75rem;" onclick="window.vmBulkEditPrices()">Edit prices</button>
                <button type="button" class="btn btn-secondary" style="font-size: 0.75rem;" onclick="window.vmBulkUpdateStock()">Update stock</button>
                <button type="button" class="btn" style="color: var(--danger); font-size: 0.75rem;" onclick="window.vmBulkDelete()">Delete selected</button>
            </div>
            
            <div style="border: 1px solid var(--border-color); border-radius: 0.5rem; overflow: hidden; overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.875rem;">
                    <thead>
                        <tr style="background: var(--bg-content); border-bottom: 1px solid var(--border-color);">
                            <th style="padding: 0.75rem 1rem; width: 40px;"><input type="checkbox" id="vm-select-all"></th>
                            <th style="padding: 0.75rem 1rem; font-weight: 500; color: var(--text-muted);">Variant</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 500; color: var(--text-muted);">Price</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 500; color: var(--text-muted);">Stock</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 500; color: var(--text-muted);">SKU</th>
                            <th style="padding: 0.75rem 1rem;"></th>
                        </tr>
                    </thead>
                    <tbody id="vm-variants-tbody">
                        <!-- Filled by JS -->
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- Right Column: Media Gallery -->
    <div class="card">
        <h3 style="margin-top: 0; margin-bottom: 1rem;">Media Gallery</h3>
        
        <!-- Upload Form -->
        <form method="POST" action="/admin/products/edit/<?= $product['id'] ?>" style="margin-bottom: 2rem; padding-bottom: 2rem; border-bottom: 1px solid var(--border-color);">
            <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="hidden" name="action" value="assign_image">
            
            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                <label style="font-size: 0.875rem;">Select Image</label>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <input type="hidden" id="image_url" name="image_url" value="" required>
                    <div id="image_preview" style="width: 80px; height: 80px; border-radius: 0.5rem; background: var(--bg-sidebar); border: 1px dashed var(--border-color); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <i data-lucide="image" style="color: var(--text-muted);"></i>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="openMediaPicker((url) => {
                        document.getElementById('image_url').value = url;
                        document.getElementById('image_preview').innerHTML = '<img src=&quot;' + url + '&quot; style=&quot;width:100%; height:100%; object-fit:cover;&quot;>';
                    })">Select from Media Library</button>
                </div>
            </div>

            <?php if (count($variants) > 0): ?>
            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 1rem;">
                <label style="font-size: 0.875rem;">Assign to Variant (Optional)</label>
                <select name="variant_id" style="padding: 0.5rem; border-radius: 0.25rem; border: 1px solid var(--border-color); background: var(--bg-main); color: white;">
                    <option value="">Global Image (All Variants)</option>
                    <?php foreach ($variants as $v): ?>
                        <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['color_name'] ?: $v['size']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <button type="submit" class="btn btn-primary" style="width: 100%;"><i data-lucide="upload"></i> Upload</button>
        </form>

        <!-- Image Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 1rem;">
            <?php foreach ($images as $img): ?>
                <?php 
                    $variantName = 'Global';
                    if ($img['variant_id']) {
                        foreach ($variants as $v) {
                            if ($v['id'] == $img['variant_id']) $variantName = $v['color_name'] ?: $v['size'];
                        }
                    }
                ?>
                <div style="position: relative; aspect-ratio: 1; border-radius: 0.5rem; overflow: hidden; border: 2px solid <?= $img['is_primary'] ? '#6366f1' : 'var(--border-color)' ?>; group;">
                    <img src="<?= htmlspecialchars($img['url']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <div style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0,0,0,0.7); font-size: 0.65rem; padding: 0.25rem; text-align: center;">
                        <?= htmlspecialchars($variantName) ?>
                    </div>
                    <form method="POST" action="/admin/products/edit/<?= $product['id'] ?>" style="position: absolute; top: 0.25rem; right: 0.25rem; margin: 0;">
                        <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="action" value="delete_image">
                        <input type="hidden" name="image_id" value="<?= $img['id'] ?>">
                        <button type="submit" style="background: #ef4444; color: white; border: none; border-radius: 0.25rem; padding: 0.25rem; cursor: pointer;">
                            <i data-lucide="x" style="width: 12px; height: 12px;"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Slide-in Drawer -->
<div id="vm-drawer-overlay" style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 40; display: none; opacity: 0; transition: opacity 0.3s;"></div>
<div id="vm-drawer" style="position: fixed; top: 0; right: -400px; bottom: 0; width: 400px; max-width: 100%; background: var(--bg-main); border-left: 1px solid var(--border-color); z-index: 50; transition: right 0.3s; display: flex; flex-direction: column;">
    <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center;">
        <h3 style="margin: 0;" id="vm-drawer-title">Variant</h3>
        <button type="button" id="vm-drawer-close" style="background: none; border: none; color: var(--text-muted); cursor: pointer;"><i data-lucide="x"></i></button>
    </div>
    <div style="padding: 1.5rem; flex: 1; overflow-y: auto;">
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="font-size: 0.875rem;">Price Override</label>
            <input type="number" step="0.01" id="vm-drawer-price" class="vm-input" style="width: 100%;" placeholder="Leave blank to use base price">
        </div>
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="font-size: 0.875rem;">Compare-at Price</label>
            <input type="number" step="0.01" id="vm-drawer-compare-price" class="vm-input" style="width: 100%;" placeholder="0.00">
        </div>
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="font-size: 0.875rem;">Stock Quantity</label>
            <input type="number" id="vm-drawer-stock" class="vm-input" style="width: 100%;">
        </div>
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="font-size: 0.875rem;">SKU</label>
            <input type="text" id="vm-drawer-sku" class="vm-input" style="width: 100%;">
        </div>
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="font-size: 0.875rem;">Barcode</label>
            <input type="text" id="vm-drawer-barcode" class="vm-input" style="width: 100%;">
        </div>
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="font-size: 0.875rem;">Weight</label>
            <input type="number" step="0.01" id="vm-drawer-weight" class="vm-input" style="width: 100%;">
        </div>
        <div class="form-group" style="margin-bottom: 1rem;">
            <label style="font-size: 0.875rem;">Variant Image</label>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <input type="hidden" id="vm-drawer-image" value="">
                <div id="vm-drawer-image-preview" style="width: 60px; height: 60px; border-radius: 0.25rem; background: var(--bg-content); border: 1px dashed var(--border-color); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                </div>
                <button type="button" class="btn btn-secondary" onclick="openMediaPicker((url) => {
                    document.getElementById('vm-drawer-image').value = url;
                    document.getElementById('vm-drawer-image-preview').innerHTML = '<img src=&quot;' + url + '&quot; style=&quot;width:100%; height:100%; object-fit:cover;&quot;>';
                })">Choose Image</button>
            </div>
        </div>
    </div>
    <div style="padding: 1.5rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end;">
        <button type="button" class="btn btn-primary" onclick="window.vmSaveDrawer()">Done</button>
    </div>
</div>

<style>
.vm-input {
    padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; background: var(--bg-main); color: var(--text-main);
}
#vm-drawer.active { right: 0; }
#vm-drawer-overlay.active { display: block; opacity: 1; }
</style>

<script>
    window.INITIAL_OPTIONS = <?= $product['options_schema'] ? $product['options_schema'] : '[]' ?>;
    window.INITIAL_VARIANTS = <?= json_encode($variants) ?>;
</script>
<script src="/admin/assets/js/media-picker.js"></script>
<script src="/admin/assets/js/product-variants.js"></script>

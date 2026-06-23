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
            <form method="POST" action="/admin/products/edit/<?= $product['id'] ?>" style="display: flex; flex-direction: column; gap: 1.5rem;">
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
        <div class="card">
            <h3 style="margin-top: 0; margin-bottom: 1rem;">Product Variants</h3>
            
            <?php if (count($variants) > 0): ?>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 2rem;">
                <?php foreach ($variants as $variant): ?>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid var(--border-color); border-radius: 0.5rem; background: var(--bg-main);">
                    <div style="display: flex; align-items: center; gap: 1rem;">
                        <?php if ($variant['color_hex']): ?>
                        <div style="width: 24px; height: 24px; border-radius: 50%; background-color: <?= htmlspecialchars($variant['color_hex']) ?>; border: 2px solid rgba(255,255,255,0.2);"></div>
                        <?php endif; ?>
                        <div>
                            <strong><?= htmlspecialchars($variant['color_name'] ?: $variant['size']) ?></strong>
                            <?php if ($variant['price_override']): ?>
                                <span style="color: #10b981; margin-left: 0.5rem;">+$<?= number_format($variant['price_override'], 2) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <form method="POST" action="/admin/products/edit/<?= $product['id'] ?>" style="margin: 0;">
                        <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <input type="hidden" name="action" value="delete_variant">
                        <input type="hidden" name="variant_id" value="<?= $variant['id'] ?>">
                        <button type="submit" class="btn" style="background: transparent; color: #ef4444; padding: 0.5rem;" onsubmit="return confirm('Delete this variant?');">
                            <i data-lucide="trash-2" style="width: 18px; height: 18px;"></i>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <p style="color: #94a3b8; font-size: 0.875rem; margin-bottom: 1.5rem;">No variants added yet. Add colors or sizes below.</p>
            <?php endif; ?>

            <!-- Add Variant Form -->
            <form method="POST" action="/admin/products/edit/<?= $product['id'] ?>" style="background: rgba(0,0,0,0.1); padding: 1.5rem; border-radius: 0.5rem;">
                <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                <input type="hidden" name="action" value="add_variant">
                <h4 style="margin-top: 0; margin-bottom: 1rem;">Add New Variant</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-size: 0.75rem; color: #94a3b8;">Color Name (e.g. Midnight Blue)</label>
                        <input type="text" name="color_name" style="padding: 0.5rem; border-radius: 0.25rem; border: 1px solid var(--border-color); background: var(--bg-main); color: white;">
                    </div>
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-size: 0.75rem; color: #94a3b8;">Color Hex (e.g. #1e3a8a)</label>
                        <input type="color" name="color_hex" style="padding: 0; height: 34px; width: 100%; border-radius: 0.25rem; border: 1px solid var(--border-color); background: var(--bg-main);">
                    </div>
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-size: 0.75rem; color: #94a3b8;">Size (e.g. XL)</label>
                        <input type="text" name="size" style="padding: 0.5rem; border-radius: 0.25rem; border: 1px solid var(--border-color); background: var(--bg-main); color: white;">
                    </div>
                    <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <label style="font-size: 0.75rem; color: #94a3b8;">Price Override (Optional)</label>
                        <input type="number" step="0.01" name="price_override" style="padding: 0.5rem; border-radius: 0.25rem; border: 1px solid var(--border-color); background: var(--bg-main); color: white;">
                    </div>
                </div>
                <button type="submit" class="btn btn-secondary" style="width: 100%;">Add Variant</button>
            </form>
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

<script src="/admin/assets/js/media-picker.js"></script>

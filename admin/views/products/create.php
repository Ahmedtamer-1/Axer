<?php
/** @var string|null $error */
?>
<div class="header-bar">
    <div class="header-title">
        <h1>Create Product</h1>
        <p>Add a new product to your storefront catalog.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/products" class="btn btn-secondary">Cancel</a>
    </div>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger" style="padding: 1rem; border-radius: 0.5rem; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; margin-bottom: 1.5rem;">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 800px;">
    <form method="POST" action="/admin/products/create" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 1.5rem;">
    <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
            <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Product Name</label>
            <input type="text" name="name" id="name" required style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
        </div>

        <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
            <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Slug (URL Path)</label>
            <input type="text" name="slug" id="slug" placeholder="leave blank to auto-generate" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
        </div>

        <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
            <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Description</label>
            <textarea name="description" rows="5" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem; resize: vertical;"></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Price</label>
                <input type="number" name="price" step="0.01" value="0.00" required style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Inventory / Stock</label>
                <input type="number" name="stock" value="0" required style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Status</label>
                <select name="status" style="padding: 0.75rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem; background: white;">
                    <option value="draft">Draft</option>
                    <option value="active">Active</option>
                </select>
            </div>

            <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-main);">Primary Image</label>
                <div style="display: flex; gap: 1rem; align-items: center;">
                    <input type="hidden" id="image_url" name="image_url" value="">
                    <div id="image_preview" style="width: 100px; height: 100px; border-radius: 0.5rem; background: var(--bg-sidebar); border: 1px dashed var(--border-color); display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <i data-lucide="image" style="color: var(--text-muted);"></i>
                    </div>
                    <button type="button" class="btn btn-secondary" onclick="openMediaPicker((url) => {
                        document.getElementById('image_url').value = url;
                        document.getElementById('image_preview').innerHTML = '<img src=&quot;' + url + '&quot; style=&quot;width:100%; height:100%; object-fit:cover;&quot;>';
                    })">Select from Media Library</button>
                </div>
            </div>
        </div>

        <div style="margin-top: 1rem; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary" style="padding: 0.75rem 2rem;">
                <i data-lucide="check"></i> Create Product
            </button>
        </div>
    </form>
</div>

<script src="/admin/assets/js/media-picker.js"></script>
<script>
    // Auto-generate slug from title
    document.getElementById('name').addEventListener('input', function(e) {
        var slugInput = document.getElementById('slug');
        if (slugInput.value === '' || slugInput.dataset.auto === 'true') {
            slugInput.value = e.target.value
                .toLowerCase()
                .replace(/[^a-z0-9\s-]/g, '')
                .replace(/\s+/g, '-')
                .replace(/-+/g, '-');
            slugInput.dataset.auto = 'true';
        }
    });

    document.getElementById('slug').addEventListener('input', function(e) {
        e.target.dataset.auto = 'false';
    });
</script>

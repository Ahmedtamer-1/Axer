<div class="header-bar">
    <div class="header-title">
        <h1>Media Library</h1>
        <p>Upload, organize, and manage image assets for your catalog and storefront.</p>
    </div>
</div>

<!-- Upload Area -->
<div class="card" style="border: 2px dashed var(--border-color); text-align: center; padding: 2rem; margin-bottom: 2rem; background: #fafafa;">
    <form action="/admin/media/upload" method="POST" enctype="multipart/form-data" id="uploadForm">
        <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <i data-lucide="cloud-upload" style="width: 48px; height: 48px; color: var(--primary); margin-bottom: 0.75rem;"></i>
        <h3 style="font-size: 1.1rem; font-weight: 600; margin-bottom: 0.25rem;">Drag and drop files here, or click to browse</h3>
        <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1.25rem;">Supports JPG, PNG, WEBP, GIF, SVG, and MP4 up to 10MB each.</p>
        <input type="file" name="files[]" id="fileInput" multiple style="display: none;" onchange="document.getElementById('uploadForm').submit();">
        <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click();"><i data-lucide="upload"></i> Select Files</button>
    </form>
</div>

<!-- Media Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.25rem;">
    <?php if (empty($media)): ?>
        <div class="card" style="grid-column: 1 / -1; text-align: center; padding: 3rem; color: var(--text-muted);">
            No media files uploaded yet. Drag & drop files above to get started.
        </div>
    <?php else: ?>
        <?php foreach ($media as $item): ?>
            <div class="card" style="padding: 0.75rem; display: flex; flex-direction: column; justify-content: space-between; overflow: hidden; position: relative;">
                <div style="width: 100%; height: 140px; border-radius: 0.375rem; background: #f1f5f9; display: flex; align-items: center; justify-content: center; overflow: hidden; margin-bottom: 0.75rem;">
                    <?php if (strpos($item['mime_type'] ?? '', 'image/') === 0): ?>
                        <img src="<?= htmlspecialchars($item['path']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <?php else: ?>
                        <i data-lucide="file" style="width: 36px; height: 36px; color: var(--text-muted);"></i>
                    <?php endif; ?>
                </div>
                
                <div style="font-size: 0.8rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-bottom: 0.25rem;" title="<?= htmlspecialchars($item['original_name'] ?? $item['filename']) ?>">
                    <?= htmlspecialchars($item['original_name'] ?? $item['filename']) ?>
                </div>
                
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: var(--text-muted);">
                    <span><?= round(($item['size'] ?? 0) / 1024, 1) ?> KB</span>
                    <div style="display: flex; gap: 0.25rem;">
                        <button type="button" class="btn btn-secondary" style="padding: 0.25rem 0.4rem;" onclick="copyUrl('<?= htmlspecialchars($item['path']) ?>')" title="Copy Link"><i data-lucide="copy" style="width: 14px; height: 14px;"></i></button>
                        <form action="/admin/media/delete" method="POST" onsubmit="return confirm('Delete this file?');" style="display: inline;">
                            <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                            <input type="hidden" name="id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-secondary" style="padding: 0.25rem 0.4rem; color: var(--danger);" title="Delete"><i data-lucide="trash-2" style="width: 14px; height: 14px;"></i></button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
function copyUrl(url) {
    const fullUrl = window.location.origin + url;
    navigator.clipboard.writeText(fullUrl).then(() => {
        alert('Image URL copied to clipboard!\n' + fullUrl);
    });
}
</script>

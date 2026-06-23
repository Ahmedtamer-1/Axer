<?php
/** @var array $media */
?>
<div class="header-bar">
    <div class="header-title">
        <h1>Media Library</h1>
        <p>Manage all your uploaded images and files. (Total: <?= count($media) ?> files)</p>
    </div>
    <div class="header-actions" style="display: flex; gap: 1rem;">
        <form method="POST" action="/admin/media/upload" enctype="multipart/form-data" style="display: flex; gap: 1rem; margin: 0;" id="upload-form">
            <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <input type="file" name="files[]" id="media-upload" accept="image/*" multiple style="display: none;" onchange="document.getElementById('upload-btn-text').innerText = 'Uploading...'; this.form.submit()">
            <label for="media-upload" class="btn btn-primary" style="cursor: pointer; margin: 0; display: inline-flex; align-items: center; gap: 0.5rem;" id="upload-btn-text">
                <i data-lucide="upload"></i> Upload Files
            </label>
        </form>
    </div>
</div>

<div class="card">
    <?php if (empty($media)): ?>
        <div style="text-align: center; padding: 4rem 2rem; color: #94a3b8;">
            <i data-lucide="image" style="width: 48px; height: 48px; margin-bottom: 1rem; opacity: 0.5;"></i>
            <h3 style="margin-top: 0;">No media files</h3>
            <p>Upload images to see them here.</p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1.5rem;">
            <?php foreach ($media as $item): ?>
                <div style="border: 1px solid var(--border-color); border-radius: 0.5rem; overflow: hidden; background: var(--bg-main); display: flex; flex-direction: column;">
                    <!-- Preview -->
                    <div style="aspect-ratio: 1; background: #0f172a; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                        <?php if (strpos($item['mime_type'], 'image/') === 0): ?>
                            <img src="<?= htmlspecialchars($item['path']) ?>" alt="<?= htmlspecialchars($item['alt_text'] ?? $item['original_name']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                        <?php else: ?>
                            <i data-lucide="file" style="width: 48px; height: 48px; color: #64748b;"></i>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Details -->
                    <div style="padding: 1rem; flex-grow: 1; display: flex; flex-direction: column; justify-content: space-between;">
                        <div style="margin-bottom: 1rem;">
                            <div style="font-weight: 500; font-size: 0.875rem; color: var(--text-main); word-break: break-all; margin-bottom: 0.25rem;">
                                <?= htmlspecialchars($item['original_name']) ?>
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b;">
                                <?= number_format($item['size'] / 1024, 1) ?> KB &bull; <?= htmlspecialchars($item['folder']) ?>
                            </div>
                        </div>
                        
                        <!-- Actions -->
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" readonly value="<?= htmlspecialchars($item['path']) ?>" style="flex-grow: 1; font-size: 0.75rem; padding: 0.25rem 0.5rem; border-radius: 0.25rem; border: 1px solid var(--border-color); background: var(--bg-content); color: var(--text-main);" onclick="this.select()">
                            
                            <form method="POST" action="/admin/media/delete" style="margin: 0;" onsubmit="return confirm('Are you sure you want to delete this file? It may break images on your site.');">
                                <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                <button type="submit" class="btn" style="background: transparent; color: #ef4444; padding: 0.25rem; border: 1px solid var(--border-color);">
                                    <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

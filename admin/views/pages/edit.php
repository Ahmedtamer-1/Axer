<div class="header-bar">
    <div class="header-title">
        <h1>Edit Page</h1>
        <p>Modify basic properties and settings of this page.</p>
    </div>
    <div class="header-actions">
        <?php if ($page['status'] !== 'published'): ?>
            <a href="/<?= htmlspecialchars($page['slug'], ENT_QUOTES, 'UTF-8') ?>?preview=1" target="_blank" rel="noopener" class="btn btn-secondary"><i data-lucide="eye"></i> Preview Draft</a>
        <?php endif; ?>
        <a href="/admin/pages/builder/<?= $page['id'] ?>" class="btn btn-primary"><i data-lucide="layout"></i> Open Visual Builder</a>
        <a href="/admin/pages" class="btn btn-secondary"><i data-lucide="arrow-left"></i> Back to Pages</a>
    </div>
</div>

<style>
    .form-group {
        margin-bottom: 1.5rem;
    }
    .form-group label {
        display: block;
        font-weight: 500;
        margin-bottom: 0.5rem;
        font-size: 0.875rem;
    }
    .form-control {
        width: 100%;
        padding: 0.75rem 1rem;
        border-radius: 0.5rem;
        border: 1px solid var(--border-color);
        font-family: var(--font-main);
        font-size: 0.875rem;
        transition: all 0.2s ease;
        box-sizing: border-box;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
    }
    textarea.form-control {
        min-height: 200px;
        resize: vertical;
    }
    .field-hint { margin: 0.375rem 0 0; font-size: 0.78rem; color: var(--text-muted); }
    .seo-panel summary { cursor: pointer; font-weight: 600; font-size: 0.9rem; margin-bottom: 1rem; }
</style>

<?php if (isset($error)): ?>
    <div class="card" style="background-color: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: var(--danger);">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="card">
    <form action="/admin/pages/edit/<?= $page['id'] ?>" method="POST">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Axer\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
            <label for="title">Page Title</label>
            <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label for="slug">URL Slug</label>
            <input type="text" id="slug" name="slug" class="form-control" value="<?= htmlspecialchars($page['slug'], ENT_QUOTES, 'UTF-8') ?>" required>
        </div>

        <div class="form-group">
            <label for="content">HTML Content</label>
            <textarea id="content" name="content" class="form-control" placeholder="Enter page HTML content..."><?= htmlspecialchars($page['content'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="template">Template</label>
                <select id="template" name="template" class="form-control">
                    <option value="page" <?= $page['template'] === 'page' ? 'selected' : '' ?>>Standard Page</option>
                    <option value="home" <?= $page['template'] === 'home' ? 'selected' : '' ?>>Homepage</option>
                    <option value="contact" <?= $page['template'] === 'contact' ? 'selected' : '' ?>>Contact Page</option>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="draft" <?= $page['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="published" <?= $page['status'] === 'published' ? 'selected' : '' ?>>Published</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 500;">
                    <input type="checkbox" name="show_in_nav" value="1" <?= !empty($page['show_in_nav']) ? 'checked' : '' ?> style="width: auto;">
                    Show in storefront navigation
                </label>
                <p class="field-hint">Adds this page to the header and footer menus.</p>
            </div>

            <div class="form-group">
                <label for="sort_order">Nav Sort Order</label>
                <input type="number" id="sort_order" name="sort_order" class="form-control" value="<?= (int) ($page['sort_order'] ?? 0) ?>">
                <p class="field-hint">Lower numbers appear first.</p>
            </div>
        </div>

        <details class="card seo-panel" style="background: var(--bg-subtle, #f8fafc); margin: 0 0 1.5rem;" open>
            <summary>Search Engine (SEO)</summary>

            <div class="form-group">
                <label for="seo_title">SEO Title <span id="seo-title-count" class="field-hint"></span></label>
                <input type="text" id="seo_title" name="seo_title" class="form-control" maxlength="255"
                       value="<?= htmlspecialchars($page['seo_title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="<?= htmlspecialchars($page['title'], ENT_QUOTES, 'UTF-8') ?>">
                <p class="field-hint">Falls back to the page title when left empty. Aim for under 60 characters.</p>
            </div>

            <div class="form-group">
                <label for="seo_description">Meta Description <span id="seo-desc-count" class="field-hint"></span></label>
                <textarea id="seo_description" name="seo_description" class="form-control" style="min-height: 80px;" maxlength="500"
                          placeholder="A short summary shown in search results..."><?= htmlspecialchars($page['seo_description'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                <p class="field-hint">Aim for under 160 characters.</p>
            </div>

            <div style="border: 1px solid var(--border-color); border-radius: 0.5rem; padding: 1rem; background: #fff;">
                <div style="font-size: 0.7rem; color: var(--text-muted); margin-bottom: 0.25rem;">yourstore.com/<?= htmlspecialchars($page['slug'], ENT_QUOTES, 'UTF-8') ?></div>
                <div id="seo-preview-title" style="color: #1a0dab; font-size: 1.05rem; line-height: 1.3;"><?= htmlspecialchars(($page['seo_title'] ?? '') ?: $page['title'], ENT_QUOTES, 'UTF-8') ?></div>
                <div id="seo-preview-desc" style="color: #4d5156; font-size: 0.85rem; margin-top: 0.25rem;"><?= htmlspecialchars($page['seo_description'] ?? 'No description provided.', ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </details>

        <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
            <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Changes</button>
        </div>
    </form>
</div>

<script>
(function () {
    var titleInput = document.getElementById('seo_title');
    var descInput = document.getElementById('seo_description');
    var titleCount = document.getElementById('seo-title-count');
    var descCount = document.getElementById('seo-desc-count');
    var previewTitle = document.getElementById('seo-preview-title');
    var previewDesc = document.getElementById('seo-preview-desc');
    var pageTitle = <?= json_encode($page['title'], JSON_HEX_TAG | JSON_HEX_AMP) ?>;

    function updateCounts() {
        if (titleCount) titleCount.textContent = '(' + titleInput.value.length + '/60)';
        if (descCount) descCount.textContent = '(' + descInput.value.length + '/160)';
        if (previewTitle) previewTitle.textContent = titleInput.value || pageTitle;
        if (previewDesc) previewDesc.textContent = descInput.value || 'No description provided.';
    }

    if (titleInput && descInput) {
        titleInput.addEventListener('input', updateCounts);
        descInput.addEventListener('input', updateCounts);
        updateCounts();
    }
})();
</script>

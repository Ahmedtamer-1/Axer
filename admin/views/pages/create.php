<div class="header-bar">
    <div class="header-title">
        <h1>Create Page</h1>
        <p>Add a new static or dynamic page to your site.</p>
    </div>
    <div class="header-actions">
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
</style>

<?php if (isset($error)): ?>
    <div class="card" style="background-color: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: var(--danger);">
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<div class="card">
    <form action="/admin/pages/create" method="POST">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars(\Axer\Core\Csrf::token(), ENT_QUOTES, 'UTF-8') ?>">

        <div class="form-group">
            <label for="title">Page Title</label>
            <input type="text" id="title" name="title" class="form-control" placeholder="e.g. Home, About Us" required>
        </div>

        <div class="form-group">
            <label for="slug">URL Slug (leave empty to auto-generate)</label>
            <input type="text" id="slug" name="slug" class="form-control" placeholder="e.g. about-us">
        </div>

        <div class="form-group">
            <label for="content">HTML Content (if not using Visual Page Builder)</label>
            <textarea id="content" name="content" class="form-control" placeholder="Enter page HTML content..."></textarea>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label for="template">Template</label>
                <select id="template" name="template" class="form-control">
                    <option value="page">Standard Page</option>
                    <option value="home">Homepage</option>
                    <option value="contact">Contact Page</option>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status" class="form-control">
                    <option value="draft">Draft</option>
                    <option value="published">Published</option>
                </select>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
            <div class="form-group">
                <label style="display: flex; align-items: center; gap: 0.5rem; font-weight: 500;">
                    <input type="checkbox" name="show_in_nav" value="1" style="width: auto;">
                    Show in storefront navigation
                </label>
                <p class="field-hint">Adds this page to the header and footer menus.</p>
            </div>

            <div class="form-group">
                <label for="sort_order">Nav Sort Order</label>
                <input type="number" id="sort_order" name="sort_order" class="form-control" value="0">
                <p class="field-hint">Lower numbers appear first.</p>
            </div>
        </div>

        <details class="card" style="background: var(--bg-subtle, #f8fafc); margin: 0 0 1.5rem;">
            <summary style="cursor: pointer; font-weight: 600; font-size: 0.9rem; margin-bottom: 1rem;">Search Engine (SEO)</summary>

            <div class="form-group">
                <label for="seo_title">SEO Title</label>
                <input type="text" id="seo_title" name="seo_title" class="form-control" maxlength="255" placeholder="Falls back to the page title if left empty">
            </div>

            <div class="form-group">
                <label for="seo_description">Meta Description</label>
                <textarea id="seo_description" name="seo_description" class="form-control" style="min-height: 80px;" maxlength="500" placeholder="A short summary shown in search results..."></textarea>
            </div>
        </details>

        <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
            <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Page</button>
        </div>
    </form>
</div>

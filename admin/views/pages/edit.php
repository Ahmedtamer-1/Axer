<div class="header-bar">
    <div class="header-title">
        <h1>Edit Page</h1>
        <p>Modify basic properties and settings of this page.</p>
    </div>
    <div class="header-actions">
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
</style>

<?php if (isset($error)): ?>
    <div class="card" style="background-color: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); color: var(--danger);">
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class="card">
    <form action="/admin/pages/edit/<?= $page['id'] ?>" method="POST">
        <div class="form-group">
            <label for="title">Page Title</label>
            <input type="text" id="title" name="title" class="form-control" value="<?= htmlspecialchars($page['title']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="slug">URL Slug</label>
            <input type="text" id="slug" name="slug" class="form-control" value="<?= htmlspecialchars($page['slug']) ?>" required>
        </div>
        
        <div class="form-group">
            <label for="content">HTML Content</label>
            <textarea id="content" name="content" class="form-control" placeholder="Enter page HTML content..."><?= htmlspecialchars($page['content'] ?? '') ?></textarea>
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
        
        <div style="display: flex; justify-content: flex-end; margin-top: 1rem;">
            <button type="submit" class="btn btn-primary"><i data-lucide="save"></i> Save Changes</button>
        </div>
    </form>
</div>

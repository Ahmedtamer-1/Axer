<div class="header-bar">
    <div class="header-title">
        <h1>Pages</h1>
        <p>Manage the pages on your site.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/pages/create" class="btn btn-primary"><i data-lucide="plus"></i> Add Page</a>
    </div>
</div>

<style>
    .table-card {
        padding: 0;
        overflow: hidden;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        text-align: left;
    }
    th, td {
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--border-color);
    }
    th {
        background-color: var(--bg-content);
        color: var(--text-muted);
        font-weight: 600;
        font-size: 0.875rem;
    }
    tr:last-child td {
        border-bottom: none;
    }
    .badge {
        display: inline-flex;
        align-items: center;
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        font-weight: 600;
        border-radius: 9999px;
    }
    .badge-published {
        background-color: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    .badge-draft {
        background-color: rgba(100, 116, 139, 0.1);
        color: var(--text-muted);
    }
    .actions-cell {
        display: flex;
        gap: 0.5rem;
    }
    .btn-icon {
        padding: 0.5rem;
        border-radius: 0.375rem;
        color: var(--text-muted);
        border: 1px solid var(--border-color);
        background-color: #ffffff;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
    }
    .btn-icon:hover {
        background-color: var(--bg-content);
        color: var(--text-main);
    }
    .btn-icon-danger:hover {
        background-color: rgba(239, 68, 68, 0.1);
        color: var(--danger);
        border-color: rgba(239, 68, 68, 0.2);
    }
</style>

<div class="card table-card">
    <table>
        <thead>
            <tr>
                <th>Title</th>
                <th>Slug</th>
                <th>Status</th>
                <th>Template</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($pages)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; color: var(--text-muted);">No pages found. Click "Add Page" to create one.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($pages as $page): ?>
                    <tr>
                        <td style="font-weight: 500;"><?= htmlspecialchars($page['title']) ?></td>
                        <td><code>/<?= htmlspecialchars($page['slug']) ?></code></td>
                        <td>
                            <span class="badge badge-<?= $page['status'] ?>">
                                <?= ucfirst(htmlspecialchars($page['status'])) ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($page['template']) ?></td>
                        <td>
                            <div class="actions-cell">
                                <a href="/admin/pages/builder/<?= $page['id'] ?>" class="btn-icon" title="Visual Page Builder">
                                    <i data-lucide="layout"></i>
                                </a>
                                <a href="/admin/pages/edit/<?= $page['id'] ?>" class="btn-icon" title="Edit Page Details">
                                    <i data-lucide="edit-2"></i>
                                </a>
                                <form action="/admin/pages/delete/<?= $page['id'] ?>" method="POST" onsubmit="return confirm('Are you sure you want to delete this page?');" style="display:inline;">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button type="submit" class="btn-icon btn-icon-danger" title="Delete Page">
                                        <i data-lucide="trash-2"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

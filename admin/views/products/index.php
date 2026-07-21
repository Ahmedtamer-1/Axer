<div class="header-bar">
    <div class="header-title">
        <h1>Products Spreadsheet</h1>
        <p>Click any cell to edit inline, add items quickly, or use bulk actions.</p>
    </div>
    <div class="header-actions">
        <button id="quickAddBtn" class="btn btn-secondary"><i data-lucide="plus"></i> Quick Add Row</button>
        <a href="/admin/products/create" class="btn btn-primary"><i data-lucide="package-plus"></i> Detailed Product Form</a>
    </div>
</div>

<!-- Spreadsheet Search & Bulk Toolbar -->
<div class="card" style="padding: 1rem; margin-bottom: 1rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-grow: 1; max-width: 450px;">
        <i data-lucide="search" style="color: var(--text-muted);"></i>
        <input type="text" id="searchInput" class="form-control" placeholder="Search by name, SKU, or brand..." style="padding-left: 0.5rem;">
    </div>
    
    <div id="bulkActions" style="display: none; align-items: center; gap: 0.75rem;">
        <span id="selectedCount" style="font-size: 0.875rem; font-weight: 600; color: var(--primary);">0 selected</span>
        <button id="bulkDeleteBtn" class="btn btn-danger" style="padding: 0.4rem 0.875rem; font-size: 0.8rem;"><i data-lucide="trash-2"></i> Delete Selected</button>
    </div>
</div>

<!-- Interactive Grid Table -->
<div class="card" style="padding: 0; overflow: hidden;">
    <div class="table-responsive">
        <table class="table" id="spreadsheetTable">
            <thead>
                <tr>
                    <th style="width: 40px; text-align: center;"><input type="checkbox" id="selectAll"></th>
                    <th style="width: 60px;">Image</th>
                    <th>Product Name</th>
                    <th style="width: 140px;">SKU</th>
                    <th style="width: 120px;">Price (EGP)</th>
                    <th style="width: 120px;">Stock</th>
                    <th style="width: 130px;">Status</th>
                    <th style="width: 140px;">Brand</th>
                    <th style="width: 100px; text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody id="spreadsheetBody">
                <?php if (empty($products)): ?>
                    <tr id="emptyRow">
                        <td colspan="9" style="text-align: center; color: var(--text-muted); padding: 2.5rem;">No products found. Click "Quick Add Row" or use the Detailed Form to create one.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($products as $p): ?>
                        <tr data-id="<?= $p['id'] ?>">
                            <td style="text-align: center;"><input type="checkbox" class="row-checkbox" value="<?= $p['id'] ?>"></td>
                            <td>
                                <div style="width: 40px; height: 40px; border-radius: 0.375rem; background: #e2e8f0; display: flex; align-items: center; justify-content: center; overflow: hidden;">
                                    <?php if (!empty($p['image_url'])): ?>
                                        <img src="<?= htmlspecialchars($p['image_url']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <i data-lucide="image" style="width: 18px; color: var(--text-muted);"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="editable" data-field="name" contenteditable="true" style="font-weight: 600; cursor: pointer; outline: none;"><?= htmlspecialchars($p['name']) ?></td>
                            <td class="editable" data-field="sku" contenteditable="true" style="font-family: monospace; cursor: pointer; outline: none;"><?= htmlspecialchars($p['sku'] ?? '') ?></td>
                            <td class="editable" data-field="price" contenteditable="true" style="cursor: pointer; outline: none; text-align: right;"><?= htmlspecialchars(number_format((float)($p['price'] ?? 0), 2, '.', '')) ?></td>
                            <td class="editable" data-field="stock" contenteditable="true" style="cursor: pointer; outline: none; text-align: right; font-weight: 600; color: <?= ($p['stock'] ?? 0) < 5 ? 'var(--danger)' : 'inherit' ?>"><?= (int)($p['stock'] ?? 0) ?></td>
                            <td>
                                <select class="status-select form-control" style="padding: 0.25rem 0.5rem; font-size: 0.8rem; border-radius: 0.375rem;" data-id="<?= $p['id'] ?>">
                                    <option value="active" <?= ($p['status'] ?? '') === 'active' ? 'selected' : '' ?>>Active</option>
                                    <option value="draft" <?= ($p['status'] ?? '') === 'draft' ? 'selected' : '' ?>>Draft</option>
                                    <option value="archived" <?= ($p['status'] ?? '') === 'archived' ? 'selected' : '' ?>>Archived</option>
                                </select>
                            </td>
                            <td class="editable" data-field="brand" contenteditable="true" style="cursor: pointer; outline: none;"><?= htmlspecialchars($p['brand'] ?? '') ?></td>
                            <td style="text-align: right;">
                                <div style="display: inline-flex; gap: 0.25rem;">
                                    <a href="/admin/products/edit/<?= $p['id'] ?>" class="btn btn-secondary" style="padding: 0.3rem 0.5rem;" title="Full Edit"><i data-lucide="edit-3"></i></a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const table = document.getElementById('spreadsheetTable');
    const body = document.getElementById('spreadsheetBody');
    const searchInput = document.getElementById('searchInput');
    const selectAll = document.getElementById('selectAll');
    const bulkActions = document.getElementById('bulkActions');
    const selectedCount = document.getElementById('selectedCount');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const quickAddBtn = document.getElementById('quickAddBtn');

    // Inline edit handler
    body.addEventListener('focusout', function(e) {
        if (e.target.classList.contains('editable')) {
            saveCell(e.target);
        }
    });

    body.addEventListener('keydown', function(e) {
        if (e.target.classList.contains('editable')) {
            if (e.key === 'Enter') {
                e.preventDefault();
                e.target.blur();
            }
        }
    });

    function saveCell(cell) {
        const row = cell.closest('tr');
        const id = row.getAttribute('data-id');
        const field = cell.getAttribute('data-field');
        const value = cell.innerText.trim();

        if (!id || !field) return;

        cell.style.background = 'rgba(99,102,241,0.1)';

        fetch('/admin/api/products/update/' + id, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '<?= $_SESSION['csrf_token'] ?? '' ?>'
            },
            body: JSON.stringify({ [field]: value, _csrf: '<?= $_SESSION['csrf_token'] ?? '' ?>' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                cell.style.background = 'rgba(16,185,129,0.15)';
                setTimeout(() => cell.style.background = 'transparent', 1000);
            } else {
                cell.style.background = 'rgba(239,68,68,0.15)';
            }
        })
        .catch(() => {
            cell.style.background = 'rgba(239,68,68,0.15)';
        });
    }

    // Status dropdown change
    body.addEventListener('change', function(e) {
        if (e.target.classList.contains('status-select')) {
            const id = e.target.getAttribute('data-id');
            const val = e.target.value;
            fetch('/admin/api/products/update/' + id, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ status: val, _csrf: '<?= $_SESSION['csrf_token'] ?? '' ?>' })
            });
        }
    });

    // Quick Add Row
    quickAddBtn.addEventListener('click', function() {
        fetch('/admin/api/products/quick-create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name: 'New Product', _csrf: '<?= $_SESSION['csrf_token'] ?? '' ?>' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    });

    // Search filter
    searchInput.addEventListener('input', function() {
        const q = this.value.toLowerCase();
        const rows = body.querySelectorAll('tr');
        rows.forEach(r => {
            const text = r.innerText.toLowerCase();
            r.style.display = text.includes(q) ? '' : 'none';
        });
    });

    // Bulk Checkbox Handling
    function updateBulkUI() {
        const checked = body.querySelectorAll('.row-checkbox:checked');
        if (checked.length > 0) {
            bulkActions.style.display = 'flex';
            selectedCount.innerText = checked.length + ' selected';
        } else {
            bulkActions.style.display = 'none';
        }
    }

    selectAll.addEventListener('change', function() {
        body.querySelectorAll('.row-checkbox').forEach(cb => cb.checked = this.checked);
        updateBulkUI();
    });

    body.addEventListener('change', function(e) {
        if (e.target.classList.contains('row-checkbox')) {
            updateBulkUI();
        }
    });

    bulkDeleteBtn.addEventListener('click', function() {
        const checked = Array.from(body.querySelectorAll('.row-checkbox:checked')).map(cb => cb.value);
        if (!checked.length || !confirm('Are you sure you want to delete these products?')) return;

        fetch('/admin/api/products/bulk-delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ ids: checked, _csrf: '<?= $_SESSION['csrf_token'] ?? '' ?>' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                location.reload();
            }
        });
    });
});
</script>

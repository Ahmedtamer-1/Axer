<?php
/** @var array $products */
?>
<div class="header-bar">
    <div class="header-title">
        <h1>Products</h1>
        <p>Manage your product catalog, prices, and stock levels.</p>
    </div>
    <div class="header-actions">
        <a href="/admin/products/create" class="btn btn-primary">
            <i data-lucide="plus"></i> Add Product
        </a>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
            <tr style="background-color: var(--bg-content); border-bottom: 1px solid var(--border-color);">
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Product</th>
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Status</th>
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Price</th>
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Stock</th>
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted); text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
                <tr>
                    <td colspan="5" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                        <i data-lucide="shopping-bag" style="width: 48px; height: 48px; stroke-width: 1; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No products found. Get started by adding a product!</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                    <tr style="border-bottom: 1px solid var(--border-color); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(99,102,241,0.02)'" onmouseout="this.style.backgroundColor='transparent'">
                        <td style="padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem;">
                            <div style="width: 48px; height: 48px; border-radius: 0.375rem; background: var(--bg-content); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border-color);">
                                <?php if (!empty($product['primary_image'])): ?>
                                    <img src="<?= htmlspecialchars($product['primary_image']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                <?php else: ?>
                                    <i data-lucide="image" style="color: var(--text-muted); width: 20px; height: 20px;"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($product['name']) ?></div>
                                <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($product['slug']) ?></div>
                            </div>
                        </td>
                        <td style="padding: 1rem 1.5rem;">
                            <?php if ($product['status'] === 'active'): ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: rgba(16, 185, 129, 0.1); color: var(--success);">Active</span>
                            <?php else: ?>
                                <span style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: rgba(100, 116, 139, 0.1); color: var(--text-muted);">Draft</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem 1.5rem; font-weight: 500;">
                            <?= number_format($product['price'], 2) ?>
                        </td>
                        <td style="padding: 1rem 1.5rem;">
                            <span style="color: <?= $product['stock'] > 0 ? 'var(--text-main)' : 'var(--danger)' ?>; font-weight: 500;">
                                <?= $product['stock'] ?> in stock
                            </span>
                        </td>
                        <td style="padding: 1rem 1.5rem; text-align: right;">
                            <div style="display: inline-flex; gap: 0.5rem;">
                                <a href="/admin/products/edit/<?= $product['id'] ?>" class="btn btn-secondary" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                                    <i data-lucide="edit-2" style="width: 14px; height: 14px;"></i> Edit
                                </a>
                                <form method="POST" action="/admin/products/delete/<?= $product['id'] ?>" onsubmit="return confirm('Are you sure you want to delete this product?');" style="display: inline;">
                                    <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                                    <button type="submit" class="btn" style="padding: 0.375rem 0.75rem; font-size: 0.75rem; background-color: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.2); color: var(--danger);">
                                        <i data-lucide="trash-2" style="width: 14px; height: 14px;"></i> Delete
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

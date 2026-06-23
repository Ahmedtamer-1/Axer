<?php
/** @var array $order */
/** @var array $items */

$shipping = json_decode($order['shipping_address'] ?? '[]', true) ?: [];
?>
<div class="header-bar">
    <div class="header-title">
        <a href="/admin/orders" style="display: inline-flex; align-items: center; gap: 0.25rem; color: var(--text-muted); text-decoration: none; margin-bottom: 0.5rem; font-size: 0.875rem;">
            <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i> Back to Orders
        </a>
        <h1>Order Details #<?= htmlspecialchars($order['order_number']) ?></h1>
        <p>Placed on <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></p>
    </div>
</div>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <!-- Left Column: Items & Shipping -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <!-- Items Card -->
        <div class="card" style="padding: 0; overflow: hidden;">
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-color); font-weight: 600;">Items in Order</div>
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="background-color: var(--bg-content); border-bottom: 1px solid var(--border-color);">
                        <th style="padding: 0.75rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Product</th>
                        <th style="padding: 0.75rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Price</th>
                        <th style="padding: 0.75rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted); text-align: center;">Qty</th>
                        <th style="padding: 0.75rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted); text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($items)): ?>
                        <tr>
                            <td colspan="4" style="padding: 2rem; text-align: center; color: var(--text-muted);">No items recorded for this order.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($items as $item): ?>
                            <tr style="border-bottom: 1px solid var(--border-color);">
                                <td style="padding: 1rem 1.5rem; display: flex; align-items: center; gap: 1rem;">
                                    <div style="width: 40px; height: 40px; border-radius: 0.25rem; background: var(--bg-content); display: flex; align-items: center; justify-content: center; overflow: hidden; border: 1px solid var(--border-color);">
                                        <?php if (!empty($item['product_image'])): ?>
                                            <img src="<?= htmlspecialchars($item['product_image']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                                        <?php else: ?>
                                            <i data-lucide="image" style="color: var(--text-muted); width: 16px; height: 16px;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight: 600; color: var(--text-main);"><?= htmlspecialchars($item['product_name'] ?? 'Unknown Product') ?></div>
                                        <div style="font-size: 0.75rem; color: var(--text-muted);">SKU: <?= htmlspecialchars($item['sku'] ?? 'N/A') ?></div>
                                    </div>
                                </td>
                                <td style="padding: 1rem 1.5rem;">
                                    <?= number_format($item['price'], 2) ?>
                                </td>
                                <td style="padding: 1rem 1.5rem; text-align: center;">
                                    <?= $item['quantity'] ?>
                                </td>
                                <td style="padding: 1rem 1.5rem; text-align: right; font-weight: 500;">
                                    <?= number_format($item['price'] * $item['quantity'], 2) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div style="padding: 1.5rem; display: flex; flex-direction: column; align-items: flex-end; gap: 0.5rem; background: var(--bg-content);">
                <div style="display: flex; justify-content: space-between; width: 280px; font-size: 0.875rem;">
                    <span style="color: var(--text-muted);">Subtotal:</span>
                    <span style="font-weight: 500;"><?= number_format($order['subtotal'], 2) ?> <?= htmlspecialchars($order['currency']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; width: 280px; font-size: 0.875rem;">
                    <span style="color: var(--text-muted);">Shipping cost:</span>
                    <span style="font-weight: 500;"><?= number_format($order['shipping_cost'], 2) ?> <?= htmlspecialchars($order['currency']) ?></span>
                </div>
                <div style="display: flex; justify-content: space-between; width: 280px; font-size: 0.875rem;">
                    <span style="color: var(--text-muted);">Discount:</span>
                    <span style="font-weight: 500; color: var(--danger);">-<?= number_format($order['discount_amount'], 2) ?> <?= htmlspecialchars($order['currency']) ?></span>
                </div>
                <hr style="width: 280px; border: 0; border-top: 1px solid var(--border-color); margin: 0.25rem 0;">
                <div style="display: flex; justify-content: space-between; width: 280px; font-size: 1.125rem; font-weight: 700;">
                    <span>Total:</span>
                    <span style="color: var(--primary);"><?= number_format($order['total'], 2) ?> <?= htmlspecialchars($order['currency']) ?></span>
                </div>
            </div>
        </div>

        <!-- Shipping & Customer details -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div class="card">
                <div style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="user" style="width: 18px; height: 18px; color: var(--text-muted);"></i> Customer Information
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
                    <div><strong>Name:</strong> <?= htmlspecialchars($order['customer_name']) ?></div>
                    <div><strong>Email:</strong> <?= htmlspecialchars($order['customer_email']) ?></div>
                    <div><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone'] ?? 'N/A') ?></div>
                </div>
            </div>

            <div class="card">
                <div style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    <i data-lucide="map-pin" style="width: 18px; height: 18px; color: var(--text-muted);"></i> Shipping Address
                </div>
                <div style="display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.875rem; line-height: 1.5;">
                    <?php if (empty($shipping)): ?>
                        <div style="color: var(--text-muted);">No shipping address provided.</div>
                    <?php else: ?>
                        <div><strong><?= htmlspecialchars(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')) ?></strong></div>
                        <div><?= htmlspecialchars($shipping['address_line1'] ?? '') ?></div>
                        <?php if (!empty($shipping['address_line2'])): ?>
                            <div><?= htmlspecialchars($shipping['address_line2']) ?></div>
                        <?php endif; ?>
                        <div><?= htmlspecialchars(($shipping['city'] ?? '') . ', ' . ($shipping['state'] ?? '') . ' ' . ($shipping['postal_code'] ?? '')) ?></div>
                        <div><?= htmlspecialchars($shipping['country'] ?? '') ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Status & Fulfillment -->
    <div style="display: flex; flex-direction: column; gap: 1.5rem;">
        <div class="card">
            <div style="font-weight: 600; margin-bottom: 1.25rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.75rem;">Fulfillment & Payment</div>
            <form method="POST" action="/admin/orders/update/<?= $order['id'] ?>
    <input type="hidden" name="_csrf" value="<?= $_SESSION['csrf_token'] ?? '' ?>">" style="display: flex; flex-direction: column; gap: 1.25rem;">
                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-muted);">Order Status</label>
                    <select name="status" style="padding: 0.625rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem; background: white; width: 100%;">
                        <option value="pending" <?= $order['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="confirmed" <?= $order['status'] === 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="processing" <?= $order['status'] === 'processing' ? 'selected' : '' ?>>Processing</option>
                        <option value="shipped" <?= $order['status'] === 'shipped' ? 'selected' : '' ?>>Shipped</option>
                        <option value="delivered" <?= $order['status'] === 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $order['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                        <option value="refunded" <?= $order['status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>

                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-muted);">Payment Status</label>
                    <select name="payment_status" style="padding: 0.625rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem; background: white; width: 100%;">
                        <option value="unpaid" <?= $order['payment_status'] === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
                        <option value="pending" <?= $order['payment_status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="paid" <?= $order['payment_status'] === 'paid' ? 'selected' : '' ?>>Paid</option>
                        <option value="failed" <?= $order['payment_status'] === 'failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="refunded" <?= $order['payment_status'] === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>

                <div class="form-group" style="display: flex; flex-direction: column; gap: 0.5rem;">
                    <label style="font-weight: 500; font-size: 0.875rem; color: var(--text-muted);">Admin Notes</label>
                    <textarea name="admin_notes" rows="4" placeholder="Internal updates, tracking links, details..." style="padding: 0.625rem; border-radius: 0.375rem; border: 1px solid var(--border-color); font-family: inherit; font-size: 0.875rem; resize: vertical;"><?= htmlspecialchars($order['admin_notes'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="justify-content: center; width: 100%; padding: 0.75rem;">
                    <i data-lucide="refresh-cw"></i> Update Details
                </button>
            </form>
        </div>

        <div class="card">
            <div style="font-weight: 600; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="credit-card" style="width: 18px; height: 18px; color: var(--text-muted);"></i> Payment Info
            </div>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; font-size: 0.875rem;">
                <div><strong>Method:</strong> <?= htmlspecialchars($order['payment_method'] ?? 'N/A') ?></div>
                <div><strong>Reference:</strong> <?= htmlspecialchars($order['payment_ref'] ?? 'N/A') ?></div>
                <div><strong>Paid At:</strong> <?= $order['paid_at'] ? date('M d, Y h:i A', strtotime($order['paid_at'])) : 'Unpaid' ?></div>
            </div>
        </div>
    </div>
</div>

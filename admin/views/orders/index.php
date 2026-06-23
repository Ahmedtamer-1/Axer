<?php
/** @var array $orders */
?>
<div class="header-bar">
    <div class="header-title">
        <h1>Orders</h1>
        <p>Monitor customer checkout logs, payment receipts, and fulfillment status.</p>
    </div>
</div>

<div class="card" style="padding: 0; overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse; text-align: left;">
        <thead>
            <tr style="background-color: var(--bg-content); border-bottom: 1px solid var(--border-color);">
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Order Number</th>
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Customer</th>
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Date</th>
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Payment</th>
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Fulfillment</th>
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted);">Total</th>
                <th style="padding: 1rem 1.5rem; font-weight: 600; font-size: 0.875rem; color: var(--text-muted); text-align: right;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="7" style="padding: 3rem; text-align: center; color: var(--text-muted);">
                        <i data-lucide="receipt" style="width: 48px; height: 48px; stroke-width: 1; margin-bottom: 1rem; opacity: 0.5;"></i>
                        <p>No orders found yet.</p>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
                    <tr style="border-bottom: 1px solid var(--border-color); transition: background-color 0.2s;" onmouseover="this.style.backgroundColor='rgba(99,102,241,0.02)'" onmouseout="this.style.backgroundColor='transparent'">
                        <td style="padding: 1rem 1.5rem;">
                            <span style="font-weight: 600; color: var(--text-main);">#<?= htmlspecialchars($order['order_number']) ?></span>
                        </td>
                        <td style="padding: 1rem 1.5rem;">
                            <div style="font-weight: 500; color: var(--text-main);"><?= htmlspecialchars($order['customer_name']) ?></div>
                            <div style="font-size: 0.75rem; color: var(--text-muted);"><?= htmlspecialchars($order['customer_email']) ?></div>
                        </td>
                        <td style="padding: 1rem 1.5rem; font-size: 0.875rem;">
                            <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?>
                        </td>
                        <td style="padding: 1rem 1.5rem;">
                            <?php if ($order['payment_status'] === 'paid'): ?>
                                <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: rgba(16, 185, 129, 0.1); color: var(--success);">Paid</span>
                            <?php elseif ($order['payment_status'] === 'pending'): ?>
                                <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: rgba(245, 158, 11, 0.1); color: #f59e0b;">Pending</span>
                            <?php else: ?>
                                <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: rgba(239, 68, 68, 0.1); color: var(--danger);"><?= ucfirst($order['payment_status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem 1.5rem;">
                            <?php if ($order['status'] === 'delivered'): ?>
                                <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: rgba(16, 185, 129, 0.1); color: var(--success);">Delivered</span>
                            <?php elseif ($order['status'] === 'shipped' || $order['status'] === 'processing'): ?>
                                <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: rgba(99, 102, 241, 0.1); color: var(--primary);"><?= ucfirst($order['status']) ?></span>
                            <?php elseif ($order['status'] === 'cancelled'): ?>
                                <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: rgba(239, 68, 68, 0.1); color: var(--danger);">Cancelled</span>
                            <?php else: ?>
                                <span style="display: inline-flex; align-items: center; padding: 0.25rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; background-color: rgba(100, 116, 139, 0.1); color: var(--text-muted);"><?= ucfirst($order['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 1rem 1.5rem; font-weight: 600; color: var(--text-main);">
                            <?= htmlspecialchars($order['currency']) ?> <?= number_format($order['total'], 2) ?>
                        </td>
                        <td style="padding: 1rem 1.5rem; text-align: right;">
                            <a href="/admin/orders/view/<?= $order['id'] ?>" class="btn btn-secondary" style="padding: 0.375rem 0.75rem; font-size: 0.75rem;">
                                <i data-lucide="eye" style="width: 14px; height: 14px;"></i> View Details
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

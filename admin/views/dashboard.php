<div class="header-bar">
    <div class="header-title">
        <h1>Dashboard</h1>
        <p>Overview of your store's performance.</p>
    </div>
</div>

<style>
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 1.5rem;
        margin-bottom: 2.5rem;
    }
    
    .stat-card {
        background-color: var(--bg-card);
        border: 1px solid var(--border-color);
        border-radius: 0.75rem;
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
        display: flex;
        align-items: center;
        gap: 1.25rem;
    }
    
    .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 0.5rem;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .stat-icon.primary {
        background-color: rgba(99, 102, 241, 0.1);
        color: var(--primary);
    }
    
    .stat-icon.success {
        background-color: rgba(16, 185, 129, 0.1);
        color: var(--success);
    }
    
    .stat-info {
        display: flex;
        flex-direction: column;
    }
    
    .stat-value {
        font-size: 1.5rem;
        font-weight: 700;
        color: var(--text-main);
    }
    
    .stat-label {
        font-size: 0.875rem;
        color: var(--text-muted);
    }
</style>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon primary">
            <i data-lucide="shopping-bag"></i>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $productsCount ?></span>
            <span class="stat-label">Total Products</span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon primary">
            <i data-lucide="receipt"></i>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= $ordersCount ?></span>
            <span class="stat-label">Total Orders</span>
        </div>
    </div>
    
    <div class="stat-card">
        <div class="stat-icon success">
            <i data-lucide="dollar-sign"></i>
        </div>
        <div class="stat-info">
            <span class="stat-value"><?= number_format($totalSales, 2) ?> EGP</span>
            <span class="stat-label">Net Sales</span>
        </div>
    </div>
</div>

<div class="card">
    <h2>Welcome to Axer Admin</h2>
    <p style="color: var(--text-muted); margin-top: 0.5rem; line-height: 1.6;">
        From here you can manage your shop products, process customer orders, modify pages, switch themes, or configure global settings.
    </p>
</div>

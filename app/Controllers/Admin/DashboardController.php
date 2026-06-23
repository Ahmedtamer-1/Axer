<?php

namespace Lume\Controllers\Admin;

use Lume\Core\Request;
use Lume\Core\Response;
use Lume\Database\QueryBuilder;

class DashboardController extends AdminController
{
    public function index(Request $request): Response
    {
        $this->checkAuth($request);

        // Fetch basic stats
        $productsCount = 0;
        $ordersCount = 0;
        $totalSales = 0.00;

        try {
            $productsCount = QueryBuilder::table('products')->count();
            $ordersCount = QueryBuilder::table('orders')->count();
            
            $salesResult = QueryBuilder::table('orders')
                ->where('payment_status', 'paid')
                ->select(QueryBuilder::raw('SUM(total) as total_sales'))
                ->first();
                
            $totalSales = (float)($salesResult['total_sales'] ?? 0.00);
        } catch (\Exception $e) {
            // Keep stats as 0 if tables don't exist yet or DB isn't connected
        }

        return $this->renderAdmin('dashboard', [
            'title' => 'Dashboard',
            'productsCount' => $productsCount,
            'ordersCount' => $ordersCount,
            'totalSales' => $totalSales
        ]);
    }
}

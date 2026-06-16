<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\OrderItem;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class RevenueReportsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // 1. Today Revenue
        $todayRevenue = Order::whereDate('created_at', today())
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // 2. This Week Revenue
        $thisWeekRevenue = Order::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // 3. Top Selling Product (Today)
        $topProductQuery = OrderItem::select('product_id', DB::raw('SUM(quantity) as total_qty'))
            ->groupBy('product_id')
            ->orderBy('total_qty', 'desc')
            ->with('product')
            ->first();

        $topProduct = 'N/A';
        if ($topProductQuery && $topProductQuery->product) {
            $topProduct = $topProductQuery->product->name . " ({$topProductQuery->total_qty} sold)";
        }

        // 4. Top Selling Category (Today)
        $topCategoryQuery = OrderItem::select('products.category_id', DB::raw('SUM(order_items.quantity) as total_qty'))
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->groupBy('products.category_id')
            ->orderBy('total_qty', 'desc')
            ->with('product.category')
            ->first();

        $topCategory = 'N/A';
        if ($topCategoryQuery && $topCategoryQuery->product && $topCategoryQuery->product->category) {
            $topCategory = $topCategoryQuery->product->category->name . " ({$topCategoryQuery->total_qty} sold)";
        }

        return [
            Stat::make('Today Revenue', 'IDR ' . number_format($todayRevenue, 0, ',', '.'))
                ->description('Today\'s closed paid sales')
                ->color('success'),
            Stat::make('This Week Revenue', 'IDR ' . number_format($thisWeekRevenue, 0, ',', '.'))
                ->description('This week\'s accumulated sales')
                ->color('info'),
            Stat::make('Top Selling Product', $topProduct)
                ->description('Highest sales volume item')
                ->color('primary'),
            Stat::make('Top Selling Category', $topCategory)
                ->description('Highest sales volume category')
                ->color('warning'),
        ];
    }
}

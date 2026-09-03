<?php

namespace App\Filament\Cashier\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class CashierOrderOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $stats = Cache::remember('cashier_order_overview_stats', 30, function () {
            return [
                'orders_today'     => \App\Models\Order::whereDate('created_at', today())->count(),
                'revenue_today'    => (float) \App\Models\Order::whereDate('created_at', today())->where('payment_status', 'paid')->sum('total_amount'),
                'pending_payments' => \App\Models\Order::where('payment_status', 'pending')->where('status', '!=', 'cancelled')->count(),
                'active_tables'    => \App\Models\TableSession::where('status', 'open')->count(),
            ];
        });

        return [
            Stat::make('Orders Today', $stats['orders_today'])
                ->description('Total orders placed today')
                ->color('primary'),
            Stat::make('Revenue Today', 'IDR ' . number_format($stats['revenue_today'], 0, ',', '.'))
                ->description('Total completed paid revenue')
                ->color('success'),
            Stat::make('Pending Payments', $stats['pending_payments'])
                ->description('Orders awaiting payment confirmation')
                ->color('warning'),
            Stat::make('Active Tables', $stats['active_tables'])
                ->description('Currently occupied tables')
                ->color('info'),
        ];
    }
}

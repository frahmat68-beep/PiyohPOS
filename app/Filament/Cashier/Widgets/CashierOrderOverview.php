<?php

namespace App\Filament\Cashier\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashierOrderOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Orders Today', \App\Models\Order::whereDate('created_at', today())->count())
                ->description('Total orders placed today')
                ->color('primary'),
            Stat::make('Revenue Today', 'IDR ' . number_format(\App\Models\Order::whereDate('created_at', today())->where('payment_status', 'paid')->sum('total_amount'), 0, ',', '.'))
                ->description('Total completed paid revenue')
                ->color('success'),
            Stat::make('Pending Payments', \App\Models\Order::where('payment_status', 'pending')->where('status', '!=', 'cancelled')->count())
                ->description('Orders awaiting payment confirmation')
                ->color('warning'),
            Stat::make('Active Tables', \App\Models\TableSession::where('status', 'open')->count())
                ->description('Currently occupied tables')
                ->color('info'),
        ];
    }
}

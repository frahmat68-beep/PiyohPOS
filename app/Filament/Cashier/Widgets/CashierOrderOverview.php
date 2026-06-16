<?php

namespace App\Filament\Cashier\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CashierOrderOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Open Orders', \App\Models\Order::whereIn('status', ['pending', 'confirmed', 'preparing'])->count())
                ->description('Pending, Confirmed, or Preparing')
                ->color('warning'),
            Stat::make('Ready Orders', \App\Models\Order::where('status', 'ready')->count())
                ->description('Waiting to be served')
                ->color('success'),
            Stat::make('Completed Orders', \App\Models\Order::where('status', 'completed')->count())
                ->description('Today\'s closed checkouts')
                ->color('info'),
            Stat::make('Active Tables', \App\Models\TableSession::where('status', 'open')->count())
                ->description('Currently occupied tables')
                ->color('primary'),
        ];
    }
}

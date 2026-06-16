<?php

namespace App\Filament\Kitchen\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class KitchenOrderOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pending Orders', \App\Models\Order::where('status', 'confirmed')->count())
                ->description('Awaiting kitchen start')
                ->color('danger'),
            Stat::make('Preparing Orders', \App\Models\Order::where('status', 'preparing')->count())
                ->description('Currently being prepared')
                ->color('warning'),
            Stat::make('Ready Orders', \App\Models\Order::where('status', 'ready')->count())
                ->description('Finished preparation')
                ->color('success'),
        ];
    }
}

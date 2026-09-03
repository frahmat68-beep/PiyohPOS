<?php

namespace App\Filament\Kitchen\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Filament\Kitchen\Widgets\KitchenOrderOverview;
use App\Filament\Kitchen\Widgets\KitchenOrdersTable;

class Dashboard extends BaseDashboard
{
    public function getWidgets(): array
    {
        return [
            KitchenOrderOverview::class,
            KitchenOrdersTable::class,
        ];
    }
}

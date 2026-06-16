<?php

namespace App\Filament\Widgets;

use App\Models\Table;
use App\Models\TableSession;
use Filament\Widgets\ChartWidget;

class TableUtilizationChart extends ChartWidget
{
    protected ?string $heading = 'Table Seating Status';

    protected function getData(): array
    {
        $occupied = TableSession::where('status', 'open')->count();
        $totalTables = Table::count();
        $vacant = max(0, $totalTables - $occupied);

        return [
            'datasets' => [
                [
                    'label' => 'Tables Status',
                    'data' => [$occupied, $vacant],
                    'backgroundColor' => ['#f59e0b', '#10b981'],
                ],
            ],
            'labels' => ['Occupied', 'Vacant'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

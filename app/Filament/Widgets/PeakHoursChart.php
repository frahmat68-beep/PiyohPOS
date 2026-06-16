<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PeakHoursChart extends ChartWidget
{
    protected ?string $heading = 'Peak Transaction Hours (Today)';

    protected function getData(): array
    {
        $hourlyData = Order::select(DB::raw('HOUR(created_at) as hour'), DB::raw('COUNT(*) as count'))
            ->whereDate('created_at', today())
            ->groupBy('hour')
            ->orderBy('hour', 'asc')
            ->get();

        $labels = [];
        $data = [];
        for ($i = 0; $i < 24; $i++) {
            $labels[] = sprintf('%02d:00', $i);
            $found = $hourlyData->firstWhere('hour', $i);
            $data[] = $found ? $found->count : 0;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Transactions Count',
                    'data' => $data,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

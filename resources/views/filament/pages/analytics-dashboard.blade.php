<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        @livewire(\App\Filament\Widgets\RevenueTrendChart::class)
        @livewire(\App\Filament\Widgets\PeakHoursChart::class)
        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-6">
            @livewire(\App\Filament\Widgets\TableUtilizationChart::class)
            @livewire(\App\Filament\Widgets\RevenueReportsOverview::class)
        </div>
    </div>
</x-filament-panels::page>

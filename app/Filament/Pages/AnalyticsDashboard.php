<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class AnalyticsDashboard extends Page
{
    protected string $view = 'filament.pages.analytics-dashboard';
    protected static ?string $title = 'Analytics & Reports';
    protected static \BackedEnum|string|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedChartBar;
}

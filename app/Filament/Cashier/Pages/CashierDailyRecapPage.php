<?php

namespace App\Filament\Cashier\Pages;

use App\Models\Outlet;
use App\Services\DailyRecapService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashierDailyRecapPage extends Page
{
    protected string $view = 'filament.pages.daily-recap';
    protected static ?string $slug = 'daily-recap';
    protected static ?string $title = 'Rekap Penjualan Harian & Tutup Toko';
    protected static ?string $navigationLabel = 'Rekap Transaksi';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    public static function canAccess(): bool
    {
        return true;
    }

    public ?string $selectedDate = null;
    public ?int $selectedOutletId = null;
    public array $recapData = [];

    public function mount(): void
    {
        $this->selectedDate = now()->format('Y-m-d');
        $user = auth()->user();
        $this->selectedOutletId = $user && $user->active_outlet_id ? $user->active_outlet_id : (Outlet::first()?->id ?? 1);
        $this->loadRecap();
    }

    public function updatedSelectedDate(): void
    {
        $this->loadRecap();
    }

    public function loadRecap(): void
    {
        $service = app(DailyRecapService::class);
        $date    = Carbon::parse($this->selectedDate);
        $this->recapData = $service->compute($this->selectedOutletId, $date);
    }

    public function closeStore(): void
    {
        $service = app(DailyRecapService::class);
        $date    = Carbon::parse($this->selectedDate);
        $service->saveDailyRecap($this->selectedOutletId, $date, auth()->id(), true);

        Notification::make()
            ->title('Toko Berhasil Ditutup')
            ->body('Rekap penjualan hari ini telah tersimpan.')
            ->success()
            ->send();

        $this->loadRecap();
    }

    public function downloadAccurateCsv(): StreamedResponse
    {
        $service  = app(DailyRecapService::class);
        $date     = Carbon::parse($this->selectedDate);
        $csvData  = $service->exportCsv($this->selectedOutletId, $date);
        $filename = "Rekap-Accurate-{$this->selectedDate}-Outlet-{$this->selectedOutletId}.csv";

        return response()->streamDownload(function () use ($csvData) {
            echo $csvData;
        }, $filename, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\Outlet;
use App\Services\DailyRecapService;
use BackedEnum;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyRecapPage extends Page
{
    protected string $view = 'filament.pages.daily-recap';
    protected static ?string $title = 'Rekap Transaksi & Tutup Kasir';
    protected static ?string $navigationLabel = 'Rekap Transaksi Harian';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    public ?string $selectedDate = null;
    public ?int $selectedOutletId = null;
    public array $recapData = [];

    public function mount(): void
    {
        $this->selectedDate = now()->format('Y-m-d');
        $firstOutlet = Outlet::first();
        $this->selectedOutletId = $firstOutlet ? $firstOutlet->id : 1;
        $this->loadRecap();
    }

    public function updatedSelectedDate(): void
    {
        $this->loadRecap();
    }

    public function updatedSelectedOutletId(): void
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
            ->body('Rekap transaksi hari ini telah dibekukan dan tersimpan.')
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

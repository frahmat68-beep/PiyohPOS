<?php

namespace App\Filament\Pages;

use App\Models\SyncLog;
use App\Services\SyncService;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class SyncDashboard extends Page
{
    protected string $view = 'filament.pages.sync-dashboard';
    protected static ?string $title = 'Master Data Sync Dashboard';
    protected static \BackedEnum|string|null $navigationIcon = \Filament\Support\Icons\Heroicon::OutlinedArrowPath;

    public function getViewData(): array
    {
        return [
            'syncLogs' => SyncLog::orderBy('created_at', 'desc')->take(30)->get(),
        ];
    }

    // Failed Sync Retry
    public function retrySync(int $logId)
    {
        $log = SyncLog::findOrFail($logId);
        $syncService = app(SyncService::class);
        $payload = $log->payload;

        try {
            $formattedPayload = [];
            if ($log->entity_type === 'category') {
                $formattedPayload['categories'] = $payload;
            } elseif ($log->entity_type === 'product') {
                $formattedPayload['products'] = $payload;
            } elseif ($log->entity_type === 'price') {
                $formattedPayload['prices'] = $payload;
            } elseif ($log->entity_type === 'outlet') {
                $formattedPayload['outlets'] = $payload;
            }

            $syncService->syncAll($formattedPayload);

            Notification::make()
                ->title('Retry Sync Successful')
                ->success()
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Retry Sync Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}

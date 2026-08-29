<?php

namespace App\Filament\Cashier\Widgets;

use App\Models\TableSession;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;

class TableLockStatusWidget extends TableWidget
{
    protected static ?int $sort = 3;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $pollingInterval = '5s';
    protected static ?string $heading = '🪑 Status Meja & Kunci Checkout Pelanggan';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TableSession::query()
                    ->with('table')
                    ->where('status', 'open')
                    ->orderBy('is_locked', 'desc')
                    ->orderBy('updated_at', 'desc')
            )
            ->columns([
                TextColumn::make('table.number')
                    ->label('No. Meja')
                    ->weight('bold')
                    ->formatStateUsing(fn ($record) => "Meja {$record->table->number}"),

                TextColumn::make('session_code')
                    ->label('Kode Sesi')
                    ->fontFamily('mono')
                    ->limit(10),

                TextColumn::make('is_locked')
                    ->label('Status Kunci')
                    ->badge()
                    ->color(fn (bool $state, TableSession $record): string => ($state && $record->locked_at && $record->locked_at->diffInMinutes(now()) < 10) ? 'danger' : 'success')
                    ->formatStateUsing(function (bool $state, TableSession $record): string {
                        if ($state && $record->locked_at && $record->locked_at->diffInMinutes(now()) < 10) {
                            $menit = 10 - $record->locked_at->diffInMinutes(now());
                            return "🔒 Sedang Checkout (Sisa lock ~{$menit}m)";
                        }
                        return "🟢 Terbuka / Siap Pesan";
                    }),

                TextColumn::make('locked_at')
                    ->label('Waktu Mulai Checkout')
                    ->since()
                    ->placeholder('-'),

                TextColumn::make('cartItems')
                    ->label('Jumlah Item di Meja')
                    ->formatStateUsing(fn (TableSession $record) => $record->cartItems->sum('quantity') . ' item'),
            ])
            ->actions([
                Action::make('forceUnlock')
                    ->label('Buka Paksa Kunci')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->visible(fn (TableSession $record): bool => (bool) $record->is_locked)
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Buka Paksa Kunci Meja')
                    ->modalDescription('Tindakan ini akan melepaskan kunci checkout di meja ini sehingga perangkat lain di meja bisa kembali menambah menu atau checkout ulang.')
                    ->modalSubmitActionLabel('Ya, Buka Kunci Meja')
                    ->action(function (TableSession $record) {
                        $record->unlockCart();

                        Notification::make()
                            ->title('Kunci Meja Dibuka')
                            ->body("Kunci checkout Meja {$record->table->number} berhasil dibuka paksa.")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}

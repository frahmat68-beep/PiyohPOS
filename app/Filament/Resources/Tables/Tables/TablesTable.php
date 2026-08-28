<?php

namespace App\Filament\Resources\Tables\Tables;

use App\Models\Table as TableModel;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Support\Str;

class TablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('outlet.name')
                    ->label('Outlet')
                    ->badge()
                    ->searchable(),
                TextColumn::make('number')
                    ->label('No. Meja')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('seating_capacity')
                    ->label('Kapasitas')
                    ->numeric()
                    ->formatStateUsing(fn ($state) => "{$state} Kursi")
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'occupied' => 'danger',
                        'vacant'   => 'success',
                        'reserved' => 'warning',
                        default    => 'gray',
                    }),
                TextColumn::make('qr_token')
                    ->label('QR Token')
                    ->fontFamily('mono')
                    ->limit(12)
                    ->copyable()
                    ->copyMessage('QR Token disalin!'),
            ])
            ->filters([])
            ->actions([
                // Open Customer QR Menu in New Tab
                Action::make('open_menu')
                    ->label('Buka Menu QR')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn (TableModel $record): string => url("/scan/{$record->qr_token}"))
                    ->openUrlInNewTab(),

                EditAction::make(),

                // Regenerate QR Token
                Action::make('regenerate_token')
                    ->label('Regenerate Token')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Buat Ulang QR Token Meja')
                    ->modalDescription('Token lama akan tidak berlaku lagi. QR code fisik di meja harus diganti dengan yang baru.')
                    ->action(fn (TableModel $record) => $record->update(['qr_token' => Str::random(32)])),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
            ]);
    }
}

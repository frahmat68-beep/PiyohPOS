<?php

namespace App\Filament\Cashier\Widgets;

use App\Models\Product;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Builder;

class LowStockReminderWidget extends TableWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $pollingInterval = '10s';
    protected static ?string $heading = '⚠️ Peringatan Stok Menipis & Habis';

    public static function canView(): bool
    {
        return Product::query()
            ->whereNotNull('stock_quantity')
            ->whereRaw('stock_quantity <= low_stock_threshold')
            ->where('is_active', true)
            ->exists();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::query()
                    ->whereNotNull('stock_quantity')
                    ->whereRaw('stock_quantity <= low_stock_threshold')
                    ->where('is_active', true)
                    ->orderBy('stock_quantity', 'asc')
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->weight('bold')
                    ->description(fn (Product $record) => $record->category ? $record->category->name : ''),

                TextColumn::make('stock_quantity')
                    ->label('Sisa Stok')
                    ->badge()
                    ->color(fn (int $state): string => $state <= 0 ? 'danger' : 'warning')
                    ->formatStateUsing(fn (int $state): string => $state <= 0 ? 'HABIS (0)' : "Tersisa {$state} unit"),

                TextColumn::make('low_stock_threshold')
                    ->label('Batas Minimum')
                    ->formatStateUsing(fn (int $state) => "< {$state} unit"),

                TextColumn::make('base_price')
                    ->label('Harga')
                    ->money('IDR'),
            ])
            ->actions([
                Action::make('updateStock')
                    ->label('Update Stok')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->form([
                        TextInput::make('stock_quantity')
                            ->label('Jumlah Stok Baru')
                            ->numeric()
                            ->required()
                            ->default(fn (Product $record) => $record->stock_quantity),
                    ])
                    ->action(function (Product $record, array $data) {
                        $record->update([
                            'stock_quantity' => (int) $data['stock_quantity'],
                        ]);
                    }),
                Action::make('markZero')
                    ->label('Tandai Habis (0)')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn (Product $record) => $record->update(['stock_quantity' => 0])),
            ]);
    }
}

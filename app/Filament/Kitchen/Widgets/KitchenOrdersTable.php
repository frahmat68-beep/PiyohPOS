<?php

namespace App\Filament\Kitchen\Widgets;

use App\Models\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;

class KitchenOrdersTable extends TableWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $pollingInterval = '5s';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()
                ->whereIn('status', [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY])
                ->with(['table', 'orderItems.product'])
                ->orderByRaw("FIELD(status, 'confirmed', 'preparing', 'ready')")
                ->oldest('confirmed_at')
            )
            ->columns([
                TextColumn::make('order_number')
                    ->label('Pesanan')
                    ->weight('bold')
                    ->description(fn (Order $record): string => $record->table ? "Meja {$record->table->number}" : 'Takeaway'),

                TextColumn::make('customer_name')
                    ->label('Pelanggan'),

                TextColumn::make('status')
                    ->label('Posisi KDS')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'danger',
                        'preparing' => 'warning',
                        'ready' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'confirmed' => '1. Antrian Masuk',
                        'preparing' => '2. Sedang Diracik',
                        'ready' => '3. Siap Diantar',
                        default => $state,
                    }),

                TextColumn::make('orderItems')
                    ->label('Daftar Menu Racikan')
                    ->formatStateUsing(fn ($record) => $record->orderItems->map(fn ($item) => "{$item->quantity}x {$item->product->name}" . ($item->notes ? " [Catatan: {$item->notes}]" : ''))->join(' • '))
                    ->wrap()
                    ->weight('semibold'),

                TextColumn::make('created_at')
                    ->label('Lama Tunggu')
                    ->since()
                    ->sortable()
                    ->color(fn (Order $record): string => $record->created_at->diffInMinutes(now()) >= 10 ? 'danger' : 'gray'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Filter Kolom')
                    ->options([
                        Order::STATUS_CONFIRMED => '1. Antrian Baru',
                        Order::STATUS_PREPARING => '2. Sedang Diracik',
                        Order::STATUS_READY => '3. Siap Diambil',
                    ]),
            ])
            ->actions([
                // Start Preparation (confirmed -> preparing)
                Action::make('prepare')
                    ->label('Mulai Racik')
                    ->color('warning')
                    ->button()
                    ->icon('heroicon-o-play')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_CONFIRMED)
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_PREPARING, 'Kitchen started preparation.')),

                // Complete Preparation (preparing -> ready)
                Action::make('ready')
                    ->label('Tandai Siap')
                    ->color('success')
                    ->button()
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_PREPARING)
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_READY, 'Kitchen marked preparation as ready.')),
            ]);
    }
}

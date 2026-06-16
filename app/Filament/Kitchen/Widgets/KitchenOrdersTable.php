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

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()
                ->whereIn('status', [Order::STATUS_CONFIRMED, Order::STATUS_PREPARING, Order::STATUS_READY])
                ->with(['table', 'orderItems.product'])
            )
            ->columns([
                TextColumn::make('order_number')
                    ->weight('bold'),
                TextColumn::make('table.number')
                    ->label('Table'),
                TextColumn::make('customer_name')
                    ->label('Customer'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'confirmed' => 'danger',
                        'preparing' => 'warning',
                        'ready' => 'success',
                        default => 'gray',
                    }),
                // Display list of items ordered
                TextColumn::make('orderItems')
                    ->label('Items to Prepare')
                    ->formatStateUsing(fn ($record) => $record->orderItems->map(fn ($item) => "{$item->quantity}x {$item->product->name}" . ($item->notes ? " ({$item->notes})" : ''))->join(', ')),
                TextColumn::make('created_at')
                    ->label('Placed At')
                    ->dateTime('H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        Order::STATUS_CONFIRMED => 'Pending',
                        Order::STATUS_PREPARING => 'Preparing',
                        Order::STATUS_READY => 'Ready',
                    ]),
            ])
            ->actions([
                // Detail Action
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Order $record): string => "/admin/orders/{$record->id}"),

                // Start Preparation (confirmed -> preparing)
                Action::make('prepare')
                    ->label('Start Preparing')
                    ->color('warning')
                    ->icon('heroicon-o-play')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_CONFIRMED)
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_PREPARING, 'Kitchen started preparation.')),

                // Complete Preparation (preparing -> ready)
                Action::make('ready')
                    ->label('Mark Ready')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_PREPARING)
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_READY, 'Kitchen marked preparation as ready.')),
            ]);
    }
}

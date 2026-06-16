<?php

namespace App\Filament\Cashier\Widgets;

use App\Models\Order;
use App\Models\Payment;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Colors\Color;

class CashierOrdersTable extends TableWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()->with(['table', 'outlet']))
            ->columns([
                TextColumn::make('order_number')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('outlet.name')
                    ->sortable(),
                TextColumn::make('table.number')
                    ->label('Table')
                    ->sortable(),
                TextColumn::make('customer_name')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'danger',
                        'confirmed' => 'warning',
                        'preparing' => 'info',
                        'ready' => 'success',
                        'served' => 'primary',
                        'completed' => 'gray',
                        'cancelled' => 'rose',
                        default => 'gray',
                    }),
                TextColumn::make('total_amount')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('payment_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'danger',
                        'paid' => 'success',
                        'failed' => 'rose',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Time')
                    ->dateTime('H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'confirmed' => 'Confirmed',
                        'preparing' => 'Preparing',
                        'ready' => 'Ready',
                        'served' => 'Served',
                        'completed' => 'Completed',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),
            ])
            ->actions([
                // Detail Action
                Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->url(fn (Order $record): string => "/admin/orders/{$record->id}"),

                // Confirm order (pending -> confirmed)
                Action::make('confirm')
                    ->label('Confirm')
                    ->color('warning')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_PENDING)
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_CONFIRMED, 'Order confirmed by cashier.')),

                // Serve order (ready -> served)
                Action::make('serve')
                    ->label('Serve')
                    ->color('primary')
                    ->icon('heroicon-o-bell')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_READY)
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_SERVED, 'Order served by cashier.')),

                // Process Payment Action
                Action::make('pay')
                    ->label('Payment')
                    ->color('success')
                    ->icon('heroicon-o-credit-card')
                    ->visible(fn (Order $record): bool => $record->payment_status !== 'paid' && $record->status !== Order::STATUS_CANCELLED)
                    ->form([
                        \Filament\Forms\Components\Select::make('payment_method')
                            ->options([
                                'cash' => 'Cash',
                                'qris' => 'QRIS',
                                'card' => 'Card',
                            ])
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->numeric()
                            ->required()
                            ->default(fn (Order $record) => $record->total_amount),
                    ])
                    ->action(function (Order $record, array $data) {
                        Payment::create([
                            'order_id' => $record->id,
                            'payment_method' => $data['payment_method'],
                            'payment_status' => 'paid',
                            'amount' => $data['amount'],
                            'paid_at' => now(),
                        ]);

                        $record->update([
                            'payment_status' => 'paid',
                            'payment_method' => $data['payment_method'],
                        ]);
                    }),

                // Complete Order (served/paid -> completed)
                Action::make('complete')
                    ->label('Complete')
                    ->color('success')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_SERVED && $record->payment_status === 'paid')
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_COMPLETED, 'Order completed by cashier.')),

                // Cancel Order
                Action::make('cancel')
                    ->label('Cancel')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (Order $record): bool => in_array($record->status, [Order::STATUS_PENDING, Order::STATUS_CONFIRMED]))
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_CANCELLED, 'Order cancelled by cashier.')),
            ]);
    }
}

// Helper to determine check status
function in_repeatable_completed_state(Order $record): bool {
    return in_array($record->status, [Order::STATUS_COMPLETED, Order::STATUS_CANCELLED]);
}

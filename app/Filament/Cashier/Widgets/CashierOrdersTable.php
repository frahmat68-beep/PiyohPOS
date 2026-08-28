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
    protected static ?string $pollingInterval = '5s';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()->with(['table', 'outlet', 'orderItems.product'])->latest())
            ->columns([
                TextColumn::make('order_number')
                    ->label('No. Pesanan')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn (Order $record): string => $record->table ? "Meja {$record->table->number}" : 'Takeaway'),

                TextColumn::make('customer_name')
                    ->label('Pemesan')
                    ->searchable(),

                TextColumn::make('orderItems')
                    ->label('Menu Dipesan')
                    ->formatStateUsing(fn ($record) => $record->orderItems->map(fn ($it) => "{$it->quantity}x {$it->product->name}" . ($it->notes ? " ({$it->notes})" : ''))->join(', '))
                    ->wrap()
                    ->limit(40),

                TextColumn::make('status')
                    ->label('Status Order')
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
                    ->label('Total')
                    ->money('IDR')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('payment_status')
                    ->label('Pembayaran')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'danger',
                        'paid' => 'success',
                        'failed' => 'rose',
                        'refunded' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->since()
                    ->sortable()
                    ->description(fn (Order $record): ?string => ($record->status === Order::STATUS_PENDING && $record->created_at->diffInMinutes(now()) >= 3) 
                        ? ($record->created_at->diffInMinutes(now()) >= 7 ? '⚠️ Kritis (>7m)' : '⏱️ Perlu Respon (>3m)') 
                        : null
                    )
                    ->color(fn (Order $record): string => ($record->status === Order::STATUS_PENDING && $record->created_at->diffInMinutes(now()) >= 3)
                        ? ($record->created_at->diffInMinutes(now()) >= 7 ? 'danger' : 'warning')
                        : 'gray'
                    ),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending (Menunggu)',
                        'confirmed' => 'Confirmed (Antrian)',
                        'preparing' => 'Preparing (Diracik)',
                        'ready' => 'Ready (Siap)',
                        'served' => 'Served (Disajikan)',
                        'completed' => 'Completed (Selesai)',
                        'cancelled' => 'Cancelled (Batal)',
                    ]),
                SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Belum Bayar',
                        'paid' => 'Lunas',
                    ]),
            ])
            ->actions([
                // 1. Progressive Action: Confirm (Pending -> Confirmed)
                Action::make('confirm')
                    ->label('Konfirmasi Pesanan')
                    ->color('warning')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_PENDING)
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_CONFIRMED, 'Order confirmed by cashier.')),

                // 2. Progressive Action: Serve (Ready -> Served)
                Action::make('serve')
                    ->label('Sajikan ke Meja')
                    ->color('primary')
                    ->icon('heroicon-o-bell')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_READY)
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_SERVED, 'Order served by cashier.')),

                // 3. Progressive Action: Process Payment
                Action::make('pay')
                    ->label('Proses Pembayaran')
                    ->color('success')
                    ->icon('heroicon-o-credit-card')
                    ->visible(fn (Order $record): bool => $record->payment_status !== 'paid' && $record->status !== Order::STATUS_CANCELLED)
                    ->form([
                        \Filament\Forms\Components\Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options([
                                'cash' => 'Tunai (Cash)',
                                'qris' => 'QRIS',
                                'card' => 'Debit / Kartu Kredit',
                            ])
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label('Nominal Pembayaran (Rp)')
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

                // 4. Progressive Action: Complete (Served & Paid -> Completed)
                Action::make('complete')
                    ->label('Selesaikan Order')
                    ->color('success')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_SERVED && $record->payment_status === 'paid')
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_COMPLETED, 'Order completed by cashier.')),

                // Cancel Action
                Action::make('cancel')
                    ->label('Batalkan')
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

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

class CashierOrdersTable extends TableWidget
{
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $pollingInterval = '5s';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()->with(['table', 'outlet', 'orderItems.product', 'deliveredBy'])->latest())
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
                        'pending_payment' => 'danger',
                        'pending'         => 'danger',
                        'confirmed'       => 'warning',
                        'preparing'       => 'info',
                        'ready'           => 'success',
                        'served'          => 'primary',
                        'completed'       => 'gray',
                        'cancelled'       => 'rose',
                        default           => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending_payment' => 'Pending Bayar',
                        'pending'         => 'Pending Konfirmasi',
                        'confirmed'       => 'Dikonfirmasi',
                        'preparing'       => 'Diracik',
                        'ready'           => 'Siap Saji',
                        'served'          => 'Disajikan',
                        'completed'       => 'Selesai',
                        'cancelled'       => 'Batal',
                        default           => ucfirst($state),
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
                        'pending'  => 'danger',
                        'paid'     => 'success',
                        'failed'   => 'rose',
                        'refunded' => 'gray',
                        default    => 'gray',
                    })
                    ->description(function (Order $record): ?string {
                        if ($record->midtrans_payment_type) {
                            return 'Midtrans: ' . strtoupper($record->midtrans_payment_type);
                        }
                        if ($record->payment_method) {
                            return ucfirst($record->payment_method);
                        }
                        return null;
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending'  => 'Menunggu Bayar',
                        'paid'     => 'Lunas',
                        'failed'   => 'Gagal',
                        'refunded' => 'Refund',
                        default    => ucfirst($state),
                    }),

                TextColumn::make('delivered_at')
                    ->label('Pengantaran')
                    ->badge()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->formatStateUsing(fn ($state, Order $record) => $state 
                        ? 'Sudah Diantar' 
                        : 'Belum Diantar'
                    )
                    ->description(fn (Order $record): ?string => $record->delivered_at 
                        ? "Pukul {$record->delivered_at->format('H:i')} oleh " . ($record->deliveredBy ? $record->deliveredBy->name : 'Kasir')
                        : null
                    ),

                TextColumn::make('created_at')
                    ->label('Waktu')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending_payment' => 'Pending Bayar (Online)',
                        'pending'         => 'Pending Konfirmasi',
                        'confirmed'       => 'Confirmed (Antrian)',
                        'preparing'       => 'Preparing (Diracik)',
                        'ready'           => 'Ready (Siap)',
                        'served'          => 'Served (Disajikan)',
                        'completed'       => 'Completed (Selesai)',
                        'cancelled'       => 'Cancelled (Batal)',
                    ]),
                SelectFilter::make('payment_status')
                    ->options([
                        'pending' => 'Belum Bayar',
                        'paid'    => 'Lunas',
                    ]),
                SelectFilter::make('delivered')
                    ->label('Status Pengantaran')
                    ->options([
                        'delivered'     => 'Sudah Diantar',
                        'not_delivered' => 'Belum Diantar',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if ($data['value'] === 'delivered') {
                            return $query->whereNotNull('delivered_at');
                        }
                        if ($data['value'] === 'not_delivered') {
                            return $query->whereNull('delivered_at');
                        }
                        return $query;
                    }),
            ])
            ->actions([
                // Checklist Action: Tandai Sudah Diantar
                Action::make('markDelivered')
                    ->label('Tandai Sudah Diantar')
                    ->color('success')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (Order $record): bool => $record->payment_status === 'paid' && $record->delivered_at === null && $record->status !== Order::STATUS_CANCELLED)
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pengantaran Menu')
                    ->modalDescription('Pastikan seluruh menu pesanan fisik telah sampai di meja customer.')
                    ->modalSubmitActionLabel('Ya, Sudah Diantar')
                    ->action(function (Order $record) {
                        $record->update([
                            'delivered_at'         => now(),
                            'delivered_by_user_id' => auth()->id(),
                        ]);

                        if ($record->status === Order::STATUS_READY) {
                            $record->transitionTo(Order::STATUS_SERVED, 'Order diantar ke meja oleh kasir.');
                        }

                        $record->timelines()->create([
                            'status'     => 'delivered',
                            'notes'      => 'Minuman/makanan fisik telah sampai di tangan customer.',
                            'created_by' => auth()->id(),
                            'created_at' => now(),
                        ]);
                    }),

                // Confirm Action
                Action::make('confirm')
                    ->label('Konfirmasi')
                    ->color('warning')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Order $record): bool => in_array($record->status, [Order::STATUS_PENDING, Order::STATUS_PENDING_PAYMENT]) && $record->payment_status === 'paid')
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_CONFIRMED, 'Order confirmed by cashier.')),

                // Serve Action
                Action::make('serve')
                    ->label('Sajikan ke Meja')
                    ->color('primary')
                    ->icon('heroicon-o-bell')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_READY)
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_SERVED, 'Order served by cashier.')),

                // Manual Payment Action (for cash orders)
                Action::make('pay')
                    ->label('Bayar Tunai')
                    ->color('success')
                    ->icon('heroicon-o-banknotes')
                    ->visible(fn (Order $record): bool => $record->payment_status !== 'paid' && $record->status !== Order::STATUS_CANCELLED)
                    ->form([
                        \Filament\Forms\Components\Select::make('payment_method')
                            ->label('Metode Pembayaran')
                            ->options([
                                'cash' => 'Tunai (Cash)',
                                'qris' => 'QRIS Kasir',
                                'card' => 'Debit / EDC',
                            ])
                            ->default('cash')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('amount')
                            ->label('Nominal Pembayaran (Rp)')
                            ->numeric()
                            ->required()
                            ->default(fn (Order $record) => $record->total_amount),
                    ])
                    ->action(function (Order $record, array $data) {
                        Payment::create([
                            'order_id'       => $record->id,
                            'payment_method' => $data['payment_method'],
                            'payment_status' => 'paid',
                            'amount'         => $data['amount'],
                            'paid_at'        => now(),
                        ]);

                        $record->update([
                            'payment_status' => 'paid',
                            'payment_method' => $data['payment_method'],
                        ]);

                        if ($record->status === Order::STATUS_PENDING || $record->status === Order::STATUS_PENDING_PAYMENT) {
                            $record->transitionTo(Order::STATUS_CONFIRMED, "Pembayaran manual kasir ({$data['payment_method']}) berhasil diterima.");
                        }
                    }),

                // Complete Action
                Action::make('complete')
                    ->label('Selesai')
                    ->color('success')
                    ->icon('heroicon-o-check')
                    ->visible(fn (Order $record): bool => $record->status === Order::STATUS_SERVED && $record->payment_status === 'paid')
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_COMPLETED, 'Order completed by cashier.')),

                // Cancel Action
                Action::make('cancel')
                    ->label('Batalkan')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn (Order $record): bool => in_array($record->status, [Order::STATUS_PENDING, Order::STATUS_PENDING_PAYMENT, Order::STATUS_CONFIRMED]))
                    ->requiresConfirmation()
                    ->action(fn (Order $record) => $record->transitionTo(Order::STATUS_CANCELLED, 'Order cancelled by cashier.')),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Grid;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(2)
                    ->schema([
                        Section::make('Order Information')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('order_number')
                                    ->weight('bold'),
                                TextEntry::make('outlet.name')
                                    ->label('Outlet'),
                                TextEntry::make('table.number')
                                    ->label('Table Number'),
                                TextEntry::make('customer_name')
                                    ->label('Customer'),
                                TextEntry::make('status')
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
                            ]),

                        Section::make('Payment Information')
                            ->columnSpan(1)
                            ->schema([
                                TextEntry::make('total_amount')
                                    ->money('IDR'),
                                TextEntry::make('payment_status')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'danger',
                                        'paid' => 'success',
                                        'failed' => 'rose',
                                        'refunded' => 'gray',
                                        default => 'gray',
                                    }),
                                TextEntry::make('payment_method')
                                    ->placeholder('N/A'),
                            ]),
                    ]),

                Section::make('Ordered Items')
                    ->schema([
                        RepeatableEntry::make('orderItems')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('product.name')
                                            ->label('Product'),
                                        TextEntry::make('quantity')
                                            ->label('Quantity'),
                                        TextEntry::make('price')
                                            ->money('IDR')
                                            ->label('Price'),
                                        TextEntry::make('notes')
                                            ->label('Notes')
                                            ->placeholder('None'),
                                    ]),
                            ]),
                    ]),

                Section::make('Order Status Timeline History')
                    ->schema([
                        RepeatableEntry::make('timelines')
                            ->label('')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        TextEntry::make('status')
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
                                        TextEntry::make('notes')
                                            ->label('Notes'),
                                        TextEntry::make('creator.name')
                                            ->label('Action By')
                                            ->placeholder('System / Customer'),
                                        TextEntry::make('created_at')
                                            ->label('Time')
                                            ->dateTime('d M Y H:i:s'),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}

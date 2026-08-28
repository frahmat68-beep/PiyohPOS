<?php

namespace App\Filament\Cashier\Resources\ProductStocks;

use App\Models\Product;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Actions\Action;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

class ProductStockResource extends Resource
{
    protected static ?string $model = Product::class;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;
    protected static ?string $navigationLabel = 'Manajemen Stok';
    protected static ?string $modelLabel = 'Stok Produk';
    protected static ?string $pluralModelLabel = 'Stok Produk';

    public static function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Product::query()->with('category')->orderBy('category_id')->orderBy('name'))
            ->columns([
                TextColumn::make('category.name')
                    ->label('Kategori')
                    ->badge()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Produk')
                    ->searchable()
                    ->weight('bold'),

                TextInputColumn::make('stock_quantity')
                    ->label('Jumlah Stok (Unit)')
                    ->type('number')
                    ->sortable(),

                TextInputColumn::make('low_stock_threshold')
                    ->label('Batas Minimum')
                    ->type('number'),

                ToggleColumn::make('is_active')
                    ->label('Aktif'),

                TextColumn::make('base_price')
                    ->label('Harga Dasar')
                    ->money('IDR'),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Kategori'),
                TernaryFilter::make('stock_status')
                    ->label('Kondisi Stok')
                    ->placeholder('Semua')
                    ->trueLabel('Stok Menipis / Habis')
                    ->falseLabel('Stok Aman')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('stock_quantity')->whereRaw('stock_quantity <= low_stock_threshold'),
                        false: fn (Builder $query) => $query->where(fn ($q) => $q->whereNull('stock_quantity')->orWhereRaw('stock_quantity > low_stock_threshold')),
                    ),
            ])
            ->actions([
                Action::make('setZero')
                    ->label('Habis (0)')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->action(fn (Product $record) => $record->update(['stock_quantity' => 0])),
                Action::make('setUnlimited')
                    ->label('Tak Terbatas')
                    ->color('gray')
                    ->icon('heroicon-o-infinity')
                    ->action(fn (Product $record) => $record->update(['stock_quantity' => null])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Cashier\Resources\ProductStocks\Pages\ListProductStocks::route('/'),
        ];
    }
}

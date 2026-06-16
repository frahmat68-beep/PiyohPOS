<?php

namespace App\Filament\Resources\Products\Tables;

use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category.name')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('base_price')
                    ->money('IDR')
                    ->sortable(),
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('source_system')
                    ->label('Source')
                    ->badge()
                    ->color('info'),
                TextColumn::make('last_synced_at')
                    ->label('Last Sync')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // READ-ONLY: no edit/delete actions
                // Managed exclusively by PiyohWeb (Master Data System)
            ])
            ->toolbarActions([
                // READ-ONLY: no create/delete bulk actions
            ]);
    }
}

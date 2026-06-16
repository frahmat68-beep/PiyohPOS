<?php

namespace App\Filament\Resources\Products\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'productPrices';

    // READ-ONLY: form is intentionally empty - prices are managed by PiyohWeb
    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('price')
            ->columns([
                TextColumn::make('outlet.name')
                    ->searchable(),
                TextColumn::make('price')
                    ->money('IDR')
                    ->sortable(),
                IconColumn::make('is_available')
                    ->boolean(),
                TextColumn::make('last_synced_at')
                    ->label('Last Sync')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([])
            ->headerActions([
                // READ-ONLY: no create/associate — managed by PiyohWeb
            ])
            ->recordActions([
                // READ-ONLY: no edit/delete — managed by PiyohWeb
            ])
            ->toolbarActions([
                // READ-ONLY: no bulk actions
            ]);
    }
}

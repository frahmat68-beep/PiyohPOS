<?php

namespace App\Filament\Resources\Tables\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('outlet_id')
                    ->relationship('outlet', 'name')
                    ->required(),
                TextInput::make('number')
                    ->required(),
                TextInput::make('seating_capacity')
                    ->required()
                    ->numeric()
                    ->default(4),
                Select::make('status')
                    ->options([
                        'vacant' => 'Vacant',
                        'occupied' => 'Occupied',
                        'reserved' => 'Reserved',
                    ])
                    ->required()
                    ->default('vacant'),
            ]);
    }
}

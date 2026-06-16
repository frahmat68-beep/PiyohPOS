<?php

namespace App\Filament\Resources\Tables\Tables;

use App\Models\Table as TableModel;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Illuminate\Support\Str;

class TablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('outlet.name')
                    ->searchable(),
                TextColumn::make('number')
                    ->searchable(),
                TextColumn::make('seating_capacity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('qr_token')
                    ->searchable(),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
                
                // Regenerate QR Token
                Action::make('regenerate_token')
                    ->label('Regenerate Token')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(fn (TableModel $record) => $record->update(['qr_token' => Str::random(32)])),

                // Download QR PNG (Mocked download action)
                Action::make('download_png')
                    ->label('Download PNG')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function (TableModel $record) {
                        // In actual deployment, it serves a generated image payload.
                        // We will return a simulated output.
                        return response()->streamDownload(function () use ($record) {
                            echo "MOCK_PNG_DATA_FOR_TABLE_" . $record->number;
                        }, "table-{$record->number}-qr.png");
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    
                    // Batch Print Action (A4)
                    BulkAction::make('batch_print')
                        ->label('Batch Print A4')
                        ->icon('heroicon-o-printer')
                        ->action(function (\Illuminate\Support\Collection $records) {
                            return response()->streamDownload(function () use ($records) {
                                echo "BATCH_A4_PDF_PRINT_OUTLET_TABLES:\n";
                                foreach ($records as $table) {
                                    echo "- Table Number: {$table->number}, Token: {$table->qr_token}\n";
                                }
                            }, "batch-tables-print.pdf");
                        }),
                ]),
            ]);
    }
}

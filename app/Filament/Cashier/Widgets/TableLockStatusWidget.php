<?php

namespace App\Filament\Cashier\Widgets;

use App\Models\TableSession;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class TableLockStatusWidget extends TableWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';
    protected static ?string $pollingInterval = '5s';
    protected static ?string $heading = '🪑 Status Meja & Kunci Checkout Pelanggan';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TableSession::query()
                    ->with('table')
                    ->where('status', 'open')
                    ->orderBy('is_locked', 'desc')
                    ->orderBy('updated_at', 'desc')
            )
            ->columns([
                TextColumn::make('table.number')
                    ->label('No. Meja')
                    ->weight('bold')
                    ->formatStateUsing(fn ($record) => "Meja {$record->table->number}"),

                TextColumn::make('session_code')
                    ->label('Kode Sesi')
                    ->fontFamily('mono')
                    ->limit(10),

                TextColumn::make('is_locked')
                    ->label('Status Kunci')
                    ->badge()
                    ->color(fn (bool $state, TableSession $record): string => ($state && $record->locked_at && (int) $record->locked_at->diffInSeconds(now()) < 600) ? 'danger' : 'success')
                    ->formatStateUsing(function (bool $state, TableSession $record): string {
                        if ($state && $record->locked_at && (int) $record->locked_at->diffInSeconds(now()) < 600) {
                            $remSec = max(0, 600 - (int) $record->locked_at->diffInSeconds(now()));
                            $m = floor($remSec / 60);
                            $s = $remSec % 60;
                            return "🔒 Checkout ({$m}m {$s}s)";
                        }
                        return "🟢 Siap Pesan";
                    }),

                TextColumn::make('locked_at')
                    ->label('Waktu Checkout')
                    ->since()
                    ->placeholder('-'),

                TextColumn::make('cartItems')
                    ->label('Item di Meja')
                    ->formatStateUsing(fn (TableSession $record) => $record->cartItems->sum('quantity') . ' item'),
            ])
            ->actions([
                Action::make('forceUnlock')
                    ->label('Buka Kunci')
                    ->icon('heroicon-m-lock-open')
                    ->color('warning')
                    ->button()
                    ->size('xs')
                    ->visible(fn (TableSession $record): bool => (bool) $record->is_locked)
                    ->requiresConfirmation()
                    ->modalHeading(fn (TableSession $record) => "Konfirmasi Buka Paksa Kunci Meja {$record->table->number}")
                    ->modalDescription('PERINGATAN: Tindakan ini akan melepaskan kunci checkout di meja ini. Gunakan hanya jika HP pelanggan mati, hilang, atau terjadi kendala di meja. Aksi ini akan dicatat ke audit log.')
                    ->modalSubmitActionLabel('Ya, Buka Kunci Meja')
                    ->action(function (TableSession $record) {
                        $user = auth()->user();
                        $staffName = $user ? $user->name : 'Staff/Kasir';
                        $staffEmail = $user ? $user->email : 'unknown';
                        $previousDevice = $record->locked_by_device;
                        $lockedAt = $record->locked_at ? $record->locked_at->toIso8601String() : null;

                        // Perform Unlock with force timestamp
                        $record->forceUnlockCart();

                        // 1. System Audit Log via Laravel Log
                        Log::warning('[AUDIT_TRAIL] Table lock was FORCE UNLOCKED by staff', [
                            'action'           => 'table_force_unlock',
                            'table_id'         => $record->table_id,
                            'table_number'     => $record->table ? $record->table->number : null,
                            'table_session_id' => $record->id,
                            'session_code'     => $record->session_code,
                            'staff_id'         => auth()->id(),
                            'staff_name'       => $staffName,
                            'staff_email'      => $staffEmail,
                            'locked_by_device' => $previousDevice,
                            'locked_at'        => $lockedAt,
                            'unlocked_at'      => now()->toIso8601String(),
                            'ip_address'       => request()->ip(),
                        ]);

                        // 2. Activity Log via Spatie Activitylog (if available)
                        if (function_exists('activity')) {
                            activity('table_management')
                                ->performedOn($record)
                                ->causedBy($user)
                                ->withProperties([
                                    'table_number'     => $record->table ? $record->table->number : null,
                                    'locked_by_device' => $previousDevice,
                                    'locked_at'        => $lockedAt,
                                ])
                                ->log("Kasir {$staffName} membuka paksa kunci checkout Meja {$record->table->number}");
                        }

                        Notification::make()
                            ->title('Kunci Meja Dibuka')
                            ->body("Kunci checkout Meja {$record->table->number} berhasil dibuka paksa dan dicatat di audit log.")
                            ->success()
                            ->send();
                    }),

                Action::make('closeTable')
                    ->label('Tutup Meja')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(fn (TableSession $record) => "Tutup Meja {$record->table->number}?")
                    ->modalDescription('Aksi ini akan menutup sesi meja saat ini, menghapus keranjang yang belum dicheckout, dan mengosongkan meja untuk tamu berikutnya.')
                    ->modalSubmitActionLabel('Ya, Tutup Meja')
                    ->action(function (TableSession $record) {
                        $user = auth()->user();
                        $staffName = $user ? $user->name : 'Staff/Kasir';

                        // Delete remaining cart items
                        \App\Models\CartItem::where('table_session_id', $record->id)->delete();

                        // Close session
                        $record->update([
                            'status' => 'closed',
                            'closed_at' => now(),
                            'is_locked' => false,
                            'locked_by_device' => null,
                            'locked_at' => null,
                        ]);

                        if ($record->table) {
                            $record->table->update(['status' => 'vacant']);
                        }

                        Log::info('[TABLE_MANAGEMENT] Table session closed manually by staff', [
                            'table_id' => $record->table_id,
                            'table_number' => $record->table ? $record->table->number : null,
                            'staff_name' => $staffName,
                        ]);

                        Notification::make()
                            ->title('Meja Ditutup')
                            ->body("Sesi Meja {$record->table->number} berhasil ditutup.")
                            ->success()
                            ->send();
                    }),
            ]);
    }
}

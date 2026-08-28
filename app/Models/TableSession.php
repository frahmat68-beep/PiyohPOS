<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'table_id',
        'session_code',
        'status',
        'is_locked',
        'locked_by_device',
        'locked_at',
        'opened_at',
        'closed_at',
        'expires_at',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function cartItems(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * Check if the cart is currently locked by a different device.
     * Auto-expires lock after 10 minutes to prevent permanent stall.
     */
    public function isLockedForDevice(?string $deviceId): bool
    {
        if (! $this->is_locked) {
            return false;
        }

        // If lock has been held for > 10 minutes, treat as expired/unlocked
        if ($this->locked_at && $this->locked_at->diffInMinutes(now()) >= 10) {
            $this->unlockCart();
            return false;
        }

        if (empty($this->locked_by_device)) {
            return false;
        }

        return $this->locked_by_device !== $deviceId;
    }

    public function lockCart(string $deviceId): void
    {
        $this->update([
            'is_locked' => true,
            'locked_by_device' => $deviceId,
            'locked_at' => now(),
        ]);
    }

    public function unlockCart(): void
    {
        $this->update([
            'is_locked' => false,
            'locked_by_device' => null,
            'locked_at' => null,
        ]);
    }
}

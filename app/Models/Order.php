<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    // Order Status Workflow
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PREPARING = 'preparing';
    const STATUS_READY = 'ready';
    const STATUS_SERVED = 'served';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'outlet_id',
        'table_id',
        'order_number',
        'customer_name',
        'status',
        'payment_status',
        'payment_method',
        'tax_amount',
        'service_charge',
        'total_amount',
        'accurate_sync_status',
        'confirmed_at',
        'preparing_at',
        'ready_at',
        'served_at',
        'completed_at',
        'cancelled_at',
    ];

    protected $casts = [
        'tax_amount' => 'decimal:2',
        'service_charge' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'confirmed_at' => 'datetime',
        'preparing_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(Table::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(OrderTimeline::class)->orderBy('created_at', 'asc');
    }

    /**
     * Strict Order Status Pipeline transition.
     */
    public function transitionTo(string $newStatus, ?string $notes = null, ?int $userId = null): void
    {
        $currentStatus = $this->status;

        if ($currentStatus === $newStatus) {
            return;
        }

        // Terminal states cannot be changed
        if (in_array($currentStatus, [self::STATUS_COMPLETED, self::STATUS_CANCELLED])) {
            throw new \Exception("Cannot transition order from terminal status {$currentStatus}.");
        }

        // Allowed status progression
        $allowed = false;
        if ($newStatus === self::STATUS_CANCELLED) {
            // Cancelled can only be set from pending or confirmed
            if (in_array($currentStatus, [self::STATUS_PENDING, self::STATUS_CONFIRMED])) {
                $allowed = true;
            }
        } else {
            // Strict sequence mapping
            $sequence = [
                self::STATUS_PENDING => 0,
                self::STATUS_CONFIRMED => 1,
                self::STATUS_PREPARING => 2,
                self::STATUS_READY => 3,
                self::STATUS_SERVED => 4,
                self::STATUS_COMPLETED => 5,
            ];

            if (isset($sequence[$currentStatus]) && isset($sequence[$newStatus])) {
                // Must be exactly next step (+1)
                if ($sequence[$newStatus] === $sequence[$currentStatus] + 1) {
                    $allowed = true;
                }
            }
        }

        if (!$allowed) {
            throw new \Exception("Invalid order status transition from {$currentStatus} to {$newStatus}.");
        }

        // Apply transition
        $this->status = $newStatus;
        $timestampField = "{$newStatus}_at";
        $this->$timestampField = now();
        $this->save();

        // Create timeline entry
        $this->timelines()->create([
            'status' => $newStatus,
            'notes' => $notes ?: "Order status updated to {$newStatus}.",
            'created_by' => $userId ?: (auth()->check() ? auth()->id() : null),
            'created_at' => now(),
        ]);
    }
}

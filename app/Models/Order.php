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
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_PENDING = 'pending';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_PREPARING = 'preparing';
    const STATUS_READY = 'ready';
    const STATUS_SERVED = 'served';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Ephemeral list of items removed during checkout stock validation.
     */
    public array $removed_items = [];

    protected $fillable = [
        'outlet_id',
        'table_id',
        'order_number',
        'tracking_token',
        'customer_name',
        'status',
        'payment_status',
        'payment_method',
        'midtrans_transaction_id',
        'midtrans_payment_type',
        'midtrans_snap_token',
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
        'delivered_at',
        'delivered_by_user_id',
        'paid_after_force_unlock_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            if (empty($order->tracking_token)) {
                $order->tracking_token = \Illuminate\Support\Str::random(32);
            }
        });
    }

    public function getTrackingUrl(): string
    {
        return route('qr.order.tracking', [
            'orderNumber' => $this->order_number,
            'trackingToken' => $this->tracking_token,
        ]);
    }

    /**
     * Check if Midtrans Snap Token has expired (Midtrans default lifespan: 24 hours).
     */
    public function isSnapTokenExpired(): bool
    {
        if (! $this->midtrans_snap_token || ! $this->created_at) {
            return false;
        }

        return $this->created_at->diffInHours(now()) >= 24;
    }

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
        'delivered_at' => 'datetime',
        'paid_after_force_unlock_at' => 'datetime',
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

    public function deliveredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivered_by_user_id');
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
            // Cancelled can be set from pending_payment, pending, or confirmed
            if (in_array($currentStatus, [self::STATUS_PENDING_PAYMENT, self::STATUS_PENDING, self::STATUS_CONFIRMED])) {
                $allowed = true;
            }
        } elseif ($currentStatus === self::STATUS_PENDING_PAYMENT && in_array($newStatus, [self::STATUS_CONFIRMED, self::STATUS_PENDING])) {
            $allowed = true;
        } else {
            // Strict sequence mapping
            $sequence = [
                self::STATUS_PENDING_PAYMENT => -1,
                self::STATUS_PENDING => 0,
                self::STATUS_CONFIRMED => 1,
                self::STATUS_PREPARING => 2,
                self::STATUS_READY => 3,
                self::STATUS_SERVED => 4,
                self::STATUS_COMPLETED => 5,
            ];

            if (isset($sequence[$currentStatus]) && isset($sequence[$newStatus])) {
                // Must be next step (+1) or skip pending to confirmed when paid directly
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

        // Auto-close active TableSession when order reaches completed or served status
        if (in_array($newStatus, [self::STATUS_COMPLETED, self::STATUS_SERVED]) && $this->table_id) {
            $activeSession = TableSession::where('table_id', $this->table_id)
                ->where('status', 'open')
                ->first();

            if ($activeSession) {
                // If there are no other pending/active orders on this table session
                $otherActiveOrders = Order::where('table_id', $this->table_id)
                    ->where('id', '!=', $this->id)
                    ->whereIn('status', [
                        self::STATUS_PENDING_PAYMENT,
                        self::STATUS_PENDING,
                        self::STATUS_CONFIRMED,
                        self::STATUS_PREPARING,
                        self::STATUS_READY,
                    ])
                    ->exists();

                if (!$otherActiveOrders && $newStatus === self::STATUS_COMPLETED) {
                    $activeSession->update([
                        'status' => 'closed',
                        'closed_at' => now(),
                        'is_locked' => false,
                        'locked_by_device' => null,
                        'locked_at' => null,
                    ]);
                }
            }
        }
    }
}

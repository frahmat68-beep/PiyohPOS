<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyRecap extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'recap_date',
        'total_orders',
        'total_revenue',
        'cash_revenue',
        'midtrans_revenue',
        'qris_revenue',
        'other_revenue',
        'tax_total',
        'service_charge_total',
        'cancelled_orders_count',
        'payment_method_breakdown',
        'items_summary',
        'is_closed',
        'closed_at',
        'closed_by_user_id',
    ];

    protected $casts = [
        'recap_date' => 'date',
        'total_orders' => 'integer',
        'total_revenue' => 'decimal:2',
        'cash_revenue' => 'decimal:2',
        'midtrans_revenue' => 'decimal:2',
        'qris_revenue' => 'decimal:2',
        'other_revenue' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'service_charge_total' => 'decimal:2',
        'cancelled_orders_count' => 'integer',
        'payment_method_breakdown' => 'array',
        'items_summary' => 'array',
        'is_closed' => 'boolean',
        'closed_at' => 'datetime',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }
}

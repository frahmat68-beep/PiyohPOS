<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_id',
        'source_system',
        'category_id',
        'name',
        'slug',
        'description',
        'base_price',
        'stock_quantity',
        'low_stock_threshold',
        'sku',
        'is_active',
        'last_synced_at',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'stock_quantity' => 'integer',
        'low_stock_threshold' => 'integer',
        'is_active' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function productPrices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isOutOfStock(): bool
    {
        return $this->stock_quantity !== null && $this->stock_quantity <= 0;
    }

    public function isLowStock(): bool
    {
        return $this->stock_quantity !== null && $this->stock_quantity > 0 && $this->stock_quantity <= $this->low_stock_threshold;
    }

    public function isAvailableForOrdering(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isOutOfStock()) {
            return false;
        }

        return true;
    }
}

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
        'image_url',
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

    public function getEffectiveImageUrl(): ?string
    {
        if (! empty($this->image_url)) {
            return $this->image_url;
        }

        $categorySlug = $this->category?->slug;

        return match ($categorySlug) {
            'hot-coffee', 'iced-coffee' => 'https://images.unsplash.com/photo-1541167760496-1628856ab772?q=80&w=600',
            'non-coffee' => 'https://images.unsplash.com/photo-1536256263959-770b48d82b0a?q=80&w=600',
            'signature-drink' => 'https://images.unsplash.com/photo-1517701604599-bb29b565090c?q=80&w=600',
            'manual-brew' => 'https://images.unsplash.com/photo-1495474472287-4d71bcdd2085?q=80&w=600',
            'artisan-tea', 'iced-tea' => 'https://images.unsplash.com/photo-1576092768241-dec231879fc3?q=80&w=600',
            'blended' => 'https://images.unsplash.com/photo-1572490122747-3968b75cc699?q=80&w=600',
            'baristas-present' => 'https://images.unsplash.com/photo-1513558161293-cdaf765ed2fd?q=80&w=600',
            'choco-series' => 'https://images.unsplash.com/photo-1542990253-0d0f5be5f0ed?q=80&w=600',
            default => 'https://images.unsplash.com/photo-1507133750040-4a8f57021571?q=80&w=600',
        };
    }
}

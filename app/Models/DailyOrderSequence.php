<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyOrderSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'outlet_id',
        'order_date',
        'last_sequence',
    ];

    protected $casts = [
        'order_date'    => 'date',
        'last_sequence' => 'integer',
    ];

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}

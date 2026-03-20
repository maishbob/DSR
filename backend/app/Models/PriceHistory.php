<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceHistory extends Model
{
    protected $fillable = ['product_id', 'price_per_litre', 'effective_from', 'effective_to', 'created_by'];

    protected $casts = [
        'price_per_litre' => 'decimal:4',
        'effective_from'  => 'date',
        'effective_to'    => 'date',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

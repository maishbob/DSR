<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockTransaction extends Model
{
    use BelongsToStation;
    protected $fillable = [
        'shop_product_id',
        'station_id',
        'type',
        'trans_date',
        'quantity',
        'document_ref',
        'notes',
        'entered_by',
    ];

    protected $casts = [
        'trans_date' => 'date',
        'quantity'   => 'decimal:3',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function shopProduct()
    {
        return $this->belongsTo(ShopProduct::class);
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}

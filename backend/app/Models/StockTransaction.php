<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockTransaction extends Model
{
    protected $fillable = [
        'shop_product_id', 'station_id', 'type',
        'trans_date', 'quantity', 'document_ref', 'notes', 'entered_by',
    ];

    protected $casts = [
        'trans_date' => 'date',
        'quantity'   => 'decimal:3',
    ];

    public function shopProduct()
    {
        return $this->belongsTo(ShopProduct::class);
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}

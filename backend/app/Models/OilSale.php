<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OilSale extends Model
{
    protected $fillable = [
        'shift_id', 'shop_product_id', 'opening_stock', 'quantity', 'unit_price', 'total_value', 'entered_by',
    ];

    protected $casts = [
        'opening_stock' => 'decimal:3',
        'quantity'      => 'decimal:3',
        'unit_price'    => 'decimal:2',
        'total_value'   => 'decimal:2',
    ];

    protected $appends = ['closing_stock'];

    public function getClosingStockAttribute(): float
    {
        return round((float) $this->opening_stock - (float) $this->quantity, 3);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
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

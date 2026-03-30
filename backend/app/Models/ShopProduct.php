<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStation;
use Illuminate\Database\Eloquent\Model;

class ShopProduct extends Model
{
    use BelongsToStation;
    protected $fillable = [
        'station_id',
        'product_name',
        'unit',
        'current_price',
        'cost',
        'forecourt_stock',
        'store_stock',
        'is_active',
    ];

    protected $casts = [
        'current_price'   => 'decimal:2',
        'cost'            => 'decimal:2',
        'forecourt_stock' => 'decimal:3',
        'store_stock'     => 'decimal:3',
        'is_active'       => 'boolean',
    ];

    protected $appends = ['current_stock'];

    public function getCurrentStockAttribute(): float
    {
        return round((float) $this->forecourt_stock + (float) $this->store_stock, 3);
    }

    public function station()
    {
        return $this->belongsTo(Station::class);
    }

    public function oilSales()
    {
        return $this->hasMany(OilSale::class);
    }

    public function stockTransactions()
    {
        return $this->hasMany(StockTransaction::class)->orderByDesc('trans_date')->orderByDesc('id');
    }
}

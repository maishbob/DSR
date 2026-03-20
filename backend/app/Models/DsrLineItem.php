<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DsrLineItem extends Model
{
    protected $fillable = [
        'daily_sales_record_id', 'product_id', 'tank_id',
        'opening_meter', 'closing_meter', 'litres_sold',
        'price_per_litre', 'revenue',
        'opening_stock', 'deliveries', 'expected_stock',
        'actual_stock', 'variance',
        'credit_sales_litres', 'credit_sales_value',
    ];

    protected $casts = [
        'opening_meter'       => 'decimal:3',
        'closing_meter'       => 'decimal:3',
        'litres_sold'         => 'decimal:3',
        'price_per_litre'     => 'decimal:4',
        'revenue'             => 'decimal:2',
        'opening_stock'       => 'decimal:3',
        'deliveries'          => 'decimal:3',
        'expected_stock'      => 'decimal:3',
        'actual_stock'        => 'decimal:3',
        'variance'            => 'decimal:3',
        'credit_sales_litres' => 'decimal:3',
        'credit_sales_value'  => 'decimal:2',
    ];

    public function dailySalesRecord(): BelongsTo
    {
        return $this->belongsTo(DailySalesRecord::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }
}

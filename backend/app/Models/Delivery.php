<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Delivery extends Model
{
    use BelongsToStation;
    protected $fillable = [
        'station_id',
        'product_id',
        'tank_id',
        'shift_id',
        'delivery_date',
        'supplier_name',
        'waybill_number',
        'delivery_quantity',
        'tank_dip_before',
        'tank_dip_after',
        'delivery_variance',
        'notes',
        'entered_by',
    ];

    protected $casts = [
        'delivery_date'      => 'date',
        'delivery_quantity'  => 'decimal:2',
        'tank_dip_before'    => 'decimal:2',
        'tank_dip_after'     => 'decimal:2',
        'delivery_variance'  => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::saving(function (Delivery $delivery) {
            if ($delivery->tank_dip_before !== null && $delivery->tank_dip_after !== null) {
                $received = (float)$delivery->tank_dip_after - (float)$delivery->tank_dip_before;
                $delivery->delivery_variance = round($received - (float)$delivery->delivery_quantity, 2);
            }
        });
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tank(): BelongsTo
    {
        return $this->belongsTo(Tank::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}

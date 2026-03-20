<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tank extends Model
{
    protected $fillable = [
        'station_id', 'product_id', 'tank_name', 'tank_capacity', 'linked_tank_id', 'is_active',
        'is_complex', 'last_closing_stock', 'last_dip_stock', 'last_dip_2',
    ];

    protected $casts = [
        'tank_capacity'      => 'decimal:2',
        'is_active'          => 'boolean',
        'is_complex'         => 'boolean',
        'last_closing_stock' => 'decimal:2',
        'last_dip_stock'     => 'decimal:2',
        'last_dip_2'         => 'decimal:2',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function tankDips(): HasMany
    {
        return $this->hasMany(TankDip::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    /**
     * The secondary tank physically linked/manifolded to this one.
     */
    public function linkedTank(): BelongsTo
    {
        return $this->belongsTo(Tank::class, 'linked_tank_id');
    }

    public function nozzles(): HasMany
    {
        return $this->hasMany(PumpNozzle::class);
    }

    public function latestDip(): ?TankDip
    {
        return $this->tankDips()->latest()->first();
    }
}

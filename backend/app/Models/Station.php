<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends Model
{
    protected $fillable = ['owner_id', 'station_name', 'location', 'timezone', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Owner::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function tanks(): HasMany
    {
        return $this->hasMany(Tank::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(Shift::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function creditCustomers(): HasMany
    {
        return $this->hasMany(CreditCustomer::class);
    }

    public function pumpNozzles(): HasMany
    {
        return $this->hasMany(PumpNozzle::class);
    }

    public function shopProducts(): HasMany
    {
        return $this->hasMany(ShopProduct::class);
    }

    public function dailySalesRecords(): HasMany
    {
        return $this->hasMany(DailySalesRecord::class);
    }
}

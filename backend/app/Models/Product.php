<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToStation;
    protected $fillable = ['station_id', 'product_name', 'unit', 'cost_per_litre', 'is_active'];

    protected $casts = [
        'is_active'      => 'boolean',
        'cost_per_litre' => 'decimal:4',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function priceHistories(): HasMany
    {
        return $this->hasMany(PriceHistory::class);
    }

    public function currentPrice(): ?PriceHistory
    {
        return $this->priceHistories()
            ->whereNull('effective_to')
            ->orderByDesc('effective_from')
            ->first();
    }

    public function priceOn(string $date): ?PriceHistory
    {
        return $this->priceHistories()
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    public function tanks(): HasMany
    {
        return $this->hasMany(Tank::class);
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }
}

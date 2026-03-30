<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CreditCustomer extends Model
{
    use BelongsToStation;
    protected $fillable = [
        'station_id',
        'customer_name',
        'contact',
        'phone',
        'email',
        'address',
        'city',
        'pin',
        'vat_number',
        'is_withholding_vat_agent',
        'credit_limit',
        'discount_multiplier',
        'initial_opening_balance',
        'is_active',
    ];

    protected $casts = [
        'credit_limit'             => 'decimal:2',
        'discount_multiplier'      => 'decimal:4',
        'initial_opening_balance'  => 'decimal:2',
        'is_withholding_vat_agent' => 'boolean',
        'is_active'                => 'boolean',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function creditSales(): HasMany
    {
        return $this->hasMany(CreditSale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function getTotalPurchasesAttribute(): float
    {
        return (float)$this->creditSales()->sum('total_value');
    }

    public function getTotalPaidAttribute(): float
    {
        return (float)$this->payments()->sum('amount');
    }

    public function getBalanceAttribute(): float
    {
        return round((float)$this->initial_opening_balance + $this->total_purchases - $this->total_paid, 2);
    }
}

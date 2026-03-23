<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Shift extends Model
{
    protected $fillable = [
        'station_id', 'shift_date', 'shift_type',
        'opened_at', 'closed_at', 'status',
        'opened_by', 'closed_by',
        'actual_cash', 'mpesa_amount', 'cash_variance_status',
    ];

    protected $casts = [
        'shift_date'  => 'date',
        'opened_at'   => 'datetime',
        'closed_at'   => 'datetime',
        'actual_cash' => 'decimal:2',
        'mpesa_amount'=> 'decimal:2',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function meterReadings(): HasMany
    {
        return $this->hasMany(MeterReading::class);
    }

    public function tankDips(): HasMany
    {
        return $this->hasMany(TankDip::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class);
    }

    public function creditSales(): HasMany
    {
        return $this->hasMany(CreditSale::class);
    }

    public function oilSales(): HasMany
    {
        return $this->hasMany(OilSale::class);
    }

    public function cardPayments(): HasMany
    {
        return $this->hasMany(CardPayment::class);
    }

    public function posTransactions(): HasMany
    {
        return $this->hasMany(PosTransaction::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function dailySalesRecord(): HasOne
    {
        return $this->hasOne(DailySalesRecord::class);
    }

    public function isLocked(): bool
    {
        return $this->status === 'locked';
    }
}

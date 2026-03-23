<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailySalesRecord extends Model
{
    protected $fillable = [
        'shift_id', 'station_id', 'shift_date', 'shift_type',
        'serial_number', 'dsr_covers_days',
        'prepared_by', 'verified_by',
        // Fuel stock
        'total_litres_sold', 'total_revenue', 'total_deliveries',
        'expected_stock', 'actual_stock', 'variance',
        // Payment channels
        'total_credit_sales', 'total_card_sales', 'total_pos_sales',
        'cash_collected', 'mpesa_collected',
        // Other
        'total_oil_sales', 'total_expenses',
        'z_amount_a', 'z_amount_b', 'z_amount_d',
        // Summary
        'total_fuel_sales', 'gross_sales', 'net_sales_balance',
        // Variance + override
        'variance_status', 'override_reason', 'override_by', 'override_at',
        // Meta
        'product_breakdown', 'generated_at', 'approved_at', 'approved_by', 'locked',
    ];

    protected $casts = [
        'shift_date'         => 'date',
        'total_litres_sold'  => 'decimal:3',
        'total_revenue'      => 'decimal:2',
        'total_fuel_sales'   => 'decimal:2',
        'total_credit_sales' => 'decimal:2',
        'total_card_sales'   => 'decimal:2',
        'total_pos_sales'    => 'decimal:2',
        'cash_collected'     => 'decimal:2',
        'mpesa_collected'    => 'decimal:2',
        'total_oil_sales'    => 'decimal:2',
        'total_expenses'     => 'decimal:2',
        'z_amount_a'         => 'decimal:2',
        'z_amount_b'         => 'decimal:2',
        'z_amount_d'         => 'decimal:2',
        'gross_sales'        => 'decimal:2',
        'net_sales_balance'  => 'decimal:2',
        'total_deliveries'   => 'decimal:3',
        'expected_stock'     => 'decimal:3',
        'actual_stock'       => 'decimal:3',
        'variance'           => 'decimal:3',
        'product_breakdown'  => 'array',
        'generated_at'       => 'datetime',
        'approved_at'        => 'datetime',
        'override_at'        => 'datetime',
        'locked'             => 'boolean',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(DsrLineItem::class);
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(Adjustment::class);
    }
}

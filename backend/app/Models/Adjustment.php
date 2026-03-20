<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Adjustment extends Model
{
    protected $fillable = [
        'daily_sales_record_id', 'station_id', 'adjustment_type',
        'reason', 'original_value', 'corrected_value', 'metadata', 'created_by',
    ];

    protected $casts = [
        'original_value'  => 'decimal:4',
        'corrected_value' => 'decimal:4',
        'metadata'        => 'array',
    ];

    public function dailySalesRecord(): BelongsTo
    {
        return $this->belongsTo(DailySalesRecord::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

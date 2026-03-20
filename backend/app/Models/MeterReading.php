<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterReading extends Model
{
    protected $fillable = [
        'shift_id', 'nozzle_id',
        'opening_mechanical', 'closing_mechanical',
        'opening_electrical', 'closing_electrical',
        'opening_shs',        'closing_shs',
        'entered_by',
    ];

    protected $casts = [
        'opening_mechanical' => 'decimal:1',
        'closing_mechanical' => 'decimal:1',
        'opening_electrical' => 'decimal:3',
        'closing_electrical' => 'decimal:3',
        'opening_shs'        => 'decimal:2',
        'closing_shs'        => 'decimal:2',
        'shs_sold'           => 'decimal:2',
    ];

    protected $appends = ['litres_sold', 'mechanical_sales', 'shs_sold'];

    /**
     * Litres sold — calculated from the electrical (more accurate) meter.
     */
    public function getLitresSoldAttribute(): ?float
    {
        if ($this->closing_electrical === null) return null;
        return round((float) $this->closing_electrical - (float) $this->opening_electrical, 3);
    }

    /**
     * Mechanical sales — rounded integer difference for cross-check.
     */
    public function getMechanicalSalesAttribute(): ?float
    {
        if ($this->closing_mechanical === null) return null;
        return round((float) $this->closing_mechanical - (float) $this->opening_mechanical, 1);
    }

    /**
     * Shs sold — difference of the revenue odometer on the pump.
     * Independent cross-check against (electronic litres × price).
     */
    public function getShsSoldAttribute(): ?float
    {
        if ($this->closing_shs === null) return null;
        return round((float) $this->closing_shs - (float) $this->opening_shs, 2);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function nozzle(): BelongsTo
    {
        return $this->belongsTo(PumpNozzle::class, 'nozzle_id');
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}

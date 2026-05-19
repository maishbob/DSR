<?php

namespace App\Models;

use App\Models\Concerns\BelongsToStation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosTransaction extends Model
{
    use BelongsToStation;
    protected $fillable = [
        'shift_id',
        'station_id',
        'trans_date',
        'reference',
        'amount',
        'entered_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}

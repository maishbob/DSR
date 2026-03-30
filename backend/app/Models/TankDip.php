<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TankDip extends Model
{
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $stationId = auth()->user()?->station_id;
        if (! $stationId) {
            abort(403);
        }

        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->whereHas('shift', fn($q) => $q->where('station_id', $stationId))
            ->firstOrFail();
    }
    protected $fillable = ['tank_id', 'shift_id', 'dip_type', 'dip_volume', 'entered_by', 'is_locked'];

    protected $casts = ['dip_volume' => 'decimal:2'];

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

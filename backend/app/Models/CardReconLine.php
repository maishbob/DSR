<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardReconLine extends Model
{
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $stationId = auth()->user()?->station_id;
        if (! $stationId) {
            abort(403);
        }

        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->whereHas('cardRecon', fn($q) => $q->where('station_id', $stationId))
            ->firstOrFail();
    }
    protected $fillable = [
        'card_recon_id',
        'trans_date',
        'ref',
        'amount',
    ];

    protected $casts = [
        'trans_date' => 'date',
        'amount'     => 'decimal:2',
    ];

    public function cardRecon(): BelongsTo
    {
        return $this->belongsTo(CardRecon::class);
    }
}

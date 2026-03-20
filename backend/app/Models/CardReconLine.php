<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardReconLine extends Model
{
    protected $fillable = [
        'card_recon_id', 'trans_date', 'ref', 'amount',
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

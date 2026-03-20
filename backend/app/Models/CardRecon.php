<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CardRecon extends Model
{
    protected $fillable = [
        'station_id', 'card_name', 'batch_ref', 'recon_date', 'created_by',
    ];

    protected $casts = [
        'recon_date' => 'date',
    ];

    protected $appends = ['total_amount'];

    public function lines(): HasMany
    {
        return $this->hasMany(CardReconLine::class)->orderBy('trans_date')->orderBy('id');
    }

    public function getTotalAmountAttribute(): float
    {
        return (float) $this->lines()->sum('amount');
    }
}

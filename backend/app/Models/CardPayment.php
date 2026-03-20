<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CardPayment extends Model
{
    protected $fillable = [
        'shift_id', 'card_name', 'trans_date', 'reference', 'amount',
        'recon_date', 'batch_ref', 'entered_by',
    ];

    protected $casts = [
        'trans_date'  => 'date',
        'recon_date'  => 'date',
        'amount'      => 'decimal:2',
    ];

    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    public function enteredBy()
    {
        return $this->belongsTo(User::class, 'entered_by');
    }
}

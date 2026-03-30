<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditSale extends Model
{
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $stationId = auth()->user()?->station_id;
        if (! $stationId) {
            abort(403);
        }

        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->whereHas('creditCustomer', fn($q) => $q->where('station_id', $stationId))
            ->firstOrFail();
    }
    protected $fillable = [
        'credit_customer_id',
        'product_id',
        'shift_id',
        'debit_note',
        'type',
        'quantity',
        'price_applied',
        'total_value',
        'vat_amount',
        'wht_amount',
        'vehicle_plate',
        'notes',
        'entered_by',
        'is_locked',
    ];

    protected $casts = [
        'quantity'      => 'decimal:3',
        'price_applied' => 'decimal:4',
        'total_value'   => 'decimal:2',
        'vat_amount'    => 'decimal:2',
        'wht_amount'    => 'decimal:2',
    ];

    // Kenya VAT rate on fuel
    const VAT_RATE = 0.16;
    // Kenya Withholding Tax on petroleum
    const WHT_RATE = 0.0172;

    protected static function booted(): void
    {
        static::saving(function (CreditSale $sale) {
            $sale->total_value = round((float) $sale->quantity * (float) $sale->price_applied, 2);
            $net = $sale->total_value / (1 + self::VAT_RATE);
            $sale->vat_amount = round($sale->total_value - $net, 2);
            $sale->wht_amount = round($sale->total_value * self::WHT_RATE, 2);

            // Auto-generate debit note if not set
            if (empty($sale->debit_note)) {
                $sale->debit_note = self::generateDebitNote();
            }
        });
    }

    private static function generateDebitNote(): string
    {
        $last = static::max('id') ?? 0;
        $seq  = str_pad($last + 1, 6, '0', STR_PAD_LEFT);
        return date('ymd') . '-' . $seq;
    }

    public function creditCustomer(): BelongsTo
    {
        return $this->belongsTo(CreditCustomer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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

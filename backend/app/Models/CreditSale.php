<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditSale extends Model
{
    public function resolveRouteBinding($value, $field = null): ?Model
    {
        $stationId = auth()->user()?->effectiveStationId();
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
        'is_locked'     => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (CreditSale $sale) {
            $service = app(\App\Services\CreditSaleService::class);

            // Compute total value
            $sale->total_value = round((float) $sale->quantity * (float) $sale->price_applied, 2);

            // Get station's configured tax rates (defaults to Kenya standard rates)
            $station = $sale->creditCustomer->station;
            $vatRate = $station?->vat_rate ?? 0.16;
            $whtRate = $station?->wht_rate ?? 0.0172;

            // Calculate taxes using service (inclusive VAT calculation)
            $net = $sale->total_value / (1 + $vatRate);
            $sale->vat_amount = round($sale->total_value - $net, 2);
            $sale->wht_amount = round($sale->total_value * $whtRate, 2);

            // Auto-generate debit note atomically if not set
            if (empty($sale->debit_note)) {
                $sale->debit_note = $service->generateDebitNoteNumber();
            }
        });
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

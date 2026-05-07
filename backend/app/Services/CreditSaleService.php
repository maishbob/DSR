<?php

namespace App\Services;

use App\Models\CreditSale;
use Illuminate\Support\Facades\DB;

class CreditSaleService
{
    /**
     * Calculate VAT and WHT based on configured station rates.
     * Rates default to Kenya standard rates but can vary by station.
     */
    public function calculateTaxes(float $totalValue, float $vatRate = 0.16, float $whtRate = 0.0172): array
    {
        $vatAmount = round($totalValue * $vatRate, 2);
        $whtAmount = round($totalValue * $whtRate, 2);

        return [
            'vat_amount' => $vatAmount,
            'wht_amount' => $whtAmount,
        ];
    }

    public function generateDebitNoteNumber(): string
    {
        $date = now()->format('Ymd');
        $nextSeq = DB::transaction(function () {
            $lastNote = CreditSale::whereDate('created_at', today())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $seq = $lastNote ? (int) substr($lastNote->debit_note, -6) + 1 : 1;
            return str_pad($seq, 6, '0', STR_PAD_LEFT);
        });

        return "{$date}-{$nextSeq}";
    }
}

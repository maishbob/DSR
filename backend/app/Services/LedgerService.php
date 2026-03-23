<?php

namespace App\Services;

use App\Models\CreditSale;
use App\Models\Delivery;
use App\Models\Expense;
use App\Models\FinancialTransaction;
use App\Models\MeterReading;
use App\Models\OilSale;
use App\Models\Payment;
use App\Models\PosTransaction;
use Illuminate\Support\Facades\Auth;

/**
 * LedgerService
 *
 * Single point of entry for writing to the financial_transactions ledger.
 * All methods are idempotent: they check for an existing entry by
 * (reference_type, reference_id) and update rather than duplicate.
 *
 * The ledger is append-only in production. Corrections go through
 * DsrController::storeAdjustment, not through editing existing rows.
 */
class LedgerService
{
    /**
     * Record a fuel sale from a meter reading.
     * Amount = litres_sold × price_per_litre (passed in from ReconciliationEngine).
     */
    public function recordFuelSale(MeterReading $reading, float $pricePerLitre): void
    {
        $litres = $reading->litres_sold ?? 0;
        if ($litres <= 0) return;

        $amount = round($litres * $pricePerLitre, 2);

        $this->upsert($reading, [
            'station_id'  => $reading->shift->station_id,
            'shift_id'    => $reading->shift_id,
            'trans_date'  => $reading->shift->shift_date->toDateString(),
            'type'        => 'fuel_sale',
            'description' => sprintf(
                'Fuel sale — %s — %.3fL @ %.2f',
                $reading->nozzle->nozzle_ref ?? 'Nozzle',
                $litres,
                $pricePerLitre,
            ),
            'amount'              => $amount,
            'direction'           => 'debit',   // revenue OUT of stock (debit stock, credit cash)
            'product_id'          => $reading->nozzle->product_id ?? null,
            'credit_customer_id'  => null,
        ]);
    }

    /**
     * Record a credit sale.
     */
    public function recordCreditSale(CreditSale $sale): void
    {
        $this->upsert($sale, [
            'station_id'         => $sale->shift->station_id,
            'shift_id'           => $sale->shift_id,
            'trans_date'         => $sale->shift->shift_date->toDateString(),
            'type'               => 'credit_sale',
            'description'        => sprintf(
                'Credit sale — %s — %.3fL',
                $sale->creditCustomer->customer_name ?? 'Customer',
                $sale->quantity,
            ),
            'amount'             => (float) $sale->total_value,
            'direction'          => 'debit',   // debit the customer's account
            'product_id'         => $sale->product_id,
            'credit_customer_id' => $sale->credit_customer_id,
        ]);
    }

    /**
     * Record a payment received from a credit customer.
     */
    public function recordPayment(Payment $payment): void
    {
        $this->upsert($payment, [
            'station_id'         => $payment->station_id,
            'shift_id'           => null,
            'trans_date'         => $payment->payment_date ?? now()->toDateString(),
            'type'               => 'payment',
            'description'        => sprintf(
                'Payment — %s — %s',
                $payment->creditCustomer->customer_name ?? 'Customer',
                $payment->receipt_no ?? $payment->reference ?? '',
            ),
            'amount'             => (float) $payment->amount,
            'direction'          => 'credit',  // credit the customer's account
            'product_id'         => null,
            'credit_customer_id' => $payment->credit_customer_id,
        ]);
    }

    /**
     * Record a fuel delivery.
     */
    public function recordDelivery(Delivery $delivery): void
    {
        $this->upsert($delivery, [
            'station_id'  => $delivery->station_id,
            'shift_id'    => $delivery->shift_id,
            'trans_date'  => $delivery->delivery_date->toDateString(),
            'type'        => 'delivery',
            'description' => sprintf(
                'Delivery — %s — %.3fL — %s',
                $delivery->product->product_name ?? 'Product',
                $delivery->delivery_quantity,
                $delivery->supplier_name ?? '',
            ),
            'amount'      => (float) $delivery->delivery_quantity,  // litres; no KES value recorded
            'direction'   => 'credit',   // stock IN
            'product_id'  => $delivery->product_id,
        ]);
    }

    /**
     * Record a shift expense.
     */
    public function recordExpense(Expense $expense): void
    {
        $this->upsert($expense, [
            'station_id'  => $expense->shift->station_id,
            'shift_id'    => $expense->shift_id,
            'trans_date'  => $expense->shift->shift_date->toDateString(),
            'type'        => 'expense',
            'description' => $expense->expense_item,
            'amount'      => (float) $expense->amount,
            'direction'   => 'credit',   // cash OUT (reduces cash balance)
            'product_id'  => null,
        ]);
    }

    /**
     * Record an oil/shop sale.
     */
    public function recordOilSale(OilSale $sale): void
    {
        $this->upsert($sale, [
            'station_id'  => $sale->shift->station_id,
            'shift_id'    => $sale->shift_id,
            'trans_date'  => $sale->shift->shift_date->toDateString(),
            'type'        => 'oil_sale',
            'description' => sprintf(
                'Oil sale — %s — qty %s',
                $sale->shopProduct->product_name ?? 'Product',
                $sale->quantity,
            ),
            'amount'      => (float) $sale->total_value,
            'direction'   => 'debit',
            'product_id'  => null,
        ]);
    }

    /**
     * Record a POS transaction.
     */
    public function recordPos(PosTransaction $pos): void
    {
        $this->upsert($pos, [
            'station_id'  => $pos->station_id,
            'shift_id'    => $pos->shift_id,
            'trans_date'  => $pos->trans_date ?? now()->toDateString(),
            'type'        => 'pos',
            'description' => 'POS — ' . ($pos->reference ?? ''),
            'amount'      => (float) $pos->amount,
            'direction'   => 'debit',
            'product_id'  => null,
        ]);
    }

    /**
     * Write an adjustment entry (never updates an existing entry).
     * Used by DsrController::storeAdjustment after a DSR is locked.
     */
    public function recordAdjustment(
        int $stationId,
        string $description,
        float $amount,
        string $direction,
        ?int $shiftId = null,
        ?string $date = null,
    ): FinancialTransaction {
        return FinancialTransaction::create([
            'station_id'   => $stationId,
            'shift_id'     => $shiftId,
            'trans_date'   => $date ?? now()->toDateString(),
            'type'         => 'adjustment',
            'description'  => $description,
            'amount'       => abs($amount),
            'direction'    => $direction,
            'created_by'   => Auth::id(),
        ]);
    }

    // -------------------------------------------------------------------------

    /**
     * Insert or update a ledger entry keyed by (reference_type, reference_id).
     * Ensures each source record has exactly one ledger line.
     */
    private function upsert(object $model, array $data): void
    {
        $refType = class_basename($model);
        $refId   = $model->getKey();

        FinancialTransaction::updateOrCreate(
            ['reference_type' => $refType, 'reference_id' => $refId],
            array_merge($data, ['created_by' => Auth::id()]),
        );
    }
}

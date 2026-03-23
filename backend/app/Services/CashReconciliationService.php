<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\OilSale;
use App\Models\Payment;
use App\Models\Shift;

/**
 * CashReconciliationService
 *
 * Answers: "How much cash should be in the drawer?" for any shift.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * CASH FLOW MODEL
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * INFLOWS
 *   fuel_cash_sales       = total_fuel_revenue − credit_sales − card_sales − pos_sales − mpesa_amount
 *   oil_cash_sales        = sum of oil_sales.total_value      [ASSUMPTION: all oil/shop sales are cash]
 *   cash_payments_received = payments WHERE payment_method='cash' AND payment_date=shift_date AND station_id=shift.station_id
 *
 * OUTFLOWS
 *   cash_expenses         = sum of expenses.amount            [ASSUMPTION: all expenses are cash]
 *
 * EXPECTED CASH
 *   = fuel_cash_sales + oil_cash_sales + cash_payments_received − cash_expenses
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * ASSUMPTIONS (explicit, auditable)
 * ─────────────────────────────────────────────────────────────────────────────
 *
 * 1. All oil/shop sales (OilSale) are assumed to be cash transactions.
 *    Rationale: oil_sales has no payment_method column. If credit oil sales
 *    are introduced, a payment_method column must be added and this method updated.
 *
 * 2. All expenses are assumed to be paid in cash.
 *    Rationale: expenses has no payment_method column.
 *
 * 3. MPESA (mobile money) is captured as a shift-level total (shifts.mpesa_amount),
 *    not per-transaction. It is a non-cash channel and reduces the expected fuel cash.
 *
 * 4. Cash payments from credit customers are matched by station_id + payment_date.
 *    Rationale: payments table has no shift_id, only payment_date. This means
 *    payments entered on the same calendar date as the shift are included.
 *    If a station runs two shifts on the same day, cash payments are attributed
 *    to whichever shift is reconciled first. This is a known limitation.
 *
 * 5. Fuel price used is the price active at the time of the shift date.
 *    If no price is set, fuel revenue is zero and a zero-price flag is returned.
 */
class CashReconciliationService
{
    public function __construct(
        private readonly ReconciliationEngine $engine,
    ) {}

    /**
     * Calculate the full cash reconciliation for a shift.
     *
     * @return array{
     *     fuel_revenue: float,
     *     credit_sales: float,
     *     card_sales: float,
     *     pos_sales: float,
     *     mpesa_amount: float,
     *     fuel_cash_sales: float,
     *     oil_cash_sales: float,
     *     cash_payments_received: float,
     *     cash_expenses: float,
     *     expected_cash: float,
     *     actual_cash: float|null,
     *     variance: float|null,
     *     variance_pct: float|null,
     *     variance_status: string,
     *     has_readings: bool,
     *     missing_prices: string[],
     * }
     */
    public function calculate(Shift $shift): array
    {
        $shift->loadMissing([
            'meterReadings.nozzle.product.priceHistories',
            'creditSales',
            'cardPayments',
            'posTransactions',
            'oilSales',
            'expenses',
        ]);

        $shiftDate   = $shift->shift_date->toDateString();
        $missingPrices = [];

        // ── Fuel revenue ────────────────────────────────────────────────────
        $fuelRevenue = 0.0;
        $hasReadings = false;

        foreach ($shift->meterReadings as $reading) {
            if ($reading->closing_electrical === null) continue;
            $hasReadings = true;

            $product = $reading->nozzle?->product;
            if (!$product) continue;

            $price = $this->engine->getPriceForDate($product, $shiftDate);
            if ($price == 0.0) {
                $missingPrices[] = $product->product_name;
            }

            $litres       = (float)($reading->litres_sold ?? 0);
            $fuelRevenue += round($litres * $price, 2);
        }

        // ── Non-cash deductions from fuel revenue ───────────────────────────
        $creditSales = round(
            (float)$shift->creditSales->sum('total_value'), 2
        );
        $cardSales = round(
            (float)$shift->cardPayments->sum('amount'), 2
        );
        $posSales = round(
            (float)$shift->posTransactions->sum('amount'), 2
        );
        $mpesaAmount = round(
            (float)($shift->mpesa_amount ?? 0), 2
        );

        $fuelCashSales = max(0.0, round(
            $fuelRevenue - $creditSales - $cardSales - $posSales - $mpesaAmount,
            2
        ));

        // ── Oil/shop cash sales ─────────────────────────────────────────────
        // ASSUMPTION: all oil sales are cash (no payment_method column on oil_sales)
        $oilCashSales = round(
            (float)$shift->oilSales->sum('total_value'), 2
        );

        // ── Cash payments received from credit customers ─────────────────────
        // Matched by station_id + payment_date (payments has no shift_id)
        $cashPaymentsReceived = round(
            (float)Payment::where('station_id', $shift->station_id)
                ->whereDate('payment_date', $shiftDate)
                ->where('payment_method', 'cash')
                ->sum('amount'),
            2
        );

        // ── Cash expenses ────────────────────────────────────────────────────
        // ASSUMPTION: all expenses are cash (no payment_method column on expenses)
        $cashExpenses = round(
            (float)$shift->expenses->sum('amount'), 2
        );

        // ── Expected cash ────────────────────────────────────────────────────
        $expectedCash = round(
            $fuelCashSales + $oilCashSales + $cashPaymentsReceived - $cashExpenses,
            2
        );

        // ── Actual cash and variance ─────────────────────────────────────────
        $actualCash = $shift->actual_cash !== null
            ? round((float)$shift->actual_cash, 2)
            : null;

        $variance    = $actualCash !== null ? round($actualCash - $expectedCash, 2) : null;
        $variancePct = ($actualCash !== null && $expectedCash > 0)
            ? round((abs($variance) / $expectedCash) * 100, 2)
            : null;

        $varianceStatus = $this->classifyVariance(
            $variance !== null ? abs($variance) : 0.0,
            $variancePct ?? 0.0,
            $actualCash !== null,
        );

        return [
            // Breakdown — for transparency/audit
            'fuel_revenue'           => $fuelRevenue,
            'credit_sales'           => $creditSales,
            'card_sales'             => $cardSales,
            'pos_sales'              => $posSales,
            'mpesa_amount'           => $mpesaAmount,
            'fuel_cash_sales'        => $fuelCashSales,
            'oil_cash_sales'         => $oilCashSales,
            'cash_payments_received' => $cashPaymentsReceived,
            'cash_expenses'          => $cashExpenses,
            // Result
            'expected_cash'          => $expectedCash,
            'actual_cash'            => $actualCash,
            'variance'               => $variance,
            'variance_pct'           => $variancePct,
            'variance_status'        => $varianceStatus,
            // Flags
            'has_readings'           => $hasReadings,
            'missing_prices'         => array_unique($missingPrices),
        ];
    }

    /**
     * Classify cash variance against configured thresholds.
     * Returns 'pending' when actual_cash has not been entered yet.
     */
    public function classifyVariance(float $absVariance, float $pct, bool $actualEntered): string
    {
        if (!$actualEntered) return 'pending';

        $t = config('dsr.cash_variance_thresholds');

        if ($absVariance >= $t['critical_abs'] || $pct >= $t['critical_pct']) {
            return 'critical';
        }
        if ($absVariance >= $t['warning_abs'] || $pct >= $t['warning_pct']) {
            return 'warning';
        }
        return 'ok';
    }
}

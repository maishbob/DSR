<?php

namespace App\Services;

use App\Models\CreditSale;
use App\Models\DailySalesRecord;
use App\Models\DsrLineItem;
use App\Models\Expense;
use App\Models\FinancialTransaction;
use App\Models\MeterReading;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shift;
use App\Models\TankDip;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DsrService
{
    public function __construct(
        private readonly ReconciliationEngine $engine,
        private readonly VarianceEngine $variance,
        private readonly LedgerService $ledger,
        private readonly CashReconciliationService $cash,
    ) {}

    /**
     * Generate (or regenerate) the DSR for a shift.
     * Idempotent — safe to call multiple times before approval.
     * Forbidden once the DSR is locked.
     */
    public function generateForShift(Shift $shift): DailySalesRecord
    {
        if ($shift->isLocked()) {
            throw new \RuntimeException('Cannot regenerate a locked DSR.');
        }

        return DB::transaction(function () use ($shift) {
            $products = Product::where('station_id', $shift->station_id)
                ->where('is_active', true)
                ->get();

            $totalLitres     = 0.0;
            $totalRevenue    = 0.0;
            $totalCredit     = 0.0;
            $totalDeliveries = 0.0;
            $totalExpected   = 0.0;
            $totalActual     = 0.0;
            $lineItems       = [];
            $productBreakdown = [];

            $allProductData = $this->engine->reconcileAllProducts($shift, $products);

            foreach ($products as $product) {
                $data = $allProductData[$product->id];

                $totalLitres     += $data['litres_sold'];
                $totalRevenue    += $data['revenue'];
                $totalCredit     += $data['credit_sales_value'];
                $totalDeliveries += $data['deliveries'];
                $totalExpected   += $data['expected_stock'];
                $totalActual     += $data['actual_stock'];

                $lineItems[]                              = $data;
                $productBreakdown[$product->product_name] = $data;
            }

            $totalVariance = round($totalActual - $totalExpected, 3);
            $totalCash     = round($totalRevenue - $totalCredit, 2);

            // Stamp the shift as closed (locking happens later, on approval)
            if ($shift->status === 'open') {
                $shift->update([
                    'status'    => 'closed',
                    'closed_at' => now(),
                    'closed_by' => Auth::id(),
                ]);
            }

            $dsr = DailySalesRecord::updateOrCreate(
                ['shift_id' => $shift->id],
                [
                    'station_id'         => $shift->station_id,
                    'shift_date'         => $shift->shift_date,
                    'shift_type'         => $shift->shift_type,
                    'total_litres_sold'  => round($totalLitres, 3),
                    'total_revenue'      => round($totalRevenue, 2),
                    'total_credit_sales' => round($totalCredit, 2),
                    'total_cash_sales'   => round($totalCash, 2),
                    'total_deliveries'   => round($totalDeliveries, 3),
                    'expected_stock'     => round($totalExpected, 3),
                    'actual_stock'       => round($totalActual, 3),
                    'variance'           => $totalVariance,
                    'product_breakdown'  => $productBreakdown,
                    'generated_at'       => now(),
                    'prepared_by'        => Auth::id(),
                ]
            );

            // Rebuild line items fresh on each generation
            $dsr->lineItems()->delete();
            foreach ($lineItems as $item) {
                $dsr->lineItems()->create($item);
            }

            // Classify stock variance status
            $dsr->refresh()->load('lineItems');
            $varianceStatus = $this->variance->classifyDsr($dsr);

            // Compute cash reconciliation and snapshot into DSR
            $cashRecon = $this->cash->calculate($shift->fresh());
            $dsr->update([
                'variance_status'  => $varianceStatus,
                'cash_collected'   => $cashRecon['actual_cash'],
                'mpesa_collected'  => $cashRecon['mpesa_amount'],
                'total_expenses'   => $cashRecon['cash_expenses'],
                'total_cash_sales' => $cashRecon['fuel_cash_sales'],
                'total_oil_sales'  => $cashRecon['oil_cash_sales'],
            ]);

            return $dsr->fresh(['lineItems.product', 'shift', 'station']);
        });
    }

    /**
     * Approve and lock a DSR.
     *
     * CRITICAL variance requires an explicit override_reason.
     * On success:
     *   - Locks the DSR record
     *   - Locks the shift (status = locked)
     *   - Locks meter_readings, tank_dips, credit_sales, payments
     *   - Writes fuel-sale entries to the financial ledger
     */
    public function approveDsr(DailySalesRecord $dsr, int $userId, ?string $overrideReason = null): DailySalesRecord
    {
        if ($dsr->locked) {
            throw new \RuntimeException('DSR is already locked.');
        }

        $stockStatus = $dsr->variance_status ?? $this->variance->classifyDsr($dsr);

        // Cash reconciliation check — also blocks on critical cash variance
        $shift = $dsr->shift;
        $cashStatus = $shift->cash_variance_status ?? 'pending';

        // Treat 'pending' (no actual_cash entered) as a warning, not a block
        $effectiveStatus = $this->worstStatus($stockStatus, $cashStatus === 'pending' ? 'ok' : $cashStatus);
        $status = $effectiveStatus;

        if ($status === 'critical' && empty($overrideReason)) {
            throw new \RuntimeException(
                'An override reason is required to approve a DSR with a CRITICAL variance.'
            );
        }

        return DB::transaction(function () use ($dsr, $userId, $overrideReason, $status) {
            $shift = $dsr->shift;

            // 1. Lock the DSR
            $dsr->update([
                'approved_at'     => now(),
                'approved_by'     => $userId,
                'locked'          => true,
                'variance_status' => $status,
                'override_reason' => $overrideReason ?: null,
                'override_by'     => $overrideReason ? $userId : null,
                'override_at'     => $overrideReason ? now() : null,
                'verified_by'     => $userId,
            ]);

            // 2. Lock the shift
            $shift->update(['status' => 'locked']);

            // 3. Lock all financial records for this shift
            MeterReading::where('shift_id', $shift->id)->update(['is_locked' => true]);
            TankDip::where('shift_id', $shift->id)->update(['is_locked' => true]);
            CreditSale::where('shift_id', $shift->id)->update(['is_locked' => true]);

            // Payments aren't shift-linked, so match by station + shift date
            Payment::where('station_id', $shift->station_id)
                ->whereDate('payment_date', $shift->shift_date)
                ->update(['is_locked' => true]);

            // 4. Write fuel sales to the financial ledger
            $readings = MeterReading::where('shift_id', $shift->id)
                ->with(['nozzle.product', 'shift'])
                ->get();

            foreach ($readings as $reading) {
                $product = $reading->nozzle?->product;
                if (!$product) continue;
                $price = $this->engine->getPriceForDate($product, $shift->shift_date->toDateString());
                $this->ledger->recordFuelSale($reading, $price);
            }

            return $dsr->fresh();
        });
    }

    /**
     * Reopen a locked DSR so corrections can be made before re-approval.
     *
     * Reverses approveDsr: unlocks all financial records, resets the shift to
     * open, clears approval fields, and removes the fuel-sale ledger entries
     * (they will be recreated when the DSR is re-approved).
     */
    public function reopenDsr(DailySalesRecord $dsr, string $reason): void
    {
        if (!$dsr->locked) {
            throw new \RuntimeException('DSR is not locked.');
        }

        DB::transaction(function () use ($dsr) {
            $shift = $dsr->shift;

            // Remove fuel-sale ledger entries — recreated on next approval
            FinancialTransaction::where('shift_id', $shift->id)
                ->where('type', 'fuel_sale')
                ->delete();

            // Unlock all financial records for this shift
            MeterReading::where('shift_id', $shift->id)->update(['is_locked' => false]);
            TankDip::where('shift_id', $shift->id)->update(['is_locked' => false]);
            CreditSale::where('shift_id', $shift->id)->update(['is_locked' => false]);
            Payment::where('station_id', $shift->station_id)
                ->whereDate('payment_date', $shift->shift_date)
                ->update(['is_locked' => false]);

            // Reset DSR approval fields
            $dsr->update([
                'locked'          => false,
                'approved_at'     => null,
                'approved_by'     => null,
                'variance_status' => null,
                'override_reason' => null,
                'override_by'     => null,
                'override_at'     => null,
                'verified_by'     => null,
            ]);

            // Reset shift back to open so entries can be edited and DSR regenerated
            $shift->update([
                'status'    => 'open',
                'closed_at' => null,
                'closed_by' => null,
            ]);
        });
    }

    private function worstStatus(string $a, string $b): string
    {
        $rank = ['ok' => 0, 'warning' => 1, 'critical' => 2];
        return ($rank[$a] ?? 0) >= ($rank[$b] ?? 0) ? $a : $b;
    }
}

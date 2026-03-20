<?php

namespace App\Services;

use App\Models\DailySalesRecord;
use App\Models\DsrLineItem;
use App\Models\Product;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DsrService
{
    public function __construct(
        private readonly ReconciliationEngine $engine
    ) {}

    /**
     * Generate or regenerate the DSR for a shift.
     * This is idempotent — calling it again recalculates and updates.
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

            $totalLitres    = 0.0;
            $totalRevenue   = 0.0;
            $totalCredit    = 0.0;
            $totalDeliveries = 0.0;
            $totalExpected  = 0.0;
            $totalActual    = 0.0;
            $lineItems      = [];
            $productBreakdown = [];

            foreach ($products as $product) {
                $data = $this->engine->reconcileShiftProduct($shift, $product);

                $totalLitres    += $data['litres_sold'];
                $totalRevenue   += $data['revenue'];
                $totalCredit    += $data['credit_sales_value'];
                $totalDeliveries += $data['deliveries'];
                $totalExpected  += $data['expected_stock'];
                $totalActual    += $data['actual_stock'];

                $lineItems[] = $data;
                $productBreakdown[$product->product_name] = $data;
            }

            $totalVariance  = round($totalActual - $totalExpected, 3);
            $totalCash      = round($totalRevenue - $totalCredit, 2);

            // Close the shift (if not already closed/locked)
            if ($shift->status === 'open') {
                $shift->update([
                    'status'    => 'closed',
                    'closed_at' => now(),
                    'closed_by' => Auth::id(),
                ]);
            }

            // Create or update the DSR record
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
                ]
            );

            // Rebuild line items
            $dsr->lineItems()->delete();
            foreach ($lineItems as $item) {
                $dsr->lineItems()->create($item);
            }

            return $dsr->fresh(['lineItems.product', 'shift', 'station']);
        });
    }

    /**
     * Approve and lock a DSR. Once locked, no further edits are allowed.
     */
    public function approveDsr(DailySalesRecord $dsr, int $userId): DailySalesRecord
    {
        if ($dsr->locked) {
            throw new \RuntimeException('DSR is already locked.');
        }

        DB::transaction(function () use ($dsr, $userId) {
            $dsr->update([
                'approved_at' => now(),
                'approved_by' => $userId,
                'locked'      => true,
            ]);

            $dsr->shift()->update(['status' => 'locked']);
        });

        return $dsr->fresh();
    }
}

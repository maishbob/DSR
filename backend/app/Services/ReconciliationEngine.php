<?php

namespace App\Services;

use App\Models\CreditSale;
use App\Models\Delivery;
use App\Models\DsrLineItem;
use App\Models\MeterReading;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Tank;
use App\Models\TankDip;
use Illuminate\Support\Collection;

class ReconciliationEngine
{
    /**
     * Calculate litres sold from the electrical meter (primary source of truth).
     */
    public function calculateMeterSales(MeterReading $reading): float
    {
        if ($reading->closing_electrical === null) return 0.0;
        return round((float)$reading->closing_electrical - (float)$reading->opening_electrical, 3);
    }

    /**
     * Look up the applicable price for a product on a given date.
     */
    public function getPriceForDate(Product $product, string $date): float
    {
        $price = $product->priceHistories()
            ->where('effective_from', '<=', $date)
            ->where(function ($q) use ($date) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $date);
            })
            ->orderByDesc('effective_from')
            ->first();

        return $price ? (float)$price->price_per_litre : 0.0;
    }

    /**
     * Get opening stock for a tank: closing dip of the previous shift.
     * Falls back to the opening dip of the current shift if no prior shift exists.
     */
    public function getOpeningStock(Tank $tank, Shift $shift): float
    {
        $previousDip = TankDip::where('tank_id', $tank->id)
            ->where('dip_type', 'closing')
            ->whereHas('shift', function ($q) use ($tank, $shift) {
                $q->where('station_id', $tank->station_id)
                  ->where(function ($sq) use ($shift) {
                      $sq->where('shift_date', '<', $shift->shift_date)
                         ->orWhere(function ($sq2) use ($shift) {
                             $sq2->where('shift_date', $shift->shift_date)
                                 ->where('id', '<', $shift->id);
                         });
                  });
            })
            ->orderByDesc('id')
            ->first();

        if ($previousDip) {
            return (float)$previousDip->dip_volume;
        }

        $openingDip = TankDip::where('tank_id', $tank->id)
            ->where('shift_id', $shift->id)
            ->where('dip_type', 'opening')
            ->first();

        return $openingDip ? (float)$openingDip->dip_volume : 0.0;
    }

    /**
     * Get total deliveries into a tank during a shift.
     */
    public function getDeliveriesForShift(Tank $tank, Shift $shift): float
    {
        return (float)Delivery::where('tank_id', $tank->id)
            ->where('shift_id', $shift->id)
            ->sum('delivery_quantity');
    }

    /**
     * Get the closing dip volume for a tank in a shift.
     */
    public function getActualStock(Tank $tank, Shift $shift): float
    {
        $dip = TankDip::where('tank_id', $tank->id)
            ->where('shift_id', $shift->id)
            ->where('dip_type', 'closing')
            ->first();

        return $dip ? (float)$dip->dip_volume : 0.0;
    }

    /**
     * Get credit sales for a product in a shift.
     */
    public function getCreditSalesForShift(Product $product, Shift $shift): array
    {
        $sales = CreditSale::where('product_id', $product->id)
            ->where('shift_id', $shift->id)
            ->get();

        return [
            'litres' => round((float)$sales->sum('quantity'), 3),
            'value'  => round((float)$sales->sum('total_value'), 2),
        ];
    }

    /**
     * Reconcile all products for a shift in bulk.
     *
     * Pre-loads all required data in 8 fixed queries (independent of product/tank
     * count), then resolves each product's figures from in-memory collections.
     * Returns an array keyed by product_id.
     */
    public function reconcileAllProducts(Shift $shift, Collection $products): array
    {
        if ($products->isEmpty()) {
            return [];
        }

        $shiftDate  = $shift->shift_date->toDateString();
        $productIds = $products->pluck('id');

        // 1. Prices
        $prices = PriceHistory::whereIn('product_id', $productIds)
            ->where('effective_from', '<=', $shiftDate)
            ->where(fn($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $shiftDate))
            ->orderByDesc('effective_from')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id')
            ->map(fn($ph) => (float) $ph->price_per_litre);

        // 2. Meter readings grouped by product_id (via nozzle)
        $meterReadings = MeterReading::where('shift_id', $shift->id)
            ->with('nozzle:id,product_id')
            ->get()
            ->groupBy(fn($r) => $r->nozzle?->product_id);

        // 3. Tanks grouped by product_id
        $tanksByProduct = Tank::where('station_id', $shift->station_id)
            ->where('is_active', true)
            ->whereIn('product_id', $productIds)
            ->get()
            ->groupBy('product_id');

        $tankIds = $tanksByProduct->flatten()->pluck('id');

        if ($tankIds->isNotEmpty()) {
            // 4. Current shift dips grouped by tank_id
            $currentDips = TankDip::whereIn('tank_id', $tankIds)
                ->where('shift_id', $shift->id)
                ->get()
                ->groupBy('tank_id');

            // 5. Latest closing dip per tank from any prior shift
            $previousDips = TankDip::whereIn('tank_id', $tankIds)
                ->where('dip_type', 'closing')
                ->whereHas('shift', fn($q) => $q
                    ->where('station_id', $shift->station_id)
                    ->where(fn($sq) => $sq
                        ->where('shift_date', '<', $shift->shift_date)
                        ->orWhere(fn($sq2) => $sq2
                            ->where('shift_date', $shift->shift_date)
                            ->where('id', '<', $shift->id)
                        )
                    )
                )
                ->orderByDesc('id')
                ->get()
                ->unique('tank_id')
                ->keyBy('tank_id');

            // 6. Delivery totals per tank
            $deliveries = Delivery::whereIn('tank_id', $tankIds)
                ->where('shift_id', $shift->id)
                ->selectRaw('tank_id, SUM(delivery_quantity) as total_qty')
                ->groupBy('tank_id')
                ->pluck('total_qty', 'tank_id');
        } else {
            $currentDips  = collect();
            $previousDips = collect();
            $deliveries   = collect();
        }

        // 7. Credit sales summed per product
        $creditSales = CreditSale::whereIn('product_id', $productIds)
            ->where('shift_id', $shift->id)
            ->selectRaw('product_id, SUM(quantity) as total_qty, SUM(total_value) as total_value')
            ->groupBy('product_id')
            ->get()
            ->keyBy('product_id');

        // 8. Previous cumulative variance per product (latest locked line item)
        $prevCumPcts = DsrLineItem::whereIn('product_id', $productIds)
            ->whereHas('dailySalesRecord', fn($q) => $q
                ->where('station_id', $shift->station_id)
                ->where('locked', true)
            )
            ->orderByDesc('id')
            ->get()
            ->unique('product_id')
            ->keyBy('product_id');

        return $products->keyBy('id')->map(function ($product) use (
            $shiftDate, $prices, $meterReadings, $tanksByProduct,
            $currentDips, $previousDips, $deliveries, $creditSales, $prevCumPcts
        ) {
            $pricePerLitre = $prices->get($product->id, 0.0);
            $readings      = $meterReadings->get($product->id, collect());
            $tanks         = $tanksByProduct->get($product->id, collect());

            $litresSold   = round($readings->sum(fn($r) => $this->calculateMeterSales($r)), 3);
            $openingMeter = round((float) $readings->sum('opening_electrical'), 3);
            $closingMeter = round((float) $readings->sum('closing_electrical'), 3);
            $revenue      = round($litresSold * $pricePerLitre, 2);

            $shsSold        = round((float) $readings->sum('shs_sold'), 2);
            $shsExpected    = $revenue;
            $shsDiscrepancy = round(abs($shsSold - $shsExpected), 2);
            $tolerancePct   = (float) config('dsr.shs_tolerance_pct', 1.0);
            $shsWarning     = $shsExpected > 0
                && (($shsDiscrepancy / $shsExpected) * 100) > $tolerancePct;

            $openingStock    = 0.0;
            $totalDeliveries = 0.0;
            $actualStock     = 0.0;
            $tankId          = null;

            foreach ($tanks as $tank) {
                $prevDip = $previousDips->get($tank->id);
                if ($prevDip) {
                    $openingStock += (float) $prevDip->dip_volume;
                } else {
                    $openingDip    = $currentDips->get($tank->id, collect())->firstWhere('dip_type', 'opening');
                    $openingStock += $openingDip ? (float) $openingDip->dip_volume : 0.0;
                }

                $totalDeliveries += (float) ($deliveries->get($tank->id) ?? 0);

                $closingDip   = $currentDips->get($tank->id, collect())->firstWhere('dip_type', 'closing');
                $actualStock += $closingDip ? (float) $closingDip->dip_volume : 0.0;

                $tankId = $tank->id;
            }

            $expectedStock = round($openingStock + $totalDeliveries - $litresSold, 3);
            $variance      = round($actualStock - $expectedStock, 3);
            $variancePct   = $litresSold > 0
                ? round((abs($variance) / $litresSold) * 100, 3)
                : 0.0;

            $prevCumPct            = (float) ($prevCumPcts->get($product->id)?->cumulative_variance_pct ?? 0.0);
            $cumulativeVariancePct = round($prevCumPct + $variancePct, 3);

            $cs = $creditSales->get($product->id);

            return [
                'product_id'              => $product->id,
                'tank_id'                 => $tankId,
                'opening_meter'           => $openingMeter,
                'closing_meter'           => $closingMeter,
                'litres_sold'             => $litresSold,
                'price_per_litre'         => $pricePerLitre,
                'revenue'                 => $revenue,
                'opening_stock'           => round($openingStock, 3),
                'deliveries'              => round($totalDeliveries, 3),
                'expected_stock'          => $expectedStock,
                'actual_stock'            => round($actualStock, 3),
                'variance'                => $variance,
                'shortage'                => $variance < 0 ? abs($variance) : 0.0,
                'excess'                  => $variance > 0 ? $variance : 0.0,
                'cumulative_variance_pct' => $cumulativeVariancePct,
                'credit_sales_litres'     => round((float) ($cs?->total_qty ?? 0), 3),
                'credit_sales_value'      => round((float) ($cs?->total_value ?? 0), 2),
            ];
        })->all();
    }

    /**
     * Run full reconciliation for a shift and product.
     *
     * IMPORTANT: meter_readings has no product_id column. Product is on the nozzle.
     * We aggregate across all nozzles whose product_id matches, then aggregate
     * tank stock across all tanks carrying that product.
     */
    public function reconcileShiftProduct(Shift $shift, Product $product): array
    {
        $shiftDate     = $shift->shift_date->toDateString();
        $pricePerLitre = $this->getPriceForDate($product, $shiftDate);

        // --- Meter readings: aggregate all nozzles for this product ---
        $readings = MeterReading::where('shift_id', $shift->id)
            ->whereHas('nozzle', fn($q) => $q->where('product_id', $product->id))
            ->get();

        $litresSold   = round($readings->sum(fn($r) => $this->calculateMeterSales($r)), 3);
        $openingMeter = round((float)$readings->sum('opening_electrical'), 3);
        $closingMeter = round((float)$readings->sum('closing_electrical'), 3);
        $revenue      = round($litresSold * $pricePerLitre, 2);

        // SHS cross-check: pump's own KES odometer vs (litres × price)
        $shsSold        = round((float)$readings->sum('shs_sold'), 2);
        $shsExpected    = $revenue;
        $shsDiscrepancy = round(abs($shsSold - $shsExpected), 2);
        $tolerancePct   = (float)config('dsr.shs_tolerance_pct', 1.0);
        $shsWarning     = $shsExpected > 0
            && (($shsDiscrepancy / $shsExpected) * 100) > $tolerancePct;

        // --- Tank stock: aggregate all tanks for this product ---
        $tanks = Tank::where('station_id', $shift->station_id)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->get();

        $openingStock    = 0.0;
        $totalDeliveries = 0.0;
        $actualStock     = 0.0;
        $tankId          = null;

        foreach ($tanks as $tank) {
            $openingStock    += $this->getOpeningStock($tank, $shift);
            $totalDeliveries += $this->getDeliveriesForShift($tank, $shift);
            $actualStock     += $this->getActualStock($tank, $shift);
            $tankId           = $tank->id;
        }

        $expectedStock = round($openingStock + $totalDeliveries - $litresSold, 3);
        $variance      = round($actualStock - $expectedStock, 3);

        // Variance percentage for this period
        $variancePct = $litresSold > 0
            ? round((abs($variance) / $litresSold) * 100, 3)
            : 0.0;

        // Rolling cumulative variance: add to last finalised line item's cumulative
        $prevCumPct = DsrLineItem::where('product_id', $product->id)
            ->whereHas('dailySalesRecord', function ($q) use ($shift) {
                $q->where('station_id', $shift->station_id)->where('locked', true);
            })
            ->latest('id')
            ->value('cumulative_variance_pct') ?? 0.0;

        $cumulativeVariancePct = round((float)$prevCumPct + $variancePct, 3);

        // Credit sales
        $creditSales = $this->getCreditSalesForShift($product, $shift);

        return [
            'product_id'              => $product->id,
            'tank_id'                 => $tankId,
            'opening_meter'           => $openingMeter,
            'closing_meter'           => $closingMeter,
            'litres_sold'             => $litresSold,
            'price_per_litre'         => $pricePerLitre,
            'revenue'                 => $revenue,
            'opening_stock'           => round($openingStock, 3),
            'deliveries'              => round($totalDeliveries, 3),
            'expected_stock'          => $expectedStock,
            'actual_stock'            => round($actualStock, 3),
            'variance'                => $variance,
            'shortage'                => $variance < 0 ? abs($variance) : 0.0,
            'excess'                  => $variance > 0 ? $variance : 0.0,
            'cumulative_variance_pct' => $cumulativeVariancePct,
            'credit_sales_litres'     => $creditSales['litres'],
            'credit_sales_value'      => $creditSales['value'],
        ];
    }
}

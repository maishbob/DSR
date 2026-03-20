<?php

namespace App\Services;

use App\Models\MeterReading;
use App\Models\Product;
use App\Models\Shift;
use App\Models\Tank;
use App\Models\TankDip;
use App\Models\Delivery;
use App\Models\CreditSale;
use Illuminate\Support\Collection;

class ReconciliationEngine
{
    /**
     * Calculate litres sold from meter readings for a shift and product.
     */
    public function calculateMeterSales(MeterReading $reading): float
    {
        if ($reading->closing_meter === null) return 0.0;
        return round((float)$reading->closing_meter - (float)$reading->opening_meter, 3);
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
     * Calculate revenue: litres_sold × price_per_litre.
     */
    public function calculateRevenue(float $litresSold, float $pricePerLitre): float
    {
        return round($litresSold * $pricePerLitre, 2);
    }

    /**
     * Get opening stock for a tank.
     * Opening stock = closing dip of previous shift for this tank.
     */
    public function getOpeningStock(Tank $tank, Shift $shift): float
    {
        // Find the previous shift's closing dip for this tank
        $previousDip = TankDip::whereHas('shift', function ($q) use ($tank, $shift) {
                $q->where('station_id', $tank->station_id)
                  ->where(function ($sq) use ($shift) {
                      $sq->where('shift_date', '<', $shift->shift_date)
                         ->orWhere(function ($sq2) use ($shift) {
                             $sq2->where('shift_date', $shift->shift_date)
                                 ->where('shift_type', 'night')
                                 ->where('shift_type', '!=', $shift->shift_type);
                         });
                  });
            })
            ->where('tank_id', $tank->id)
            ->where('dip_type', 'closing')
            ->orderByDesc('id')
            ->first();

        if ($previousDip) {
            return (float)$previousDip->dip_volume;
        }

        // Also check opening dip of this shift
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
     * Calculate expected stock.
     * expected_stock = opening_stock + deliveries - litres_sold
     */
    public function calculateExpectedStock(float $openingStock, float $deliveries, float $litresSold): float
    {
        return round($openingStock + $deliveries - $litresSold, 3);
    }

    /**
     * Calculate variance.
     * variance = actual_dip - expected_stock
     */
    public function calculateVariance(float $actualDip, float $expectedStock): float
    {
        return round($actualDip - $expectedStock, 3);
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
     * Run full reconciliation for a shift and product, returning a data array.
     */
    public function reconcileShiftProduct(Shift $shift, Product $product): array
    {
        $shiftDate = $shift->shift_date->toDateString();
        $pricePerLitre = $this->getPriceForDate($product, $shiftDate);

        // Meter readings
        $meterReading = MeterReading::where('shift_id', $shift->id)
            ->where('product_id', $product->id)
            ->first();

        $litresSold   = $meterReading ? $this->calculateMeterSales($meterReading) : 0.0;
        $openingMeter = $meterReading ? (float)$meterReading->opening_meter : 0.0;
        $closingMeter = $meterReading ? (float)$meterReading->closing_meter : 0.0;
        $revenue      = $this->calculateRevenue($litresSold, $pricePerLitre);

        // Tank-level reconciliation (aggregate across all tanks for this product)
        $tanks = Tank::where('station_id', $shift->station_id)
            ->where('product_id', $product->id)
            ->where('is_active', true)
            ->get();

        $openingStock   = 0.0;
        $totalDeliveries = 0.0;
        $expectedStock  = 0.0;
        $actualStock    = 0.0;
        $tankId         = null;

        foreach ($tanks as $tank) {
            $tankOpeningStock   = $this->getOpeningStock($tank, $shift);
            $tankDeliveries     = $this->getDeliveriesForShift($tank, $shift);
            $tankActualStock    = $this->getActualStock($tank, $shift);
            $openingStock      += $tankOpeningStock;
            $totalDeliveries   += $tankDeliveries;
            $actualStock       += $tankActualStock;
            $tankId             = $tank->id; // last tank id (for single-tank products)
        }

        $expectedStock = $this->calculateExpectedStock($openingStock, $totalDeliveries, $litresSold);
        $variance      = $this->calculateVariance($actualStock, $expectedStock);

        // Credit sales
        $creditSales = $this->getCreditSalesForShift($product, $shift);

        return [
            'product_id'          => $product->id,
            'tank_id'             => $tankId,
            'opening_meter'       => $openingMeter,
            'closing_meter'       => $closingMeter,
            'litres_sold'         => $litresSold,
            'price_per_litre'     => $pricePerLitre,
            'revenue'             => $revenue,
            'opening_stock'       => round($openingStock, 3),
            'deliveries'          => round($totalDeliveries, 3),
            'expected_stock'      => $expectedStock,
            'actual_stock'        => round($actualStock, 3),
            'variance'            => $variance,
            'credit_sales_litres' => $creditSales['litres'],
            'credit_sales_value'  => $creditSales['value'],
        ];
    }
}

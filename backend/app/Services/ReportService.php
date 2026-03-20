<?php

namespace App\Services;

use App\Models\CreditCustomer;
use App\Models\DailySalesRecord;
use App\Models\Delivery;
use App\Models\Station;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    /**
     * Wet Stock Report: Opening + Deliveries - Sales = Expected vs Actual dip.
     */
    public function wetStockReport(Station $station, string $from, string $to): array
    {
        $dsrs = DailySalesRecord::where('station_id', $station->id)
            ->whereBetween('shift_date', [$from, $to])
            ->with(['lineItems.product', 'lineItems.tank', 'shift'])
            ->orderBy('shift_date')
            ->get();

        $rows = [];
        foreach ($dsrs as $dsr) {
            foreach ($dsr->lineItems as $item) {
                $rows[] = [
                    'date'           => $dsr->shift_date->format('Y-m-d'),
                    'shift_type'     => $dsr->shift_type,
                    'product'        => $item->product->product_name,
                    'tank'           => $item->tank?->tank_name,
                    'opening_stock'  => $item->opening_stock,
                    'deliveries'     => $item->deliveries,
                    'litres_sold'    => $item->litres_sold,
                    'expected_stock' => $item->expected_stock,
                    'actual_stock'   => $item->actual_stock,
                    'variance'       => $item->variance,
                ];
            }
        }

        return $rows;
    }

    /**
     * Daily Sales Summary: revenue, litres, credit for a date range.
     */
    public function salesSummary(Station $station, string $from, string $to): array
    {
        return DailySalesRecord::where('station_id', $station->id)
            ->whereBetween('shift_date', [$from, $to])
            ->orderBy('shift_date')
            ->get(['shift_date', 'shift_type', 'total_litres_sold', 'total_revenue',
                   'total_credit_sales', 'total_cash_sales', 'variance', 'locked'])
            ->toArray();
    }

    /**
     * Credit customer statement: all sales and payments in a date range.
     */
    public function creditCustomerStatement(CreditCustomer $customer, string $from, string $to): array
    {
        $sales = $customer->creditSales()
            ->whereHas('shift', fn($q) => $q->whereBetween('shift_date', [$from, $to]))
            ->with(['product', 'shift'])
            ->orderBy('created_at')
            ->get()
            ->map(fn($s) => [
                'date'        => $s->shift->shift_date->format('Y-m-d'),
                'type'        => 'sale',
                'product'     => $s->product->product_name,
                'quantity'    => $s->quantity,
                'unit_price'  => $s->price_applied,
                'amount'      => -$s->total_value,
                'balance'     => null,
            ]);

        $payments = $customer->payments()
            ->whereBetween('payment_date', [$from, $to])
            ->orderBy('payment_date')
            ->get()
            ->map(fn($p) => [
                'date'        => $p->payment_date->format('Y-m-d'),
                'type'        => 'payment',
                'product'     => null,
                'quantity'    => null,
                'unit_price'  => null,
                'amount'      => $p->amount,
                'balance'     => null,
            ]);

        $transactions = $sales->merge($payments)->sortBy('date')->values();

        $runningBalance = 0.0;
        foreach ($transactions as &$txn) {
            $runningBalance -= $txn['amount']; // sales are negative credits
            $txn['balance'] = round($runningBalance, 2);
        }

        return [
            'customer'     => $customer->only(['id', 'customer_name', 'phone', 'credit_limit']),
            'from'         => $from,
            'to'           => $to,
            'transactions' => $transactions->toArray(),
            'balance'      => $customer->balance,
        ];
    }

    /**
     * Multi-station summary for an owner.
     */
    public function multiStationSummary(int $ownerId, string $from, string $to): array
    {
        return DB::table('daily_sales_records as dsr')
            ->join('stations', 'stations.id', '=', 'dsr.station_id')
            ->where('stations.owner_id', $ownerId)
            ->whereBetween('dsr.shift_date', [$from, $to])
            ->select(
                'stations.id as station_id',
                'stations.station_name',
                DB::raw('SUM(dsr.total_litres_sold) as total_litres'),
                DB::raw('SUM(dsr.total_revenue) as total_revenue'),
                DB::raw('SUM(dsr.total_deliveries) as total_deliveries'),
                DB::raw('SUM(dsr.variance) as total_variance'),
                DB::raw('COUNT(*) as shift_count')
            )
            ->groupBy('stations.id', 'stations.station_name')
            ->orderByDesc('total_revenue')
            ->get()
            ->toArray();
    }

    /**
     * Delivery history for a station.
     */
    public function deliveryHistory(Station $station, string $from, string $to): array
    {
        return Delivery::where('station_id', $station->id)
            ->whereBetween('delivery_date', [$from, $to])
            ->with(['product', 'tank'])
            ->orderBy('delivery_date', 'desc')
            ->get()
            ->toArray();
    }
}

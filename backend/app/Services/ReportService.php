<?php

namespace App\Services;

use App\Models\CreditCustomer;
use App\Models\DailySalesRecord;
use App\Models\Delivery;
use App\Models\Shift;
use App\Models\Station;
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
        $salesBefore = $customer->creditSales()
            ->whereHas('shift', fn($q) => $q->where('shift_date', '<', $from))
            ->sum('total_value');

        $paymentsBefore = $customer->payments()
            ->where('payment_date', '<', $from)
            ->sum('amount');

        $broughtForward = round(
            (float) ($customer->initial_opening_balance ?? 0)
            + (float) $salesBefore
            - (float) $paymentsBefore,
            2
        );

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

        $runningBalance = $broughtForward;
        foreach ($transactions as &$txn) {
            $runningBalance -= $txn['amount']; // sales are negative credits
            $txn['balance'] = round($runningBalance, 2);
        }

        return [
            'customer'     => $customer->only(['id', 'customer_name', 'phone', 'credit_limit']),
            'from'         => $from,
            'to'           => $to,
            'brought_forward' => $broughtForward,
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

    /**
     * Dashboard KPIs for a station on a given date.
     */
    public function dashboardKpis(Station $station, string $date): array
    {
        $todayDsrs = DailySalesRecord::where('station_id', $station->id)
            ->where('shift_date', $date)
            ->get();

        $todayRevenue = $todayDsrs->sum('total_revenue');
        $todayLitres = $todayDsrs->sum('total_litres_sold');
        $todayVariance = $todayDsrs->sum('variance');

        $openShifts = Shift::where('station_id', $station->id)
            ->where('status', 'open')
            ->with(['meterReadings.nozzle.product', 'tankDips.tank'])
            ->get();

        $recentDeliveries = Delivery::where('station_id', $station->id)
            ->where('delivery_date', '>=', now()->subDays(7)->toDateString())
            ->with(['product', 'tank'])
            ->orderByDesc('delivery_date')
            ->limit(10)
            ->get();

        $revenueTrend = DailySalesRecord::where('station_id', $station->id)
            ->where('shift_date', '>=', now()->subDays(7)->toDateString())
            ->select(
                'shift_date',
                DB::raw('SUM(total_revenue) as revenue'),
                DB::raw('SUM(total_litres_sold) as litres')
            )
            ->groupBy('shift_date')
            ->orderBy('shift_date')
            ->get();

        $topDebtors = DB::table('credit_customers')
            ->where('credit_customers.station_id', $station->id)
            ->where('credit_customers.is_active', true)
            ->select(
                'credit_customers.id',
                'credit_customers.customer_name',
                DB::raw('credit_customers.initial_opening_balance
                    + COALESCE((SELECT SUM(cs.total_value) FROM credit_sales cs WHERE cs.credit_customer_id = credit_customers.id), 0)
                    - COALESCE((SELECT SUM(p.amount) FROM payments p WHERE p.credit_customer_id = credit_customers.id), 0)
                    as balance')
            )
            ->having('balance', '>', 0)
            ->orderByDesc('balance')
            ->limit(5)
            ->get();

        return [
            'todayRevenue'     => $todayRevenue,
            'todayLitres'      => $todayLitres,
            'todayVariance'    => $todayVariance,
            'openShifts'       => $openShifts,
            'recentDeliveries' => $recentDeliveries,
            'revenueTrend'     => $revenueTrend,
            'topDebtors'       => $topDebtors,
        ];
    }

    public function dsrIncomeReport(Station $station, string $from, string $to): array
    {
        return DailySalesRecord::where('station_id', $station->id)
            ->whereBetween('shift_date', [$from, $to])
            ->orderBy('shift_date')
            ->orderBy('shift_type')
            ->get([
                'shift_date', 'shift_type', 'serial_number',
                'total_cash_sales', 'total_credit_sales', 'total_card_sales',
                'total_pos_sales', 'mpesa_collected', 'total_revenue',
            ])
            ->toArray();
    }
}

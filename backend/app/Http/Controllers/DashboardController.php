<?php

namespace App\Http\Controllers;

use App\Models\DailySalesRecord;
use App\Models\Delivery;
use App\Models\Shift;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function index(Request $request)
    {
        $user    = $request->user();
        $station = $user->station;
        $today   = now()->toDateString();

        // Today's DSR totals
        $todayDsrs = DailySalesRecord::where('station_id', $station->id)
            ->where('shift_date', $today)
            ->get();

        $todayRevenue = $todayDsrs->sum('total_revenue');
        $todayLitres  = $todayDsrs->sum('total_litres_sold');
        $todayVariance = $todayDsrs->sum('variance');

        // Open shifts
        $openShifts = Shift::where('station_id', $station->id)
            ->where('status', 'open')
            ->with(['meterReadings.nozzle.product', 'tankDips.tank'])
            ->get();

        // Recent deliveries (last 7 days)
        $recentDeliveries = Delivery::where('station_id', $station->id)
            ->where('delivery_date', '>=', now()->subDays(7)->toDateString())
            ->with(['product', 'tank'])
            ->orderByDesc('delivery_date')
            ->limit(10)
            ->get();

        // Last 7 days revenue trend
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

        // Credit balances (top 5 debtors)
        $topDebtors = DB::table('credit_customers')
            ->where('credit_customers.station_id', $station->id)
            ->where('credit_customers.is_active', true)
            ->leftJoin('credit_sales', 'credit_customers.id', '=', 'credit_sales.credit_customer_id')
            ->leftJoin('payments', 'credit_customers.id', '=', 'payments.credit_customer_id')
            ->select(
                'credit_customers.id',
                'credit_customers.customer_name',
                DB::raw('COALESCE(MAX(credit_customers.initial_opening_balance),0) + COALESCE(SUM(credit_sales.total_value),0) - COALESCE(SUM(payments.amount),0) as balance')
            )
            ->groupBy('credit_customers.id', 'credit_customers.customer_name')
            ->having('balance', '>', 0)
            ->orderByDesc('balance')
            ->limit(5)
            ->get();

        return Inertia::render('Dashboard', [
            'station'          => $station,
            'todayRevenue'     => $todayRevenue,
            'todayLitres'      => $todayLitres,
            'todayVariance'    => $todayVariance,
            'openShifts'       => $openShifts,
            'recentDeliveries' => $recentDeliveries,
            'revenueTrend'     => $revenueTrend,
            'topDebtors'       => $topDebtors,
        ]);
    }

    public function ownerDashboard(Request $request)
    {
        $user  = $request->user();
        $owner = $user->owner ?? $user->ownedAccount;
        $from  = now()->subDays(30)->toDateString();
        $to    = now()->toDateString();

        $multiStation = $this->reportService->multiStationSummary($owner->id, $from, $to);

        return Inertia::render('OwnerDashboard', [
            'owner'        => $owner->load('stations'),
            'stationStats' => $multiStation,
            'from'         => $from,
            'to'           => $to,
        ]);
    }
}

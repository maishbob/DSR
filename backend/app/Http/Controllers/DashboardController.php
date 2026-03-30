<?php

namespace App\Http\Controllers;

use App\Models\DailySalesRecord;
use App\Models\Delivery;
use App\Models\Shift;
use App\Models\Station;
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
        $station = $this->resolveStation($user, $request);

        // Owner with no station selected → station picker
        if (! $station) {
            return redirect()->route('select-station');
        }

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

    /**
     * Show station picker for owners.
     */
    public function selectStation(Request $request)
    {
        $user  = $request->user();
        $owner = $user->ownedAccount;

        if (! $owner) {
            // Non-owner users shouldn't be here — send them to dashboard
            return redirect()->route('dashboard');
        }

        $stations = $owner->stations()->orderBy('station_name')->get();

        // If owner has exactly one station, auto-select it
        if ($stations->count() === 1) {
            $request->session()->put('station_id', $stations->first()->id);
            return redirect()->route('dashboard');
        }

        return Inertia::render('SelectStation', [
            'owner'    => $owner,
            'stations' => $stations,
        ]);
    }

    /**
     * Set the active station in session for owner users.
     */
    public function switchStation(Request $request)
    {
        $request->validate([
            'station_id' => 'required|integer|exists:stations,id',
        ]);

        $user  = $request->user();
        $owner = $user->ownedAccount;

        // Verify the station belongs to this owner
        if (! $owner || ! $owner->stations()->where('id', $request->station_id)->exists()) {
            abort(403);
        }

        $request->session()->put('station_id', (int) $request->station_id);

        return redirect()->route('dashboard');
    }

    /**
     * Resolve the effective station for the current user.
     * Owners can switch stations via session; other users use their assigned station.
     */
    private function resolveStation($user, Request $request): ?Station
    {
        if ($user->isOwner() && $user->ownedAccount) {
            $sessionStationId = $request->session()->get('station_id');

            if ($sessionStationId) {
                $station = $user->ownedAccount->stations()->where('id', $sessionStationId)->first();
                if ($station) {
                    return $station;
                }
                // Invalid session station — clear it
                $request->session()->forget('station_id');
            }

            return null;
        }

        return $user->station;
    }
}

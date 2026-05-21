<?php

namespace App\Services;

use App\Models\MeterReading;
use App\Models\PumpNozzle;
use App\Models\Shift;
use App\Models\Station;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ShiftService
{
    public function openShift(Station $station, string $shiftType, string $date): Shift
    {
        $existing = Shift::where('station_id', $station->id)
            ->where('shift_date', $date)
            ->where('shift_type', $shiftType)
            ->first();

        if ($existing) {
            throw new \RuntimeException("A {$shiftType} shift already exists for {$date}.");
        }

        $nextDsrNumber = (int) Shift::where('station_id', $station->id)
            ->whereNotNull('dsr_number')
            ->max(DB::raw('CAST(dsr_number AS UNSIGNED)')) + 1;

        $shift = Shift::create([
            'station_id' => $station->id,
            'shift_date' => $date,
            'shift_type' => $shiftType,
            'opened_at'  => now(),
            'opened_by'  => Auth::id(),
            'status'     => 'open',
            'dsr_number' => (string) $nextDsrNumber,
        ]);

        // Auto-seed opening meter readings from each nozzle's last known readings.
        // The operator only needs to enter closing values.
        $nozzles = PumpNozzle::where('station_id', $station->id)
            ->where('is_active', true)
            ->get();

        foreach ($nozzles as $nozzle) {
            MeterReading::create([
                'shift_id'           => $shift->id,
                'nozzle_id'          => $nozzle->id,
                'opening_mechanical' => $nozzle->last_mech ?? 0,
                'opening_electrical' => $nozzle->last_elec ?? 0,
                'opening_shs'        => $nozzle->last_shs,
                'entered_by'         => Auth::id(),
            ]);
        }

        return $shift;
    }

    /**
     * Month-to-date cumulative litres sold per product for a given shift.
     * Resets on the 1st of each month.
     *
     * Returns an array of:
     *   [ 'product_id', 'product_name', 'bf' (litres b/forward), 'today' (litres this shift), 'cumulative' ]
     */
    public function getCumulativeSales(Shift $shift): array
    {
        $stationId = $shift->station_id;
        $shiftDate = $shift->shift_date instanceof \Carbon\Carbon
            ? $shift->shift_date
            : \Carbon\Carbon::parse($shift->shift_date);

        $monthStart = $shiftDate->copy()->startOfMonth()->toDateString();

        // Litres per product for all prior shifts this month
        $bfRows = DB::table('meter_readings as mr')
            ->join('pump_nozzles as pn', 'pn.id', '=', 'mr.nozzle_id')
            ->join('products as p', 'p.id', '=', 'pn.product_id')
            ->join('shifts as s', 's.id', '=', 'mr.shift_id')
            ->where('s.station_id', $stationId)
            ->where('s.shift_date', '>=', $monthStart)
            ->where(function ($q) use ($shift) {
                $q->where('s.shift_date', '<', $shift->shift_date)
                  ->orWhere(function ($q2) use ($shift) {
                      $q2->where('s.shift_date', '=', $shift->shift_date)
                         ->where('s.id', '<', $shift->id);
                  });
            })
            ->whereNotNull('mr.closing_electrical')
            ->groupBy('p.id', 'p.product_name')
            ->orderBy('p.product_name')
            ->select('p.id as product_id', 'p.product_name',
                DB::raw('SUM(mr.closing_electrical - mr.opening_electrical) as litres'))
            ->get()
            ->keyBy('product_id');

        // Litres per product for the current shift
        $todayRows = DB::table('meter_readings as mr')
            ->join('pump_nozzles as pn', 'pn.id', '=', 'mr.nozzle_id')
            ->join('products as p', 'p.id', '=', 'pn.product_id')
            ->where('mr.shift_id', $shift->id)
            ->whereNotNull('mr.closing_electrical')
            ->groupBy('p.id', 'p.product_name')
            ->orderBy('p.product_name')
            ->select('p.id as product_id', 'p.product_name',
                DB::raw('SUM(mr.closing_electrical - mr.opening_electrical) as litres'))
            ->get()
            ->keyBy('product_id');

        // Merge: union of all product IDs from both sets
        $productIds = $bfRows->keys()->merge($todayRows->keys())->unique();

        return $productIds->map(function ($pid) use ($bfRows, $todayRows) {
            $bf    = (float) ($bfRows[$pid]->litres    ?? 0);
            $today = (float) ($todayRows[$pid]->litres ?? 0);
            $name  = $bfRows[$pid]->product_name ?? $todayRows[$pid]->product_name;
            return [
                'product_id'   => $pid,
                'product_name' => $name,
                'bf'           => round($bf, 2),
                'today'        => round($today, 2),
                'cumulative'   => round($bf + $today, 2),
            ];
        })->values()->all();
    }

    /**
     * Month-to-date cumulative deliveries (litres) per product for a given shift.
     * Resets on the 1st of each month.
     *
     * Returns an array of:
     *   [ 'product_id', 'product_name', 'bf', 'today', 'cumulative' ]
     */
    public function getCumulativePurchases(Shift $shift): array
    {
        $stationId = $shift->station_id;
        $shiftDate = $shift->shift_date instanceof \Carbon\Carbon
            ? $shift->shift_date
            : \Carbon\Carbon::parse($shift->shift_date);

        $monthStart = $shiftDate->copy()->startOfMonth()->toDateString();

        $bfRows = DB::table('deliveries as d')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->join('shifts as s', 's.id', '=', 'd.shift_id')
            ->where('s.station_id', $stationId)
            ->where('s.shift_date', '>=', $monthStart)
            ->where(function ($q) use ($shift) {
                $q->where('s.shift_date', '<', $shift->shift_date)
                  ->orWhere(function ($q2) use ($shift) {
                      $q2->where('s.shift_date', '=', $shift->shift_date)
                         ->where('s.id', '<', $shift->id);
                  });
            })
            ->groupBy('p.id', 'p.product_name')
            ->orderBy('p.product_name')
            ->select('p.id as product_id', 'p.product_name',
                DB::raw('SUM(d.delivery_quantity) as litres'))
            ->get()
            ->keyBy('product_id');

        $todayRows = DB::table('deliveries as d')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->where('d.shift_id', $shift->id)
            ->groupBy('p.id', 'p.product_name')
            ->orderBy('p.product_name')
            ->select('p.id as product_id', 'p.product_name',
                DB::raw('SUM(d.delivery_quantity) as litres'))
            ->get()
            ->keyBy('product_id');

        $productIds = $bfRows->keys()->merge($todayRows->keys())->unique();

        return $productIds->map(function ($pid) use ($bfRows, $todayRows) {
            $bf    = (float) ($bfRows[$pid]->litres    ?? 0);
            $today = (float) ($todayRows[$pid]->litres ?? 0);
            $name  = $bfRows[$pid]->product_name ?? $todayRows[$pid]->product_name;
            return [
                'product_id'   => $pid,
                'product_name' => $name,
                'bf'           => round($bf, 2),
                'today'        => round($today, 2),
                'cumulative'   => round($bf + $today, 2),
            ];
        })->values()->all();
    }

    public function getTodayShifts(Station $station): array
    {
        $today = now()->toDateString();
        $shifts = Shift::where('station_id', $station->id)
            ->where('shift_date', $today)
            ->with(['meterReadings.nozzle.product', 'tankDips.tank', 'dailySalesRecord'])
            ->get();

        return $shifts->toArray();
    }
}

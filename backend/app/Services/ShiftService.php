<?php

namespace App\Services;

use App\Models\MeterReading;
use App\Models\PumpNozzle;
use App\Models\Shift;
use App\Models\Station;
use Illuminate\Support\Facades\Auth;

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

        $shift = Shift::create([
            'station_id' => $station->id,
            'shift_date' => $date,
            'shift_type' => $shiftType,
            'opened_at'  => now(),
            'opened_by'  => Auth::id(),
            'status'     => 'open',
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

    public function getTodayShifts(Station $station): array
    {
        $today = now()->toDateString();
        $shifts = Shift::where('station_id', $station->id)
            ->where('shift_date', $today)
            ->with(['meterReadings.product', 'tankDips.tank', 'dailySalesRecord'])
            ->get();

        return $shifts->toArray();
    }
}

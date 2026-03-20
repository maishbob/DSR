<?php

namespace App\Http\Controllers;

use App\Models\MeterReading;
use App\Models\PumpNozzle;
use App\Models\Shift;
use App\Services\AuditService;
use Illuminate\Http\Request;

class MeterReadingController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Save closing readings for a nozzle.
     * The opening readings were pre-seeded when the shift was opened.
     * Immediately updates nozzle's last_* so the next shift gets correct openings.
     */
    public function store(Request $request, Shift $shift)
    {
        $this->denyIfLocked($shift);

        $validated = $request->validate([
            'nozzle_id'          => 'required|exists:pump_nozzles,id',
            'closing_mechanical' => 'required|numeric|min:0',
            'closing_electrical' => 'required|numeric|min:0',
            'closing_shs'        => 'nullable|numeric|min:0',
        ]);

        // Ensure nozzle belongs to this station
        $nozzle = PumpNozzle::where('id', $validated['nozzle_id'])
            ->where('station_id', $shift->station_id)
            ->firstOrFail();

        // Find or create the pre-seeded reading
        $reading = MeterReading::firstOrCreate(
            ['shift_id' => $shift->id, 'nozzle_id' => $nozzle->id],
            [
                'opening_mechanical' => $nozzle->last_mech ?? 0,
                'opening_electrical' => $nozzle->last_elec ?? 0,
                'opening_shs'        => $nozzle->last_shs,
                'entered_by'         => auth()->id(),
            ]
        );

        // Correct stale zero openings (shift opened before nozzle readings were set)
        $openingMech = (float) $reading->opening_mechanical;
        $openingElec = (float) $reading->opening_electrical;
        $openingShs  = $reading->opening_shs;

        if ($openingElec == 0 && $nozzle->last_elec > 0) {
            $openingMech = (float) ($nozzle->last_mech ?? 0);
            $openingElec = (float) ($nozzle->last_elec ?? 0);
            $openingShs  = $nozzle->last_shs;
        }

        $old = $reading->toArray();

        $reading->update([
            'opening_mechanical' => $openingMech,
            'opening_electrical' => $openingElec,
            'opening_shs'        => $openingShs,
            'closing_mechanical' => $validated['closing_mechanical'],
            'closing_electrical' => $validated['closing_electrical'],
            'closing_shs'        => $validated['closing_shs'] ?? null,
            'litres_sold'        => round(
                (float) $validated['closing_electrical'] - $openingElec,
                3
            ),
            'shs_sold' => $validated['closing_shs'] !== null
                ? round((float) $validated['closing_shs'] - (float) $openingShs, 2)
                : null,
            'entered_by' => auth()->id(),
        ]);

        // Immediately push closing values to nozzle so next shift opens correctly
        $nozzle->update([
            'last_mech' => $validated['closing_mechanical'],
            'last_elec' => $validated['closing_electrical'],
            'last_shs'  => $validated['closing_shs'] ?? null,
        ]);

        $this->audit->log('upserted', $reading, $old, $reading->fresh()->toArray(), $shift->station_id);

        return back()->with('success', 'Meter reading saved.');
    }

    public function update(Request $request, MeterReading $meterReading)
    {
        $this->denyIfLocked($meterReading->shift);

        $validated = $request->validate([
            'closing_mechanical' => 'required|numeric|min:0',
            'closing_electrical' => 'required|numeric|min:0',
            'closing_shs'        => 'nullable|numeric|min:0',
        ]);

        $old = $meterReading->toArray();

        $meterReading->update([
            'closing_mechanical' => $validated['closing_mechanical'],
            'closing_electrical' => $validated['closing_electrical'],
            'closing_shs'        => $validated['closing_shs'] ?? null,
            'litres_sold'        => round(
                (float) $validated['closing_electrical'] - (float) $meterReading->opening_electrical,
                3
            ),
            'shs_sold' => $validated['closing_shs'] !== null
                ? round((float) $validated['closing_shs'] - (float) $meterReading->opening_shs, 2)
                : null,
        ]);

        // Keep nozzle last_* in sync
        $meterReading->nozzle->update([
            'last_mech' => $validated['closing_mechanical'],
            'last_elec' => $validated['closing_electrical'],
            'last_shs'  => $validated['closing_shs'] ?? null,
        ]);

        $this->audit->log('updated', $meterReading, $old, $meterReading->fresh()->toArray(), $meterReading->shift->station_id);

        return back()->with('success', 'Meter reading updated.');
    }

    /**
     * Clear closing readings — reverts nozzle last_* to this reading's opening values.
     */
    public function destroy(MeterReading $meterReading)
    {
        $this->denyIfLocked($meterReading->shift);

        $old = $meterReading->toArray();

        $meterReading->update([
            'closing_mechanical' => null,
            'closing_electrical' => null,
            'closing_shs'        => null,
            'litres_sold'        => null,
            'shs_sold'           => null,
        ]);

        // Revert nozzle to the opening readings of this shift
        $meterReading->nozzle->update([
            'last_mech' => $meterReading->opening_mechanical,
            'last_elec' => $meterReading->opening_electrical,
            'last_shs'  => $meterReading->opening_shs,
        ]);

        $this->audit->log('cleared', $meterReading, $old, $meterReading->fresh()->toArray(), $meterReading->shift->station_id);

        return back()->with('success', 'Meter reading cleared.');
    }

    private function denyIfLocked(Shift $shift): void
    {
        if ($shift->isLocked()) abort(403, 'Shift is locked.');
    }
}

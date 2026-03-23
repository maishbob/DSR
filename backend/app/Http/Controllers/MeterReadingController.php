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
     * Save closing readings for a nozzle in a shift.
     *
     * Opening values are derived from the previous shift's closing reading for
     * the same nozzle — making the shift chain self-consistent and removing
     * reliance on manually-set nozzle.last_* fields as source of truth.
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

        $nozzle = PumpNozzle::where('id', $validated['nozzle_id'])
            ->where('station_id', $shift->station_id)
            ->firstOrFail();

        [$openingMech, $openingElec, $openingShs] = $this->deriveOpenings($nozzle, $shift);

        $reading = MeterReading::firstOrCreate(
            ['shift_id' => $shift->id, 'nozzle_id' => $nozzle->id],
            [
                'opening_mechanical' => $openingMech,
                'opening_electrical' => $openingElec,
                'opening_shs'        => $openingShs,
                'entered_by'         => auth()->id(),
            ]
        );

        $old = $reading->wasRecentlyCreated ? null : $reading->toArray();

        // Correct stale zero openings (row created before nozzle readings were set)
        if (!$reading->wasRecentlyCreated && (float)$reading->opening_electrical == 0 && $openingElec > 0) {
            $reading->opening_mechanical = $openingMech;
            $reading->opening_electrical = $openingElec;
            $reading->opening_shs        = $openingShs;
        }

        $reading->closing_mechanical = $validated['closing_mechanical'];
        $reading->closing_electrical = $validated['closing_electrical'];
        $reading->closing_shs        = $validated['closing_shs'] ?? null;
        $reading->litres_sold        = round(
            (float)$validated['closing_electrical'] - (float)$reading->opening_electrical, 3
        );
        $reading->shs_sold = $validated['closing_shs'] !== null
            ? round((float)$validated['closing_shs'] - (float)$reading->opening_shs, 2)
            : null;
        $reading->entered_by = auth()->id();
        $reading->save();

        // Keep nozzle cache in sync (used for UI display only)
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
        $this->denyIfRecordLocked($meterReading);

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
                (float)$validated['closing_electrical'] - (float)$meterReading->opening_electrical, 3
            ),
            'shs_sold' => $validated['closing_shs'] !== null
                ? round((float)$validated['closing_shs'] - (float)$meterReading->opening_shs, 2)
                : null,
        ]);

        $meterReading->nozzle->update([
            'last_mech' => $validated['closing_mechanical'],
            'last_elec' => $validated['closing_electrical'],
            'last_shs'  => $validated['closing_shs'] ?? null,
        ]);

        $this->audit->log('updated', $meterReading, $old, $meterReading->fresh()->toArray(), $meterReading->shift->station_id);

        return back()->with('success', 'Meter reading updated.');
    }

    /**
     * Clear closing readings.
     * Reverts nozzle.last_* to the opening values of this reading so the
     * shift chain remains consistent.
     */
    public function destroy(MeterReading $meterReading)
    {
        $this->denyIfLocked($meterReading->shift);
        $this->denyIfRecordLocked($meterReading);

        $old = $meterReading->toArray();

        $meterReading->update([
            'closing_mechanical' => null,
            'closing_electrical' => null,
            'closing_shs'        => null,
            'litres_sold'        => null,
            'shs_sold'           => null,
        ]);

        $meterReading->nozzle->update([
            'last_mech' => $meterReading->opening_mechanical,
            'last_elec' => $meterReading->opening_electrical,
            'last_shs'  => $meterReading->opening_shs,
        ]);

        $this->audit->log('cleared', $meterReading, $old, $meterReading->fresh()->toArray(), $meterReading->shift->station_id);

        return back()->with('success', 'Meter reading cleared.');
    }

    // -------------------------------------------------------------------------

    /**
     * Derive opening readings for a nozzle in a given shift.
     *
     * Priority:
     *  1. Closing values of the most recent prior reading for this nozzle
     *  2. Nozzle.last_* (set manually in Settings or at station setup)
     *  3. Zero (absolute fallback for brand-new nozzles)
     *
     * @return array{float, float, float|null}
     */
    private function deriveOpenings(PumpNozzle $nozzle, Shift $shift): array
    {
        $prev = MeterReading::where('nozzle_id', $nozzle->id)
            ->whereNotNull('closing_electrical')
            ->whereHas('shift', function ($q) use ($shift) {
                $q->where('station_id', $shift->station_id)
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

        if ($prev) {
            return [
                (float)$prev->closing_mechanical,
                (float)$prev->closing_electrical,
                $prev->closing_shs !== null ? (float)$prev->closing_shs : null,
            ];
        }

        return [
            (float)($nozzle->last_mech ?? 0),
            (float)($nozzle->last_elec ?? 0),
            $nozzle->last_shs !== null ? (float)$nozzle->last_shs : null,
        ];
    }

    private function denyIfLocked(Shift $shift): void
    {
        if ($shift->isLocked()) abort(403, 'Shift is locked — this DSR has been finalised.');
    }

    private function denyIfRecordLocked(MeterReading $reading): void
    {
        if ($reading->is_locked) abort(403, 'This reading has been locked by DSR approval.');
    }
}

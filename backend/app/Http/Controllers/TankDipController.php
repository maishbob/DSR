<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Models\TankDip;
use App\Services\AuditService;
use Illuminate\Http\Request;

class TankDipController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function store(Request $request, Shift $shift)
    {
        if ($shift->isLocked()) abort(403, 'Shift is locked.');

        $validated = $request->validate([
            'tank_id'    => 'required|exists:tanks,id',
            'dip_type'   => 'required|in:opening,closing',
            'dip_volume' => 'required|numeric|min:0',
        ]);

        $dip = TankDip::updateOrCreate(
            [
                'tank_id'  => $validated['tank_id'],
                'shift_id' => $shift->id,
                'dip_type' => $validated['dip_type'],
            ],
            [
                'dip_volume' => $validated['dip_volume'],
                'entered_by' => auth()->id(),
            ]
        );

        $this->audit->log('upserted', $dip, null, $dip->toArray(), $shift->station_id);

        return back()->with('success', 'Tank dip saved.');
    }

    public function update(Request $request, TankDip $tankDip)
    {
        if ($tankDip->shift->isLocked()) abort(403, 'Shift is locked.');

        $validated = $request->validate([
            'dip_volume' => 'required|numeric|min:0',
        ]);

        $old = $tankDip->toArray();
        $tankDip->update($validated);
        $this->audit->log('updated', $tankDip, $old, $tankDip->toArray(), $tankDip->shift->station_id);

        return back()->with('success', 'Tank dip updated.');
    }
}

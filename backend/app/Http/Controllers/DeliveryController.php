<?php

namespace App\Http\Controllers;

use App\Models\Delivery;
use App\Models\Shift;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DeliveryController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request)
    {
        $station = $request->user()->station;
        $deliveries = Delivery::where('station_id', $station->id)
            ->with(['product', 'tank'])
            ->orderByDesc('delivery_date')
            ->paginate(30);

        return Inertia::render('Deliveries/Index', [
            'deliveries' => $deliveries,
            'station'    => $station,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id'        => 'required|exists:products,id',
            'tank_id'           => 'required|exists:tanks,id',
            'shift_id'          => 'nullable|exists:shifts,id',
            'delivery_date'     => 'required|date',
            'supplier_name'     => 'required|string|max:255',
            'waybill_number'    => 'nullable|string|max:100',
            'delivery_quantity' => 'required|numeric|min:0.001',
            'tank_dip_before'   => 'nullable|numeric|min:0',
            'tank_dip_after'    => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string',
        ]);

        $station = $request->user()->station;

        if (isset($validated['shift_id'])) {
            $shift = Shift::findOrFail($validated['shift_id']);
            if ($shift->isLocked()) abort(403, 'Shift is locked.');
        }

        $delivery = Delivery::create(array_merge($validated, [
            'station_id' => $station->id,
            'entered_by' => auth()->id(),
        ]));

        $this->audit->log('created', $delivery, null, $delivery->toArray(), $station->id);

        return back()->with('success', 'Delivery recorded.');
    }

    public function update(Request $request, Delivery $delivery)
    {
        if ($delivery->shift?->isLocked()) abort(403, 'Shift is locked.');

        $validated = $request->validate([
            'supplier_name'     => 'required|string|max:255',
            'waybill_number'    => 'nullable|string|max:100',
            'delivery_quantity' => 'required|numeric|min:0.001',
            'tank_dip_before'   => 'nullable|numeric|min:0',
            'tank_dip_after'    => 'nullable|numeric|min:0',
            'notes'             => 'nullable|string',
        ]);

        $old = $delivery->toArray();
        $delivery->update($validated);
        $this->audit->log('updated', $delivery, $old, $delivery->toArray(), $delivery->station_id);

        return back()->with('success', 'Delivery updated.');
    }

    public function destroy(Delivery $delivery)
    {
        if ($delivery->shift?->isLocked()) abort(403, 'Shift is locked.');

        $this->audit->log('deleted', $delivery, $delivery->toArray(), null, $delivery->station_id);
        $delivery->delete();

        return back()->with('success', 'Delivery deleted.');
    }
}

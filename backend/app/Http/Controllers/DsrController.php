<?php

namespace App\Http\Controllers;

use App\Models\DailySalesRecord;
use App\Models\Shift;
use App\Services\DsrService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DsrController extends Controller
{
    public function __construct(private readonly DsrService $dsrService) {}

    public function index(Request $request)
    {
        $station = $request->user()->station;

        $records = DailySalesRecord::where('station_id', $station->id)
            ->orderByDesc('shift_date')
            ->orderBy('shift_type')
            ->paginate(30);

        return Inertia::render('Dsr/Index', [
            'records' => $records,
            'station' => $station,
        ]);
    }

    public function show(DailySalesRecord $dsr)
    {
        $dsr->load([
            'lineItems.product',
            'lineItems.tank',
            'shift.meterReadings.product',
            'shift.tankDips.tank',
            'shift.deliveries.product',
            'shift.creditSales.creditCustomer',
            'shift.creditSales.product',
            'approvedBy',
            'adjustments.createdBy',
            'station',
        ]);

        return Inertia::render('Dsr/Show', [
            'dsr' => $dsr,
        ]);
    }

    public function approve(Request $request, DailySalesRecord $dsr)
    {
        if (!$request->user()->isManager()) {
            abort(403, 'Only managers can approve DSRs.');
        }

        if ($dsr->locked) {
            return back()->withErrors(['dsr' => 'DSR is already locked.']);
        }

        $this->dsrService->approveDsr($dsr, $request->user()->id);

        return redirect()->route('dsr.show', $dsr)
            ->with('success', 'DSR approved and locked.');
    }

    public function storeAdjustment(Request $request, DailySalesRecord $dsr)
    {
        if (!$dsr->locked) {
            return back()->withErrors(['dsr' => 'Adjustments can only be added to locked DSRs.']);
        }

        $validated = $request->validate([
            'adjustment_type' => 'required|string|max:100',
            'reason'          => 'required|string',
            'original_value'  => 'nullable|numeric',
            'corrected_value' => 'nullable|numeric',
            'metadata'        => 'nullable|array',
        ]);

        $dsr->adjustments()->create(array_merge($validated, [
            'station_id' => $dsr->station_id,
            'created_by' => auth()->id(),
        ]));

        return back()->with('success', 'Adjustment recorded.');
    }
}

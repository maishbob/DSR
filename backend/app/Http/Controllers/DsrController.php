<?php

namespace App\Http\Controllers;

use App\Models\DailySalesRecord;
use App\Services\AuditService;
use App\Services\DsrService;
use App\Services\VarianceEngine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DsrController extends Controller
{
    public function __construct(
        private readonly DsrService $dsrService,
        private readonly VarianceEngine $varianceEngine,
        private readonly AuditService $audit,
    ) {}

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
            'shift.meterReadings.nozzle.product',
            'shift.tankDips.tank',
            'shift.deliveries.product',
            'shift.creditSales.creditCustomer',
            'shift.creditSales.product',
            'shift.expenses',
            'shift.cardPayments',
            'shift.posTransactions',
            'approvedBy',
            'adjustments.createdBy',
            'station',
        ]);

        return Inertia::render('Dsr/Show', [
            'dsr'            => $dsr,
            'varianceLabels' => [
                'ok'       => $this->varianceEngine->statusLabel('ok'),
                'warning'  => $this->varianceEngine->statusLabel('warning'),
                'critical' => $this->varianceEngine->statusLabel('critical'),
            ],
        ]);
    }

    /**
     * Approve and lock a DSR.
     *
     * CRITICAL variance: requires override_reason in the request body.
     * Returns validation errors (with variance_status) when blocked.
     */
    public function approve(Request $request, DailySalesRecord $dsr)
    {
        abort_unless($request->user()->isManager(), 403, 'Only managers can approve DSRs.');

        if ($dsr->locked) {
            return back()->withErrors(['dsr' => 'DSR is already locked.']);
        }

        $validated = $request->validate([
            'override_reason' => 'nullable|string|max:1000',
        ]);

        try {
            $this->dsrService->approveDsr(
                $dsr,
                $request->user()->id,
                $validated['override_reason'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors([
                'dsr'             => $e->getMessage(),
                'variance_status' => $dsr->variance_status,
            ]);
        }

        $this->audit->log('approved', $dsr, null, [
            'dsr_number'      => $dsr->dsr_number,
            'shift_date'      => (string) $dsr->shift_date,
            'shift_type'      => $dsr->shift_type,
            'variance_status' => $dsr->variance_status,
            'override_reason' => $validated['override_reason'] ?? null,
        ], $dsr->station_id);

        return redirect()->route('dsr.show', $dsr)
            ->with('success', 'DSR approved and locked.');
    }

    /**
     * Reopen a locked DSR so corrections can be made before re-approval.
     * Manager-only. Requires an explicit reason.
     */
    public function reopen(Request $request, DailySalesRecord $dsr)
    {
        abort_unless($request->user()->isManager(), 403, 'Only managers can reopen DSRs.');

        if (!$dsr->locked) {
            return back()->withErrors(['dsr' => 'This DSR is not locked.']);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $this->dsrService->reopenDsr($dsr, $validated['reason']);

        $this->audit->log('reopened', $dsr, null, [
            'dsr_number' => $dsr->dsr_number ?? $dsr->id,
            'shift_date' => (string) $dsr->shift_date,
            'shift_type' => $dsr->shift_type,
            'reason'     => $validated['reason'],
        ], $dsr->station_id);

        return redirect()->route('shifts.show', $dsr->shift_id)
            ->with('success', 'DSR reopened. Make your corrections then regenerate and re-approve.');
    }

    /**
     * Store an adjustment on a locked DSR.
     * Corrections to live data must be made before generating the DSR.
     * Post-lock corrections go through this endpoint as compensating entries.
     */
    public function storeAdjustment(Request $request, DailySalesRecord $dsr)
    {
        abort_unless($request->user()->isManager(), 403, 'Only managers can add adjustments.');

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

        $adjustment = $dsr->adjustments()->create(array_merge($validated, [
            'station_id' => $dsr->station_id,
            'created_by' => auth()->id(),
        ]));

        $this->audit->log('created', $adjustment, null, $adjustment->toArray(), $dsr->station_id);

        return back()->with('success', 'Adjustment recorded.');
    }
}

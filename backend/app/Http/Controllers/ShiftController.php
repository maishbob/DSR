<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Services\CashReconciliationService;
use App\Services\DsrService;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly DsrService $dsrService,
        private readonly CashReconciliationService $cashService,
    ) {}

    public function index(Request $request): Response
    {
        $station = $request->user()->station;
        $date = $request->get('date', now()->toDateString());

        $shifts = Shift::where('station_id', $station->id)
            ->where('shift_date', $date)
            ->with([
                'meterReadings.nozzle.product',
                'tankDips.tank.product',
                'deliveries.product',
                'creditSales.creditCustomer',
                'oilSales.shopProduct',
                'cardPayments',
                'posTransactions',
                'expenses',
                'dailySalesRecord',
                'openedBy',
            ])
            ->get();

        return Inertia::render('Shifts/Index', [
            'shifts'  => $shifts,
            'date'    => $date,
            'station' => $station,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'shift_date' => 'required|date',
            'shift_type' => 'required|in:day,night',
        ]);

        $station = $request->user()->station;
        $shift = $this->shiftService->openShift(
            $station,
            $validated['shift_type'],
            $validated['shift_date']
        );

        return redirect()->route('shifts.show', $shift)
            ->with('success', 'Shift opened successfully.');
    }

    public function show(Shift $shift): Response
    {
        $this->authorizeStation($shift);

        $shift->load([
            'meterReadings.nozzle.product',
            'tankDips.tank.product',
            'deliveries.product.priceHistories',
            'creditSales.creditCustomer',
            'creditSales.product',
            'oilSales.shopProduct',
            'cardPayments',
            'posTransactions',
            'expenses',
            'dailySalesRecord.lineItems.product',
            'openedBy',
            'closedBy',
        ]);

        $station = $shift->station()->with([
            'products.priceHistories',
            'tanks.product',
            'pumpNozzles.product',
            'pumpNozzles.tank',
            'shopProducts',
            'creditCustomers',
        ])->first();

        return Inertia::render('Shifts/Show', [
            'shift'              => $shift,
            'station'            => $station,
            'cashReconciliation' => $this->cashService->calculate($shift),
        ]);
    }

    /**
     * Save the operator's actual cash count and MPESA total for the shift.
     *
     * This is the only write path for actual_cash and mpesa_amount.
     * It recalculates and persists the cash_variance_status immediately
     * so it survives page reloads and is available during DSR generation.
     */
    public function updateCash(Request $request, Shift $shift)
    {
        $this->authorizeStation($shift);

        if ($shift->isLocked()) {
            return back()->withErrors(['cash' => 'Shift is locked.']);
        }

        $validated = $request->validate([
            'actual_cash'  => 'required|numeric|min:0',
            'mpesa_amount' => 'nullable|numeric|min:0',
        ]);

        // Persist MPESA first so calculate() uses the new value
        $shift->update([
            'mpesa_amount' => $validated['mpesa_amount'] ?? 0,
        ]);

        // Recalculate with fresh MPESA value
        $recon = $this->cashService->calculate($shift->fresh());

        $shift->update([
            'actual_cash'          => $validated['actual_cash'],
            'cash_variance_status' => $recon['variance_status'],
        ]);

        $this->logCashSubmission($shift, $recon);

        return back()->with('success', 'Cash count saved.');
    }

    public function generateDsr(Request $request, Shift $shift)
    {
        $this->authorizeStation($shift);

        $dsr = $this->dsrService->generateForShift($shift);

        return redirect()->route('dsr.show', $dsr)
            ->with('success', 'DSR generated successfully.');
    }

    // -------------------------------------------------------------------------

    private function authorizeStation(Shift $shift): void
    {
        $user = auth()->user();
        if ($shift->station_id !== $user->station_id && !$user->isOwner()) {
            abort(403);
        }
    }

    private function logCashSubmission(Shift $shift, array $recon): void
    {
        \App\Models\AuditLog::create([
            'user_id'    => auth()->id(),
            'station_id' => $shift->station_id,
            'action'     => 'cash_count_submitted',
            'model_type' => 'Shift',
            'model_id'   => $shift->id,
            'old_values' => null,
            'new_values' => [
                'actual_cash'    => $recon['actual_cash'],
                'expected_cash'  => $recon['expected_cash'],
                'variance'       => $recon['variance'],
                'variance_status'=> $recon['variance_status'],
            ],
            'ip_address' => request()->ip(),
        ]);
    }
}

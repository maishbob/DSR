<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Services\CashReconciliationService;
use App\Services\DsrService;
use App\Services\ShiftService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $date = $request->get('date');

        $query = Shift::where('station_id', $station->id)
            ->select('shifts.*')
            ->addSelect([
                'fuel_sales_total' => DB::table('meter_readings')
                    ->join('pump_nozzles', 'pump_nozzles.id', '=', 'meter_readings.nozzle_id')
                    ->join('price_histories', function ($join) {
                        $join->on('price_histories.product_id', '=', 'pump_nozzles.product_id')
                            ->whereNull('price_histories.effective_to');
                    })
                    ->whereColumn('meter_readings.shift_id', 'shifts.id')
                    ->selectRaw('SUM(meter_readings.litres_sold * price_histories.price_per_litre)')
            ])
            ->withSum('oilSales as oil_sales_total', 'total_value')
            ->withCount('expenses')
            ->with(['dailySalesRecord', 'openedBy'])
            ->orderByDesc('shift_date')
            ->orderByDesc('shift_type');

        if ($date) {
            $query->where('shift_date', $date);
        }

        $shifts = $query->paginate(20)->withQueryString();

        return Inertia::render('Shifts/Index', [
            'shifts'  => $shifts,
            'date'    => $date ?? '',
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
        $this->checkShiftAccess($shift);

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
        $this->checkShiftAccess($shift);

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
        $this->checkShiftAccess($shift);

        $dsr = $this->dsrService->generateForShift($shift);

        return redirect()->route('dsr.show', $dsr)
            ->with('success', 'DSR generated successfully.');
    }

    // -------------------------------------------------------------------------

    protected function checkShiftAccess(Shift $shift): void
    {
        $user = auth()->user();

        if ($shift->station_id === $user->station_id) {
            return;
        }

        if ($user->isOwner() && $user->ownsStation($shift->station_id)) {
            return;
        }

        abort(403);
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
                'variance_status' => $recon['variance_status'],
            ],
            'ip_address' => request()->ip(),
        ]);
    }
}

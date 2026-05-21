<?php

namespace App\Http\Controllers;

use App\Models\CreditSale;
use App\Models\MeterReading;
use App\Models\Shift;
use App\Models\TankDip;
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
                    ->join('price_histories', 'price_histories.product_id', '=', 'pump_nozzles.product_id')
                    ->whereColumn('price_histories.effective_from', '<=', 'shifts.shift_date')
                    ->where(function ($q) {
                        $q->whereNull('price_histories.effective_to')
                            ->orWhereColumn('price_histories.effective_to', '>=', 'shifts.shift_date');
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

        try {
            $shift = $this->shiftService->openShift(
                $station,
                $validated['shift_type'],
                $validated['shift_date']
            );
        } catch (\RuntimeException $e) {
            return back()->withErrors(['shift_date' => $e->getMessage()]);
        }

        return redirect()->route('dsr.view-by-number', $shift->dsr_number)
            ->with('success', 'DSR opened successfully.');
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
            'creditCustomers' => fn($q) => $q->withSum('creditSales', 'total_value')
                                              ->withSum('payments', 'amount'),
        ])->first();

        return Inertia::render('Shifts/Show', [
            'shift'              => $shift,
            'station'            => $station,
            'cashReconciliation' => $this->cashService->calculate($shift),
            'cumulativeSales'     => $this->shiftService->getCumulativeSales($shift),
            'cumulativePurchases' => $this->shiftService->getCumulativePurchases($shift),
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

    public function destroy(Request $request, Shift $shift)
    {
        abort_unless($request->user()->isManager(), 403, 'Only managers can delete DSRs.');
        $this->checkShiftAccess($shift);

        $hasRecords = $shift->meterReadings()->exists()
            || $shift->oilSales()->exists()
            || $shift->creditSales()->exists()
            || $shift->expenses()->exists()
            || $shift->cardPayments()->exists()
            || $shift->posTransactions()->exists()
            || $shift->deliveries()->exists()
            || $shift->tankDips()->exists()
            || $shift->dailySalesRecord()->exists();

        if ($hasRecords) {
            return back()->withErrors(['delete' => 'Cannot delete a DSR that has records.']);
        }

        $shift->delete();

        return redirect()->route('dsr.index')->with('success', 'DSR deleted.');
    }

    public function generateDsr(Request $request, Shift $shift)
    {
        $this->checkShiftAccess($shift);

        $dsr = $this->dsrService->generateForShift($shift);

        return redirect()->route('dsr.show', $dsr)
            ->with('success', 'DSR generated successfully.');
    }

    /**
     * Unlock a locked shift. Manager-only.
     *
     * If a DSR record exists, delegates to DsrService::reopenDsr so all
     * ledger entries and approval fields are cleaned up properly.
     * For legacy shifts with no DSR record, unlocks records directly.
     */
    public function unlock(Request $request, Shift $shift)
    {
        abort_unless($request->user()->isManager(), 403, 'Only managers can unlock shifts.');
        $this->checkShiftAccess($shift);

        if (!$shift->isLocked()) {
            return back()->withErrors(['unlock' => 'This shift is not locked.']);
        }

        $validated = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $dsr = $shift->dailySalesRecord;

        if ($dsr) {
            $this->dsrService->reopenDsr($dsr, $validated['reason']);
        } else {
            DB::transaction(function () use ($shift) {
                MeterReading::where('shift_id', $shift->id)->update(['is_locked' => false]);
                TankDip::where('shift_id', $shift->id)->update(['is_locked' => false]);
                CreditSale::where('shift_id', $shift->id)->update(['is_locked' => false]);
                $shift->update(['status' => 'open']);
            });
        }

        \App\Models\AuditLog::create([
            'user_id'    => $request->user()->id,
            'station_id' => $shift->station_id,
            'action'     => 'shift_unlocked',
            'model_type' => 'Shift',
            'model_id'   => $shift->id,
            'old_values' => null,
            'new_values' => ['reason' => $validated['reason']],
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Shift unlocked. Make your corrections, then regenerate and re-approve the DSR.');
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

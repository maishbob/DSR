<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use App\Services\DsrService;
use App\Services\ShiftService; // still used for openShift
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShiftController extends Controller
{
    public function __construct(
        private readonly ShiftService $shiftService,
        private readonly DsrService $dsrService,
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
            'shifts' => $shifts,
            'date'   => $date,
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
            'shift'   => $shift,
            'station' => $station,
        ]);
    }

    public function generateDsr(Request $request, Shift $shift)
    {
        $this->authorizeStation($shift);

        $dsr = $this->dsrService->generateForShift($shift);

        return redirect()->route('dsr.show', $dsr)
            ->with('success', 'DSR generated successfully.');
    }

    private function authorizeStation(Shift $shift): void
    {
        $user = auth()->user();
        if ($shift->station_id !== $user->station_id && !$user->isOwner()) {
            abort(403);
        }
    }
}

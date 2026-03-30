<?php

namespace App\Http\Controllers;

use App\Models\PosTransaction;
use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PosTransactionController extends Controller
{
    public function index(Request $request)
    {
        $station = $request->user()->station;

        $transactions = PosTransaction::where('station_id', $station->id)
            ->orderByDesc('trans_date')
            ->orderByDesc('id')
            ->paginate(50);

        return Inertia::render('Pos/Index', [
            'transactions' => $transactions,
        ]);
    }

    public function update(Request $request, PosTransaction $posTransaction)
    {
        $validated = $request->validate([
            'trans_date' => 'required|date',
            'reference'  => 'required|string|max:50',
            'amount'     => 'required|numeric|min:0.01',
        ]);

        $posTransaction->update($validated);
        return back()->with('success', 'POS transaction updated.');
    }

    public function store(Request $request, Shift $shift)
    {
        abort_if($shift->isLocked(), 403, 'Shift is locked.');

        $validated = $request->validate([
            'reference' => 'required|string|max:50',
            'amount'    => 'required|numeric|min:0.01',
        ]);

        PosTransaction::create(array_merge($validated, [
            'shift_id'   => $shift->id,
            'station_id' => $shift->station_id,
            'trans_date' => $shift->shift_date,
            'entered_by' => auth()->id(),
        ]));

        return back()->with('success', 'POS transaction recorded.');
    }

    public function destroy(PosTransaction $posTransaction)
    {
        abort_if($posTransaction->shift->isLocked(), 403, 'Shift is locked.');
        $posTransaction->delete();
        return back()->with('success', 'POS transaction removed.');
    }
}

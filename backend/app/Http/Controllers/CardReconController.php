<?php

namespace App\Http\Controllers;

use App\Models\CardRecon;
use App\Models\CardReconLine;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CardReconController extends Controller
{
    public function index(Request $request)
    {
        $station = $request->user()->station;

        $recons = CardRecon::where('station_id', $station->id)
            ->with(['lines' => fn($q) => $q->orderBy('trans_date')->orderBy('id')])
            ->withSum('lines', 'amount')
            ->orderByDesc('recon_date')
            ->orderByDesc('id')
            ->paginate(50);

        $recons->through(function ($r) {
            $r->total_amount = (float) ($r->lines_sum_amount ?? 0);
            return $r;
        });

        return Inertia::render('CardRecons/Index', [
            'recons' => $recons,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'card_name'  => 'required|string|max:50',
            'batch_ref'  => 'nullable|string|max:30',
            'recon_date' => 'required|date',
        ]);

        $station = $request->user()->station;
        $recon = CardRecon::create(array_merge($validated, [
            'station_id' => $station->id,
            'created_by' => auth()->id(),
        ]));

        return back()->with('success', 'Recon batch created.')->with('recon_id', $recon->id);
    }

    public function update(Request $request, CardRecon $cardRecon)
    {
        $validated = $request->validate([
            'card_name'  => 'required|string|max:50',
            'batch_ref'  => 'nullable|string|max:30',
            'recon_date' => 'required|date',
        ]);

        $cardRecon->update($validated);
        return back()->with('success', 'Recon updated.');
    }

    public function destroy(CardRecon $cardRecon)
    {
        $cardRecon->delete();
        return back()->with('success', 'Recon deleted.');
    }

    // ── Lines ────────────────────────────────────────────────
    public function storeLine(Request $request, CardRecon $cardRecon)
    {
        $validated = $request->validate([
            'trans_date' => 'required|date',
            'ref'        => 'nullable|string|max:50',
            'amount'     => 'required|numeric|min:0.01',
        ]);

        CardReconLine::create(array_merge($validated, [
            'card_recon_id' => $cardRecon->id,
        ]));

        return back()->with('success', 'Transaction added.');
    }

    public function destroyLine(CardReconLine $cardReconLine)
    {
        $cardReconLine->delete();
        return back()->with('success', 'Transaction removed.');
    }
}

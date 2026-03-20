<?php

namespace App\Http\Controllers;

use App\Models\CardPayment;
use App\Models\Shift;
use Illuminate\Http\Request;

class CardPaymentController extends Controller
{
    public function store(Request $request, Shift $shift)
    {
        abort_if($shift->isLocked(), 403, 'Shift is locked.');

        $validated = $request->validate([
            'card_name'  => 'required|string|max:50',
            'trans_date' => 'required|date',
            'reference'  => 'required|string|max:50',
            'amount'     => 'required|numeric|min:0.01',
            'recon_date' => 'nullable|date',
            'batch_ref'  => 'nullable|string|max:50',
        ]);

        CardPayment::create(array_merge($validated, [
            'shift_id'   => $shift->id,
            'entered_by' => auth()->id(),
        ]));

        return back()->with('success', 'Card payment recorded.');
    }

    public function destroy(CardPayment $cardPayment)
    {
        abort_if($cardPayment->shift->isLocked(), 403, 'Shift is locked.');
        $cardPayment->delete();
        return back()->with('success', 'Card payment removed.');
    }
}

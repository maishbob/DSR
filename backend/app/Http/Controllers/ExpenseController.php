<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Shift;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function store(Request $request, Shift $shift)
    {
        abort_if($shift->isLocked(), 403, 'Shift is locked.');

        $validated = $request->validate([
            'expense_item' => 'required|string|max:150',
            'amount'       => 'required|numeric|min:0.01',
        ]);

        Expense::create(array_merge($validated, [
            'shift_id'   => $shift->id,
            'entered_by' => auth()->id(),
        ]));

        return back()->with('success', 'Expense recorded.');
    }

    public function destroy(Expense $expense)
    {
        abort_if($expense->shift->isLocked(), 403, 'Shift is locked.');
        $expense->delete();
        return back()->with('success', 'Expense removed.');
    }
}

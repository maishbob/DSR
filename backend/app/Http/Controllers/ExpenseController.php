<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Models\Shift;
use App\Services\AuditService;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function store(Request $request, Shift $shift)
    {
        abort_if($shift->isLocked(), 403, 'Shift is locked.');

        $validated = $request->validate([
            'expense_item' => 'required|string|max:150',
            'amount'       => 'required|numeric|min:0.01',
        ]);

        $expense = Expense::create(array_merge($validated, [
            'shift_id'   => $shift->id,
            'entered_by' => auth()->id(),
        ]));

        $this->audit->log('created', $expense, null, $expense->toArray(), $shift->station_id);

        return back()->with('success', 'Expense recorded.');
    }

    public function destroy(Expense $expense)
    {
        abort_if($expense->shift->isLocked(), 403, 'Shift is locked.');

        $snapshot  = $expense->toArray();
        $stationId = $expense->shift->station_id;
        $expense->delete();

        $this->audit->log('deleted', $expense, $snapshot, null, $stationId);

        return back()->with('success', 'Expense removed.');
    }
}

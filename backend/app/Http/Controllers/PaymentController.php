<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PaymentController extends Controller
{
    public function __construct(private readonly AuditService $audit) {}

    public function index(Request $request)
    {
        $station = $request->user()->station;

        $payments = Payment::where('payments.station_id', $station->id)
            ->join('credit_customers', 'credit_customers.id', '=', 'payments.credit_customer_id')
            ->select('payments.*', 'credit_customers.customer_name')
            ->orderByDesc('payment_date')
            ->orderByDesc('payments.id')
            ->paginate(50);

        return Inertia::render('Payments/Index', [
            'payments' => $payments,
        ]);
    }

    public function update(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'payment_date'   => 'required|date',
            'receipt_no'     => 'nullable|string|max:30',
            'trans_type'     => 'nullable|in:receipts,fuel,lpg,pos,invoice',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,mpesa,bank_transfer,cheque,rtgs,equity_card,other',
            'shift_id'       => ['nullable', Rule::exists('shifts', 'id')->where('station_id', $payment->station_id)],
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string',
        ]);

        $original = $payment->only(array_keys($validated));
        $payment->update($validated);

        $this->audit->log('updated', $payment, $original, $payment->only(array_keys($validated)), $payment->station_id);

        return back()->with('success', 'Payment updated.');
    }

    public function destroy(Payment $payment)
    {
        $snapshot  = $payment->toArray();
        $stationId = $payment->station_id;
        $payment->delete();

        $this->audit->log('deleted', $payment, $snapshot, null, $stationId);

        return back()->with('success', 'Payment deleted.');
    }
}

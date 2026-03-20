<?php

namespace App\Http\Controllers;

use App\Models\CreditCustomer;
use App\Models\CreditSale;
use App\Models\Payment;
use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CreditCustomerController extends Controller
{
    public function index(Request $request)
    {
        $station = $request->user()->station;
        $customers = CreditCustomer::where('station_id', $station->id)
            ->withSum('creditSales', 'total_value')
            ->withSum('payments', 'amount')
            ->orderBy('customer_name')
            ->get()
            ->map(function ($c) {
                $c->balance = round(
                    (float)($c->initial_opening_balance ?? 0)
                    + (float)($c->credit_sales_sum_total_value ?? 0)
                    - (float)($c->payments_sum_amount ?? 0),
                    2
                );
                return $c;
            });

        return Inertia::render('Credits/Index', [
            'customers' => $customers,
            'station'   => $station,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'customer_name'            => 'required|string|max:255',
            'contact'                  => 'nullable|string|max:100',
            'phone'                    => 'nullable|string|max:20',
            'email'                    => 'nullable|email|max:255',
            'address'                  => 'nullable|string|max:255',
            'city'                     => 'nullable|string|max:100',
            'pin'                      => 'nullable|string|max:30',
            'vat_number'               => 'nullable|string|max:30',
            'is_withholding_vat_agent' => 'boolean',
            'credit_limit'             => 'required|numeric|min:0',
            'discount_multiplier'      => 'nullable|numeric|min:0|max:1',
            'initial_opening_balance'  => 'nullable|numeric',
        ]);

        $station = $request->user()->station;
        CreditCustomer::create(array_merge($validated, ['station_id' => $station->id]));

        return back()->with('success', 'Customer added.');
    }

    public function show(Request $request, CreditCustomer $creditCustomer)
    {
        $creditCustomer->load([
            'creditSales.product',
            'creditSales.shift.dailySalesRecord',
            'payments',
        ]);

        $fromDate = $request->input('from_date');
        $toDate   = $request->input('to_date');

        // Build unified transaction list
        $sales = $creditCustomer->creditSales->map(fn($s) => [
            'id'          => 'sale_' . $s->id,
            'date'        => $s->shift?->shift_date ?? substr($s->created_at, 0, 10),
            'type'        => 'sale',
            'description' => ($s->product?->product_name ?? 'Sale')
                             . ($s->vehicle_plate ? ' – ' . $s->vehicle_plate : ''),
            'reference'   => $s->shift?->dailySalesRecord?->dsr_number ?? null,
            'sale_type'   => $s->type,
            'quantity'    => $s->quantity,
            'price'       => $s->price_applied,
            'debit'       => (float)$s->total_value,
            'credit'      => null,
        ]);

        $payments = $creditCustomer->payments->map(fn($p) => [
            'id'          => 'pay_' . $p->id,
            'date'        => $p->payment_date,
            'type'        => 'payment',
            'description' => ucwords(str_replace('_', ' ', $p->payment_method)),
            'reference'   => $p->reference,
            'sale_type'   => null,
            'quantity'    => null,
            'price'       => null,
            'debit'       => null,
            'credit'      => (float)$p->amount,
        ]);

        $all = $sales->concat($payments)->sortBy('date')->values();

        // Compute brought-forward balance before from_date
        $broughtForward = (float)($creditCustomer->initial_opening_balance ?? 0);
        if ($fromDate) {
            foreach ($all as $tx) {
                if ($tx['date'] < $fromDate) {
                    $broughtForward += ($tx['debit'] ?? 0) - ($tx['credit'] ?? 0);
                }
            }
            $transactions = $all->filter(
                fn($tx) => $tx['date'] >= $fromDate && (!$toDate || $tx['date'] <= $toDate)
            )->values();
        } else {
            $transactions = $all;
        }

        return Inertia::render('Credits/Show', [
            'customer'        => $creditCustomer,
            'transactions'    => $transactions,
            'brought_forward' => $broughtForward,
            'from_date'       => $fromDate,
            'to_date'         => $toDate,
        ]);
    }

    public function update(Request $request, CreditCustomer $creditCustomer)
    {
        $validated = $request->validate([
            'customer_name'            => 'required|string|max:255',
            'contact'                  => 'nullable|string|max:100',
            'phone'                    => 'nullable|string|max:20',
            'email'                    => 'nullable|email|max:255',
            'address'                  => 'nullable|string|max:255',
            'city'                     => 'nullable|string|max:100',
            'pin'                      => 'nullable|string|max:30',
            'vat_number'               => 'nullable|string|max:30',
            'is_withholding_vat_agent' => 'boolean',
            'credit_limit'             => 'required|numeric|min:0',
            'discount_multiplier'      => 'nullable|numeric|min:0|max:1',
            'initial_opening_balance'  => 'nullable|numeric',
            'is_active'                => 'boolean',
        ]);

        $creditCustomer->update($validated);

        return back()->with('success', 'Customer updated.');
    }

    // Record a credit sale
    public function storeSale(Request $request)
    {
        $validated = $request->validate([
            'credit_customer_id' => 'required|exists:credit_customers,id',
            'product_id'         => 'required|exists:products,id',
            'shift_id'           => 'required|exists:shifts,id',
            'type'               => 'nullable|in:fuel,oil,other',
            'quantity'           => 'required|numeric|min:0.001',
            'price_applied'      => 'required|numeric|min:0',
            'vehicle_plate'      => 'nullable|string|max:20',
            'notes'              => 'nullable|string',
        ]);

        $shift = Shift::findOrFail($validated['shift_id']);
        if ($shift->isLocked()) abort(403, 'Shift is locked.');

        CreditSale::create(array_merge($validated, ['entered_by' => auth()->id()]));

        return back()->with('success', 'Credit sale recorded.');
    }

    // Delete a credit sale
    public function destroySale(CreditSale $creditSale)
    {
        $creditSale->delete();
        return back()->with('success', 'Credit sale deleted.');
    }

    // Record a payment
    public function storePayment(Request $request, CreditCustomer $creditCustomer)
    {
        $validated = $request->validate([
            'payment_date'   => 'required|date',
            'receipt_no'     => 'nullable|string|max:30',
            'trans_type'     => 'nullable|in:receipts,fuel,lpg,pos,invoice',
            'amount'         => 'required|numeric|min:0.01',
            'payment_method' => 'nullable|in:cash,mpesa,bank_transfer,cheque,rtgs,equity_card,other',
            'reference'      => 'nullable|string|max:100',
            'notes'          => 'nullable|string',
        ]);

        Payment::create(array_merge($validated, [
            'credit_customer_id' => $creditCustomer->id,
            'station_id'         => $creditCustomer->station_id,
            'received_by'        => auth()->id(),
        ]));

        return back()->with('success', 'Payment recorded.');
    }
}

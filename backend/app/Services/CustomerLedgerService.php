<?php

namespace App\Services;

use App\Models\CreditCustomer;

class CustomerLedgerService
{
    public function getBalanceSummary(CreditCustomer $customer): array
    {
        $totalPurchases = (float) $customer->creditSales()->sum('total_value');
        $totalPaid = (float) $customer->payments()->sum('amount');
        $balance = round((float) $customer->initial_opening_balance + $totalPurchases - $totalPaid, 2);

        return [
            'initial_opening_balance' => $customer->initial_opening_balance,
            'total_purchases'         => $totalPurchases,
            'total_paid'              => $totalPaid,
            'balance'                 => $balance,
        ];
    }
}

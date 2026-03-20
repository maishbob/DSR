<?php

namespace App\Http\Controllers;

use App\Models\CreditCustomer;
use App\Services\ReportService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function wetStock(Request $request)
    {
        $station  = $request->user()->station;
        $from     = $request->get('from', now()->startOfMonth()->toDateString());
        $to       = $request->get('to', now()->toDateString());

        $rows = $this->reportService->wetStockReport($station, $from, $to);

        return Inertia::render('Reports/WetStock', [
            'rows'    => $rows,
            'station' => $station,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    public function salesSummary(Request $request)
    {
        $station = $request->user()->station;
        $from    = $request->get('from', now()->startOfMonth()->toDateString());
        $to      = $request->get('to', now()->toDateString());

        $rows = $this->reportService->salesSummary($station, $from, $to);

        return Inertia::render('Reports/SalesSummary', [
            'rows'    => $rows,
            'station' => $station,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    public function deliveryHistory(Request $request)
    {
        $station = $request->user()->station;
        $from    = $request->get('from', now()->startOfMonth()->toDateString());
        $to      = $request->get('to', now()->toDateString());

        $rows = $this->reportService->deliveryHistory($station, $from, $to);

        return Inertia::render('Reports/DeliveryHistory', [
            'rows'    => $rows,
            'station' => $station,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    public function creditStatement(Request $request, CreditCustomer $creditCustomer)
    {
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to   = $request->get('to', now()->toDateString());

        $data = $this->reportService->creditCustomerStatement($creditCustomer, $from, $to);

        return Inertia::render('Reports/CreditStatement', [
            'data' => $data,
            'from' => $from,
            'to'   => $to,
        ]);
    }

    public function varianceReport(Request $request)
    {
        $station = $request->user()->station;
        $from    = $request->get('from', now()->startOfMonth()->toDateString());
        $to      = $request->get('to', now()->toDateString());

        $rows = $this->reportService->wetStockReport($station, $from, $to);
        $variances = array_filter($rows, fn($r) => abs($r['variance']) > 0.001);

        return Inertia::render('Reports/Variance', [
            'rows'    => array_values($variances),
            'station' => $station,
            'from'    => $from,
            'to'      => $to,
        ]);
    }
}

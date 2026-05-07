<?php

namespace App\Http\Controllers;

use App\Exports\CreditStatementExport;
use App\Exports\DeliveryHistoryExport;
use App\Exports\SalesSummaryExport;
use App\Exports\VarianceExport;
use App\Exports\WetStockExport;
use App\Models\CreditCustomer;
use App\Services\ReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reportService) {}

    public function wetStock(Request $request)
    {
        $station = $request->user()->station;
        $from    = $request->get('from', now()->startOfMonth()->toDateString());
        $to      = $request->get('to', now()->toDateString());

        $rows = $this->reportService->wetStockReport($station, $from, $to);

        if ($export = $this->exportFormat($request)) {
            $filename = $this->filename('wet-stock', $station, $from, $to);
            return $export === 'pdf'
                ? Pdf::loadView('exports.pdf.wet-stock', compact('rows', 'station', 'from', 'to'))
                    ->setPaper('a4', 'landscape')
                    ->download("{$filename}.pdf")
                : Excel::download(new WetStockExport($rows), "{$filename}.xlsx");
        }

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

        if ($export = $this->exportFormat($request)) {
            $filename = $this->filename('sales-summary', $station, $from, $to);
            return $export === 'pdf'
                ? Pdf::loadView('exports.pdf.sales-summary', compact('rows', 'station', 'from', 'to'))
                    ->setPaper('a4', 'landscape')
                    ->download("{$filename}.pdf")
                : Excel::download(new SalesSummaryExport($rows), "{$filename}.xlsx");
        }

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

        if ($export = $this->exportFormat($request)) {
            $filename = $this->filename('delivery-history', $station, $from, $to);
            return $export === 'pdf'
                ? Pdf::loadView('exports.pdf.delivery-history', compact('rows', 'station', 'from', 'to'))
                    ->setPaper('a4', 'landscape')
                    ->download("{$filename}.pdf")
                : Excel::download(new DeliveryHistoryExport($rows), "{$filename}.xlsx");
        }

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

        if ($export = $this->exportFormat($request)) {
            $slug = Str::slug($creditCustomer->customer_name ?: 'customer');
            $filename = "credit-statement-{$slug}-{$from}-to-{$to}";
            return $export === 'pdf'
                ? Pdf::loadView('exports.pdf.credit-statement', compact('data', 'from', 'to'))
                    ->setPaper('a4', 'portrait')
                    ->download("{$filename}.pdf")
                : Excel::download(new CreditStatementExport($data, $from, $to), "{$filename}.xlsx");
        }

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
        $variances = array_values(array_filter($rows, fn($r) => abs($r['variance']) > 0.001));

        if ($export = $this->exportFormat($request)) {
            $filename = $this->filename('variance', $station, $from, $to);
            return $export === 'pdf'
                ? Pdf::loadView('exports.pdf.variance', ['rows' => $variances, 'station' => $station, 'from' => $from, 'to' => $to])
                    ->setPaper('a4', 'landscape')
                    ->download("{$filename}.pdf")
                : Excel::download(new VarianceExport($variances), "{$filename}.xlsx");
        }

        return Inertia::render('Reports/Variance', [
            'rows'    => $variances,
            'station' => $station,
            'from'    => $from,
            'to'      => $to,
        ]);
    }

    private function exportFormat(Request $request): ?string
    {
        $format = strtolower((string) $request->get('format', ''));
        return in_array($format, ['pdf', 'xlsx'], true) ? $format : null;
    }

    private function filename(string $report, $station, string $from, string $to): string
    {
        $slug = Str::slug($station->station_name ?? 'station');
        return "{$report}-{$slug}-{$from}-to-{$to}";
    }
}

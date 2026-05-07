<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class SalesSummaryExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        $data = array_map(fn($r) => [
            $r['shift_date'],
            ucfirst($r['shift_type']),
            (float) $r['total_litres_sold'],
            (float) $r['total_revenue'],
            (float) $r['total_cash_sales'],
            (float) $r['total_credit_sales'],
            (float) $r['variance'],
            $r['locked'] ? 'Locked' : 'Draft',
        ], $this->rows);

        if (count($this->rows)) {
            $data[] = [
                'TOTAL',
                '',
                array_sum(array_column($this->rows, 'total_litres_sold')),
                array_sum(array_column($this->rows, 'total_revenue')),
                array_sum(array_column($this->rows, 'total_cash_sales')),
                array_sum(array_column($this->rows, 'total_credit_sales')),
                '',
                '',
            ];
        }

        return $data;
    }

    public function headings(): array
    {
        return ['Date', 'Shift', 'Litres', 'Revenue (KES)', 'Cash (KES)', 'Credit (KES)', 'Variance (L)', 'Status'];
    }

    public function title(): string
    {
        return 'Sales Summary';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:H1')->getFont()->setBold(true);
                $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
                $sheet->freezePane('A2');

                $rowCount = count($this->rows);
                if ($rowCount > 0) {
                    $totalRow = $rowCount + 2;
                    $sheet->getStyle("A{$totalRow}:H{$totalRow}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$totalRow}:H{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F9FAFB');
                }
            },
        ];
    }
}

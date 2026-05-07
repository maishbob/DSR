<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class VarianceExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return array_map(function ($r) {
            $abs = abs($r['variance']);
            $sev = $abs > 100 ? 'High' : ($abs > 50 ? 'Medium' : 'Low');
            return [
                $r['date'],
                ucfirst($r['shift_type']),
                $r['product'],
                (float) $r['expected_stock'],
                (float) $r['actual_stock'],
                (float) $r['variance'],
                $sev,
            ];
        }, $this->rows);
    }

    public function headings(): array
    {
        return ['Date', 'Shift', 'Product', 'Expected', 'Actual', 'Variance (L)', 'Severity'];
    }

    public function title(): string
    {
        return 'Variance';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:G1')->getFont()->setBold(true);
                $sheet->getStyle('A1:G1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
                $sheet->freezePane('A2');
            },
        ];
    }
}

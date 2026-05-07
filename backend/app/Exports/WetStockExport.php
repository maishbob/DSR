<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class WetStockExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return array_map(fn($r) => [
            $r['date'],
            ucfirst($r['shift_type']),
            $r['product'],
            $r['tank'] ?? '—',
            (float) $r['opening_stock'],
            (float) $r['deliveries'],
            (float) $r['litres_sold'],
            (float) $r['expected_stock'],
            (float) $r['actual_stock'],
            (float) $r['variance'],
        ], $this->rows);
    }

    public function headings(): array
    {
        return ['Date', 'Shift', 'Product', 'Tank', 'Opening', '+ Deliveries', '- Sold', '= Expected', 'Actual Dip', 'Variance'];
    }

    public function title(): string
    {
        return 'Wet Stock';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:J1')->getFont()->setBold(true);
                $sheet->getStyle('A1:J1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
                $sheet->freezePane('A2');
            },
        ];
    }
}

<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class DeliveryHistoryExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return array_map(fn($r) => [
            $r['delivery_date'],
            $r['product']['product_name'] ?? '—',
            $r['tank']['tank_name'] ?? '—',
            $r['supplier_name'] ?? '—',
            $r['waybill_number'] ?? '—',
            (float) $r['delivery_quantity'],
            $r['tank_dip_before'] !== null ? (float) $r['tank_dip_before'] : null,
            $r['tank_dip_after']  !== null ? (float) $r['tank_dip_after']  : null,
            $r['delivery_variance'] !== null ? (float) $r['delivery_variance'] : null,
        ], $this->rows);
    }

    public function headings(): array
    {
        return ['Date', 'Product', 'Tank', 'Supplier', 'Waybill', 'Qty (L)', 'Dip Before', 'Dip After', 'Variance'];
    }

    public function title(): string
    {
        return 'Deliveries';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:I1')->getFont()->setBold(true);
                $sheet->getStyle('A1:I1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
                $sheet->freezePane('A2');
            },
        ];
    }
}

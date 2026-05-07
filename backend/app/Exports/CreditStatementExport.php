<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CreditStatementExport implements FromArray, WithHeadings, WithTitle, ShouldAutoSize, WithEvents
{
    public function __construct(private array $data, private string $from, private string $to) {}

    public function array(): array
    {
        $rows = [];
        foreach ($this->data['transactions'] ?? [] as $txn) {
            $rows[] = [
                $txn['date'],
                ucfirst($txn['type']),
                $txn['product'] ?? '—',
                !empty($txn['quantity']) ? (float) $txn['quantity'] : null,
                $txn['type'] === 'payment' ? abs($txn['amount']) : -abs($txn['amount']),
                (float) $txn['balance'],
            ];
        }
        return $rows;
    }

    public function headings(): array
    {
        return ['Date', 'Type', 'Product', 'Qty (L)', 'Amount (KES)', 'Balance (KES)'];
    }

    public function title(): string
    {
        $name = $this->data['customer']['customer_name'] ?? 'Customer';
        return substr(preg_replace('/[^A-Za-z0-9 ]/', '', $name), 0, 28);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:F1')->getFont()->setBold(true);
                $sheet->getStyle('A1:F1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
                $sheet->freezePane('A2');
            },
        ];
    }
}

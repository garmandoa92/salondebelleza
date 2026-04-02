<?php

namespace App\Exports\SalesExport;

use App\Exports\Concerns\ExcelStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class SummarySheet implements FromArray, WithTitle, WithEvents
{
    use ExcelStyles;

    public function __construct(private Collection $sales, private array $filters) {}

    public function title(): string { return 'Resumen'; }

    public function array(): array
    {
        $total = $this->sales->sum('total');
        $iva = $this->sales->sum('iva_amount');
        $discount = $this->sales->sum('discount_amount');
        $tips = $this->sales->sum('tip');

        return [
            ['Periodo', 'Total ventas', 'Total ingresos', 'IVA cobrado', 'Descuentos', 'Propinas'],
            [
                ($this->filters['date_from'] ?? '-') . ' al ' . ($this->filters['date_to'] ?? '-'),
                $this->sales->count(),
                $total,
                $iva,
                $discount,
                $tips,
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), 1, 6)];
    }
}

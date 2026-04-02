<?php

namespace App\Exports\SalesExport;

use App\Exports\Concerns\ExcelStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class StylistSheet implements FromArray, WithTitle, WithEvents
{
    use ExcelStyles;

    public function __construct(private Collection $sales) {}

    public function title(): string { return 'Por estilista'; }

    public function array(): array
    {
        $data = [];
        foreach ($this->sales as $sale) {
            foreach ($sale->items as $item) {
                $name = $item->stylist->name ?? 'Sin asignar';
                if (!isset($data[$name])) $data[$name] = ['count' => 0, 'total' => 0, 'commission' => 0];
                $data[$name]['count']++;
                $data[$name]['total'] += (float) $item->subtotal;
                if ($item->commission) $data[$name]['commission'] += (float) $item->commission->amount;
            }
        }

        $rows = [['Estilista', 'Servicios realizados', 'Total vendido', 'Comision estimada']];
        foreach ($data as $name => $d) {
            $rows[] = [$name, $d['count'], $d['total'], $d['commission']];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        $count = count($this->array()) - 1;
        return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), $count, 4)];
    }
}

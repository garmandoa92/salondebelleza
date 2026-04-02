<?php

namespace App\Exports\SalesExport;

use App\Exports\Concerns\ExcelStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class PaymentMethodSheet implements FromArray, WithTitle, WithEvents
{
    use ExcelStyles;

    public function __construct(private Collection $sales) {}

    public function title(): string { return 'Por metodo de pago'; }

    public function array(): array
    {
        $methods = ['cash' => 'Efectivo', 'transfer' => 'Transferencia', 'card_debit' => 'Tarjeta debito', 'card_credit' => 'Tarjeta credito', 'other' => 'Otro'];
        $totals = [];
        $counts = [];
        $grandTotal = 0;

        foreach ($this->sales as $sale) {
            foreach ($sale->payment_methods ?? [] as $p) {
                $m = $p['method'] ?? 'cash';
                $totals[$m] = ($totals[$m] ?? 0) + (float) $p['amount'];
                $counts[$m] = ($counts[$m] ?? 0) + 1;
                $grandTotal += (float) $p['amount'];
            }
        }

        $rows = [['Metodo de pago', 'Cantidad ventas', 'Total', '% del total']];
        foreach ($methods as $key => $label) {
            if (($totals[$key] ?? 0) > 0) {
                $pct = $grandTotal > 0 ? ($totals[$key] / $grandTotal) : 0;
                $rows[] = [$label, $counts[$key] ?? 0, $totals[$key], $pct];
            }
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        $count = count($this->array()) - 1;
        return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), $count, 4)];
    }
}

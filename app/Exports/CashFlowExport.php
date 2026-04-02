<?php

namespace App\Exports;

use App\Exports\Concerns\ExcelStyles;
use App\Models\Commission;
use App\Models\Sale;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class CashFlowExport implements FromArray, WithTitle, WithEvents
{
    use ExcelStyles;

    private int $rowCount = 0;

    public function __construct(private array $filters) {}

    public function title(): string { return 'Flujo de caja'; }

    public function array(): array
    {
        $dateFrom = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $this->filters['date_to'] ?? now()->toDateString();

        $sales = Sale::where('status', 'completed')
            ->whereBetween('completed_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->with('client:id,first_name,last_name')
            ->orderBy('completed_at')
            ->get();

        $paidCommissions = Commission::where('status', 'paid')
            ->whereBetween('paid_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->with('stylist:id,name')
            ->orderBy('paid_at')
            ->get();

        $rows = [['Fecha', 'Tipo', 'Concepto', 'Entrada', 'Salida', 'Saldo acumulado']];
        $balance = 0;

        // Merge and sort by date
        $entries = collect();
        foreach ($sales as $s) {
            $entries->push(['date' => $s->completed_at, 'type' => 'Venta', 'concept' => $s->client ? "{$s->client->first_name} {$s->client->last_name}" : 'Sin cliente', 'in' => (float) $s->total, 'out' => 0]);
        }
        foreach ($paidCommissions as $c) {
            $entries->push(['date' => $c->paid_at, 'type' => 'Comision', 'concept' => $c->stylist->name ?? '-', 'in' => 0, 'out' => (float) $c->amount]);
        }

        foreach ($entries->sortBy('date') as $e) {
            $balance += $e['in'] - $e['out'];
            $rows[] = [
                $e['date']->format('d/m/Y'),
                $e['type'],
                $e['concept'],
                $e['in'] > 0 ? $e['in'] : '',
                $e['out'] > 0 ? $e['out'] : '',
                round($balance, 2),
            ];
        }

        $this->rowCount = count($rows) - 1;
        return $rows;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), $this->rowCount, 6)];
    }
}

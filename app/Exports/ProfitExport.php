<?php

namespace App\Exports;

use App\Exports\Concerns\ExcelStyles;
use App\Models\Commission;
use App\Models\Sale;
use App\Models\SaleItem;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class ProfitExport implements WithMultipleSheets
{
    public function __construct(private array $filters) {}

    public function sheets(): array
    {
        $dateFrom = $this->filters['date_from'] ?? now()->startOfMonth()->toDateString();
        $dateTo = $this->filters['date_to'] ?? now()->toDateString();

        $sales = Sale::where('status', 'completed')
            ->whereBetween('completed_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->with(['items.stylist', 'items.commission'])
            ->get();

        $commissions = Commission::whereBetween('created_at', [$dateFrom, $dateTo . ' 23:59:59'])
            ->with('stylist:id,name')
            ->get();

        return [
            new class($sales, $commissions, $this->filters) implements FromArray, WithTitle, WithEvents {
                use ExcelStyles;
                public function __construct(private $sales, private $commissions, private $filters) {}
                public function title(): string { return 'P&L Resumen'; }
                public function array(): array {
                    $serviceIncome = $this->sales->flatMap->items->filter(fn ($i) => ($i->type->value ?? $i->type) === 'service')->sum('subtotal');
                    $productIncome = $this->sales->flatMap->items->filter(fn ($i) => ($i->type->value ?? $i->type) === 'product')->sum('subtotal');
                    $packageIncome = $this->sales->flatMap->items->filter(fn ($i) => ($i->type->value ?? $i->type) === 'package')->sum('subtotal');
                    $totalIncome = $serviceIncome + $productIncome + $packageIncome;
                    $totalCommissions = $this->commissions->sum('amount');
                    $totalIva = $this->sales->sum('iva_amount');
                    $baseIva = $this->sales->sum(fn ($s) => (float) $s->subtotal - (float) $s->discount_amount);
                    return [
                        ['Concepto', 'Monto'],
                        ['INGRESOS', ''],
                        ['  Servicios', (float) $serviceIncome],
                        ['  Venta de productos', (float) $productIncome],
                        ['  Paquetes vendidos', (float) $packageIncome],
                        ['  TOTAL INGRESOS', (float) $totalIncome],
                        ['', ''],
                        ['COMISIONES PAGADAS', ''],
                        ...($this->commissions->groupBy(fn ($c) => $c->stylist->name ?? 'Sin asignar')->map(fn ($g, $n) => ["  {$n}", (float) $g->sum('amount')])->values()->toArray()),
                        ['  TOTAL COMISIONES', (float) $totalCommissions],
                        ['', ''],
                        ['GANANCIA OPERATIVA', (float) ($totalIncome - $totalCommissions)],
                        ['', ''],
                        ['PARA TU CONTADOR', ''],
                        ['  IVA cobrado', (float) $totalIva],
                        ['  Base imponible', (float) $baseIva],
                    ];
                }
                public function registerEvents(): array {
                    return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), 15, 2)];
                }
            },
            new class($sales) implements FromArray, WithTitle, WithEvents {
                use ExcelStyles;
                public function __construct(private $sales) {}
                public function title(): string { return 'Servicios rentables'; }
                public function array(): array {
                    $services = $this->sales->flatMap->items
                        ->filter(fn ($i) => ($i->type->value ?? $i->type) === 'service')
                        ->groupBy('name')
                        ->map(fn ($g, $n) => [
                            'name' => $n, 'count' => $g->count(),
                            'income' => (float) $g->sum('subtotal'),
                            'margin' => (float) $g->sum('subtotal'),
                        ])->sortByDesc('margin')->values();
                    $rows = [['Servicio', 'Veces', 'Ingreso total', 'Margen bruto']];
                    foreach ($services as $s) { $rows[] = [$s['name'], $s['count'], $s['income'], $s['margin']]; }
                    return $rows;
                }
                public function registerEvents(): array {
                    $count = $this->sales->flatMap->items->filter(fn ($i) => ($i->type->value ?? $i->type) === 'service')->groupBy('name')->count();
                    return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), $count, 4)];
                }
            },
        ];
    }
}

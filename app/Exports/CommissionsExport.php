<?php

namespace App\Exports;

use App\Exports\Concerns\ExcelStyles;
use App\Models\Commission;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class CommissionsExport implements WithMultipleSheets
{
    public function __construct(private array $filters) {}

    public function sheets(): array
    {
        $query = Commission::with(['stylist:id,name', 'saleItem.sale:id,completed_at'])
            ->when($this->filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($this->filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($this->filters['stylist_id'] ?? null, fn ($q, $id) => $q->where('stylist_id', $id))
            ->orderBy('created_at');

        $commissions = $query->get();

        return [
            new class($commissions) implements FromArray, WithTitle, WithEvents {
                use ExcelStyles;
                public function __construct(private $data) {}
                public function title(): string { return 'Resumen'; }
                public function array(): array {
                    $byStyleist = $this->data->groupBy(fn ($c) => $c->stylist->name ?? 'Sin asignar');
                    $rows = [['Estilista', 'Servicios', 'Total vendido', 'Comision total', '% Promedio']];
                    foreach ($byStyleist as $name => $group) {
                        $totalSold = $group->sum(fn ($c) => (float) ($c->saleItem->subtotal ?? 0));
                        $totalComm = $group->sum('amount');
                        $avgRate = $group->avg('rate');
                        $rows[] = [$name, $group->count(), $totalSold, (float) $totalComm, round($avgRate, 1) . '%'];
                    }
                    return $rows;
                }
                public function registerEvents(): array {
                    $count = $this->data->groupBy(fn ($c) => $c->stylist->name ?? '')->count();
                    return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), $count, 5)];
                }
            },
            new class($commissions) implements FromArray, WithTitle, WithEvents {
                use ExcelStyles;
                public function __construct(private $data) {}
                public function title(): string { return 'Detalle'; }
                public function array(): array {
                    $rows = [['Fecha', 'Estilista', 'Cliente', 'Servicio', 'Precio', '% Comision', 'Monto comision', 'Estado']];
                    foreach ($this->data as $c) {
                        $rows[] = [
                            $c->created_at->format('d/m/Y'),
                            $c->stylist->name ?? '-',
                            $c->saleItem->sale?->client?->full_name ?? '-',
                            $c->saleItem->name ?? '-',
                            (float) ($c->saleItem->subtotal ?? 0),
                            (float) $c->rate . '%',
                            (float) $c->amount,
                            $c->status->value ?? $c->status ?? 'pending',
                        ];
                    }
                    return $rows;
                }
                public function registerEvents(): array {
                    return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), $this->data->count(), 8)];
                }
            },
        ];
    }
}

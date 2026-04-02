<?php

namespace App\Exports;

use App\Exports\Concerns\ExcelStyles;
use App\Models\Product;
use App\Models\StockMovement;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class InventoryExport implements WithMultipleSheets
{
    public function __construct(private array $filters) {}

    public function sheets(): array
    {
        return [
            new class() implements FromArray, WithTitle, WithEvents {
                use ExcelStyles;
                public function title(): string { return 'Stock actual'; }
                public function array(): array {
                    $products = Product::where('is_active', true)->orderBy('name')->get();
                    $rows = [['Producto', 'SKU', 'Tipo', 'Unidad', 'Stock actual', 'Stock minimo', 'Bajo minimo?', 'Costo unitario', 'Valor en stock', 'Marca']];
                    foreach ($products as $p) {
                        $belowMin = ($p->min_stock > 0 && $p->stock <= $p->min_stock) ? 'SI' : 'NO';
                        $rows[] = [
                            $p->name, $p->sku ?? '', $p->type->value ?? $p->type ?? '',
                            $p->unit->value ?? $p->unit ?? '', (float) $p->stock, (float) $p->min_stock,
                            $belowMin, (float) ($p->cost_price ?? 0),
                            round((float) $p->stock * (float) ($p->cost_price ?? 0), 2),
                            $p->brand ?? '',
                        ];
                    }
                    return $rows;
                }
                public function registerEvents(): array {
                    $count = Product::where('is_active', true)->count();
                    return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), $count, 10)];
                }
            },
            new class($this->filters) implements FromArray, WithTitle, WithEvents {
                use ExcelStyles;
                public function __construct(private $filters) {}
                public function title(): string { return 'Movimientos'; }
                public function array(): array {
                    $query = StockMovement::with(['product:id,name', 'user:id,name'])
                        ->when($this->filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($this->filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))
                        ->orderByDesc('created_at');
                    $movements = $query->get();
                    $rows = [['Fecha', 'Producto', 'Tipo', 'Cantidad', 'Stock antes', 'Stock despues', 'Referencia', 'Usuario']];
                    foreach ($movements as $m) {
                        $rows[] = [
                            $m->created_at->format('d/m/Y'), $m->product->name ?? '-',
                            $m->type->value ?? $m->type ?? '', (float) $m->quantity,
                            (float) ($m->stock_before ?? 0), (float) ($m->stock_after ?? 0),
                            $m->reference ?? '', $m->user->name ?? '',
                        ];
                    }
                    return $rows;
                }
                public function registerEvents(): array {
                    $count = StockMovement::when($this->filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($this->filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))->count();
                    return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), $count, 8)];
                }
            },
        ];
    }
}

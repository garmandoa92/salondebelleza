<?php

namespace App\Exports\SalesExport;

use App\Exports\Concerns\ExcelStyles;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class DetailSheet implements FromArray, WithTitle, WithEvents
{
    use ExcelStyles;

    public function __construct(private Collection $sales) {}

    public function title(): string { return 'Detalle ventas'; }

    public function array(): array
    {
        $headers = ['Fecha', 'Hora', 'Cliente', 'Cedula/RUC', 'Estilista', 'Servicios', 'Productos',
            'Subtotal', 'Descuento', 'IVA', 'Total', 'Propina',
            'Efectivo', 'Transferencia', 'T.Debito', 'T.Credito',
            'N Factura SRI', 'Estado Factura', 'N Autorizacion'];

        $rows = [$headers];
        foreach ($this->sales as $sale) {
            $services = $sale->items->filter(fn ($i) => ($i->type->value ?? $i->type) === 'service')->pluck('name')->implode(', ');
            $products = $sale->items->filter(fn ($i) => ($i->type->value ?? $i->type) === 'product')->pluck('name')->implode(', ');
            $stylistNames = $sale->items->pluck('stylist.name')->filter()->unique()->implode(', ');
            $payments = collect($sale->payment_methods ?? []);
            $inv = $sale->sriInvoice;

            $rows[] = [
                $sale->completed_at?->format('d/m/Y'),
                $sale->completed_at?->format('H:i'),
                $sale->client ? "{$sale->client->first_name} {$sale->client->last_name}" : 'Sin cliente',
                $sale->client->cedula ?? '',
                $stylistNames,
                $services,
                $products,
                (float) $sale->subtotal,
                (float) $sale->discount_amount,
                (float) $sale->iva_amount,
                (float) $sale->total,
                (float) $sale->tip,
                (float) $payments->where('method', 'cash')->sum('amount'),
                (float) $payments->where('method', 'transfer')->sum('amount'),
                (float) $payments->where('method', 'card_debit')->sum('amount'),
                (float) $payments->where('method', 'card_credit')->sum('amount'),
                $inv ? "{$inv->establishment}-{$inv->emission_point}-{$inv->sequential}" : '',
                $inv ? ($inv->sri_status->value ?? $inv->sri_status ?? '') : '',
                $inv->sri_authorization_number ?? '',
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), $this->sales->count(), 19)];
    }
}

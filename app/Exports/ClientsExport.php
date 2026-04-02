<?php

namespace App\Exports;

use App\Exports\Concerns\ExcelStyles;
use App\Models\Client;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class ClientsExport implements FromArray, WithTitle, WithEvents
{
    use ExcelStyles;

    public function __construct(private array $filters) {}

    public function title(): string { return 'Clientes'; }

    public function array(): array
    {
        $query = Client::query()
            ->when(($this->filters['status'] ?? 'active') === 'active', fn ($q) => $q->where('is_active', true));

        $clients = $query->orderBy('first_name')->get();

        $rows = [['Nombre', 'Apellido', 'Telefono', 'Email', 'Cedula',
            'Primera visita', 'Ultima visita', 'Dias sin visitar',
            'Total visitas', 'Total gastado', 'Ticket promedio',
            'Fuente', 'Tags', 'Alergias', 'Puntos fidelidad',
            'Saldo a favor', 'Estado']];

        foreach ($clients as $c) {
            $daysSince = $c->last_visit_at ? now()->diffInDays($c->last_visit_at) : null;
            $avgTicket = $c->visit_count > 0 ? (float) $c->total_spent / $c->visit_count : 0;

            $rows[] = [
                $c->first_name, $c->last_name, $c->phone, $c->email ?? '', $c->cedula ?? '',
                $c->created_at?->format('d/m/Y'), $c->last_visit_at?->format('d/m/Y'),
                $daysSince,
                $c->visit_count, (float) $c->total_spent, round($avgTicket, 2),
                $c->source->value ?? $c->source ?? '',
                implode(', ', $c->tags ?? []),
                $c->allergies ? 'SI' : 'NO',
                $c->loyalty_points,
                (float) $c->balance,
                $c->is_active ? 'Activo' : 'Inactivo',
            ];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        $count = Client::when(($this->filters['status'] ?? 'active') === 'active', fn ($q) => $q->where('is_active', true))->count();
        return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), $count, 17)];
    }
}

<?php

namespace App\Exports;

use App\Exports\Concerns\ExcelStyles;
use App\Models\Appointment;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class AppointmentsExport implements WithMultipleSheets
{
    public function __construct(private array $filters) {}

    public function sheets(): array
    {
        $query = Appointment::with(['client:id,first_name,last_name,phone', 'service:id,name,base_price,duration_minutes', 'stylist:id,name'])
            ->when($this->filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('starts_at', '>=', $d))
            ->when($this->filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('starts_at', '<=', $d))
            ->when($this->filters['stylist_id'] ?? null, fn ($q, $id) => $q->where('stylist_id', $id))
            ->when($this->filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->orderBy('starts_at');

        $appointments = $query->get();

        return [
            new class($appointments) implements FromArray, WithTitle, WithEvents {
                use ExcelStyles;
                public function __construct(private $apts) {}
                public function title(): string { return 'Metricas'; }
                public function array(): array {
                    $total = $this->apts->count();
                    $completed = $this->apts->filter(fn ($a) => ($a->status->value ?? $a->status) === 'completed')->count();
                    $cancelled = $this->apts->filter(fn ($a) => ($a->status->value ?? $a->status) === 'cancelled')->count();
                    $noShow = $this->apts->filter(fn ($a) => ($a->status->value ?? $a->status) === 'no_show')->count();
                    $topService = $this->apts->groupBy('service_id')->sortByDesc(fn ($g) => $g->count())->keys()->first();
                    $topServiceName = $topService ? $this->apts->firstWhere('service_id', $topService)?->service?->name : '-';
                    $topStylist = $this->apts->groupBy('stylist_id')->sortByDesc(fn ($g) => $g->count())->keys()->first();
                    $topStylistName = $topStylist ? $this->apts->firstWhere('stylist_id', $topStylist)?->stylist?->name : '-';
                    return [
                        ['Metrica', 'Valor'],
                        ['Total citas', $total],
                        ['Completadas', $completed . ' (' . ($total > 0 ? round($completed / $total * 100) : 0) . '%)'],
                        ['Canceladas', $cancelled . ' (' . ($total > 0 ? round($cancelled / $total * 100) : 0) . '%)'],
                        ['No-shows', $noShow],
                        ['Servicio mas solicitado', $topServiceName],
                        ['Estilista mas ocupada', $topStylistName],
                    ];
                }
                public function registerEvents(): array {
                    return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), 6, 2)];
                }
            },
            new class($appointments) implements FromArray, WithTitle, WithEvents {
                use ExcelStyles;
                public function __construct(private $apts) {}
                public function title(): string { return 'Detalle citas'; }
                public function array(): array {
                    $rows = [['Fecha', 'Hora inicio', 'Hora fin', 'Duracion', 'Cliente', 'Telefono', 'Servicio', 'Estilista', 'Estado', 'Fuente', 'Precio']];
                    foreach ($this->apts as $a) {
                        $rows[] = [
                            $a->starts_at->format('d/m/Y'), $a->starts_at->format('H:i'), $a->ends_at->format('H:i'),
                            ($a->service->duration_minutes ?? 0) . ' min',
                            $a->client ? "{$a->client->first_name} {$a->client->last_name}" : '-', $a->client->phone ?? '',
                            $a->service->name ?? '-', $a->stylist->name ?? '-',
                            $a->status->value ?? $a->status, $a->source->value ?? $a->source ?? 'manual',
                            (float) ($a->service->base_price ?? 0),
                        ];
                    }
                    return $rows;
                }
                public function registerEvents(): array {
                    return [AfterSheet::class => fn (AfterSheet $e) => $this->styleSheet($e->sheet->getDelegate(), $this->apts->count(), 11)];
                }
            },
        ];
    }
}

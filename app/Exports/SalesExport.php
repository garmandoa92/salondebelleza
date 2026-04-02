<?php

namespace App\Exports;

use App\Exports\Concerns\ExcelStyles;
use App\Models\Sale;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesExport implements WithMultipleSheets
{
    public function __construct(
        private array $filters,
    ) {}

    public function sheets(): array
    {
        $query = Sale::where('status', 'completed')
            ->with(['client', 'items.stylist', 'sriInvoice', 'tipStylist'])
            ->when($this->filters['date_from'] ?? null, fn ($q, $d) => $q->whereDate('completed_at', '>=', $d))
            ->when($this->filters['date_to'] ?? null, fn ($q, $d) => $q->whereDate('completed_at', '<=', $d))
            ->when($this->filters['stylist_id'] ?? null, fn ($q, $id) => $q->whereHas('items', fn ($sq) => $sq->where('stylist_id', $id)))
            ->orderBy('completed_at');

        $sales = $query->get();

        return [
            new SalesExport\SummarySheet($sales, $this->filters),
            new SalesExport\DetailSheet($sales),
            new SalesExport\PaymentMethodSheet($sales),
            new SalesExport\StylistSheet($sales),
        ];
    }
}

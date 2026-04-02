<?php

namespace App\Http\Controllers\Tenant;

use App\Exports\AppointmentsExport;
use App\Exports\CashFlowExport;
use App\Exports\ClientsExport;
use App\Exports\CommissionsExport;
use App\Exports\InventoryExport;
use App\Exports\ProfitExport;
use App\Exports\SalesExport;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function sales(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'stylist_id', 'payment_method', 'status']);
        $name = $this->filename('ventas', $filters);

        return Excel::download(new SalesExport($filters), $name);
    }

    public function profit(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'branch_id']);
        $name = $this->filename('ganancias', $filters);

        return Excel::download(new ProfitExport($filters), $name);
    }

    public function appointments(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'stylist_id', 'status']);
        $name = $this->filename('citas', $filters);

        return Excel::download(new AppointmentsExport($filters), $name);
    }

    public function clients(Request $request)
    {
        $filters = $request->only(['status']);
        $name = $this->filename('clientes', $filters);

        return Excel::download(new ClientsExport($filters), $name);
    }

    public function commissions(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to', 'stylist_id', 'status']);
        $name = $this->filename('comisiones', $filters);

        return Excel::download(new CommissionsExport($filters), $name);
    }

    public function inventory(Request $request)
    {
        $filters = $request->only(['type', 'date_from', 'date_to']);
        $name = $this->filename('inventario', $filters);

        return Excel::download(new InventoryExport($filters), $name);
    }

    public function cashflow(Request $request)
    {
        $filters = $request->only(['date_from', 'date_to']);
        $name = $this->filename('flujo_caja', $filters);

        return Excel::download(new CashFlowExport($filters), $name);
    }

    private function filename(string $type, array $filters): string
    {
        $slug = tenant('id') ?? 'salon';
        $from = str_replace('-', '', $filters['date_from'] ?? now()->startOfMonth()->format('d-m-Y'));
        $to = str_replace('-', '', $filters['date_to'] ?? now()->format('d-m-Y'));

        return "{$type}_{$slug}_{$from}_{$to}.xlsx";
    }
}

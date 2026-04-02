<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessSriDocumentJob;
use App\Models\SriInvoice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SriInvoiceController extends Controller
{
    public function index(Request $request)
    {
        $query = SriInvoice::orderBy('created_at', 'desc');

        if ($status = $request->status) {
            $query->where('sri_status', $status);
        }
        if ($request->date_from) {
            $query->whereDate('issue_date', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('issue_date', '<=', $request->date_to);
        }
        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('sequential', 'like', "%{$search}%")
                    ->orWhere('buyer_identification', 'like', "%{$search}%")
                    ->orWhere('buyer_name', 'like', "%{$search}%")
                    ->orWhere('access_key', 'like', "%{$search}%");
            });
        }

        $invoices = $query->paginate(25);

        // Summary cards
        $allInvoices = SriInvoice::query();
        if ($request->date_from) $allInvoices->whereDate('issue_date', '>=', $request->date_from);
        if ($request->date_to) $allInvoices->whereDate('issue_date', '<=', $request->date_to);

        $summary = [
            'total_amount' => (clone $allInvoices)->where('sri_status', 'authorized')->sum('total'),
            'total_count' => (clone $allInvoices)->count(),
            'iva_generated' => (clone $allInvoices)->where('sri_status', 'authorized')->sum('iva_amount'),
            'pending_count' => (clone $allInvoices)->whereIn('sri_status', ['draft', 'signed', 'sent'])->count(),
        ];

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'summary' => $summary,
            'filters' => $request->only('status', 'date_from', 'date_to', 'search'),
        ]);
    }

    public function show(SriInvoice $invoice)
    {
        $invoice->load(['sale.items.stylist:id,name', 'sale.client:id,first_name,last_name,phone', 'sale.appointment.service:id,name', 'sale.appointment.stylist:id,name']);
        return response()->json($invoice);
    }

    public function ride(SriInvoice $invoice)
    {
        $tenantConfig = [
            'ruc' => tenant()->ruc ?? '0000000000001',
            'razon_social' => tenant()->razon_social ?? tenant()->name,
            'direccion_matriz' => tenant()->address ?? 'Ecuador',
        ];

        $saleItems = $invoice->sale?->items?->map(fn ($i) => $i->toArray())->toArray() ?? [];

        $generator = new \App\Services\Sri\SriRideGenerator();
        $html = $generator->generateHtml($invoice, $tenantConfig, $saleItems);

        return response($html)->header('Content-Type', 'text/html');
    }

    public function xml(SriInvoice $invoice)
    {
        $xml = $invoice->xml_signed ?? $invoice->xml_unsigned ?? '<error>XML no disponible</error>';

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
            'Content-Disposition' => "attachment; filename=\"{$invoice->access_key}.xml\"",
        ]);
    }

    public function retry(SriInvoice $invoice)
    {
        $tenantConfig = [
            'ruc' => tenant()->ruc ?? '0000000000001',
            'razon_social' => tenant()->razon_social ?? tenant()->name,
            'nombre_comercial' => tenant()->name,
            'direccion_matriz' => tenant()->address ?? 'Ecuador',
            'ambiente_sri' => tenant()->settings['ambiente_sri'] ?? 'test',
        ];

        $saleItems = $invoice->sale?->items?->map(fn ($i) => $i->toArray())->toArray() ?? [];
        $payments = $invoice->sale?->payment_methods ?? [['method' => 'cash', 'amount' => (float) $invoice->total]];

        ProcessSriDocumentJob::dispatch($invoice->id, $tenantConfig, $saleItems, $payments);

        return back()->with('success', 'Reintento enviado al SRI.');
    }
}

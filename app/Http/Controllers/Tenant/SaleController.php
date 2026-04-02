<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Package;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Models\Stylist;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SaleController extends Controller
{
    public function __construct(
        private SaleService $saleService
    ) {}

    public function index(Request $request)
    {
        $query = Sale::with(['client:id,first_name,last_name', 'items'])
            ->orderBy('created_at', 'desc');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->date_from) {
            $query->whereDate('completed_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('completed_at', '<=', $request->date_to);
        }

        $branchId = session('current_branch_id');
        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return Inertia::render('Sales/Index', [
            'sales' => $query->paginate(25),
            'summary' => $this->saleService->getDaySummary(),
            'filters' => $request->only('status', 'date_from', 'date_to'),
        ]);
    }

    public function create(Request $request)
    {
        $appointment = null;
        $preClient = null;
        $preItems = [];

        if ($request->filled('appointment_id')) {
            $appointment = Appointment::with(['client:id,first_name,last_name,phone', 'service:id,name,base_price,iva_rate', 'stylist:id,name'])
                ->find($request->appointment_id);

            if ($appointment) {
                $preClient = $appointment->client;
                $preItems = [[
                    'type' => 'service',
                    'reference_id' => $appointment->service?->id,
                    'name' => $appointment->service?->name,
                    'quantity' => 1,
                    'unit_price' => (float) ($appointment->service?->base_price ?? 0),
                    'subtotal' => (float) ($appointment->service?->base_price ?? 0),
                    'iva_rate' => $appointment->service?->iva_rate,
                    'iva_amount' => 0,
                    'discount_amount' => 0,
                    'stylist_id' => $appointment->stylist_id,
                ]];
            }
        }

        return Inertia::render('Sales/Create', [
            'services' => Service::where('is_visible', true)->get(['id', 'name', 'base_price', 'iva_rate', 'duration_minutes']),
            'products' => Product::where('is_active', true)->where('type', 'sale')->get(['id', 'name', 'sale_price', 'iva_rate', 'stock']),
            'packages' => Package::where('is_active', true)->orderBy('name')->get(['id', 'name', 'price', 'type']),
            'stylists' => Stylist::where('is_active', true)->get(['id', 'name', 'color']),
            'appointmentId' => $appointment?->id,
            'preClient' => $preClient,
            'preItems' => $preItems,
        ]);
    }

    public function show(Sale $sale)
    {
        $sale->load([
            'client:id,first_name,last_name,phone,email',
            'items.stylist:id,name,color',
            'sriInvoice',
            'appointment.service:id,name',
        ]);

        // Attach commissions per item
        $commissions = \App\Models\Commission::whereIn('sale_item_id', $sale->items->pluck('id'))->get();
        $sale->items->each(function ($item) use ($commissions) {
            $item->commission = $commissions->firstWhere('sale_item_id', $item->id);
        });

        return response()->json($sale);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'appointment_id' => ['nullable', 'uuid'],
            'client_id' => ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'in:service,product,package'],
            'items.*.reference_id' => ['required', 'uuid'],
            'items.*.name' => ['required', 'string'],
            'items.*.quantity' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
            'items.*.subtotal' => ['required', 'numeric'],
            'items.*.iva_amount' => ['nullable', 'numeric'],
            'items.*.discount_amount' => ['nullable', 'numeric'],
            'items.*.stylist_id' => ['nullable', 'uuid'],
            'subtotal' => ['required', 'numeric'],
            'discount_amount' => ['nullable', 'numeric'],
            'discount_type' => ['nullable', 'string'],
            'discount_reason' => ['nullable', 'string'],
            'iva_amount' => ['required', 'numeric'],
            'total' => ['required', 'numeric'],
            'tip' => ['nullable', 'numeric'],
            'tip_stylist_id' => ['nullable', 'uuid'],
            'payment_methods' => ['required', 'array', 'min:1'],
            'payment_methods.*.method' => ['required', 'string'],
            'payment_methods.*.amount' => ['required', 'numeric', 'min:0'],
            'advance_applied' => ['nullable', 'numeric', 'min:0'],
        ]);

        $data['branch_id'] = session('current_branch_id');
        $sale = $this->saleService->createFromCheckout($data, auth()->id());

        // Apply client balance if requested
        if ($request->filled('advance_applied') && (float) $request->advance_applied > 0 && $data['client_id']) {
            $client = \App\Models\Client::find($data['client_id']);
            if ($client && (float) $client->balance > 0) {
                $applyAmount = min((float) $request->advance_applied, (float) $client->balance);
                (new \App\Services\AdvanceService())->applyBalanceToSale($client, $sale, $applyAmount);
            }
        }

        return response()->json(['success' => true, 'sale_id' => $sale->id]);
    }

    public function invoice(Request $request, Sale $sale)
    {
        $invoiceData = $request->validate([
            'buyer_identification_type' => ['required', 'string'],
            'buyer_identification' => ['nullable', 'string'],
            'buyer_name' => ['nullable', 'string'],
            'buyer_email' => ['nullable', 'email'],
            'establishment' => ['nullable', 'string'],
            'emission_point' => ['nullable', 'string'],
        ]);

        $tenantConfig = tenant()->settings ?? [];
        $tenantConfig['ruc'] = tenant()->ruc ?? '0000000000001';
        $tenantConfig['razon_social'] = tenant()->razon_social ?? tenant()->name;
        $tenantConfig['nombre_comercial'] = tenant()->name;
        $tenantConfig['direccion_matriz'] = tenant()->address ?? 'Ecuador';
        $tenantConfig['ambiente_sri'] = $tenantConfig['ambiente_sri'] ?? 'test';

        $invoice = $this->saleService->createInvoice($sale, $invoiceData, $tenantConfig);

        return response()->json(['success' => true, 'invoice_id' => $invoice->id]);
    }

    public function retryInvoice(\App\Models\SriInvoice $invoice)
    {
        $invoice->load('sale.items');
        $tenantConfig = tenant()->settings ?? [];
        $tenantConfig['ruc'] = tenant()->ruc ?? '0000000000001';
        $tenantConfig['razon_social'] = tenant()->razon_social ?? tenant()->name;
        $tenantConfig['nombre_comercial'] = tenant()->name;
        $tenantConfig['direccion_matriz'] = tenant()->address ?? 'Ecuador';

        $saleItems = $invoice->sale?->items?->map(fn ($i) => $i->toArray())->toArray() ?? [];
        $payments = $invoice->sale?->payment_methods ?? [['method' => 'cash', 'amount' => (float) $invoice->total]];

        $invoice->update(['sri_status' => 'draft', 'error_message' => null]);

        \App\Jobs\ProcessSriDocumentJob::dispatch(
            $invoice->id, $tenantConfig, $saleItems, $payments,
        );

        return response()->json(['success' => true, 'message' => 'Reintentando envio al SRI...']);
    }

    public function summary()
    {
        return response()->json($this->saleService->getDaySummary());
    }

    public function checkoutData()
    {
        return response()->json([
            'services' => Service::where('is_visible', true)->get(['id', 'name', 'base_price', 'iva_rate', 'duration_minutes']),
            'products' => Product::where('is_active', true)->where('type', 'sale')->get(['id', 'name', 'sale_price', 'iva_rate', 'stock']),
            'packages' => \App\Models\Package::where('is_active', true)->orderBy('name')->get(['id', 'name', 'price', 'type']),
            'stylists' => Stylist::where('is_active', true)->get(['id', 'name', 'color']),
        ]);
    }
}

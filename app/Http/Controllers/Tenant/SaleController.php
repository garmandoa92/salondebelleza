<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
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

        return Inertia::render('Sales/Index', [
            'sales' => $query->paginate(25),
            'summary' => $this->saleService->getDaySummary(),
            'filters' => $request->only('status', 'date_from', 'date_to'),
        ]);
    }

    public function show(Sale $sale)
    {
        $sale->load(['client', 'items.stylist:id,name', 'sriInvoice', 'appointment.service:id,name']);

        return response()->json($sale);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'appointment_id' => ['nullable', 'uuid'],
            'client_id' => ['nullable', 'uuid'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.type' => ['required', 'in:service,product'],
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
        ]);

        $sale = $this->saleService->createFromCheckout($data, auth()->id());

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

    public function summary()
    {
        return response()->json($this->saleService->getDaySummary());
    }

    public function checkoutData()
    {
        return response()->json([
            'services' => Service::where('is_visible', true)->get(['id', 'name', 'base_price', 'duration_minutes']),
            'products' => Product::where('is_active', true)->where('type', 'sale')->get(['id', 'name', 'sale_price', 'stock']),
            'stylists' => Stylist::where('is_active', true)->get(['id', 'name', 'color']),
        ]);
    }
}

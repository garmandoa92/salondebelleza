<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockMovement;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StockMovementController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}

    public function index(Request $request)
    {
        $query = StockMovement::with(['product:id,name,sku,unit', 'creator:id,name'])
            ->orderBy('created_at', 'desc');

        if ($request->product_id) {
            $query->where('product_id', $request->product_id);
        }
        if ($request->type) {
            $query->where('type', $request->type);
        }
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        return Inertia::render('Products/Movements', [
            'movements' => $query->paginate(25),
            'products' => Product::orderBy('name')->get(['id', 'name', 'sku']),
            'filters' => $request->only('product_id', 'type', 'date_from', 'date_to'),
        ]);
    }

    public function purchase(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->productService->registerPurchase($data);

        return back()->with('success', 'Compra registrada.');
    }

    public function adjustment(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'uuid', 'exists:products,id'],
            'quantity' => ['required', 'numeric'],
            'notes' => ['required', 'string', 'max:500'],
        ]);

        $this->productService->registerAdjustment($data);

        return back()->with('success', 'Ajuste registrado.');
    }
}

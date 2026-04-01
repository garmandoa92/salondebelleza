<?php

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\ProductService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function __construct(
        private ProductService $productService
    ) {}

    public function index(Request $request)
    {
        $products = $this->productService->getFiltered($request);
        $suppliers = Product::whereNotNull('supplier')->distinct()->pluck('supplier');

        return Inertia::render('Products/Index', [
            'products' => $products,
            'suppliers' => $suppliers,
            'filters' => $request->only('search', 'type', 'supplier', 'low_stock'),
        ]);
    }

    public function create()
    {
        return Inertia::render('Products/Form');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:50', 'unique:products,sku'],
            'barcode' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'in:use,sale'],
            'unit' => ['required', 'string'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'stock' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['boolean'],
        ]);

        $this->productService->store(
            collect($data)->except('image')->toArray(),
            $request->file('image'),
        );

        return redirect()->route('tenant.products.index', ['tenant' => tenant('id')])
            ->with('success', 'Producto creado.');
    }

    public function edit(Product $product)
    {
        return Inertia::render('Products/Form', [
            'product' => $product,
        ]);
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['nullable', 'string', 'max:50', "unique:products,sku,{$product->id}"],
            'barcode' => ['nullable', 'string', 'max:50'],
            'type' => ['required', 'in:use,sale'],
            'unit' => ['required', 'string'],
            'cost_price' => ['nullable', 'numeric', 'min:0'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'min_stock' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'image' => ['nullable', 'image', 'max:2048'],
            'is_active' => ['boolean'],
        ]);

        $this->productService->update(
            $product,
            collect($data)->except('image')->toArray(),
            $request->file('image'),
        );

        return redirect()->route('tenant.products.index', ['tenant' => tenant('id')])
            ->with('success', 'Producto actualizado.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('tenant.products.index', ['tenant' => tenant('id')])
            ->with('success', 'Producto eliminado.');
    }
}

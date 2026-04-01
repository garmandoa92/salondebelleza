<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductService
{
    public function getFiltered(Request $request)
    {
        $query = Product::query();

        if ($search = $request->search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($type = $request->type) {
            $query->where('type', $type);
        }

        if ($request->supplier) {
            $query->where('supplier', $request->supplier);
        }

        if ($request->low_stock) {
            $query->whereColumn('stock', '<=', 'min_stock');
        }

        return $query->orderBy('name')->paginate(25);
    }

    public function store(array $data, $image = null): Product
    {
        if ($image) {
            $data['image_path'] = $image->store('products', 'public');
        }

        $product = Product::create($data);

        // Create initial stock movement if stock > 0
        if (($data['stock'] ?? 0) > 0) {
            StockMovement::create([
                'product_id' => $product->id,
                'type' => 'initial',
                'quantity' => $data['stock'],
                'unit_cost' => $data['cost_price'] ?? null,
                'notes' => 'Stock inicial',
                'created_by' => auth()->id(),
            ]);
        }

        return $product;
    }

    public function update(Product $product, array $data, $image = null): Product
    {
        if ($image) {
            if ($product->image_path) {
                Storage::disk('public')->delete($product->image_path);
            }
            $data['image_path'] = $image->store('products', 'public');
        }

        $product->update($data);
        return $product;
    }

    public function registerPurchase(array $data): StockMovement
    {
        $product = Product::findOrFail($data['product_id']);

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'type' => 'purchase',
            'quantity' => abs((float) $data['quantity']),
            'unit_cost' => $data['unit_cost'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        $product->increment('stock', abs((float) $data['quantity']));

        if ($data['unit_cost'] ?? null) {
            $product->update(['cost_price' => $data['unit_cost']]);
        }

        return $movement;
    }

    public function registerAdjustment(array $data): StockMovement
    {
        $product = Product::findOrFail($data['product_id']);

        $movement = StockMovement::create([
            'product_id' => $product->id,
            'type' => 'adjustment',
            'quantity' => (float) $data['quantity'],
            'notes' => $data['notes'] ?? 'Ajuste manual',
            'created_by' => auth()->id(),
        ]);

        $product->increment('stock', (float) $data['quantity']);

        return $movement;
    }

    public function consumeForService(string $productId, float $quantity, ?string $referenceType = null, ?string $referenceId = null, string $userId = ''): void
    {
        $product = Product::find($productId);
        if (! $product) return;

        StockMovement::create([
            'product_id' => $product->id,
            'type' => 'consumption',
            'quantity' => -abs($quantity),
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => 'Consumo automatico por servicio',
            'created_by' => $userId ?: auth()->id(),
        ]);

        $product->decrement('stock', abs($quantity));
    }
}

<?php

namespace App\Models;

use App\Enums\ProductType;
use App\Enums\ProductUnit;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'barcode',
        'type',
        'unit',
        'cost_price',
        'sale_price',
        'stock',
        'min_stock',
        'supplier',
        'brand',
        'image_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProductType::class,
            'unit' => ProductUnit::class,
            'cost_price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'stock' => 'decimal:3',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'reference_id');
    }

    public function isLowStock(): bool
    {
        return $this->stock <= $this->min_stock;
    }
}

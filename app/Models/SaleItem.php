<?php

namespace App\Models;

use App\Enums\SaleItemType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'sale_id',
        'type',
        'reference_id',
        'name',
        'quantity',
        'unit_price',
        'discount_amount',
        'subtotal',
        'iva_rate',
        'iva_amount',
        'stylist_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => SaleItemType::class,
            'quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'iva_rate' => 'decimal:2',
            'iva_amount' => 'decimal:2',
        ];
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function stylist()
    {
        return $this->belongsTo(Stylist::class);
    }

    public function commission()
    {
        return $this->hasOne(Commission::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'service_category_id',
        'name',
        'description',
        'base_price',
        'iva_rate',
        'duration_minutes',
        'preparation_minutes',
        'recipe',
        'image_path',
        'is_visible',
        'requires_consultation',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'preparation_minutes' => 'integer',
            'recipe' => 'array',
            'is_visible' => 'boolean',
            'requires_consultation' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function category()
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    public function stylists()
    {
        return $this->belongsToMany(Stylist::class, 'service_stylist')
            ->withPivot('custom_price');
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'reference_id');
    }
}

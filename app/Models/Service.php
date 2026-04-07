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
        'service_type',
        'base_price',
        'iva_rate',
        'duration_minutes',
        'preparation_minutes',
        'recipe',
        'image_path',
        'is_visible',
        'requires_consultation',
        'has_warranty',
        'warranty_days',
        'warranty_description',
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
            'has_warranty' => 'boolean',
            'warranty_days' => 'integer',
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

    public function getServiceTypeLabel(): string
    {
        return match ($this->service_type) {
            'hair' => 'Cabello',
            'spa' => 'Spa / Masajes',
            'facial' => 'Facial',
            'nails' => 'Uñas',
            'brows' => 'Cejas y Pestañas',
            default => 'General',
        };
    }

    public function needsBodyMap(): bool
    {
        return in_array($this->service_type, ['spa', 'facial']);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class, 'reference_id');
    }
}

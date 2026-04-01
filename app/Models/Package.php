<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Package extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'price',
        'type',
        'items',
        'validity_days',
        'image_path',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'items' => 'array',
            'is_active' => 'boolean',
            'validity_days' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function clientPackages()
    {
        return $this->hasMany(ClientPackage::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'name',
        'slug',
        'price',
        'billing_cycle',
        'max_stylists',
        'max_branches',
        'features',
        'is_active',
        'stripe_price_id',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'features' => 'array',
            'is_active' => 'boolean',
            'max_stylists' => 'integer',
            'max_branches' => 'integer',
        ];
    }

    public function tenants()
    {
        return $this->hasMany(Tenant::class);
    }

    public function isUnlimitedStylists(): bool
    {
        return $this->max_stylists === -1;
    }

    public function isUnlimitedBranches(): bool
    {
        return $this->max_branches === -1;
    }
}

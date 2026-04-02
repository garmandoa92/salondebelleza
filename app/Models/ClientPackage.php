<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientPackage extends Model
{
    use HasUuids;

    protected $fillable = [
        'receipt_number',
        'client_id',
        'package_id',
        'sale_id',
        'package_name',
        'package_price',
        'purchased_at',
        'expires_at',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'package_price' => 'decimal:2',
            'purchased_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function items()
    {
        return $this->hasMany(ClientPackageItem::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function isFullyUsed(): bool
    {
        return $this->items->every(fn ($i) => $i->used_quantity >= $i->total_quantity);
    }
}

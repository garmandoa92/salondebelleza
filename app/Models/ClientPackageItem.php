<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ClientPackageItem extends Model
{
    use HasUuids;

    protected $fillable = [
        'client_package_id',
        'service_id',
        'service_name',
        'total_quantity',
        'used_quantity',
        'last_used_at',
        'last_appointment_id',
    ];

    protected function casts(): array
    {
        return [
            'total_quantity' => 'integer',
            'used_quantity' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function clientPackage()
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function getRemainingAttribute(): int
    {
        return max(0, $this->total_quantity - $this->used_quantity);
    }
}

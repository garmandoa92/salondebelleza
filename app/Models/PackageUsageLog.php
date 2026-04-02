<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class PackageUsageLog extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [
        'client_package_id',
        'client_package_item_id',
        'appointment_id',
        'service_id',
        'sessions_used',
        'sessions_before',
        'sessions_after',
        'used_by',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'sessions_used' => 'integer',
            'sessions_before' => 'integer',
            'sessions_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function clientPackage()
    {
        return $this->belongsTo(ClientPackage::class);
    }

    public function item()
    {
        return $this->belongsTo(ClientPackageItem::class, 'client_package_item_id');
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}

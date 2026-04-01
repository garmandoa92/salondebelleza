<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'email',
        'ruc',
        'razon_social',
        'manager_user_id',
        'schedule',
        'settings',
        'sri_establishment',
        'sri_emission_point',
        'is_main',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'schedule' => 'array',
            'settings' => 'array',
            'is_main' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function stylists()
    {
        return $this->belongsToMany(Stylist::class, 'branch_stylist')
            ->withPivot('schedule', 'is_active');
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}

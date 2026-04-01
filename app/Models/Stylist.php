<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Stylist extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'phone',
        'email',
        'photo_path',
        'bio',
        'specialties',
        'commission_rules',
        'schedule',
        'color',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'specialties' => 'array',
            'commission_rules' => 'array',
            'schedule' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'service_stylist')
            ->withPivot('custom_price');
    }

    public function blockedTimes()
    {
        return $this->hasMany(BlockedTime::class);
    }
}

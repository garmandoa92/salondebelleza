<?php

namespace App\Models;

use App\Enums\ClientSource;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Client extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'phone',
        'email',
        'cedula',
        'birthday',
        'notes',
        'allergies',
        'tags',
        'preferred_stylist_id',
        'loyalty_points',
        'total_spent',
        'balance',
        'visit_count',
        'last_visit_at',
        'source',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'birthday' => 'date',
            'tags' => 'array',
            'loyalty_points' => 'integer',
            'total_spent' => 'decimal:2',
            'balance' => 'decimal:2',
            'visit_count' => 'integer',
            'last_visit_at' => 'datetime',
            'source' => ClientSource::class,
            'is_active' => 'boolean',
        ];
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function preferredStylist()
    {
        return $this->belongsTo(Stylist::class, 'preferred_stylist_id');
    }

    public function advances()
    {
        return $this->hasMany(ClientAdvance::class);
    }
}

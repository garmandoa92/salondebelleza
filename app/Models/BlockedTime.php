<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedTime extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'stylist_id',
        'starts_at',
        'ends_at',
        'reason',
        'is_recurring',
        'recurrence_rule',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_recurring' => 'boolean',
        ];
    }

    public function stylist()
    {
        return $this->belongsTo(Stylist::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}

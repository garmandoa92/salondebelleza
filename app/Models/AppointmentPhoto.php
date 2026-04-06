<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppointmentPhoto extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'appointment_id',
        'client_id',
        'type',
        'photo_path',
        'thumbnail_path',
        'caption',
        'taken_by',
        'is_visible_to_client',
    ];

    protected function casts(): array
    {
        return [
            'is_visible_to_client' => 'boolean',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function photographer()
    {
        return $this->belongsTo(User::class, 'taken_by');
    }
}

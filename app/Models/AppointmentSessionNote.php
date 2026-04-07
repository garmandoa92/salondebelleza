<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class AppointmentSessionNote extends Model
{
    use HasUuids;

    protected $fillable = [
        'appointment_id',
        'user_id',
        'body_map',
        'techniques',
        'products_used',
        'actual_duration_minutes',
        'tension_level',
        'observations',
        'next_session_recommendation',
        'client_recommendation',
        'send_whatsapp',
        'whatsapp_sent',
        'whatsapp_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'body_map' => 'array',
            'techniques' => 'array',
            'products_used' => 'array',
            'send_whatsapp' => 'boolean',
            'whatsapp_sent' => 'boolean',
            'whatsapp_sent_at' => 'datetime',
            'actual_duration_minutes' => 'integer',
        ];
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function therapist()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getTensionLabelAttribute(): string
    {
        return match ($this->tension_level) {
            'low' => 'Baja',
            'medium' => 'Media',
            'high' => 'Alta',
            default => '',
        };
    }

    public function getWorkedZones(): array
    {
        return collect($this->body_map ?? [])->where('state', 'worked')->values()->toArray();
    }

    public function getTensionZones(): array
    {
        return collect($this->body_map ?? [])->where('state', 'tension')->values()->toArray();
    }

    public function getAvoidedZones(): array
    {
        return collect($this->body_map ?? [])->where('state', 'avoided')->values()->toArray();
    }
}

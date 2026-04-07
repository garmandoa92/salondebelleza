<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentWarranty extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'appointment_id',
        'client_id',
        'service_id',
        'issued_at',
        'expires_at',
        'status',
        'warranty_appointment_id',
        'notes',
        'voided_by',
        'voided_reason',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
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

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function warrantyAppointment()
    {
        return $this->belongsTo(Appointment::class, 'warranty_appointment_id');
    }

    public function getDaysRemainingAttribute(): int
    {
        return max(0, (int) now()->diffInDays($this->expires_at, false));
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at->isPast();
    }
}

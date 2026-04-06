<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppointmentDiagnosis extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'appointment_id',
        'client_id',
        'hair_condition',
        'skin_condition',
        'products_used',
        'technique',
        'temperature',
        'exposure_time',
        'result',
        'next_visit_notes',
        'internal_notes',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'products_used' => 'array',
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
}

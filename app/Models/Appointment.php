<?php

namespace App\Models;

use App\Enums\AppointmentSource;
use App\Enums\AppointmentStatus;
use App\Enums\CancelledBy;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'client_id',
        'stylist_id',
        'service_id',
        'branch_id',
        'client_package_item_id',
        'payment_status',
        'is_warranty',
        'warranty_id',
        'sessions_used',
        'starts_at',
        'ends_at',
        'status',
        'source',
        'notes',
        'internal_notes',
        'confirmed_at',
        'reminder_sent_at',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => AppointmentStatus::class,
            'source' => AppointmentSource::class,
            'confirmed_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'cancelled_by' => CancelledBy::class,
            'cancelled_at' => 'datetime',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function stylist()
    {
        return $this->belongsTo(Stylist::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function sale()
    {
        return $this->hasOne(Sale::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function diagnosis()
    {
        return $this->hasOne(AppointmentDiagnosis::class);
    }

    public function photos()
    {
        return $this->hasMany(AppointmentPhoto::class);
    }

    public function healthConfirmations()
    {
        return $this->hasMany(AppointmentHealthConfirmation::class);
    }

    public function sessionNote()
    {
        return $this->hasOne(AppointmentSessionNote::class);
    }
}

<?php

namespace App\Models;

use App\Enums\AdvanceStatus;
use App\Enums\AdvanceType;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientAdvance extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'client_id',
        'appointment_id',
        'type',
        'amount',
        'payment_method',
        'reference',
        'notes',
        'received_by',
        'sale_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => AdvanceType::class,
            'status' => AdvanceStatus::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
        ];
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}

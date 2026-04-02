<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\SaleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'appointment_id',
        'client_id',
        'branch_id',
        'subtotal',
        'discount_amount',
        'discount_type',
        'discount_reason',
        'iva_rate',
        'iva_amount',
        'total',
        'tip',
        'tip_stylist_id',
        'payment_methods',
        'status',
        'sri_invoice_id',
        'notes',
        'completed_at',
        'completed_by',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'discount_type' => DiscountType::class,
            'iva_rate' => 'decimal:2',
            'iva_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'tip' => 'decimal:2',
            'payment_methods' => 'array',
            'status' => SaleStatus::class,
            'completed_at' => 'datetime',
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

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function sriInvoice()
    {
        return $this->belongsTo(SriInvoice::class);
    }

    public function commissions()
    {
        return $this->hasManyThrough(Commission::class, SaleItem::class);
    }

    public function tipStylist()
    {
        return $this->belongsTo(Stylist::class, 'tip_stylist_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}

<?php

namespace App\Models;

use App\Enums\BuyerIdType;
use App\Enums\InvoiceType;
use App\Enums\SriEnvironment;
use App\Enums\SriStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SriInvoice extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'invoice_type',
        'establishment',
        'emission_point',
        'sequential',
        'access_key',
        'issue_date',
        'environment',
        'buyer_identification_type',
        'buyer_identification',
        'buyer_name',
        'buyer_email',
        'subtotal_0',
        'subtotal_iva',
        'iva_rate',
        'iva_amount',
        'total',
        'xml_unsigned',
        'xml_signed',
        'ride_path',
        'sri_status',
        'sri_authorization_number',
        'sri_authorization_date',
        'sri_response',
        'error_message',
        'retry_count',
        'next_retry_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_type' => InvoiceType::class,
            'issue_date' => 'date',
            'environment' => SriEnvironment::class,
            'buyer_identification_type' => BuyerIdType::class,
            'subtotal_0' => 'decimal:2',
            'subtotal_iva' => 'decimal:2',
            'iva_rate' => 'decimal:2',
            'iva_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'sri_status' => SriStatus::class,
            'sri_authorization_date' => 'datetime',
            'sri_response' => 'array',
            'retry_count' => 'integer',
            'next_retry_at' => 'datetime',
        ];
    }

    public function sale()
    {
        return $this->hasOne(Sale::class, 'sri_invoice_id');
    }

    public function getFullNumberAttribute(): string
    {
        return "{$this->establishment}-{$this->emission_point}-{$this->sequential}";
    }
}

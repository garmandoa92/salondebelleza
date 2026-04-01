<?php

namespace App\Notifications;

use App\Models\SriInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SriInvoiceRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public SriInvoice $invoice,
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'sri_invoice_rejected',
            'title' => 'Factura rechazada por SRI',
            'message' => "La factura {$this->invoice->full_number} fue rechazada: {$this->invoice->error_message}",
            'invoice_id' => $this->invoice->id,
            'icon' => 'alert-triangle',
        ];
    }
}

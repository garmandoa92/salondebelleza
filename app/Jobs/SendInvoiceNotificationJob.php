<?php

namespace App\Jobs;

use App\Models\SriInvoice;
use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendInvoiceNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $invoiceId,
    ) {}

    public function handle(WhatsAppService $whatsApp): void
    {
        $invoice = SriInvoice::with('sale.client')->find($this->invoiceId);
        if (! $invoice) return;

        $client = $invoice->sale?->client;
        $tenantName = tenant()?->name ?? 'Salon';

        // Send via WhatsApp
        if ($client?->phone) {
            $whatsApp->sendTemplate($client->phone, 'invoice_ride', [
                ['type' => 'body', 'parameters' => [
                    ['type' => 'text', 'text' => $client->first_name],
                    ['type' => 'text', 'text' => $tenantName],
                    ['type' => 'text', 'text' => $invoice->full_number],
                    ['type' => 'text', 'text' => number_format((float) $invoice->total, 2)],
                    ['type' => 'text', 'text' => 'Gracias por tu preferencia.'],
                ]],
            ]);
        }

        // Send via email
        $email = $invoice->buyer_email ?? $client?->email;
        if ($email) {
            try {
                Mail::raw(
                    "Estimado/a {$client?->first_name},\n\nAdjunto encontrara su comprobante electronico #{$invoice->full_number} por un total de \${$invoice->total}.\n\nGracias por su preferencia.\n{$tenantName}",
                    function ($message) use ($email, $invoice, $tenantName) {
                        $message->to($email)
                            ->subject("Comprobante #{$invoice->full_number} - {$tenantName}");
                    }
                );
            } catch (\Exception $e) {
                // Email sending is best-effort
            }
        }
    }
}

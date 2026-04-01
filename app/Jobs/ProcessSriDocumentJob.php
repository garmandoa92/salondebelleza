<?php

namespace App\Jobs;

use App\Models\SriInvoice;
use App\Services\Sri\SriAccessKeyGenerator;
use App\Services\Sri\SriSignatureService;
use App\Services\Sri\SriWebService;
use App\Services\Sri\SriXmlGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessSriDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 300; // 5 minutes

    public function __construct(
        public string $invoiceId,
        public array $tenantConfig,
        public array $saleItems,
        public array $payments,
    ) {}

    public function handle(
        SriAccessKeyGenerator $keyGenerator,
        SriXmlGenerator $xmlGenerator,
        SriSignatureService $signatureService,
        SriWebService $webService,
    ): void {
        $invoice = SriInvoice::findOrFail($this->invoiceId);

        try {
            // Generate access key if not set
            if (! $invoice->access_key) {
                $accessKey = $keyGenerator->generate(
                    $invoice->issue_date->toDateString(),
                    $invoice->invoice_type->value,
                    $this->tenantConfig['ruc'] ?? '0000000000001',
                    $invoice->environment->value,
                    $invoice->establishment,
                    $invoice->emission_point,
                    $invoice->sequential,
                );
                $invoice->update(['access_key' => $accessKey]);
            }

            // Generate XML
            $xml = $xmlGenerator->generate($invoice, $this->tenantConfig, $this->saleItems, $this->payments);
            $invoice->update(['xml_unsigned' => $xml, 'sri_status' => 'signed']);

            // Sign XML
            $signedXml = $signatureService->sign($xml);
            $invoice->update(['xml_signed' => $signedXml]);

            // Send to SRI
            $invoice->update(['sri_status' => 'sent']);
            $response = $webService->enviarComprobante($signedXml, $invoice->environment->value);

            if ($response['estado'] === 'DEVUELTA') {
                $invoice->update([
                    'sri_status' => 'rejected',
                    'sri_response' => $response,
                    'error_message' => collect($response['mensajes'])->pluck('mensaje')->implode('. '),
                    'retry_count' => $invoice->retry_count + 1,
                ]);
                Log::warning("SRI invoice {$invoice->id} rejected", $response);
                return;
            }

            if ($response['estado'] === 'RECIBIDA') {
                // Query authorization (with retries)
                $authorized = false;
                for ($i = 0; $i < 3; $i++) {
                    sleep(10);
                    $authResponse = $webService->consultarAutorizacion(
                        $invoice->access_key,
                        $invoice->environment->value,
                    );

                    if ($authResponse['estado'] === 'AUTORIZADO') {
                        $invoice->update([
                            'sri_status' => 'authorized',
                            'sri_authorization_number' => $authResponse['numeroAutorizacion'],
                            'sri_authorization_date' => $authResponse['fechaAutorizacion'],
                            'sri_response' => $authResponse,
                        ]);
                        $authorized = true;
                        break;
                    }
                }

                if (! $authorized) {
                    $invoice->update([
                        'sri_status' => 'rejected',
                        'sri_response' => $authResponse ?? $response,
                        'error_message' => 'No se obtuvo autorizacion despues de 3 intentos.',
                        'retry_count' => $invoice->retry_count + 1,
                    ]);
                }
            } else {
                $invoice->update([
                    'sri_status' => 'rejected',
                    'sri_response' => $response,
                    'error_message' => $response['mensajes'][0]['mensaje'] ?? 'Error de comunicacion con el SRI',
                    'retry_count' => $invoice->retry_count + 1,
                ]);
            }
        } catch (\Throwable $e) {
            $invoice->update([
                'sri_status' => 'rejected',
                'error_message' => $e->getMessage(),
                'retry_count' => $invoice->retry_count + 1,
            ]);
            Log::error("SRI processing failed for invoice {$invoice->id}", ['error' => $e->getMessage()]);
        }
    }
}

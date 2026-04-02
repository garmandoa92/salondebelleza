<?php

namespace App\Jobs;

use App\Models\SriInvoice;
use App\Services\Sri\SriSignatureService;
use App\Services\Sri\SriWebService;
use App\Services\Sri\SriXmlGenerator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ProcessSriDocumentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 300;

    public function __construct(
        public string $invoiceId,
        public array $tenantConfig,
        public array $saleItems,
        public array $payments,
    ) {}

    public function handle(
        SriXmlGenerator $xmlGenerator,
        SriSignatureService $signatureService,
        SriWebService $webService,
    ): void {
        $invoice = SriInvoice::findOrFail($this->invoiceId);

        Log::info('SRI Job iniciado', [
            'invoice_id' => $invoice->id,
            'sequential' => $invoice->sequential,
            'type' => $invoice->invoice_type instanceof \BackedEnum ? $invoice->invoice_type->value : $invoice->invoice_type,
        ]);

        try {
            // PASO 1: Generar XML
            $xml = $xmlGenerator->generate($invoice, $this->tenantConfig, $this->saleItems, $this->payments);
            $invoice->update(['xml_unsigned' => $xml, 'sri_status' => 'signed']);
            Log::info('SRI Paso 1: XML generado', ['length' => strlen($xml)]);

            // PASO 2: Firmar XML
            $p12Content = null;
            $p12Password = null;
            $settings = $this->tenantConfig;

            if (! empty($settings['sri_certificate'])) {
                try {
                    $p12Content = base64_decode(Crypt::decrypt($settings['sri_certificate']));
                    $p12Password = Crypt::decrypt($settings['sri_certificate_password']);
                } catch (\Throwable $e) {
                    Log::warning('SRI: No se pudo desencriptar certificado', ['error' => $e->getMessage()]);
                }
            }

            $signedXml = $signatureService->sign($xml, $p12Content, $p12Password);
            $invoice->update(['xml_signed' => $signedXml]);
            Log::info('SRI Paso 2: XML firmado');

            // PASO 3: Enviar al SRI
            $env = $this->tenantConfig['ambiente_sri'] ?? 'test';
            $invoice->update(['sri_status' => 'sent']);
            $response = $webService->enviarComprobante($signedXml, $env);
            Log::info('SRI Paso 3: Enviado al SRI', ['estado' => $response['estado']]);

            if ($response['estado'] === 'ERROR') {
                $errorMsg = $response['mensajes'][0]['mensaje'] ?? 'Error de comunicacion';
                $invoice->update([
                    'sri_status' => 'rejected',
                    'sri_response' => $response,
                    'error_message' => $errorMsg,
                    'retry_count' => ($invoice->retry_count ?? 0) + 1,
                ]);
                Log::error('SRI: Error de comunicacion', ['error' => $errorMsg]);
                return;
            }

            if ($response['estado'] === 'DEVUELTA') {
                $errorMsg = collect($response['mensajes'])->pluck('mensaje')->implode('. ');
                $invoice->update([
                    'sri_status' => 'rejected',
                    'sri_response' => $response,
                    'error_message' => $errorMsg,
                    'retry_count' => ($invoice->retry_count ?? 0) + 1,
                ]);
                Log::warning('SRI: Devuelta', ['mensajes' => $errorMsg]);
                return;
            }

            if ($response['estado'] === 'RECIBIDA') {
                Log::info('SRI Paso 4: Consultando autorizacion...');
                $authorized = false;
                $authResponse = null;

                for ($i = 0; $i < 3; $i++) {
                    sleep(5);
                    $authResponse = $webService->consultarAutorizacion(
                        $invoice->access_key,
                        $env,
                    );

                    if (($authResponse['estado'] ?? '') === 'AUTORIZADO') {
                        $invoice->update([
                            'sri_status' => 'authorized',
                            'sri_authorization_number' => $authResponse['numeroAutorizacion'] ?? null,
                            'sri_authorization_date' => $authResponse['fechaAutorizacion'] ?? null,
                            'sri_response' => $authResponse,
                        ]);
                        $authorized = true;
                        Log::info('SRI: AUTORIZADA', ['auth_number' => $authResponse['numeroAutorizacion'] ?? '-']);
                        break;
                    }
                }

                if (! $authorized) {
                    $invoice->update([
                        'sri_status' => 'rejected',
                        'sri_response' => $authResponse ?? $response,
                        'error_message' => 'No se obtuvo autorizacion despues de 3 intentos.',
                        'retry_count' => ($invoice->retry_count ?? 0) + 1,
                    ]);
                    Log::warning('SRI: No autorizada despues de 3 intentos');
                }
            } else {
                $invoice->update([
                    'sri_status' => 'rejected',
                    'sri_response' => $response,
                    'error_message' => $response['mensajes'][0]['mensaje'] ?? 'Respuesta inesperada del SRI',
                    'retry_count' => ($invoice->retry_count ?? 0) + 1,
                ]);
            }
        } catch (\Throwable $e) {
            $invoice->update([
                'sri_status' => 'rejected',
                'error_message' => $e->getMessage(),
                'retry_count' => ($invoice->retry_count ?? 0) + 1,
            ]);
            Log::error('SRI: Excepcion en job', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }
    }
}

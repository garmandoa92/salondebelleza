<?php

namespace App\Services\Sri;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class SriCertificateReader
{
    /**
     * Read a .p12 certificate with legacy algorithm support.
     * Ecuador SRI certificates use RC2-40-CBC which OpenSSL 3.x PHP module
     * cannot read even with OPENSSL_CONF. We use the openssl CLI with -legacy flag.
     */
    public static function read(string $content, string $password): array
    {
        Log::info('SriCertificateReader: leyendo .p12', [
            'file_size' => strlen($content),
        ]);

        // Clear PHP OpenSSL error queue
        while (openssl_error_string() !== false) {}

        // Try PHP native first (works for modern certificates)
        $certs = [];
        if (openssl_pkcs12_read($content, $certs, $password)) {
            Log::info('SriCertificateReader: lectura PHP nativa exitosa');
            return ['certs' => $certs, 'error' => null];
        }

        Log::info('SriCertificateReader: PHP nativo fallo, usando openssl CLI con -legacy');

        // Use openssl CLI with -legacy flag for SRI Ecuador certificates
        $tmpP12 = tempnam(sys_get_temp_dir(), 'p12_');
        $tmpPem = tempnam(sys_get_temp_dir(), 'pem_');

        try {
            file_put_contents($tmpP12, $content);

            // Extract private key
            $keyProcess = new Process([
                'openssl', 'pkcs12',
                '-in', $tmpP12,
                '-passin', 'pass:' . $password,
                '-nocerts', '-nodes', '-legacy',
            ]);
            $keyProcess->run();

            if (! $keyProcess->isSuccessful()) {
                $error = trim($keyProcess->getErrorOutput());
                Log::error('SriCertificateReader: openssl key extraction fallo', ['error' => $error]);
                return ['certs' => [], 'error' => $error ?: 'No se pudo extraer la llave privada'];
            }
            $privateKey = $keyProcess->getOutput();

            // Extract certificate
            $certProcess = new Process([
                'openssl', 'pkcs12',
                '-in', $tmpP12,
                '-passin', 'pass:' . $password,
                '-clcerts', '-nokeys', '-legacy',
            ]);
            $certProcess->run();

            if (! $certProcess->isSuccessful()) {
                $error = trim($certProcess->getErrorOutput());
                Log::error('SriCertificateReader: openssl cert extraction fallo', ['error' => $error]);
                return ['certs' => [], 'error' => $error ?: 'No se pudo extraer el certificado'];
            }
            $certificate = $certProcess->getOutput();

            if (empty($privateKey) || empty($certificate)) {
                return ['certs' => [], 'error' => 'Certificado o llave vacios. Verifique la contrasena.'];
            }

            Log::info('SriCertificateReader: lectura CLI exitosa');

            return [
                'certs' => [
                    'pkey' => $privateKey,
                    'cert' => $certificate,
                ],
                'error' => null,
            ];
        } finally {
            @unlink($tmpP12);
            @unlink($tmpPem);
        }
    }
}

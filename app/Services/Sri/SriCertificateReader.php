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

    /**
     * Extract certificate metadata (subject, issuer, dates) from PEM certificate string.
     */
    public static function extractInfo(string $certPem): array
    {
        $tmpCert = tempnam(sys_get_temp_dir(), 'cert_');
        file_put_contents($tmpCert, $certPem);

        try {
            $process = new Process([
                'openssl', 'x509',
                '-in', $tmpCert,
                '-noout', '-subject', '-issuer', '-dates',
                '-nameopt', 'utf8',
            ]);
            $process->run();

            if (! $process->isSuccessful()) {
                Log::warning('SriCertificateReader: no se pudo extraer info', ['error' => $process->getErrorOutput()]);
                return [];
            }

            $output = $process->getOutput();
            $info = [];

            // Parse subject line
            if (preg_match('/subject\s*=\s*(.+)/i', $output, $m)) {
                $subject = trim($m[1]);
                $info['subject_raw'] = $subject;

                // Extract CN (Common Name) = titular
                if (preg_match('/CN\s*=\s*([^,\/]+)/i', $subject, $cn)) {
                    $info['titular'] = trim($cn[1]);
                }
                // Extract serialNumber or OID 2.5.4.5 = RUC/cedula
                if (preg_match('/serialNumber\s*=\s*(\d+)/i', $subject, $sn)) {
                    $info['ruc'] = trim($sn[1]);
                } elseif (preg_match('/OID\.2\.5\.4\.5\s*=\s*(\d+)/i', $subject, $sn)) {
                    $info['ruc'] = trim($sn[1]);
                }
            }

            // Parse issuer
            if (preg_match('/issuer\s*=\s*(.+)/i', $output, $m)) {
                $issuer = trim($m[1]);
                if (preg_match('/O\s*=\s*([^,\/]+)/i', $issuer, $o)) {
                    $info['issuer'] = trim($o[1]);
                } else {
                    $info['issuer'] = $issuer;
                }
            }

            // Parse dates
            if (preg_match('/notBefore\s*=\s*(.+)/i', $output, $m)) {
                $info['valid_from'] = date('Y-m-d', strtotime(trim($m[1])));
            }
            if (preg_match('/notAfter\s*=\s*(.+)/i', $output, $m)) {
                $info['valid_until'] = date('Y-m-d', strtotime(trim($m[1])));
                $info['is_valid'] = strtotime(trim($m[1])) > time();
                $info['days_until_expiry'] = (int) ((strtotime(trim($m[1])) - time()) / 86400);
            }

            return $info;
        } finally {
            @unlink($tmpCert);
        }
    }
}

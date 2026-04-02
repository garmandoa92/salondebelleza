<?php

namespace App\Services\Sri;

use Illuminate\Support\Facades\Log;

class SriCertificateReader
{
    /**
     * Read a .p12 certificate with legacy algorithm support.
     * Ecuador SRI certificates use RC2-40-CBC which requires the legacy OpenSSL provider.
     *
     * @return array{certs: array, error: string|null}
     */
    public static function read(string $content, string $password): array
    {
        Log::info('SriCertificateReader: intentando leer .p12', [
            'file_size' => strlen($content),
            'password_length' => strlen($password),
            'openssl_version' => OPENSSL_VERSION_TEXT,
        ]);

        // Clear error queue
        while (openssl_error_string() !== false) {}

        $certs = [];

        // Try 1: standard read
        if (openssl_pkcs12_read($content, $certs, $password)) {
            Log::info('SriCertificateReader: lectura standard exitosa');
            return ['certs' => $certs, 'error' => null];
        }

        $standardErrors = self::collectErrors();
        Log::warning('SriCertificateReader: lectura standard fallo', ['errors' => $standardErrors]);

        // Try 2: with legacy provider
        $legacyConf = config_path('openssl_legacy.cnf');
        if (! file_exists($legacyConf)) {
            Log::error('SriCertificateReader: openssl_legacy.cnf no existe en ' . $legacyConf);
            return ['certs' => [], 'error' => 'Config legacy no encontrada. Errores: ' . implode('; ', $standardErrors)];
        }

        $originalConf = getenv('OPENSSL_CONF');
        putenv('OPENSSL_CONF=' . $legacyConf);
        Log::info('SriCertificateReader: reintentando con legacy provider', ['conf' => $legacyConf]);

        while (openssl_error_string() !== false) {}

        $result = openssl_pkcs12_read($content, $certs, $password);

        // Restore
        if ($originalConf !== false) {
            putenv('OPENSSL_CONF=' . $originalConf);
        } else {
            putenv('OPENSSL_CONF');
        }

        if ($result) {
            Log::info('SriCertificateReader: lectura con legacy exitosa');
            return ['certs' => $certs, 'error' => null];
        }

        $legacyErrors = self::collectErrors();
        Log::error('SriCertificateReader: lectura con legacy tambien fallo', ['errors' => $legacyErrors]);

        $allErrors = array_merge($standardErrors, $legacyErrors);
        return ['certs' => [], 'error' => implode('; ', $allErrors) ?: 'Error desconocido al leer el certificado'];
    }

    private static function collectErrors(): array
    {
        $errors = [];
        while ($err = openssl_error_string()) {
            $errors[] = $err;
        }
        return $errors;
    }
}
